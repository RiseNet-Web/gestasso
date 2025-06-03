<?php

namespace App\Tests\Debug;

use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Enum\AuthProvider;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TestRegistration extends KernelTestCase
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

    public function testUserCreation(): void
    {
        // Données de test
        $userData = [
            'email' => 'debug.test@example.com',
            'password' => 'password123',
            'firstName' => 'Debug',
            'lastName' => 'Test',
            'onboardingType' => 'member',
            'phone' => '0123456789',
            'dateOfBirth' => '1995-06-15'
        ];

        echo "Données utilisateur:\n";
        print_r($userData);

        // Test 1: Création manuelle de l'utilisateur
        echo "\n=== Test 1: Création manuelle User ===\n";
        $user = new User();
        $user->setEmail($userData['email'])
             ->setFirstName($userData['firstName'])
             ->setLastName($userData['lastName'])
             ->setOnboardingType($userData['onboardingType'])
             ->setIsActive(true)
             ->setOnboardingCompleted(false);

        if (!empty($userData['phone'])) {
            $user->setPhone($userData['phone']);
        }

        if (!empty($userData['dateOfBirth'])) {
            $user->setDateOfBirth(new \DateTime($userData['dateOfBirth']));
        }

        $user->setRoles(['ROLE_MEMBER']);

        echo "User créé, validation...\n";
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            echo "ERREURS User:\n";
            echo $userData['dateOfBirth'] . "\n";
            foreach ($errors as $error) {
                echo "- " . $error->getPropertyPath() . ": " . $error->getMessage() . "\n";
            }
        } else {
            echo "User valide!\n";
        }

        // Test 2: Création manuelle UserAuthentication
        echo "\n=== Test 2: Création manuelle UserAuthentication ===\n";
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail($userData['email'])
                 ->setPassword(password_hash($userData['password'], PASSWORD_DEFAULT));

        echo "UserAuth créé, validation...\n";
        $authErrors = $this->validator->validate($userAuth);
        if (count($authErrors) > 0) {
            echo "ERREURS UserAuthentication:\n";
            foreach ($authErrors as $error) {
                echo "- " . $error->getPropertyPath() . ": " . $error->getMessage() . "\n";
            }
        } else {
            echo "UserAuthentication valide!\n";
        }

        // Test 3: Utilisation du service
        echo "\n=== Test 3: Service AuthenticationService ===\n";
        try {
            $result = $this->authService->register($userData);
            echo "Service réussi! Token: " . substr($result['token'], 0, 20) . "...\n";
        } catch (\Exception $e) {
            echo "ERREUR Service: " . $e->getMessage() . "\n";
            echo "Type: " . get_class($e) . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    }
} 