<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $emailFrom = 'noreply@gestasso.com'
    ) {}

    /**
     * Crée une notification pour un utilisateur
     */
    public function createNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setData($data);
        $notification->setCreatedAt(new \DateTime());

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Envoyer également par email si nécessaire
        if ($this->shouldSendEmail($type)) {
            $this->sendNotificationEmail($notification);
        }

        return $notification;
    }

    /**
     * Envoie une notification par email
     */
    private function sendNotificationEmail(Notification $notification): void
    {
        try {
            $user = $notification->getUser();
            
            $html = $this->twig->render('emails/notification.html.twig', [
                'notification' => $notification,
                'user' => $user
            ]);

            $email = (new Email())
                ->from(new Address($this->emailFrom, 'GestAsso'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject($notification->getTitle())
                ->html($html);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Logger l'erreur mais ne pas faire échouer la création de la notification
            error_log('Erreur envoi email notification: ' . $e->getMessage());
        }
    }

    /**
     * Détermine si une notification doit être envoyée par email
     */
    private function shouldSendEmail(string $type): bool
    {
        // Types de notifications qui doivent être envoyées par email
        $emailTypes = [
            'payment_reminder',
            'payment_overdue',
            'document_validation',
            'join_request_approved',
            'join_request_rejected',
            'event_created',
            'cagnotte_credited'
        ];

        return in_array($type, $emailTypes);
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        }
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(User $user): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Notification::class, 'n')
            ->set('n.isRead', ':read')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :unread')
            ->setParameter('read', true)
            ->setParameter('user', $user)
            ->setParameter('unread', false);

        return $qb->getQuery()->execute();
    }

    /**
     * Récupère les notifications non lues d'un utilisateur
     */
    public function getUnreadNotifications(User $user, int $limit = 10): array
    {
        return $this->entityManager->getRepository(Notification::class)->findBy(
            ['user' => $user, 'isRead' => false],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Compte les notifications non lues d'un utilisateur
     */
    public function countUnreadNotifications(User $user): int
    {
        return $this->entityManager->getRepository(Notification::class)->count([
            'user' => $user,
            'isRead' => false
        ]);
    }

    /**
     * Envoie des rappels de paiement en masse
     */
    public function sendPaymentReminders(array $payments): int
    {
        $count = 0;
        
        foreach ($payments as $payment) {
            $this->createNotification(
                $payment->getUser(),
                'payment_reminder',
                'Rappel de paiement',
                sprintf(
                    'Vous avez un paiement de %s€ à effectuer avant le %s pour l\'équipe %s.',
                    $payment->getAmount(),
                    $payment->getDueDate()->format('d/m/Y'),
                    $payment->getTeam()->getName()
                ),
                [
                    'paymentId' => $payment->getId(),
                    'teamId' => $payment->getTeam()->getId(),
                    'amount' => $payment->getAmount(),
                    'dueDate' => $payment->getDueDate()->format('Y-m-d')
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Notifie les gestionnaires d'une nouvelle demande d'adhésion
     */
    public function notifyJoinRequest($joinRequest): void
    {
        $club = $joinRequest->getClub();
        $team = $joinRequest->getTeam();
        
        // Notifier le propriétaire du club
        $this->createNotification(
            $club->getOwner(),
            'join_request_new',
            'Nouvelle demande d\'adhésion',
            sprintf(
                '%s souhaite rejoindre l\'équipe %s',
                $joinRequest->getUser()->getFullName(),
                $team->getName()
            ),
            [
                'joinRequestId' => $joinRequest->getId(),
                'userId' => $joinRequest->getUser()->getId(),
                'teamId' => $team->getId()
            ]
        );

        // Notifier les gestionnaires du club
        foreach ($club->getClubManagers() as $manager) {
            if ($manager->getUser() !== $club->getOwner()) {
                $this->createNotification(
                    $manager->getUser(),
                    'join_request_new',
                    'Nouvelle demande d\'adhésion',
                    sprintf(
                        '%s souhaite rejoindre l\'équipe %s',
                        $joinRequest->getUser()->getFullName(),
                        $team->getName()
                    ),
                    [
                        'joinRequestId' => $joinRequest->getId(),
                        'userId' => $joinRequest->getUser()->getId(),
                        'teamId' => $team->getId()
                    ]
                );
            }
        }
    }

    /**
     * Supprime les anciennes notifications
     */
    public function cleanupOldNotifications(int $daysToKeep = 90): int
    {
        $date = new \DateTime();
        $date->modify("-{$daysToKeep} days");

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(Notification::class, 'n')
            ->where('n.createdAt < :date')
            ->andWhere('n.isRead = :read')
            ->setParameter('date', $date)
            ->setParameter('read', true);

        return $qb->getQuery()->execute();
    }
} 