<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class NotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private NotificationService $notificationService
    ) {}

    #[Route('/notifications', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getNotifications(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $unreadOnly = $request->query->getBoolean('unreadOnly', false);
        
        $criteria = ['user' => $user];
        if ($unreadOnly) {
            $criteria['isRead'] = false;
        }
        
        $offset = ($page - 1) * $limit;
        
        $notifications = $this->notificationRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
        
        $total = $this->notificationRepository->count($criteria);
        $unreadCount = $this->notificationService->countUnreadNotifications($user);
        
        return $this->json([
            'notifications' => array_map(function (Notification $notification) {
                return [
                    'id' => $notification->getId(),
                    'type' => $notification->getType(),
                    'title' => $notification->getTitle(),
                    'message' => $notification->getMessage(),
                    'isRead' => $notification->isRead(),
                    'data' => $notification->getData(),
                    'createdAt' => $notification->getCreatedAt()->format('c')
                ];
            }, $notifications),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'unreadCount' => $unreadCount
        ]);
    }

    #[Route('/notifications/{id}/read', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function markAsRead(int $id): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        
        if (!$notification) {
            return $this->json(['error' => 'Notification non trouvée'], Response::HTTP_NOT_FOUND);
        }
        
        // Vérifier que la notification appartient à l'utilisateur
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        $this->notificationService->markAsRead($notification);
        
        return $this->json([
            'id' => $notification->getId(),
            'isRead' => $notification->isRead()
        ]);
    }

    #[Route('/notifications/read-all', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->notificationService->markAllAsRead($user);
        
        return $this->json([
            'message' => sprintf('%d notification(s) marquée(s) comme lue(s)', $count),
            'count' => $count
        ]);
    }

    #[Route('/notifications/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteNotification(int $id): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        
        if (!$notification) {
            return $this->json(['error' => 'Notification non trouvée'], Response::HTTP_NOT_FOUND);
        }
        
        // Vérifier que la notification appartient à l'utilisateur
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        $this->entityManager->remove($notification);
        $this->entityManager->flush();
        
        return $this->json(['message' => 'Notification supprimée avec succès'], Response::HTTP_NO_CONTENT);
    }

    #[Route('/notifications/unread-count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUnreadCount(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->notificationService->countUnreadNotifications($user);
        
        return $this->json(['count' => $count]);
    }
} 