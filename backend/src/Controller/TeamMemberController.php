<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\User;
use App\Service\TeamMemberService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

#[Route('/api/team-members')]
class TeamMemberController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamMemberService $teamMemberService
    ) {}

    #[Route('', name: 'add_team_member', methods: ['POST'])]
    public function addMember(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['user']) || !isset($data['team'])) {
            return $this->json(['error' => 'Les champs user et team sont obligatoires'], Response::HTTP_BAD_REQUEST);
        }

        // Extraire l'ID de l'URL IRI (ex: /api/users/123 -> 123)
        $userId = (int) basename(parse_url($data['user'], PHP_URL_PATH));
        $teamId = (int) basename(parse_url($data['team'], PHP_URL_PATH));
        $role = $data['role'] ?? 'athlete';

        // Récupérer les entités
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE_MEMBERS', $team);

        try {
            // Valider d'abord avant d'ajouter
            $errors = $this->teamMemberService->validateUserCanJoinTeam($user, $team, $role);
            
            if (!empty($errors)) {
                return $this->json([
                    'error' => 'Validation échouée',
                    'violations' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            // Ajouter le membre
            $teamMember = $this->teamMemberService->addMemberToTeam($user, $team, $role);

            return $this->json([
                'id' => $teamMember->getId(),
                'user' => [
                    'id' => $user->getId(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'email' => $user->getEmail(),
                    'age' => $user->getAge(),
                    'dateOfBirth' => $user->getDateOfBirth()?->format('Y-m-d')
                ],
                'team' => [
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                    'ageRange' => $team->getAgeRange()
                ],
                'role' => $teamMember->getRole()->value,
                'roleLabel' => $teamMember->getRole()->getLabel(),
                'joinedAt' => $teamMember->getJoinedAt()->format('c'),
                'isActive' => $teamMember->isActive()
            ], Response::HTTP_CREATED);

        } catch (BadRequestException $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Une erreur inattendue s\'est produite'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/validate', name: 'validate_team_member', methods: ['POST'])]
    public function validateMember(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['user']) || !isset($data['team'])) {
            return $this->json(['error' => 'Les champs user et team sont obligatoires'], Response::HTTP_BAD_REQUEST);
        }

        // Extraire l'ID de l'URL IRI
        $userId = (int) basename(parse_url($data['user'], PHP_URL_PATH));
        $teamId = (int) basename(parse_url($data['team'], PHP_URL_PATH));
        $role = $data['role'] ?? 'athlete';

        // Récupérer les entités
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);

        if (!$user || !$team) {
            return $this->json(['error' => 'Utilisateur ou équipe non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Valider
        $errors = $this->teamMemberService->validateUserCanJoinTeam($user, $team, $role);

        if (empty($errors)) {
            return $this->json([
                'valid' => true,
                'message' => 'L\'utilisateur peut rejoindre l\'équipe',
                'userInfo' => [
                    'age' => $user->getAge(),
                    'birthYear' => $user->getBirthYear()
                ],
                'teamInfo' => [
                    'ageRange' => $team->getAgeRange(),
                    'minBirthYear' => $team->getMinBirthYear(),
                    'maxBirthYear' => $team->getMaxBirthYear()
                ]
            ]);
        } else {
            return $this->json([
                'valid' => false,
                'violations' => $errors,
                'userInfo' => [
                    'age' => $user->getAge(),
                    'birthYear' => $user->getBirthYear()
                ],
                'teamInfo' => [
                    'ageRange' => $team->getAgeRange(),
                    'minBirthYear' => $team->getMinBirthYear(),
                    'maxBirthYear' => $team->getMaxBirthYear()
                ]
            ]);
        }
    }

    #[Route('/{id}', name: 'remove_team_member', methods: ['DELETE'])]
    public function removeMember(int $id): JsonResponse
    {
        $teamMember = $this->entityManager->getRepository(\App\Entity\TeamMember::class)->find($id);

        if (!$teamMember) {
            return $this->json(['error' => 'Membre d\'équipe non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE_MEMBERS', $teamMember->getTeam());

        // Marquer comme inactif au lieu de supprimer
        $teamMember->setIsActive(false);
        $teamMember->setLeftAt(new \DateTime());

        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();

        return $this->json(['message' => 'Membre retiré de l\'équipe'], Response::HTTP_OK);
    }
} 