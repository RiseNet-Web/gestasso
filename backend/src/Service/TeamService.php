<?php

namespace App\Service;

use App\Entity\Team;
use App\Entity\Club;
use App\Entity\Season;
use App\Entity\User;
use App\Entity\TeamMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TeamService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {}

    public function createTeam(Club $club, array $data): Team
    {
        $team = new Team();
        $team->setName($data['name'] ?? '');
        $team->setDescription($data['description'] ?? null);
        $team->setClub($club);
        $team->setAnnualPrice((string)($data['annualPrice'] ?? '0'));
        
        if (isset($data['gender'])) {
            $team->setGender($data['gender']);
        }
        if (isset($data['minBirthYear'])) {
            $team->setMinBirthYear($data['minBirthYear']);
        }
        if (isset($data['maxBirthYear'])) {
            $team->setMaxBirthYear($data['maxBirthYear']);
        }
        
        if (isset($data['seasonId'])) {
            $season = $this->entityManager->getRepository(Season::class)->find($data['seasonId']);
            if (!$season || $season->getClub() !== $club) {
                throw new \InvalidArgumentException('Saison invalide');
            }
            $team->setSeason($season);
        }
        $errors = $this->validator->validate($team);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        return $team;
    }

    public function updateTeam(Team $team, array $data): Team
    {
        if (isset($data['name'])) {
            $team->setName($data['name']);
        }
        if (isset($data['description'])) {
            $team->setDescription($data['description']);
        }
        if (isset($data['annualPrice'])) {
            $team->setAnnualPrice((string)$data['annualPrice']);
        }
        if (isset($data['gender'])) {
            $team->setGender($data['gender']);
        }
        if (isset($data['minBirthYear'])) {
            $team->setMinBirthYear($data['minBirthYear']);
        }
        if (isset($data['maxBirthYear'])) {
            $team->setMaxBirthYear($data['maxBirthYear']);
        }
        if (isset($data['seasonId'])) {
            $season = $this->entityManager->getRepository(Season::class)->find($data['seasonId']);
            if (!$season || $season->getClub() !== $team->getClub()) {
                throw new \InvalidArgumentException('Saison invalide');
            }
            $team->setSeason($season);
        }
        $errors = $this->validator->validate($team);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }
        $this->entityManager->flush();
        return $team;
    }

    public function addTeamMember(Team $team, User $user, string $role): TeamMember
    {
        $member = new TeamMember();
        $member->setTeam($team);
        $member->setUser($user);
        $member->setRole($role);
        $member->setActive(true);
        $this->entityManager->persist($member);
        $this->entityManager->flush();
        return $member;
    }

    public function removeTeamMember(Team $team, User $user): void
    {
        foreach ($team->getTeamMembers() as $member) {
            if ($member->getUser() === $user) {
                $this->entityManager->remove($member);
            }
        }
        $this->entityManager->flush();
    }

    public function deleteTeam(Team $team): void
    {
        $team->setIsActive(false);
        $this->entityManager->flush();
    }

    public function setTeamStatus(Team $team, bool $isActive): void
    {
        $team->setIsActive($isActive);
        $this->entityManager->flush();
    }
} 