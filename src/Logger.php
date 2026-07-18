<?php
declare(strict_types=1);

namespace JellyfinGuard;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static ?MonologLogger $instance = null;

    /**
     * Obtiene la instancia única del Logger (Patrón Singleton)
     */
    public static function getInstance(string $logLevel = 'info'): MonologLogger
    {
        if (self::$instance === null) {
            self::$instance = new MonologLogger('jellyfin-guard');

            // Formato limpio para los logs: [Fecha] [Nivel] Mensaje
            $outputFormat = "[%datetime%] %level_name%: %message% %context% %extra%\n";
            $formatter = new LineFormatter($outputFormat, "Y-m-d H:i:s");

            // Handler 1: Escribir en la consola de Docker (stdout)
            $stdoutHandler = new StreamHandler('php://stdout', $logLevel);
            $stdoutHandler->setFormatter($formatter);
            self::$instance->pushHandler($stdoutHandler);

            // Handler 2: Escribir en archivo local rotativo dentro de la carpeta logs/
            $fileHandler = new StreamHandler(__DIR__ . '/../logs/app.log', $logLevel);
            $fileHandler->setFormatter($formatter);
            self::$instance->pushHandler($fileHandler);
        }

        return self::$instance;
    }
}
