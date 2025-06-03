<?php

namespace App\Tests\Debug;

use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Enum\AuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DiagnosticTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private JWTTokenManagerInterface $jwtManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
    }

    public function testJWTConfiguration(): void
    {
        echo "\n=== Test 1: Configuration JWT ===\n";
        
        try {
            // Créer un utilisateur simple
            $user = new User();
            $user->setEmail('test@jwt.com')
                 ->setFirstName('Test')
                 ->setLastName('JWT')
                 ->setRoles(['ROLE_USER'])
                 ->setIsActive(true)
                 ->setOnboardingType('member')
                 ->setOnboardingCompleted(true);

            // Essayer de créer un token JWT
            $token = $this->jwtManager->create($user);
            echo "✓ Token JWT créé avec succès\n";
            echo "Token: " . substr($token, 0, 50) . "...\n";
            
            // Vérifier que le token a 3 parties
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                echo "✓ Token JWT a la structure correcte (3 parties)\n";
            } else {
                echo "✗ Token JWT malformé (" . count($parts) . " parties)\n";
            }
            
        } catch (\Exception $e) {
            echo "✗ Erreur JWT: " . $e->getMessage() . "\n";
            echo "Type: " . get_class($e) . "\n";
        }
    }

    public function testDatabaseConnection(): void
    {
        echo "\n=== Test 2: Connexion base de données ===\n";
        
        try {
            // Test simple de requête
            $connection = $this->entityManager->getConnection();
            $result = $connection->executeQuery('SELECT 1 as test')->fetchAssociative();
            
            if ($result && $result['test'] == 1) {
                echo "✓ Connexion base de données OK\n";
            } else {
                echo "✗ Problème avec la connexion base de données\n";
            }
            
        } catch (\Exception $e) {
            echo "✗ Erreur base de données: " . $e->getMessage() . "\n";
        }
    }

    public function testUserCreation(): void
    {
        echo "\n=== Test 3: Création utilisateur ===\n";
        
        try {
            $user = new User();
            $user->setEmail('diagnostic@test.com')
                 ->setFirstName('Diagnostic')
                 ->setLastName('Test')
                 ->setRoles(['ROLE_USER'])
                 ->setIsActive(true)
                 ->setOnboardingType('member')
                 ->setOnboardingCompleted(true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            echo "✓ Utilisateur créé avec succès (ID: " . $user->getId() . ")\n";
            
            // Nettoyer
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            echo "✓ Utilisateur supprimé\n";
            
        } catch (\Exception $e) {
            echo "✗ Erreur création utilisateur: " . $e->getMessage() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
        }
    }

    public function testServices(): void
    {
        echo "\n=== Test 4: Services disponibles ===\n";
        
        $services = [
            'doctrine.orm.entity_manager' => EntityManagerInterface::class,
            'lexik_jwt_authentication.jwt_manager' => JWTTokenManagerInterface::class,
        ];

        foreach ($services as $serviceId => $expectedClass) {
            try {
                $service = static::getContainer()->get($expectedClass);
                echo "✓ Service $serviceId disponible\n";
            } catch (\Exception $e) {
                echo "✗ Service $serviceId indisponible: " . $e->getMessage() . "\n";
            }
        }
    }
} 