<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use JellyfinGuard\Logger;
use JellyfinGuard\Config;
use JellyfinGuard\JellyfinClient;
use JellyfinGuard\SessionManager;

try {
    // 1. Inicializar la configuración global
    $config = new Config();
    
    // 2. Inicializar el logger con el nivel del .env
    $logger = Logger::getInstance($config->getLogLevel());

    $logger->info("==================================================");
    $logger->info("Iniciando Jellyfin Session Guard en modo continuo...");
    $logger->info("==================================================");

    // 3. Levantar los clientes de comunicación
    $jellyfinClient = new JellyfinClient($config, $logger);
    $sessionManager = new SessionManager($config, $jellyfinClient, $logger);

    $intervalo = $config->getPollInterval();

    // 4. Bucle principal de vigilancia continua
    while (true) {
        try {
            // Ejecutar la revisión de límites y sesiones
            $sessionManager->checkAndGuard();
        } catch (\Throwable $e) {
            $logger->error("Error durante el ciclo de monitoreo: " . $e->getMessage());
        }

        // Esperar los segundos configurados en el YAML antes de la próxima revisión
        sleep($intervalo);
    }

} catch (\Throwable $e) {
    // Captura fallos catastróficos iniciales (ej. sintaxis o archivos perdidos)
    $logger = Logger::getInstance('info');
    $logger->critical("Fallo crítico en el arranque del guardián: " . $e->getMessage());
    exit(1);
}
