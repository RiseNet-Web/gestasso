<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Entity\Club;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\ClubManager;
use App\Entity\TeamMember;
use App\Entity\JoinRequest;
use App\Entity\PaymentSchedule;
use App\Entity\Payment;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\Cagnotte;
use App\Entity\CagnotteTransaction;
use App\Entity\DocumentType;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:generate-fixtures',
    description: 'Génère des données de test pour l\'application'
)]
class GenerateFixturesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Génération des données de test');

        try {
            // Nettoyer les données existantes
            $this->cleanDatabase($io);

            // Créer les utilisateurs
            $users = $this->createUsers($io);

            // Créer les clubs
            $clubs = $this->createClubs($io, $users);

            // Créer les saisons
            $seasons = $this->createSeasons($io, $clubs);

            // Créer les équipes
            $teams = $this->createTeams($io, $clubs, $seasons);

            // Créer les gestionnaires de club
            $this->createClubManagers($io, $clubs, $users);

            // Créer les membres d'équipe
            $this->createTeamMembers($io, $teams, $users);

            // Créer les demandes d'adhésion
            $this->createJoinRequests($io, $teams, $users);

            // Créer les échéanciers de paiement
            $paymentSchedules = $this->createPaymentSchedules($io, $teams);

            // Créer les paiements
            $this->createPayments($io, $teams, $users, $paymentSchedules);

            // Créer les événements
            $events = $this->createEvents($io, $teams, $users);

            // Créer les participants aux événements
            $this->createEventParticipants($io, $events, $users);

            // Créer les cagnottes
            $cagnottes = $this->createCagnottes($io, $teams, $users);

            // Créer les transactions de cagnotte
            $this->createCagnotteTransactions($io, $cagnottes, $events);

            // Créer les types de documents
            $this->createDocumentTypes($io, $teams);

            // Créer les notifications
            $this->createNotifications($io, $users);

            $this->entityManager->flush();

            $io->success('Données de test générées avec succès !');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de la génération des données : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cleanDatabase(SymfonyStyle $io): void
    {
        $io->section('Nettoyage de la base de données');

        $entities = [
            'App\Entity\Notification',
            'App\Entity\DocumentType',
            'App\Entity\CagnotteTransaction',
            'App\Entity\Cagnotte',
            'App\Entity\EventParticipant',
            'App\Entity\Event',
            'App\Entity\Payment',
            'App\Entity\PaymentSchedule',
            'App\Entity\JoinRequest',
            'App\Entity\TeamMember',
            'App\Entity\ClubManager',
            'App\Entity\Team',
            'App\Entity\Season',
            'App\Entity\Club',
            'App\Entity\UserAuthentication',
            'App\Entity\User'
        ];

        foreach ($entities as $entity) {
            $this->entityManager->createQuery("DELETE FROM {$entity}")->execute();
        }

        $io->text('Base de données nettoyée');
    }

    private function createUsers(SymfonyStyle $io): array
    {
        $io->section('Création des utilisateurs');

        $users = [];
        $userData = [
            ['email' => 'owner1@example.com', 'firstName' => 'Jean', 'lastName' => 'Dupont', 'roles' => ['ROLE_CLUB_OWNER'], 'onboardingType' => 'owner'],
            ['email' => 'owner2@example.com', 'firstName' => 'Marie', 'lastName' => 'Martin', 'roles' => ['ROLE_CLUB_OWNER'], 'onboardingType' => 'owner'],
            ['email' => 'owner3@example.com', 'firstName' => 'Pierre', 'lastName' => 'Durand', 'roles' => ['ROLE_CLUB_OWNER'], 'onboardingType' => 'owner'],
            ['email' => 'manager1@example.com', 'firstName' => 'Sophie', 'lastName' => 'Bernard', 'roles' => ['ROLE_CLUB_MANAGER'], 'onboardingType' => 'member'],
            ['email' => 'manager2@example.com', 'firstName' => 'Luc', 'lastName' => 'Petit', 'roles' => ['ROLE_CLUB_MANAGER'], 'onboardingType' => 'member'],
            ['email' => 'coach1@example.com', 'firstName' => 'Antoine', 'lastName' => 'Moreau', 'roles' => ['ROLE_COACH'], 'onboardingType' => 'member'],
            ['email' => 'coach2@example.com', 'firstName' => 'Isabelle', 'lastName' => 'Simon', 'roles' => ['ROLE_COACH'], 'onboardingType' => 'member'],
            ['email' => 'coach3@example.com', 'firstName' => 'Thomas', 'lastName' => 'Laurent', 'roles' => ['ROLE_COACH'], 'onboardingType' => 'member'],
            ['email' => 'athlete1@example.com', 'firstName' => 'Emma', 'lastName' => 'Leroy', 'roles' => ['ROLE_ATHLETE'], 'onboardingType' => 'member'],
            ['email' => 'athlete2@example.com', 'firstName' => 'Hugo', 'lastName' => 'Roux', 'roles' => ['ROLE_ATHLETE'], 'onboardingType' => 'member'],
            ['email' => 'athlete3@example.com', 'firstName' => 'Léa', 'lastName' => 'David', 'roles' => ['ROLE_ATHLETE'], 'onboardingType' => 'member'],
            ['email' => 'athlete4@example.com', 'firstName' => 'Nathan', 'lastName' => 'Bertrand', 'roles' => ['ROLE_ATHLETE'], 'onboardingType' => 'member'],
            ['email' => 'athlete5@example.com', 'firstName' => 'Chloé', 'lastName' => 'Mathieu', 'roles' => ['ROLE_ATHLETE'], 'onboardingType' => 'member'],
            ['email' => 'member1@example.com', 'firstName' => 'Lucas', 'lastName' => 'Garcia', 'roles' => ['ROLE_MEMBER'], 'onboardingType' => 'member'],
            ['email' => 'member2@example.com', 'firstName' => 'Manon', 'lastName' => 'Rodriguez', 'roles' => ['ROLE_MEMBER'], 'onboardingType' => 'member'],
            ['email' => 'member3@example.com', 'firstName' => 'Enzo', 'lastName' => 'Martinez', 'roles' => ['ROLE_MEMBER'], 'onboardingType' => 'member'],
            ['email' => 'google1@example.com', 'firstName' => 'Alice', 'lastName' => 'Google', 'roles' => ['ROLE_MEMBER'], 'onboardingType' => 'member'],
            ['email' => 'apple1@example.com', 'firstName' => 'Bob', 'lastName' => 'Apple', 'roles' => ['ROLE_MEMBER'], 'onboardingType' => 'member'],
        ];

        foreach ($userData as $data) {
            $user = new User();
            $user->setEmail($data['email'])
                 ->setFirstName($data['firstName'])
                 ->setLastName($data['lastName'])
                 ->setRoles($data['roles'])
                 ->setOnboardingType($data['onboardingType'])
                 ->setOnboardingCompleted(true)
                 ->setPhone('+33' . rand(100000000, 999999999));

            $this->entityManager->persist($user);
            $users[] = $user;

            // Créer l'authentification email pour la plupart des utilisateurs
            if (!str_contains($data['email'], 'google') && !str_contains($data['email'], 'apple')) {
                $userAuth = new UserAuthentication();
                $userAuth->setUser($user)
                         ->setProvider('email')
                         ->setEmail($data['email'])
                         ->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
                
                $this->entityManager->persist($userAuth);
            }

            // Créer l'authentification Google
            if (str_contains($data['email'], 'google')) {
                $userAuth = new UserAuthentication();
                $userAuth->setUser($user)
                         ->setProvider('google')
                         ->setProviderId('google_' . rand(100000, 999999))
                         ->setEmail($data['email']);
                
                $this->entityManager->persist($userAuth);
            }

            // Créer l'authentification Apple
            if (str_contains($data['email'], 'apple')) {
                $userAuth = new UserAuthentication();
                $userAuth->setUser($user)
                         ->setProvider('apple')
                         ->setProviderId('apple_' . rand(100000, 999999))
                         ->setEmail($data['email']);
                
                $this->entityManager->persist($userAuth);
            }
        }

        $io->text(sprintf('%d utilisateurs créés', count($users)));
        return $users;
    }

    private function createClubs(SymfonyStyle $io, array $users): array
    {
        $io->section('Création des clubs');

        $clubs = [];
        $clubData = [
            ['name' => 'Club Sportif de Paris', 'description' => 'Club multisports parisien', 'isPublic' => true, 'allowJoinRequests' => true, 'ownerIndex' => 0],
            ['name' => 'Association Sportive Lyon', 'description' => 'Club de sport lyonnais', 'isPublic' => true, 'allowJoinRequests' => true, 'ownerIndex' => 1],
            ['name' => 'Club Privé Marseille', 'description' => 'Club privé marseillais', 'isPublic' => false, 'allowJoinRequests' => false, 'ownerIndex' => 2],
        ];

        foreach ($clubData as $data) {
            $club = new Club();
            $club->setName($data['name'])
                 ->setDescription($data['description'])
                 ->setOwner($users[$data['ownerIndex']])
                 ->setIsPublic($data['isPublic'])
                 ->setAllowJoinRequests($data['allowJoinRequests']);

            $this->entityManager->persist($club);
            $clubs[] = $club;
        }

        $io->text(sprintf('%d clubs créés', count($clubs)));
        return $clubs;
    }

    private function createSeasons(SymfonyStyle $io, array $clubs): array
    {
        $io->section('Création des saisons');

        $seasons = [];
        $currentYear = date('Y');

        foreach ($clubs as $club) {
            // Saison précédente
            $previousSeason = new Season();
            $previousSeason->setName("Saison " . ($currentYear - 1) . "-" . $currentYear)
                          ->setStartDate(new \DateTime(($currentYear - 1) . "-09-01"))
                          ->setEndDate(new \DateTime($currentYear . "-08-31"))
                          ->setClub($club)
                          ->setIsActive(false);

            $this->entityManager->persist($previousSeason);
            $seasons[] = $previousSeason;

            // Saison actuelle
            $currentSeason = new Season();
            $currentSeason->setName("Saison {$currentYear}-" . ($currentYear + 1))
                         ->setStartDate(new \DateTime("{$currentYear}-09-01"))
                         ->setEndDate(new \DateTime(($currentYear + 1) . "-08-31"))
                         ->setClub($club)
                         ->setIsActive(true);

            $this->entityManager->persist($currentSeason);
            $seasons[] = $currentSeason;

            // Saison future
            $futureSeason = new Season();
            $futureSeason->setName("Saison " . ($currentYear + 1) . "-" . ($currentYear + 2))
                        ->setStartDate(new \DateTime(($currentYear + 1) . "-09-01"))
                        ->setEndDate(new \DateTime(($currentYear + 2) . "-08-31"))
                        ->setClub($club)
                        ->setIsActive(false);

            $this->entityManager->persist($futureSeason);
            $seasons[] = $futureSeason;
        }

        $io->text(sprintf('%d saisons créées', count($seasons)));
        return $seasons;
    }

    private function createTeams(SymfonyStyle $io, array $clubs, array $seasons): array
    {
        $io->section('Création des équipes');

        $teams = [];
        $teamNames = ['Équipe Senior', 'Équipe Junior', 'Équipe Féminine', 'Équipe Masculine', 'Équipe Loisir', 'Équipe Compétition'];

        foreach ($clubs as $clubIndex => $club) {
            // Récupérer la saison active du club
            $activeSeason = null;
            foreach ($seasons as $season) {
                if ($season->getClub() === $club && $season->isActive()) {
                    $activeSeason = $season;
                    break;
                }
            }

            if ($activeSeason) {
                $teamCount = rand(2, 4);
                for ($i = 0; $i < $teamCount; $i++) {
                    $team = new Team();
                    $team->setName($teamNames[$i % count($teamNames)])
                         ->setDescription("Description de l'équipe " . ($i + 1))
                         ->setClub($club)
                         ->setSeason($activeSeason)
                         ->setAnnualPrice(rand(200, 800));

                    $this->entityManager->persist($team);
                    $teams[] = $team;
                }
            }
        }

        $io->text(sprintf('%d équipes créées', count($teams)));
        return $teams;
    }

    private function createClubManagers(SymfonyStyle $io, array $clubs, array $users): void
    {
        $io->section('Création des gestionnaires de club');

        $managerCount = 0;
        
        // Ajouter des gestionnaires aux clubs
        foreach ($clubs as $clubIndex => $club) {
            if ($clubIndex < 2) { // Seulement pour les 2 premiers clubs
                $manager = new ClubManager();
                $manager->setClub($club)
                        ->setUser($users[3 + $clubIndex]); // manager1 et manager2

                $this->entityManager->persist($manager);
                $managerCount++;
            }
        }

        $io->text(sprintf('%d gestionnaires de club créés', $managerCount));
    }

    private function createTeamMembers(SymfonyStyle $io, array $teams, array $users): void
    {
        $io->section('Création des membres d\'équipe');

        $memberCount = 0;
        $coachUsers = array_slice($users, 5, 3); // coach1, coach2, coach3
        $athleteUsers = array_slice($users, 8, 5); // athlete1-5

        foreach ($teams as $teamIndex => $team) {
            // Ajouter un coach
            if (isset($coachUsers[$teamIndex % count($coachUsers)])) {
                $teamMember = new TeamMember();
                $teamMember->setUser($coachUsers[$teamIndex % count($coachUsers)])
                           ->setTeam($team)
                           ->setRole('coach');

                $this->entityManager->persist($teamMember);
                $memberCount++;
            }

            // Ajouter des athlètes
            $athleteCount = rand(2, 4);
            for ($i = 0; $i < $athleteCount; $i++) {
                $athleteIndex = ($teamIndex * 2 + $i) % count($athleteUsers);
                $teamMember = new TeamMember();
                $teamMember->setUser($athleteUsers[$athleteIndex])
                           ->setTeam($team)
                           ->setRole('athlete');

                $this->entityManager->persist($teamMember);
                $memberCount++;
            }
        }

        $io->text(sprintf('%d membres d\'équipe créés', $memberCount));
    }

    private function createJoinRequests(SymfonyStyle $io, array $teams, array $users): void
    {
        $io->section('Création des demandes d\'adhésion');

        $requestCount = 0;
        $memberUsers = array_slice($users, 13, 3); // member1-3

        $statuses = ['pending', 'approved', 'rejected'];

        foreach ($teams as $teamIndex => $team) {
            if ($teamIndex < 6) { // Créer des demandes pour les 6 premières équipes
                $memberIndex = $teamIndex % count($memberUsers);
                $joinRequest = new JoinRequest();
                $joinRequest->setUser($memberUsers[$memberIndex])
                           ->setTeam($team)
                           ->setClub($team->getClub())
                           ->setMessage("Je souhaite rejoindre votre équipe car j'ai de l'expérience dans ce sport.")
                           ->setRequestedRole('athlete')
                           ->setStatus($statuses[$teamIndex % count($statuses)]);

                if ($joinRequest->getStatus() !== 'pending') {
                    $joinRequest->setReviewedBy($team->getClub()->getOwner())
                              ->setReviewedAt(new \DateTime('-' . rand(1, 30) . ' days'))
                              ->setReviewNotes('Demande traitée automatiquement par les fixtures');
                    
                    if ($joinRequest->getStatus() === 'approved') {
                        $joinRequest->setAssignedRole('athlete');
                    }
                }

                $this->entityManager->persist($joinRequest);
                $requestCount++;
            }
        }

        $io->text(sprintf('%d demandes d\'adhésion créées', $requestCount));
    }

    private function createPaymentSchedules(SymfonyStyle $io, array $teams): array
    {
        $io->section('Création des échéanciers de paiement');

        $schedules = [];
        
        foreach ($teams as $team) {
            // Créer 3 échéances par équipe
            for ($i = 1; $i <= 3; $i++) {
                $schedule = new PaymentSchedule();
                $schedule->setTeam($team)
                         ->setAmount($team->getAnnualPrice() / 3)
                         ->setDueDate(new \DateTime("+{$i} months"))
                         ->setDescription("Échéance {$i}/3 - Saison " . $team->getSeason()->getName());

                $this->entityManager->persist($schedule);
                $schedules[] = $schedule;
            }
        }

        $io->text(sprintf('%d échéanciers créés', count($schedules)));
        return $schedules;
    }

    private function createPayments(SymfonyStyle $io, array $teams, array $users, array $paymentSchedules): void
    {
        $io->section('Création des paiements');

        $paymentCount = 0;
        $statuses = ['pending', 'paid', 'partial', 'overdue'];

        foreach ($teams as $team) {
            // Récupérer les membres de l'équipe
            $teamMembers = [];
            foreach ($users as $user) {
                // Simuler l'appartenance à l'équipe (simplifié pour les fixtures)
                if (rand(0, 1)) {
                    $teamMembers[] = $user;
                    if (count($teamMembers) >= 3) break; // Limiter à 3 membres par équipe
                }
            }

            // Créer des paiements pour chaque membre
            foreach ($teamMembers as $member) {
                foreach ($paymentSchedules as $schedule) {
                    if ($schedule->getTeam() === $team) {
                        $payment = new Payment();
                        $payment->setUser($member)
                               ->setTeam($team)
                               ->setPaymentSchedule($schedule)
                               ->setAmount($schedule->getAmount())
                               ->setDueDate($schedule->getDueDate())
                               ->setStatus($statuses[array_rand($statuses)]);

                        if ($payment->getStatus() === 'paid') {
                            $payment->setAmountPaid($payment->getAmount())
                                   ->setPaidAt(new \DateTime('-' . rand(1, 30) . ' days'));
                        } elseif ($payment->getStatus() === 'partial') {
                            $payment->setAmountPaid($payment->getAmount() * 0.5);
                        }

                        $this->entityManager->persist($payment);
                        $paymentCount++;
                    }
                }
            }
        }

        $io->text(sprintf('%d paiements créés', $paymentCount));
    }

    private function createEvents(SymfonyStyle $io, array $teams, array $users): array
    {
        $io->section('Création des événements');

        $events = [];
        $eventTitles = [
            'Tournoi de fin d\'année',
            'Compétition régionale',
            'Match amical',
            'Stage d\'été',
            'Gala de fin de saison',
            'Championnat départemental'
        ];

        foreach ($teams as $teamIndex => $team) {
            if ($teamIndex < 8) { // Créer des événements pour les 8 premières équipes
                $event = new Event();
                $event->setTitle($eventTitles[$teamIndex % count($eventTitles)])
                     ->setDescription("Description de l'événement pour l'équipe " . $team->getName())
                     ->setTotalBudget(rand(500, 2000))
                     ->setClubPercentage(rand(10, 30))
                     ->setTeam($team)
                     ->setCreatedBy($team->getClub()->getOwner())
                     ->setEventDate(new \DateTime('+' . rand(30, 180) . ' days'))
                     ->setStatus(rand(0, 1) ? 'active' : 'completed');

                $this->entityManager->persist($event);
                $events[] = $event;
            }
        }

        $io->text(sprintf('%d événements créés', count($events)));
        return $events;
    }

    private function createEventParticipants(SymfonyStyle $io, array $events, array $users): void
    {
        $io->section('Création des participants aux événements');

        $participantCount = 0;

        foreach ($events as $event) {
            $participantNumber = rand(3, 6);
            $selectedUsers = array_slice($users, 8, $participantNumber); // Prendre des athlètes

            foreach ($selectedUsers as $user) {
                $participant = new EventParticipant();
                $participant->setEvent($event)
                           ->setUser($user);

                if ($event->getStatus() === 'completed') {
                    $totalBudget = $event->getTotalBudget();
                    $clubPercentage = $event->getClubPercentage();
                    $availableAmount = $totalBudget * (100 - $clubPercentage) / 100;
                    $participant->setAmountEarned($availableAmount / $participantNumber);
                }

                $this->entityManager->persist($participant);
                $participantCount++;
            }
        }

        $io->text(sprintf('%d participants créés', $participantCount));
    }

    private function createCagnottes(SymfonyStyle $io, array $teams, array $users): array
    {
        $io->section('Création des cagnottes');

        $cagnottes = [];
        $athleteUsers = array_slice($users, 8, 5); // athlete1-5

        foreach ($teams as $teamIndex => $team) {
            foreach ($athleteUsers as $userIndex => $user) {
                if (($teamIndex + $userIndex) % 3 === 0) { // Créer des cagnottes pour certains utilisateurs
                    $cagnotte = new Cagnotte();
                    $cagnotte->setUser($user)
                             ->setTeam($team)
                             ->setCurrentAmount(rand(0, 500))
                             ->setTotalEarned(rand(100, 1000));

                    $this->entityManager->persist($cagnotte);
                    $cagnottes[] = $cagnotte;
                }
            }
        }

        $io->text(sprintf('%d cagnottes créées', count($cagnottes)));
        return $cagnottes;
    }

    private function createCagnotteTransactions(SymfonyStyle $io, array $cagnottes, array $events): void
    {
        $io->section('Création des transactions de cagnotte');

        $transactionCount = 0;

        foreach ($cagnottes as $cagnotte) {
            // Créer quelques transactions pour chaque cagnotte
            $transactionNumber = rand(2, 5);
            
            for ($i = 0; $i < $transactionNumber; $i++) {
                $transaction = new CagnotteTransaction();
                $transaction->setCagnotte($cagnotte)
                           ->setAmount(rand(10, 100))
                           ->setType(rand(0, 1) ? 'credit' : 'debit')
                           ->setDescription('Transaction automatique générée par les fixtures');

                // Associer parfois à un événement
                if (rand(0, 1) && !empty($events)) {
                    $randomEvent = $events[array_rand($events)];
                    if ($randomEvent->getTeam() === $cagnotte->getTeam()) {
                        $transaction->setEvent($randomEvent);
                    }
                }

                $this->entityManager->persist($transaction);
                $transactionCount++;
            }
        }

        $io->text(sprintf('%d transactions de cagnotte créées', $transactionCount));
    }

    private function createDocumentTypes(SymfonyStyle $io, array $teams): void
    {
        $io->section('Création des types de documents');

        $documentTypeCount = 0;
        $documentTypes = [
            ['name' => 'Certificat médical', 'description' => 'Certificat médical obligatoire', 'isRequired' => true],
            ['name' => 'Autorisation parentale', 'description' => 'Pour les mineurs', 'isRequired' => true],
            ['name' => 'Photo d\'identité', 'description' => 'Photo pour la licence', 'isRequired' => false],
            ['name' => 'Assurance', 'description' => 'Attestation d\'assurance', 'isRequired' => true],
        ];

        foreach ($teams as $teamIndex => $team) {
            foreach ($documentTypes as $typeIndex => $typeData) {
                if (($teamIndex + $typeIndex) % 2 === 0) { // Créer des types pour certaines équipes
                    $documentType = new DocumentType();
                    $documentType->setTeam($team)
                                 ->setName($typeData['name'])
                                 ->setDescription($typeData['description'])
                                 ->setIsRequired($typeData['isRequired']);

                    if ($typeData['isRequired']) {
                        $documentType->setDeadline(new \DateTime('+60 days'));
                    }

                    $this->entityManager->persist($documentType);
                    $documentTypeCount++;
                }
            }
        }

        $io->text(sprintf('%d types de documents créés', $documentTypeCount));
    }

    private function createNotifications(SymfonyStyle $io, array $users): void
    {
        $io->section('Création des notifications');

        $notificationCount = 0;
        $notificationTypes = [
            ['type' => 'payment_reminder', 'title' => 'Rappel de paiement', 'message' => 'Votre paiement arrive à échéance'],
            ['type' => 'document_required', 'title' => 'Document requis', 'message' => 'Veuillez fournir le document manquant'],
            ['type' => 'event_created', 'title' => 'Nouvel événement', 'message' => 'Un nouvel événement a été créé'],
            ['type' => 'join_request_approved', 'title' => 'Demande approuvée', 'message' => 'Votre demande d\'adhésion a été approuvée'],
        ];

        foreach ($users as $userIndex => $user) {
            $notificationNumber = rand(1, 4);
            
            for ($i = 0; $i < $notificationNumber; $i++) {
                $notifData = $notificationTypes[array_rand($notificationTypes)];
                
                $notification = new Notification();
                $notification->setUser($user)
                            ->setType($notifData['type'])
                            ->setTitle($notifData['title'])
                            ->setMessage($notifData['message'])
                            ->setIsRead(rand(0, 1) === 1)
                            ->setData(['generated' => true, 'fixture' => true]);

                $this->entityManager->persist($notification);
                $notificationCount++;
            }
        }

        $io->text(sprintf('%d notifications créées', $notificationCount));
    }
} 