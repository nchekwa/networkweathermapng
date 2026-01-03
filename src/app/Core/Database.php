<?php
/**
 * Zabbix Weathermap - Database Abstraction Layer
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private ?PDO $pdo = null;
    private Config $config;
    
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->connect();
    }
    
    private function connect(): void
    {
        $dbConfig = $this->config->getDatabaseConfig();
        
        try {
            if ($dbConfig['type'] === 'sqlite') {
                $this->connectSqlite($dbConfig['path']);
            } elseif ($dbConfig['type'] === 'mysql') {
                $this->connectMysql($dbConfig);
            } else {
                throw new PDOException("Unsupported database type: {$dbConfig['type']}");
            }
            
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Initialize schema if needed
            $this->initializeSchema();
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function connectSqlite(string $path): void
    {
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->pdo = new PDO("sqlite:{$path}");
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }
    
    private function connectMysql(array $config): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name']
        );
        
        $this->pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
    }
    
    private function initializeSchema(): void
    {
        // Ensure all required tables exist (useful when upgrading schema)
        $tables = $this->query("SELECT name FROM sqlite_master WHERE type='table'");
        $existing = array_map(static fn ($row) => strtolower($row['name']), $tables);
        
        $required = [
            'maps',
            'map_groups',
            'users',
            'user_map_permissions',
            'user_group_permissions',
            'settings',
            'data_sources',
            'data_source_cache',
        ];
        
        $missing = array_diff($required, $existing);
        
        if (!empty($missing)) {
            $this->createSchema();
        }
    }
    
    private function createSchema(): void
    {
        $schema = <<<SQL
-- Maps table
CREATE TABLE IF NOT EXISTS maps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    config_file VARCHAR(255) NOT NULL UNIQUE,
    group_id INTEGER DEFAULT 1,
    active INTEGER DEFAULT 1,
    sort_order INTEGER DEFAULT 0,
    title_cache VARCHAR(255),
    thumb_width INTEGER DEFAULT 0,
    thumb_height INTEGER DEFAULT 0,
    schedule VARCHAR(32) DEFAULT '*',
    last_run DATETIME,
    duration REAL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Map groups
CREATE TABLE IF NOT EXISTS map_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(128) NOT NULL,
    sort_order INTEGER DEFAULT 0
);

-- Insert default group
INSERT OR IGNORE INTO map_groups (id, name, sort_order) VALUES (1, 'Default', 1);

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    role VARCHAR(32) DEFAULT 'viewer',
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- User permissions
CREATE TABLE IF NOT EXISTS user_map_permissions (
    user_id INTEGER,
    map_id INTEGER,
    PRIMARY KEY (user_id, map_id)
);

-- User group permissions
CREATE TABLE IF NOT EXISTS user_group_permissions (
    user_id INTEGER,
    group_id INTEGER,
    PRIMARY KEY (user_id, group_id)
);

-- Settings
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER DEFAULT 0,
    name VARCHAR(128) NOT NULL,
    value TEXT,
    UNIQUE(map_id, name)
);

-- Data sources (Zabbix API, etc.)
CREATE TABLE IF NOT EXISTS data_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(128) NOT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'zabbix',
    url VARCHAR(512) NOT NULL,
    username VARCHAR(128),
    password VARCHAR(255),
    api_token VARCHAR(512),
    active INTEGER DEFAULT 1,
    settings TEXT,
    last_check DATETIME,
    status VARCHAR(32) DEFAULT 'unknown',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Data source cache
CREATE TABLE IF NOT EXISTS data_source_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_id INTEGER NOT NULL,
    cache_key VARCHAR(255) NOT NULL,
    data TEXT,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(source_id, cache_key)
);
SQL;
        
        $statements = explode(';', $schema);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->pdo->exec($statement);
            }
        }
        
        // Create default admin user
        $this->createDefaultAdmin();
    }
    
    private function createDefaultAdmin(): void
    {
        $adminUser = $this->config->get('ADMIN_USER', 'admin');
        $adminPass = $this->config->get('ADMIN_PASSWORD', 'admin');
        
        $stmt = $this->pdo->prepare(
            "INSERT OR IGNORE INTO users (username, password_hash, role) VALUES (?, ?, 'admin')"
        );
        $stmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);
    }
    
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->execute($sql, array_values($data));
        
        return (int) $this->pdo->lastInsertId();
    }
    
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        return $this->execute($sql, array_merge(array_values($data), $whereParams));
    }
    
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->execute($sql, $params);
    }
    
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Check database integrity
     * Verifies that all required tables exist and have the correct schema
     */
    public function checkDatabaseIntegrity(): array
    {
        $results = [
            'status' => 'ok',
            'tables' => [],
            'errors' => []
        ];
        
        // Define expected schema
        $requiredTables = [
            'maps' => [
                'columns' => ['id', 'name', 'config_file', 'group_id', 'active', 'sort_order', 'title_cache', 'thumb_width', 'thumb_height', 'schedule', 'last_run', 'duration', 'created_at', 'updated_at']
            ],
            'map_groups' => [
                'columns' => ['id', 'name', 'sort_order']
            ],
            'users' => [
                'columns' => ['id', 'username', 'password_hash', 'email', 'role', 'active', 'created_at']
            ],
            'user_map_permissions' => [
                'columns' => ['user_id', 'map_id']
            ],
            'user_group_permissions' => [
                'columns' => ['user_id', 'group_id']
            ],
            'settings' => [
                'columns' => ['id', 'map_id', 'name', 'value']
            ],
            'data_sources' => [
                'columns' => ['id', 'name', 'type', 'url', 'username', 'password', 'api_token', 'active', 'settings', 'last_check', 'status', 'created_at', 'updated_at']
            ],
            'data_source_cache' => [
                'columns' => ['id', 'source_id', 'cache_key', 'data', 'expires_at', 'created_at']
            ]
        ];
        
        $existingTables = [];
        $tablesQuery = $this->query("SELECT name FROM sqlite_master WHERE type='table'");
        foreach ($tablesQuery as $row) {
            $existingTables[] = strtolower($row['name']);
        }
        
        foreach ($requiredTables as $tableName => $specs) {
            $tableStatus = [
                'name' => $tableName,
                'exists' => false,
                'missing_columns' => []
            ];
            
            if (in_array(strtolower($tableName), $existingTables)) {
                $tableStatus['exists'] = true;
                
                // Check columns
                $columns = [];
                $colsQuery = $this->query("PRAGMA table_info($tableName)");
                foreach ($colsQuery as $col) {
                    $columns[] = strtolower($col['name']);
                }
                
                foreach ($specs['columns'] as $requiredCol) {
                    if (!in_array(strtolower($requiredCol), $columns)) {
                        $tableStatus['missing_columns'][] = $requiredCol;
                        $results['errors'][] = "Table '$tableName' is missing column '$requiredCol'";
                        $results['status'] = 'error';
                    }
                }
            } else {
                $results['errors'][] = "Table '$tableName' is missing";
                $results['status'] = 'error';
            }
            
            $results['tables'][$tableName] = $tableStatus;
        }
        
        return $results;
    }
}
