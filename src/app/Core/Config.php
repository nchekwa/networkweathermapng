<?php
/**
 * Zabbix Weathermap - Configuration Manager
 */

declare(strict_types=1);

namespace App\Core;

class Config
{
    private array $config = [];
    
    public function __construct()
    {
        $this->loadDefaults();
        $this->loadFromEnvironment();
    }
    
    private function loadDefaults(): void
    {
        // Determine root directory based on environment
        // In Docker, APP_ROOT will be /app
        // In dev mode, it will be the project root
        $appRoot = dirname(__DIR__, 3);
        
        $this->config = [
            // Application
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'UTC',
            'APP_SECRET_KEY' => '',
            'APP_ROOT' => $appRoot,
            'DATA_ROOT' => $appRoot . '/data',
            
            // Database
            'DB_TYPE' => 'sqlite',
            'DB_PATH' => $appRoot . '/data/weathermap.db',  // Will be updated in loadFromEnvironment
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_NAME' => 'weathermap',
            'DB_USER' => '',
            'DB_PASSWORD' => '',
            
            // Authentication
            'AUTH_ENABLED' => 'true',
            'AUTH_TYPE' => 'local',
            'ADMIN_USER' => 'admin',
            'ADMIN_PASSWORD' => 'admin',
            
            // Map settings
            'MAP_OUTPUT_FORMAT' => 'png',
            'MAP_THUMB_SIZE' => '250',
            'MAP_REFRESH_INTERVAL' => '300',
        ];
    }
    
    private function loadFromEnvironment(): void
    {
        foreach ($this->config as $key => $default) {
            $envValue = getenv($key);
            if ($envValue !== false) {
                $this->config[$key] = $envValue;
            }
        }
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
    
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }
    
    public function all(): array
    {
        return $this->config;
    }
    
    public function isDebug(): bool
    {
        return $this->get('APP_DEBUG') === 'true';
    }
    
    public function isAuthEnabled(): bool
    {
        return $this->get('AUTH_ENABLED') === 'true';
    }
    
    public function getDatabaseConfig(): array
    {
        return [
            'type' => $this->get('DB_TYPE'),
            'path' => $this->get('DB_PATH'),
            'host' => $this->get('DB_HOST'),
            'port' => $this->get('DB_PORT'),
            'name' => $this->get('DB_NAME'),
            'user' => $this->get('DB_USER'),
            'password' => $this->get('DB_PASSWORD'),
        ];
    }
    
    // Path helpers - build paths from APP_ROOT and DATA_ROOT
    public function getAppRoot(): string
    {
        return $this->get('APP_ROOT');
    }
    
    public function getDataRoot(): string
    {
        return $this->get('DATA_ROOT');
    }
    
    public function getConfigsPath(): string
    {
        return $this->getDataRoot() . '/configs';
    }
    
    public function getOutputPath(): string
    {
        return $this->getDataRoot() . '/output';
    }
    
    public function getCachePath(): string
    {
        return $this->getDataRoot() . '/cache';
    }
    
    public function getLogsPath(): string
    {
        return $this->getAppRoot() . '/logs';
    }
    
    public function getLibPath(): string
    {
        return $this->getAppRoot() . '/lib';
    }
    
    public function getPublicPath(): string
    {
        return $this->getAppRoot() . '/public';
    }
    
    public function getObjectsPath(): string
    {
        return $this->getPublicPath() . '/objects';
    }
    
    public function getBackgroundsPath(): string
    {
        return $this->getPublicPath() . '/backgrounds';
    }
}
