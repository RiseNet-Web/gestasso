<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\PaymentSchedule;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Service\PaymentService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private PaymentRepository $paymentRepository,
        private PaymentService $paymentService,
        private NotificationService $notificationService
    ) {}

    #[Route('/teams/{id}/payments', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTeamPayments(int $id, Request $request): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_VIEW', $team);

        $status = $request->query->get('status');
        $userId = $request->query->get('userId');

        $criteria = ['team' => $team];
        
        if ($status) {
            $criteria['status'] = $status;
        }
        
        if ($userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user) {
                $criteria['user'] = $user;
            }
        }

        $payments = $this->paymentRepository->findBy($criteria, ['dueDate' => 'ASC']);

        return $this->json([
            'payments' => array_map(function (Payment $payment) {
                return [
                    'id' => $payment->getId(),
                    'user' => [
                        'id' => $payment->getUser()->getId(),
                        'firstName' => $payment->getUser()->getFirstName(),
                        'lastName' => $payment->getUser()->getLastName(),
                        'email' => $payment->getUser()->getEmail()
                    ],
                    'amount' => $payment->getAmount(),
                    'amountPaid' => $payment->getAmountPaid(),
                    'dueDate' => $payment->getDueDate()->format('Y-m-d'),
                    'paidAt' => $payment->getPaidAt()?->format('c'),
                    'status' => $payment->getStatus(),
                    'notes' => $payment->getNotes(),
                    'paymentSchedule' => $payment->getPaymentSchedule() ? [
                        'id' => $payment->getPaymentSchedule()->getId(),
                        'description' => $payment->getPaymentSchedule()->getDescription()
                    ] : null,
                    'createdAt' => $payment->getCreatedAt()->format('c')
                ];
            }, $payments),
            'summary' => [
                'total' => array_reduce($payments, fn($sum, $p) => $sum + $p->getAmount(), 0),
                'paid' => array_reduce($payments, fn($sum, $p) => $sum + $p->getAmountPaid(), 0),
                'pending' => count(array_filter($payments, fn($p) => $p->getStatus() === 'pending')),
                'overdue' => count(array_filter($payments, fn($p) => $p->getStatus() === 'overdue')),
                'completed' => count(array_filter($payments, fn($p) => $p->getStatus() === 'paid'))
            ]
        ]);
    }

    #[Route('/teams/{id}/payment-schedules', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createPaymentSchedule(int $id, Request $request): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['amount']) || !isset($data['dueDate']) || !isset($data['description'])) {
            return $this->json(['error' => 'amount, dueDate et description sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $schedule = new PaymentSchedule();
        $schedule->setTeam($team);
        $schedule->setAmount($data['amount']);
        $schedule->setDueDate(new \DateTime($data['dueDate']));
        $schedule->setDescription($data['description']);

        // Valider l'entité
        $errors = $this->validator->validate($schedule);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($schedule);
        
        // Créer les paiements pour tous les membres actifs
        $activeMembers = $team->getTeamMembers()->filter(
            fn($m) => $m->isActive() && $m->getRole() === 'athlete'
        );

        foreach ($activeMembers as $member) {
            $payment = $this->paymentService->createPaymentFromSchedule($schedule, $member->getUser());
            $this->entityManager->persist($payment);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $schedule->getId(),
            'teamId' => $team->getId(),
            'amount' => $schedule->getAmount(),
            'dueDate' => $schedule->getDueDate()->format('Y-m-d'),
            'description' => $schedule->getDescription(),
            'paymentsCreated' => $activeMembers->count(),
            'createdAt' => $schedule->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
    }

    #[Route('/payments/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updatePayment(int $id, Request $request): JsonResponse
    {
        $payment = $this->paymentRepository->find($id);
        
        if (!$payment) {
            return $this->json(['error' => 'Paiement non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('PAYMENT_UPDATE', $payment);

        $data = json_decode($request->getContent(), true);

        if (isset($data['amountPaid'])) {
            $payment->setAmountPaid($data['amountPaid']);
            
            // Mettre à jour le statut automatiquement
            if ($payment->getAmountPaid() >= $payment->getAmount()) {
                $payment->setStatus('paid');
                $payment->setPaidAt(new \DateTime());
            } elseif ($payment->getAmountPaid() > 0) {
                $payment->setStatus('partial');
            }
        }

        if (isset($data['notes'])) {
            $payment->setNotes($data['notes']);
        }

        // Valider l'entité
        $errors = $this->validator->validate($payment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        // Notifier l'utilisateur si le paiement est complet
        if ($payment->getStatus() === 'paid') {
            $this->notificationService->createNotification(
                $payment->getUser(),
                'payment_completed',
                'Paiement confirmé',
                sprintf(
                    'Votre paiement de %s€ pour l\'équipe %s a été confirmé.',
                    $payment->getAmount(),
                    $payment->getTeam()->getName()
                ),
                [
                    'paymentId' => $payment->getId(),
                    'teamId' => $payment->getTeam()->getId(),
                    'amount' => $payment->getAmount()
                ]
            );
        }

        return $this->json([
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'amountPaid' => $payment->getAmountPaid(),
            'status' => $payment->getStatus(),
            'paidAt' => $payment->getPaidAt()?->format('c'),
            'notes' => $payment->getNotes()
        ]);
    }

    #[Route('/notifications/payment-reminders', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sendPaymentReminders(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        // Vérifier que l'utilisateur a le droit d'envoyer des rappels
        if (!$user->hasRole('ROLE_COACH') && !$user->hasRole('ROLE_CLUB_MANAGER') && !$user->hasRole('ROLE_CLUB_OWNER')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit d\'envoyer des rappels de paiement');
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['paymentIds']) || !is_array($data['paymentIds'])) {
            return $this->json(['error' => 'paymentIds doit être un tableau'], Response::HTTP_BAD_REQUEST);
        }

        $payments = [];
        foreach ($data['paymentIds'] as $paymentId) {
            $payment = $this->paymentRepository->find($paymentId);
            if ($payment && in_array($payment->getStatus(), ['pending', 'overdue'])) {
                // Vérifier les permissions sur chaque paiement
                $this->denyAccessUnlessGranted('PAYMENT_SEND_REMINDER', $payment);
                $payments[] = $payment;
            }
        }

        $count = $this->notificationService->sendPaymentReminders($payments);

        return $this->json([
            'message' => sprintf('%d rappel(s) envoyé(s) avec succès', $count),
            'count' => $count
        ]);
    }
} 