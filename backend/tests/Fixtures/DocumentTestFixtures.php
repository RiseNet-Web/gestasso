<?php

namespace App\Tests\Fixtures;

use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\DocumentType;
use App\Entity\Document;
use App\Entity\TeamMember;
use App\Entity\UserAuthentication;
use App\Enum\AuthProvider;
use App\Enum\DocumentType as DocumentTypeEnum;
use App\Enum\DocumentStatus;
use App\Enum\TeamMemberRole;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fixtures pour les tests de documents
 * Basées sur les personas du markdown : Marc Dubois, Julie Moreau, Emma Leblanc
 */
class DocumentTestFixtures
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Crée la configuration complète des personas et du club selon les scénarios
     */
    public function createCompleteDocumentFixtures(): array
    {
        // Créer les personas
        $marc = $this->createMarc();
        $julie = $this->createJulie();
        $emma = $this->createEmma();

        // Créer le Racing Club Paris avec Marc comme propriétaire
        $racingClub = $this->createRacingClub($marc);

        // Créer l'équipe U18 Filles
        $u18Team = $this->createU18Team($racingClub);

        // Ajouter Julie comme coach de l'équipe
        $this->addJulieAsCoach($julie, $u18Team);

        // Ajouter Emma comme membre de l'équipe
        $this->addEmmaToTeam($emma, $u18Team);

        // Créer les types de documents pour l'équipe
        $documentTypes = $this->createDocumentTypes($u18Team);

        // Créer quelques documents d'exemple
        $documents = $this->createSampleDocuments($emma, $documentTypes);

        $this->entityManager->flush();

        return [
            'users' => [
                'marc' => $marc,
                'julie' => $julie,
                'emma' => $emma
            ],
            'club' => $racingClub,
            'team' => $u18Team,
            'documentTypes' => $documentTypes,
            'documents' => $documents
        ];
    }

    /**
     * Créer Marc Dubois (Propriétaire de club)
     */
    public function createMarc(): User
    {
        $marc = new User();
        $marc->setEmail('marc@racing.fr');
        $marc->setFirstName('Marc');
        $marc->setLastName('Dubois');
        $marc->setDateOfBirth(new \DateTime('1975-05-15'));
        $marc->setGender('M');
        $marc->setPhoneNumber('+33 6 12 34 56 78');
        $marc->setIsActive(true);

        // Authentification
        $auth = new UserAuthentication();
        $auth->setUser($marc);
        $auth->setProvider(AuthProvider::EMAIL);
        $auth->setEmail('marc@racing.fr');
        $auth->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        $auth->setIsVerified(true);
        $auth->setIsActive(true);

        $this->entityManager->persist($marc);
        $this->entityManager->persist($auth);

        return $marc;
    }

    /**
     * Créer Julie Moreau (Coach équipe U18 Filles)
     */
    public function createJulie(): User
    {
        $julie = new User();
        $julie->setEmail('julie@racing.fr');
        $julie->setFirstName('Julie');
        $julie->setLastName('Moreau');
        $julie->setDateOfBirth(new \DateTime('1985-09-12'));
        $julie->setGender('F');
        $julie->setPhoneNumber('+33 6 23 45 67 89');
        $julie->setIsActive(true);

        // Authentification
        $auth = new UserAuthentication();
        $auth->setUser($julie);
        $auth->setProvider(AuthProvider::EMAIL);
        $auth->setEmail('julie@racing.fr');
        $auth->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        $auth->setIsVerified(true);
        $auth->setIsActive(true);

        $this->entityManager->persist($julie);
        $this->entityManager->persist($auth);

        return $julie;
    }

    /**
     * Créer Emma Leblanc (Athlète, 16 ans, équipe U18)
     */
    public function createEmma(): User
    {
        $emma = new User();
        $emma->setEmail('emma@test.com');
        $emma->setFirstName('Emma');
        $emma->setLastName('Leblanc');
        $emma->setDateOfBirth(new \DateTime('2008-03-10')); // 16 ans
        $emma->setGender('F');
        $emma->setPhoneNumber('+33 6 34 56 78 90');
        $emma->setIsActive(true);

        // Authentification
        $auth = new UserAuthentication();
        $auth->setUser($emma);
        $auth->setProvider(AuthProvider::EMAIL);
        $auth->setEmail('emma@test.com');
        $auth->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        $auth->setIsVerified(true);
        $auth->setIsActive(true);

        $this->entityManager->persist($emma);
        $this->entityManager->persist($auth);

        return $emma;
    }

    /**
     * Créer le Racing Club Paris
     */
    public function createRacingClub(User $marc): Club
    {
        $racingClub = new Club();
        $racingClub->setName('Racing Club Paris');
        $racingClub->setDescription('Club de tennis parisien fondé en 1982');
        $racingClub->setOwner($marc);
        $racingClub->setIsPublic(true);
        $racingClub->setAllowJoinRequests(true);
        $racingClub->setIsActive(true);

        $this->entityManager->persist($racingClub);

        return $racingClub;
    }

    /**
     * Créer l'équipe U18 Filles
     */
    public function createU18Team(Club $club): Team
    {
        $u18Team = new Team();
        $u18Team->setName('U18 Filles');
        $u18Team->setDescription('Équipe féminine des moins de 18 ans');
        $u18Team->setClub($club);
        $u18Team->setMinAge(16);
        $u18Team->setMaxAge(18);
        $u18Team->setGender('F');
        $u18Team->setIsActive(true);

        $this->entityManager->persist($u18Team);

        return $u18Team;
    }

    /**
     * Ajouter Julie comme coach de l'équipe
     */
    public function addJulieAsCoach(User $julie, Team $team): void
    {
        $teamMember = new TeamMember();
        $teamMember->setUser($julie);
        $teamMember->setTeam($team);
        $teamMember->setRole(TeamMemberRole::COACH);
        $teamMember->setIsActive(true);

        $this->entityManager->persist($teamMember);
    }

    /**
     * Ajouter Emma à l'équipe comme joueuse
     */
    public function addEmmaToTeam(User $emma, Team $team): void
    {
        $teamMember = new TeamMember();
        $teamMember->setUser($emma);
        $teamMember->setTeam($team);
        $teamMember->setRole(TeamMemberRole::PLAYER);
        $teamMember->setIsActive(true);

        $this->entityManager->persist($teamMember);
    }

    /**
     * Créer les types de documents selon les scénarios du markdown
     */
    public function createDocumentTypes(Team $team): array
    {
        $documentTypes = [];

        // 1. Certificat médical (obligatoire, expire après 365 jours)
        $certificatMedical = new DocumentType();
        $certificatMedical->setName('Certificat médical');
        $certificatMedical->setDescription('Certificat d\'aptitude sportive obligatoire');
        $certificatMedical->setType(DocumentTypeEnum::MEDICAL_CERTIFICATE);
        $certificatMedical->setTeam($team);
        $certificatMedical->setIsRequired(true);
        $certificatMedical->setHasExpirationDate(true);
        $certificatMedical->setValidityDurationInDays(365);
        $certificatMedical->setIsActive(true);

        $this->entityManager->persist($certificatMedical);
        $documentTypes['certificat_medical'] = $certificatMedical;

        // 2. Licence FFT (obligatoire, expire après 365 jours)
        $licenceFft = new DocumentType();
        $licenceFft->setName('Licence FFT');
        $licenceFft->setDescription('Licence Fédération Française de Tennis');
        $licenceFft->setType(DocumentTypeEnum::LICENSE);
        $licenceFft->setTeam($team);
        $licenceFft->setIsRequired(true);
        $licenceFft->setHasExpirationDate(true);
        $licenceFft->setValidityDurationInDays(365);
        $licenceFft->setIsActive(true);

        $this->entityManager->persist($licenceFft);
        $documentTypes['licence_fft'] = $licenceFft;

        // 3. Autorisation parentale (obligatoire pour les mineurs, n'expire pas)
        $autorisationParentale = new DocumentType();
        $autorisationParentale->setName('Autorisation parentale');
        $autorisationParentale->setDescription('Autorisation parentale obligatoire pour les mineurs');
        $autorisationParentale->setType(DocumentTypeEnum::AUTHORIZATION);
        $autorisationParentale->setTeam($team);
        $autorisationParentale->setIsRequired(true);
        $autorisationParentale->setHasExpirationDate(false);
        $autorisationParentale->setValidityDurationInDays(null);
        $autorisationParentale->setIsActive(true);

        $this->entityManager->persist($autorisationParentale);
        $documentTypes['autorisation_parentale'] = $autorisationParentale;

        // 4. Photo d'identité (optionnel, n'expire pas)
        $photoIdentite = new DocumentType();
        $photoIdentite->setName('Photo d\'identité');
        $photoIdentite->setDescription('Photo d\'identité pour le trombinoscope');
        $photoIdentite->setType(DocumentTypeEnum::PHOTO);
        $photoIdentite->setTeam($team);
        $photoIdentite->setIsRequired(false);
        $photoIdentite->setHasExpirationDate(false);
        $photoIdentite->setValidityDurationInDays(null);
        $photoIdentite->setIsActive(true);

        $this->entityManager->persist($photoIdentite);
        $documentTypes['photo_identite'] = $photoIdentite;

        return $documentTypes;
    }

    /**
     * Créer des documents d'exemple avec différents statuts
     */
    public function createSampleDocuments(User $emma, array $documentTypes): array
    {
        $documents = [];

        // Document validé : Certificat médical d'Emma
        $certificatValide = new Document();
        $certificatValide->setUser($emma);
        $certificatValide->setDocumentTypeEntity($documentTypes['certificat_medical']);
        $certificatValide->setOriginalFileName('certificat_emma.pdf');
        $certificatValide->setSecurePath('secure/' . uniqid() . '.pdf');
        $certificatValide->setMimeType('application/pdf');
        $certificatValide->setFileSize(2411724); // 2.3 MB
        $certificatValide->setStatus(DocumentStatus::APPROVED);
        $certificatValide->setDescription('Certificat médical 2025');
        $certificatValide->setValidatedAt(new \DateTime('-30 days'));
        $certificatValide->setExpirationDate(new \DateTime('+335 days')); // 365 - 30

        $this->entityManager->persist($certificatValide);
        $documents['certificat_valide'] = $certificatValide;

        // Document en attente : Licence FFT d'Emma
        $licenceEnAttente = new Document();
        $licenceEnAttente->setUser($emma);
        $licenceEnAttente->setDocumentTypeEntity($documentTypes['licence_fft']);
        $licenceEnAttente->setOriginalFileName('licence_emma.pdf');
        $licenceEnAttente->setSecurePath('secure/' . uniqid() . '.pdf');
        $licenceEnAttente->setMimeType('application/pdf');
        $licenceEnAttente->setFileSize(1887436); // 1.8 MB
        $licenceEnAttente->setStatus(DocumentStatus::PENDING);
        $licenceEnAttente->setDescription('Licence FFT 2025');

        $this->entityManager->persist($licenceEnAttente);
        $documents['licence_pending'] = $licenceEnAttente;

        // Document rejeté : Autorisation parentale illisible
        $autorisationRejetee = new Document();
        $autorisationRejetee->setUser($emma);
        $autorisationRejetee->setDocumentTypeEntity($documentTypes['autorisation_parentale']);
        $autorisationRejetee->setOriginalFileName('autorisation_floue.pdf');
        $autorisationRejetee->setSecurePath('secure/' . uniqid() . '.pdf');
        $autorisationRejetee->setMimeType('application/pdf');
        $autorisationRejetee->setFileSize(934218); // 0.9 MB
        $autorisationRejetee->setStatus(DocumentStatus::REJECTED);
        $autorisationRejetee->setDescription('Autorisation parentale');
        $autorisationRejetee->setRejectionReason('Document illisible, merci de re-scanner');
        $autorisationRejetee->setValidatedAt(new \DateTime('-5 days'));

        $this->entityManager->persist($autorisationRejetee);
        $documents['autorisation_rejected'] = $autorisationRejetee;

        return $documents;
    }

    /**
     * Créer un club concurrent (Tennis Club Lyon) pour tester l'isolation
     */
    public function createCompetitorClub(): array
    {
        // Créer Paul Martin (propriétaire du Tennis Club Lyon)
        $paul = new User();
        $paul->setEmail('paul@lyon.fr');
        $paul->setFirstName('Paul');
        $paul->setLastName('Martin');
        $paul->setDateOfBirth(new \DateTime('1978-11-22'));
        $paul->setGender('M');
        $paul->setIsActive(true);

        // Authentification pour Paul
        $paulAuth = new UserAuthentication();
        $paulAuth->setUser($paul);
        $paulAuth->setProvider(AuthProvider::EMAIL);
        $paulAuth->setEmail('paul@lyon.fr');
        $paulAuth->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        $paulAuth->setIsVerified(true);
        $paulAuth->setIsActive(true);

        // Créer le Tennis Club Lyon
        $tennisClub = new Club();
        $tennisClub->setName('Tennis Club Lyon');
        $tennisClub->setDescription('Club de tennis lyonnais');
        $tennisClub->setOwner($paul);
        $tennisClub->setIsPublic(true);
        $tennisClub->setAllowJoinRequests(true);
        $tennisClub->setIsActive(true);

        // Créer une équipe Senior
        $seniorTeam = new Team();
        $seniorTeam->setName('Équipe Senior');
        $seniorTeam->setClub($tennisClub);
        $seniorTeam->setMinAge(18);
        $seniorTeam->setMaxAge(null);
        $seniorTeam->setIsActive(true);

        $this->entityManager->persist($paul);
        $this->entityManager->persist($paulAuth);
        $this->entityManager->persist($tennisClub);
        $this->entityManager->persist($seniorTeam);

        return [
            'user' => $paul,
            'club' => $tennisClub,
            'team' => $seniorTeam
        ];
    }

    /**
     * Créer des fichiers de test pour les uploads
     */
    public function createTestFiles(): array
    {
        $testFiles = [];

        // Fichier PDF valide (2.3 MB)
        $testFiles['certificat_valide.pdf'] = $this->createTestPdf('certificat_valide.pdf', 2.3);

        // Fichier image JPEG valide (1.8 MB)
        $testFiles['photo_identite.jpg'] = $this->createTestJpeg('photo_identite.jpg');

        // Fichier trop volumineux (6.2 MB)
        $testFiles['document_trop_gros.pdf'] = $this->createTestPdf('document_trop_gros.pdf', 6.2);

        return $testFiles;
    }

    /**
     * Créer un fichier PDF de test
     */
    private function createTestPdf(string $filename, float $sizeMB): string
    {
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        $sizeBytes = (int)($sizeMB * 1024 * 1024);
        
        // Contenu PDF minimal mais valide
        $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000079 00000 n \n0000000136 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n224\n%%EOF";
        
        // Remplir jusqu'à la taille souhaitée
        $padding = str_repeat(' ', max(0, $sizeBytes - strlen($pdfContent)));
        file_put_contents($tempFile, $pdfContent . $padding);
        
        return $tempFile;
    }

    /**
     * Créer un fichier JPEG de test
     */
    private function createTestJpeg(string $filename): string
    {
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        
        // Créer une image JPEG de test (100x100 pixels)
        $image = imagecreatetruecolor(100, 100);
        $blue = imagecolorallocate($image, 0, 100, 200);
        imagefill($image, 0, 0, $blue);
        imagejpeg($image, $tempFile, 90);
        imagedestroy($image);
        
        return $tempFile;
    }

    /**
     * Nettoyer les fichiers de test créés
     */
    public function cleanupTestFiles(array $testFiles): void
    {
        foreach ($testFiles as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
} 