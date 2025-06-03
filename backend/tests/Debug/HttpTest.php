<?php

namespace App\Tests\Debug;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class HttpTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->setServerParameter('CONTENT_TYPE', 'application/json');
        $this->client->setServerParameter('HTTP_ACCEPT', 'application/json');
    }

    public function testRegisterEndpoint(): void
    {
        echo "\n=== Test Register Endpoint ===\n";
        
        $userData = [
            'email' => 'http.test@example.com',
            'password' => 'password123',
            'firstName' => 'Http',
            'lastName' => 'Test',
            'onboardingType' => 'member'
        ];

        try {
            $this->client->request(
                'POST',
                '/api/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($userData)
            );

            $response = $this->client->getResponse();
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            echo "Status Code: " . $statusCode . "\n";
            echo "Content Type: " . $response->headers->get('Content-Type') . "\n";
            
            if ($statusCode === 500) {
                echo "✗ ERREUR 500 - Contenu de la réponse:\n";
                echo $content . "\n";
                
                // Essayer de décoder le JSON pour plus d'infos
                $data = json_decode($content, true);
                if ($data && isset($data['message'])) {
                    echo "Message d'erreur: " . $data['message'] . "\n";
                }
                if ($data && isset($data['trace'])) {
                    echo "Stack trace: " . $data['trace'] . "\n";
                }
            } elseif ($statusCode === 201) {
                echo "✓ Registration réussie\n";
                $data = json_decode($content, true);
                if ($data && isset($data['token'])) {
                    echo "Token reçu: " . substr($data['token'], 0, 50) . "...\n";
                }
            } else {
                echo "Status inattendu: " . $statusCode . "\n";
                echo "Contenu: " . $content . "\n";
            }

        } catch (\Exception $e) {
            echo "✗ Exception: " . $e->getMessage() . "\n";
            echo "Type: " . get_class($e) . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
    }

    public function testLoginEndpoint(): void
    {
        echo "\n=== Test Login Endpoint ===\n";
        
        $loginData = [
            'email' => 'nonexistent@test.com',
            'password' => 'password123'
        ];

        try {
            $this->client->request(
                'POST',
                '/api/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($loginData)
            );

            $response = $this->client->getResponse();
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            echo "Status Code: " . $statusCode . "\n";
            echo "Content Type: " . $response->headers->get('Content-Type') . "\n";
            
            if ($statusCode === 500) {
                echo "✗ ERREUR 500 - Contenu de la réponse:\n";
                echo $content . "\n";
            } elseif ($statusCode === 401) {
                echo "✓ Login échoué comme attendu (utilisateur inexistant)\n";
            } else {
                echo "Status: " . $statusCode . "\n";
                echo "Contenu: " . $content . "\n";
            }

        } catch (\Exception $e) {
            echo "✗ Exception: " . $e->getMessage() . "\n";
            echo "Type: " . get_class($e) . "\n";
        }
    }

    public function testProfileEndpoint(): void
    {
        echo "\n=== Test Profile Endpoint (sans auth) ===\n";
        
        try {
            $this->client->request('GET', '/api/profile');

            $response = $this->client->getResponse();
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            echo "Status Code: " . $statusCode . "\n";
            
            if ($statusCode === 500) {
                echo "✗ ERREUR 500 - Contenu:\n";
                echo $content . "\n";
            } elseif ($statusCode === 401) {
                echo "✓ Non authentifié comme attendu\n";
            } else {
                echo "Status: " . $statusCode . "\n";
                echo "Contenu: " . substr($content, 0, 200) . "...\n";
            }

        } catch (\Exception $e) {
            echo "✗ Exception: " . $e->getMessage() . "\n";
        }
    }
} 