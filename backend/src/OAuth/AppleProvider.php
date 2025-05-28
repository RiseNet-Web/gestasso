<?php

namespace App\OAuth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class AppleProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * @var string
     */
    private $teamId;

    /**
     * @var string
     */
    private $keyId;

    /**
     * @var string
     */
    private $privateKey;

    public function __construct(array $options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);

        if (empty($options['teamId'])) {
            throw new \InvalidArgumentException('The "teamId" option is required');
        }

        if (empty($options['keyId'])) {
            throw new \InvalidArgumentException('The "keyId" option is required');
        }

        if (empty($options['privateKey'])) {
            throw new \InvalidArgumentException('The "privateKey" option is required');
        }

        $this->teamId = $options['teamId'];
        $this->keyId = $options['keyId'];
        $this->privateKey = $options['privateKey'];
    }

    public function getBaseAuthorizationUrl()
    {
        return 'https://appleid.apple.com/auth/authorize';
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://appleid.apple.com/auth/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        // Apple ne fournit pas d'endpoint pour récupérer les détails de l'utilisateur
        // Les informations sont dans l'ID token
        return '';
    }

    protected function getDefaultScopes()
    {
        return ['name', 'email'];
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException(
                $data['error'] ?? 'Unknown error',
                $response->getStatusCode(),
                $response
            );
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        // Décoder l'ID token pour obtenir les informations de l'utilisateur
        $idToken = $response['id_token'] ?? null;
        
        if ($idToken) {
            $payload = $this->decodeIdToken($idToken);
            
            return new AppleResourceOwner([
                'sub' => $payload['sub'] ?? null,
                'email' => $payload['email'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
                'is_private_email' => $payload['is_private_email'] ?? false,
                'real_user_status' => $payload['real_user_status'] ?? null,
            ]);
        }

        return new AppleResourceOwner([]);
    }

    /**
     * Génère le client secret JWT pour Apple
     */
    protected function getClientSecret()
    {
        $header = [
            'alg' => 'ES256',
            'kid' => $this->keyId,
        ];

        $payload = [
            'iss' => $this->teamId,
            'iat' => time(),
            'exp' => time() + 3600,
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->clientId,
        ];

        return JWT::encode($payload, $this->privateKey, 'ES256', $this->keyId, $header);
    }

    /**
     * Décoder l'ID token d'Apple
     */
    private function decodeIdToken(string $idToken): array
    {
        // Pour la production, il faudrait récupérer les clés publiques d'Apple
        // et vérifier la signature du token
        // Pour l'instant, on décode simplement le payload
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid ID token format');
        }

        $payload = json_decode(base64_decode($parts[1]), true);
        if (!$payload) {
            throw new \InvalidArgumentException('Invalid ID token payload');
        }

        return $payload;
    }

    protected function getAuthorizationHeaders($token = null)
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    protected function getDefaultHeaders()
    {
        return ['Content-Type' => 'application/x-www-form-urlencoded'];
    }

    protected function prepareAccessTokenResponse(array $result)
    {
        $result = parent::prepareAccessTokenResponse($result);
        
        // Apple inclut l'ID token dans la réponse
        if (isset($result['id_token'])) {
            $result['resource_owner_id'] = $this->getResourceOwnerId($result['id_token']);
        }

        return $result;
    }

    private function getResourceOwnerId(string $idToken): ?string
    {
        try {
            $payload = $this->decodeIdToken($idToken);
            return $payload['sub'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
} 