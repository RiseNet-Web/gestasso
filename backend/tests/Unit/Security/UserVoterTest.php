<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class UserVoterTest extends TestCase
{
    private UserVoter $voter;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->voter = new UserVoter($this->entityManager);
    }

    /**
     * Test que l'utilisateur peut voir ses propres documents
     */
    public function testUserCanViewOwnDocuments(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $user, [UserVoter::VIEW_DOCUMENTS]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test qu'un utilisateur non authentifié ne peut pas accéder aux documents
     */
    public function testUnauthenticatedUserCannotViewDocuments(): void
    {
        $targetUser = new User();
        $targetUser->setEmail('target@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW_DOCUMENTS]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * Test qu'un admin peut voir les documents de n'importe qui
     */
    public function testAdminCanViewAnyDocuments(): void
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);

        $targetUser = new User();
        $targetUser->setEmail('target@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW_DOCUMENTS]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test que le voter retourne ABSTAIN pour les attributs non supportés
     */
    public function testVoterAbstainsForUnsupportedAttributes(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $user, ['UNSUPPORTED_PERMISSION']);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * Test que le voter retourne ABSTAIN pour les objets non supportés
     */
    public function testVoterAbstainsForUnsupportedSubjects(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, new \stdClass(), [UserVoter::VIEW_DOCUMENTS]);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }
} 