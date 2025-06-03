<?php

namespace App\Tests\Debug;

use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Enum\AuthProvider;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AuthenticationService $authService;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->authService = static::getContainer()->get(AuthenticationService::class);
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    public function testAuthServiceRegistration(): void
    {
        echo "\n=== Test Service Registration ===\n";
        
        // Nettoyer d'abord
        $this->cleanupTestUser('debug.register@test.com');
        
        try {
            $userData = [
                'email' => 'debug.register@test.com',
                'password' => 'password123',
                'firstName' => 'Debug',
                'lastName' => 'Register',
                'onboardingType' => 'member'
            ];

            $result = $this->authService->register($userData);
            
            echo "✓ Registration réussie\n";
            echo "Token: " . substr($result['token'], 0, 50) . "...\n";
            echo "User ID: " . $result['user']->getId() . "\n";
            
            // Nettoyer
            $this->cleanupTestUser('debug.register@test.com');
            
        } catch (\Exception $e) {
            echo "✗ Erreur Registration: " . $e->getMessage() . "\n";
            echo "Type: " . get_class($e) . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
    }

    public function testAuthServiceLogin(): void
    {
        echo "\n=== Test Service Login ===\n";
        
        // Nettoyer d'abord
        $this->cleanupTestUser('debug.login@test.com');
        
        try {
            // Créer d'abord un utilisateur
            $user = new User();
            $user->setEmail('debug.login@test.com')
                 ->setFirstName('Debug')
                 ->setLastName('Login')
                 ->setRoles(['ROLE_USER'])
                 ->setIsActive(true)
                 ->setOnboardingType('member')
                 ->setOnboardingCompleted(true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Créer l'authentification
            $userAuth = new UserAuthentication();
            $userAuth->setUser($user)
                     ->setProvider(AuthProvider::EMAIL)
                     ->setEmail('debug.login@test.com')
                     ->setPassword(password_hash('password123', PASSWORD_DEFAULT))
                     ->setIsActive(true);

            $this->entityManager->persist($userAuth);
            $this->entityManager->flush();

            echo "✓ Utilisateur de test créé\n";

            // Tester le login
            $result = $this->authService->login('debug.login@test.com', 'password123');
            
            echo "✓ Login réussi\n";
            echo "Token: " . substr($result['token'], 0, 50) . "...\n";
            echo "User ID: " . $result['user']->getId() . "\n";
            
            // Nettoyer
            $this->cleanupTestUser('debug.login@test.com');
            
        } catch (\Exception $e) {
            echo "✗ Erreur Login: " . $e->getMessage() . "\n";
            echo "Type: " . get_class($e) . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            
            // Nettoyer en cas d'erreur
            $this->cleanupTestUser('debug.login@test.com');
        }
    }

    public function testValidationService(): void
    {
        echo "\n=== Test Validation Service ===\n";
        
        try {
            // Test avec des données invalides
            $user = new User();
            $user->setEmail('invalid-email')  // Email invalide
                 ->setFirstName('')  // Prénom vide
                 ->setLastName('Test')
                 ->setRoles(['ROLE_USER'])
                 ->setIsActive(true)
                 ->setOnboardingType('member');

            $errors = $this->validator->validate($user);
            
            if (count($errors) > 0) {
                echo "✓ Validation fonctionne (trouvé " . count($errors) . " erreurs)\n";
                foreach ($errors as $error) {
                    echo "  - " . $error->getPropertyPath() . ": " . $error->getMessage() . "\n";
                }
            } else {
                echo "✗ Validation ne fonctionne pas correctement\n";
            }
            
        } catch (\Exception $e) {
            echo "✗ Erreur Validation: " . $e->getMessage() . "\n";
        }
    }

    private function cleanupTestUser(string $email): void
    {
        try {
            // Supprimer UserAuthentication
            $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
                ->findOneBy(['email' => $email]);
            if ($userAuth) {
                $this->entityManager->remove($userAuth);
            }

            // Supprimer User
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $email]);
            if ($user) {
                $this->entityManager->remove($user);
            }

            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Ignorer les erreurs de nettoyage
        }
    }
} 