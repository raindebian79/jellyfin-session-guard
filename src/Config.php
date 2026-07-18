<?php
declare(strict_types=1);

namespace JellyfinGuard;

use Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;
use RuntimeException;

class Config
{
    private string $jellyfinUrl;
    private string $jellyfinApiKey;
    private string $logLevel;
    private int $defaultMaxSessions;
    private string $keepSession;
    private int $pollInterval;
    /** @var array<string> */
    private array $ignoreUsers;
    /** @var array<string, int> */
    private array $usersLimits;

    public function __construct(string $dir = __DIR__ . '/../')
    {
        // 1. Cargar variables de entorno del archivo .env
        if (file_exists($dir . '.env')) {
            $dotenv = Dotenv::createImmutable($dir);
            $dotenv->load();
        }

        $this->jellyfinUrl = $_ENV['JELLYFIN_URL'] ?? throw new RuntimeException('JELLYFIN_URL no está definida en el .env');
        $this->jellyfinApiKey = $_ENV['JELLYFIN_API_KEY'] ?? throw new RuntimeException('JELLYFIN_API_KEY no está definida en el .env');
        $this->logLevel = $_ENV['LOG_LEVEL'] ?? 'info';

        // 2. Cargar reglas del archivo config.yaml
        $yamlPath = $dir . 'config.yaml';
        if (!file_exists($yamlPath)) {
            throw new RuntimeException('El archivo config.yaml no existe');
        }

        $yamlData = Yaml::parseFile($yamlPath);

        $this->defaultMaxSessions = (int)($yamlData['default_max_sessions'] ?? 1);
        $this->keepSession = (string)($yamlData['keep_session'] ?? 'newest');
        $this->pollInterval = (int)($yamlData['poll_interval'] ?? 5);
        $this->ignoreUsers = array_map('strval', $yamlData['ignore_users'] ?? []);
        $this->usersLimits = array_map('intval', $yamlData['users'] ?? []);
    }

    public function getJellyfinUrl(): string { return $this->jellyfinUrl; }
    public function getJellyfinApiKey(): string { return $this->jellyfinApiKey; }
    public function getLogLevel(): string { return $this->logLevel; }
    public function getDefaultMaxSessions(): int { return $this->defaultMaxSessions; }
    public function getKeepSession(): string { return $this->keepSession; }
    public function getPollInterval(): int { return $this->pollInterval; }
    /** @return array<string> */
    public function getIgnoreUsers(): array { return $this->ignoreUsers; }
    /** @return array<string, int> */
    public function getUsersLimits(): array { return $this->usersLimits; }
}
