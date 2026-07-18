<?php
declare(strict_types=1);

namespace JellyfinGuard;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class JellyfinClient
{
    private Client $httpClient;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;

        // Inicializar el cliente de Guzzle apuntando al contenedor local de Jellyfin
        $this->httpClient = new Client([
            'base_uri' => rtrim($this->config->getJellyfinUrl(), '/') . '/',
            'timeout'  => 5.0,
            'headers' => [
                'X-Emby-Token' => $this->config->getJellyfinApiKey(),
                'Accept'       => 'application/json',
            ]
        ]);
    }

    /**
     * Obtiene el listado completo de sesiones activas en Jellyfin.
     * 
     * @return array<mixed>
     */
    public function getActiveSessions(): array
    {
        try {
            $response = $this->httpClient->get('Sessions');
            $body = (string)$response->getBody();
            
            return json_decode($body, true) ?? [];
        } catch (GuzzleException $e) {
            $this->logger->error("Error al obtener las sesiones de Jellyfin: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fuerza la desconexión total de la sesión.
     * Usa un método limpio para el admin y la contramedida radical para usuarios estándar.
     */
    public function disconnectSession(string $sessionId): bool
    {
        try {
            // 1. Obtener los detalles de la sesión para sacar el UserId y UserName
            $response = $this->httpClient->get('Sessions');
            $sessions = json_decode($response->getBody()->getContents(), true);

            $userId = null;
            $userName = null;
            foreach ($sessions as $session) {
                if (($session['Id'] ?? '') === $sessionId) {
                    $userId = $session['UserId'] ?? null;
                    $userName = strtolower($session['UserName'] ?? '');
                    break;
                }
            }

            // Si no hay UserId, respaldo clásico
            if (!$userId) {
                $this->httpClient->post(sprintf('Sessions/Logout?sessionId=%s', $sessionId));
                return true;
            }

            // 2. PROTECCIÓN DE ADMIN: Si eres tú (raindebian), usamos solo el Logout nativo
            if ($userName === 'raindebian') {
                $url = sprintf('Sessions/Logout?sessionId=%s', $sessionId);
                $response = $this->httpClient->post($url);
                return $response->getStatusCode() === 204 || $response->getStatusCode() === 200;
            }

            // 3. CONTRAMEDIDA RADICAL (Para metalero5 y usuarios estándar)
            // Obtener configuración actual
            $userResponse = $this->httpClient->get(sprintf('Users/%s', $userId));
            $userData = json_decode($userResponse->getBody()->getContents(), true);
            $policy = $userData['Policy'] ?? [];

            // Deshabilitar temporalmente
            $disabledPolicy = $policy;
            $disabledPolicy['IsDisabled'] = true;
            $this->httpClient->post(sprintf('Users/%s/Policy', $userId), [
                'json' => $disabledPolicy
            ]);

            // Volver a habilitar de inmediato
            $enabledPolicy = $policy;
            $enabledPolicy['IsDisabled'] = false;
            $this->httpClient->post(sprintf('Users/%s/Policy', $userId), [
                'json' => $enabledPolicy
            ]);

            return true;

        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->logger->error("Error al desconectar la sesión {$sessionId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene la lista de IDs de todos los usuarios que son administradores.
     * @return array<string>
     */
    public function getAdminUserIds(): array
    {
        try {
            $response = $this->httpClient->get('Users');
            $users = json_decode($response->getBody()->getContents(), true);
            
            $adminIds = [];
            foreach ($users as $user) {
                if (($user['Policy']['IsAdministrator'] ?? false) === true) {
                    $adminIds[] = $user['Id'] ?? '';
                }
            }
            return array_filter($adminIds);
        } catch (\Exception $e) {
            $this->logger->error("Error al obtener lista de administradores: " . $e->getMessage());
            return [];
        }
    }
}
