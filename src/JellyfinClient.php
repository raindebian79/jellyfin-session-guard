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
     * Fuerza la desconexión de una sesión específica por su ID.
     */
    public function disconnectSession(string $sessionId): bool
    {
        try {
            $url = sprintf('Sessions/%s/Command/Disconnect', $sessionId);
            $response = $this->httpClient->post($url);
            
            return $response->getStatusCode() === 204 || $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            $this->logger->error("Error al desconectar la sesión {$sessionId}: " . $e->getMessage());
            return false;
        }
    }
}
