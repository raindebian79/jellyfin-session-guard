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

        // Agrupar sesiones activas por nombre de usuario (ignorando mayúsculas/minúsculas)
        $userSessions = [];
        foreach ($sessions as $session) {
            $userName = $session['UserName'] ?? null;
            $sessionId = $session['Id'] ?? null;

            // Saltar si la sesión no tiene datos válidos o no tiene un usuario real autenticado
            if (!$userName || !$sessionId) {
                continue;
            }

            // DETECCIÓN DINÁMICA DE ADMINISTRADOR:
            // Si cualquier sesión del usuario reporta que tiene rol de administrador,
            // marcamos de forma preventiva al usuario para otorgarle inmunidad.
            $isAdmin = $session['IsAdministrator'] ?? false;
            if ($isAdmin) {
                continue; 
            }

            $userSessions[strtolower($userName)][] = $session;
        }

        // Procesar las sesiones de cada usuario estándar (los administradores ya fueron filtrados)
        foreach ($userSessions as $lowercaseName => $sessionsList) {
            $realName = $sessionsList[0]['UserName'];

            // Comprobar si por si acaso el nombre está explícitamente en la lista estática de ignorados
            if (in_array(strtolower($realName), array_map('strtolower', $this->config->getIgnoreUsers()), true)) {
                continue;
            }

            // Determinar el límite máximo (usará el default_max_sessions = 2 si no hay reglas específicas)
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
