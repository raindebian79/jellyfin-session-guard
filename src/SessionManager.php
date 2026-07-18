<?php
declare(strict_types=1);

namespace JellyfinGuard;

use Psr\Log\LoggerInterface;

class SessionManager
{
    private Config $config;
    private JellyfinClient $client;
    private LoggerInterface $logger;

    public function __construct(Config $config, JellyfinClient $client, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Revisa el estado actual de todas las sesiones y aplica las reglas de negocio.
     */
    public function checkAndGuard(): void
    {
        $sessions = $this->client->getActiveSessions();
        if (empty($sessions)) {
            return;
        }

        // Obtener dinámicamente los IDs de los administradores reales del servidor
        $adminUserIds = $this->client->getAdminUserIds();

        // Obtener la lista de usuarios omitidos desde la configuración estática
        $ignoredUsers = array_map('strtolower', $this->config->getIgnoreUsers());

        // Agrupar sesiones activas por nombre de usuario
        $userSessions = [];
        foreach ($sessions as $session) {
            $userName = $session['UserName'] ?? null;
            $sessionId = $session['Id'] ?? null;
            $userId = $session['UserId'] ?? null;
            $clientName = $session['Client'] ?? ''; 

            // Saltar si la sesión no tiene datos válidos
            if (!$userName || !$sessionId || !$userId) {
                continue;
            }

            // Inmunidad total si está en la lista de ignorados del config
            if (in_array(strtolower($userName), $ignoredUsers, true)) {
                continue;
            }

            // Comprobación real: ¿El ID de este usuario pertenece a un administrador?
            $isAdmin = in_array($userId, $adminUserIds, true);

            // ====================================================================
            // REGLA DE EXCLUSIÓN WEB
            // ====================================================================
            if (stripos($clientName, 'Finamp') === false) {
                if ($isAdmin) {
                    // Al admin se le permite usar la web de forma nativa sin molestarle
                    continue;
                }

                $this->logger->warning("EXPULSIÓN WEB: El usuario estándar '{$userName}' intentó conectar usando '{$clientName}'. Clausurando sesión...");

                $success = $this->client->disconnectSession($sessionId);
                if ($success) {
                    $this->logger->info("Sesión web '{$sessionId}' desconectada exitosamente.");
                } else {
                    $this->logger->error("No se pudo desconectar la sesión web '{$sessionId}'.");
                }

                continue;
            }

            // Si es Finamp (sea de admin o usuario común), entra al conteo de límites por cantidad
            $userSessions[strtolower($userName)][] = $session;
        }

        // Procesar límites de sesiones simultáneas en Finamp
        foreach ($userSessions as $lowercaseName => $sessionsList) {
            $realName = $sessionsList[0]['UserName'];

            $limits = $this->config->getUsersLimits();
            $maxAllowed = $limits[$realName] ?? $limits[$lowercaseName] ?? $this->config->getDefaultMaxSessions();

            $currentCount = count($sessionsList);

            if ($currentCount > $maxAllowed) {
                $this->logger->warning("Usuario '{$realName}' excede el límite de sesiones ({$currentCount}/{$maxAllowed}). Aplicando contramedidas...");
                $this->enforceLimits($sessionsList, $maxAllowed, $realName);
            }
        }
    }

    /**
     * Identifica y cierra las sesiones excedentes según la estrategia configurada.
     * 
     * @param array<mixed> $sessionsList
     */
    private function enforceLimits(array $sessionsList, int $maxAllowed, string $userName): void
    {
        usort($sessionsList, function (array $a, array $b) {
            $dateA = isset($a['LastActivityDate']) ? strtotime($a['LastActivityDate']) : 0;
            $dateB = isset($b['LastActivityDate']) ? strtotime($b['LastActivityDate']) : 0;
            return $dateA <=> $dateB;
        });

        $sessionsToClose = [];

        if ($this->config->getKeepSession() === 'newest') {
            $excessCount = count($sessionsList) - $maxAllowed;
            $sessionsToClose = array_slice($sessionsList, 0, $excessCount);
        } else {
            $sessionsToClose = array_slice($sessionsList, $maxAllowed);
        }

        foreach ($sessionsToClose as $session) {
            $sessionId = $session['Id'];
            $clientName = $session['Client'] ?? 'Desconocido';
            $deviceName = $session['DeviceName'] ?? 'Desconocido';

            $this->logger->info("Cerrando sesión excedente del usuario '{$userName}' en dispositivo '{$deviceName}' ({$clientName}).");

            $success = $this->client->disconnectSession($sessionId);
            if ($success) {
                $this->logger->info("Sesión '{$sessionId}' desconectada exitosamente.");
            } else {
                $this->logger->error("No se pudo desconectar la sesión '{$sessionId}'.");
            }
        }
    }
}
