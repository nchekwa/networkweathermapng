<?php

namespace App\Services;

use App\Core\Config;

/**
 * MapService - Handles all map-related operations
 * 
 * This service provides CRUD operations for weathermaps and integrates
 * with the WeatherMap rendering engine.
 */
class MapService
{
    private $db;
    private $configPath;
    private $outputPath;
    private ?Config $config;
    
    public function __construct($database, Config $config)
    {
        $this->db = $database;
        $this->config = $config;
        
        // Get paths from config helper methods
        $this->configPath = $this->config->getConfigsPath();
        $this->outputPath = $this->config->getOutputPath();
        
        // Ensure directories exist
        $this->ensureDirectories();
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories(): void
    {
        $dirs = [
            $this->config->getConfigsPath(),
            $this->config->getOutputPath(),
            $this->config->getCachePath(),
            $this->config->getLogsPath(),
            $this->config->getLibPath() . '/WeatherMap/lib/pre',
            $this->config->getLibPath() . '/WeatherMap/lib/post',
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                error_log("MapService: Created directory: $dir");
            }
        }
    }

    private function findFirstExistingPath(array $paths): string
    {
        foreach ($paths as $path) {
            if ($path !== '' && file_exists($path)) {
                return $path;
            }
        }

        return $paths[0] ?? '';
    }
    
    /**
     * Get all maps
     */
    public function getAllMaps(): array
    {
        return $this->db->query("SELECT * FROM maps ORDER BY sort_order, name");
    }
    
    /**
     * Get maps by group
     */
    public function getMapsByGroup(int $groupId): array
    {
        return $this->db->query("SELECT * FROM maps WHERE group_id = ? ORDER BY sort_order, name", [$groupId]);
    }
    
    /**
     * Get a single map by ID
     */
    public function getMap(int $id): ?array
    {
        $map = $this->db->queryOne("SELECT * FROM maps WHERE id = ?", [$id]);
        return $map ?: null;
    }
    
    public function getMapByName(string $name): ?array
    {
        $map = $this->db->queryOne("SELECT * FROM maps WHERE config_file = ?", [$name]);
        return $map ?: null;
    }
    
    /**
     * Get a map by filename
     */
    public function getMapByFilename(string $filename): ?array
    {
        return $this->db->queryOne("SELECT * FROM maps WHERE config_file = ?", [$filename]);
    }
    
    /**
     * Create a new map
     */
    public function createMap(array $data): int
    {
        $name = $data['name'] ?? $data['title'] ?? 'New Map';
        
        // Generate a unique filename if not provided
        if (empty($data['config_file'])) {
            $data['config_file'] = $this->generateConfigFilename($name);
        }
        
        // Create default config file
        $configContent = $this->generateDefaultConfig($data);
        $configPath = $this->configPath . '/' . $data['config_file'];
        error_log("MapService::createMap writing config to: $configPath");
        $bytes = file_put_contents($configPath, $configContent);
        error_log("MapService::createMap wrote $bytes bytes to config file");
        
        // Get next sort order
        $result = $this->db->queryOne("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM maps");
        $sortOrder = $result['next_order'] ?? 1;
        
        // Insert into database
        return $this->db->insert('maps', [
            'name' => $name,
            'config_file' => $data['config_file'],
            'title_cache' => $name,
            'active' => $data['active'] ?? 1,
            'group_id' => $data['group_id'] ?? 1,
            'sort_order' => $sortOrder
        ]);
    }
    
    /**
     * Update an existing map
     */
    public function updateMap(int $id, array $data): bool
    {
        $map = $this->getMap($id);
        if (!$map) {
            return false;
        }
        
        $updateData = [];
        $allowedFields = ['name', 'title_cache', 'active', 'group_id', 'sort_order'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // Handle 'title' as alias for 'name'
        if (isset($data['title']) && !isset($data['name'])) {
            $updateData['name'] = $data['title'];
        }
        
        if (empty($updateData)) {
            return true; // Nothing to update
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('maps', $updateData, 'id = ?', [$id]) >= 0;
    }
    
    /**
     * Delete a map
     */
    public function deleteMap(int $id): bool
    {
        $map = $this->getMap($id);
        if (!$map) {
            return false;
        }
        
        // Delete config file
        $configPath = $this->configPath . '/' . $map['config_file'];
        if (file_exists($configPath)) {
            unlink($configPath);
        }
        
        // Delete output files
        $outputBase = $this->outputPath . '/' . pathinfo($map['config_file'], PATHINFO_FILENAME);
        foreach (['png', 'html', 'json'] as $ext) {
            $file = $outputBase . '.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Delete from database
        return $this->db->delete('maps', 'id = ?', [$id]) > 0;
    }
    
    /**
     * Get map configuration content
     */
    public function getMapConfig(int $id): ?string
    {
        $map = $this->getMap($id);
        if (!$map) {
            return null;
        }
        
        $configPath = $this->configPath . '/' . $map['config_file'];
        if (!file_exists($configPath)) {
            // Create default config if it doesn't exist
            $content = $this->generateDefaultConfig($map);
            file_put_contents($configPath, $content);
            return $content;
        }
        
        return file_get_contents($configPath);
    }
    
    /**
     * Save map configuration content
     */
    public function saveMapConfig(int $id, string $content): bool
    {
        $map = $this->getMap($id);
        if (!$map) {
            return false;
        }
        
        $configPath = $this->configPath . '/' . $map['config_file'];
        
        // Backup old config
        if (file_exists($configPath)) {
            $backupPath = $configPath . '.bak';
            copy($configPath, $backupPath);
        }
        
        // Save new config
        $result = file_put_contents($configPath, $content);
        
        if ($result !== false) {
            // Update the map's updated_at timestamp
            $this->updateMap($id, []);
        }
        
        return $result !== false;
    }
    
    /**
     * Render a map to image
     */
    public function renderMap(int $id, bool $force = false, bool $readData = true): ?array
    {
        $map = $this->getMap($id);
        if (!$map) {
            return null;
        }
        
        $configPath = $this->configPath . '/' . $map['config_file'];
        error_log("MapService::renderMap looking for config at: $configPath");
        if (!file_exists($configPath)) {
            error_log("MapService::renderMap config file not found: $configPath");
            return ['error' => 'Config file not found'];
        }
        
        $format = $this->config->get('MAP_OUTPUT_FORMAT', 'png');
        $outputBase = pathinfo($map['config_file'], PATHINFO_FILENAME);
        $suffix = $readData ? '' : '.fast';
        $imagePath = $this->outputPath . '/' . $outputBase . $suffix . '.' . $format;
        $thumbPath = $this->outputPath . '/' . $outputBase . $suffix . '.thumb.' . $format;
        $htmlPath = $this->outputPath . '/' . $outputBase . $suffix . '.html';
        
        // Check if we need to re-render
        // We consider it cached only if BOTH main image and thumbnail exist and are newer than config.
        if (!$force && file_exists($imagePath) && file_exists($thumbPath)) {
            $configTime = filemtime($configPath);
            $imageTime = filemtime($imagePath);
            $thumbTime = filemtime($thumbPath);
            
            if ($imageTime >= $configTime && $thumbTime >= $configTime) {
                return [
                    'image' => $imagePath,
                    'html' => file_exists($htmlPath) ? $htmlPath : null,
                    'cached' => true
                ];
            }
        }
        
        // Load WeatherMap library
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        try {
            $wmap = new \WeatherMap();
            $wmap->context = 'standalone';
            
            // Read the config
            error_log("MapService::renderMap reading config from: $configPath");
            $wmap->ReadConfig($configPath);
            error_log("MapService::renderMap config read successfully");
            
            // Read data from data sources
            if ($readData) {
                $wmap->ReadData();
            }
            
            // Write the image
            error_log("MapService::renderMap writing image to: $imagePath");
            error_log("MapService::renderMap writing thumbnail to: $thumbPath");
            $wmap->DrawMap($imagePath, $thumbPath);
            error_log("MapService::renderMap image and thumbnail written successfully");
            
            // Generate HTML imagemap
            if (method_exists($wmap, 'MakeHTML')) {
                $htmlContent = $wmap->MakeHTML();
                if ($htmlContent) {
                    file_put_contents($htmlPath, $htmlContent);
                }
            }
            
            // Update last render time in database
            if ($readData) {
                $this->db->update('maps', ['last_run' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            }
            
            return [
                'image' => $imagePath,
                'html' => file_exists($htmlPath) ? $htmlPath : null,
                'cached' => false
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate a unique config filename
     */
    private function generateConfigFilename(string $title): string
    {
        $base = preg_replace('/[^a-z0-9]+/i', '_', strtolower($title));
        $base = trim($base, '_');
        
        if (empty($base)) {
            $base = 'map';
        }
        
        $filename = $base . '.conf';
        $counter = 1;
        
        while (file_exists($this->configPath . '/' . $filename)) {
            $filename = $base . '_' . $counter . '.conf';
            $counter++;
        }
        
        return $filename;
    }
    
    /**
     * Generate default map configuration
     */
    private function generateDefaultConfig(array $data): string
    {
        $title = $data['name'] ?? $data['title'] ?? 'New Map';
        $width = $data['width'] ?? 800;
        $height = $data['height'] ?? 600;

        $fontRegular = $this->findFirstExistingPath([
            '/usr/share/fonts/ttf-dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ]);
        $fontBold = $this->findFirstExistingPath([
            '/usr/share/fonts/ttf-dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ]);
        
        return <<<CONFIG
# WeatherMap Configuration
# Generated: {$this->getCurrentTimestamp()}

# Map dimensions and title
WIDTH {$width}
HEIGHT {$height}
TITLE {$title}

# Background color
BGCOLOR 255 255 255

# Default fonts
FONTDEFINE 1 {$fontRegular} 8
FONTDEFINE 2 {$fontRegular} 10
FONTDEFINE 3 {$fontBold} 12

TITLEFONT 3
TIMEFONT 2
KEYFONT 1

# Timestamp position
TIMEPOS 10 {$height} Created: %b %d %Y %H:%M:%S

# Default scale
SCALE DEFAULT 0 0     192 192 192
SCALE DEFAULT 0 1     255 255 255
SCALE DEFAULT 1 10    140 0 255
SCALE DEFAULT 10 25   32 32 255
SCALE DEFAULT 25 40   0 192 255
SCALE DEFAULT 40 55   0 240 0
SCALE DEFAULT 55 70   240 240 0
SCALE DEFAULT 70 85   255 192 0
SCALE DEFAULT 85 100  255 0 0

# Legend position
KEYPOS DEFAULT 10 10

# Default node settings
NODE DEFAULT
    MAXVALUE 100

# Default link settings
LINK DEFAULT
    BANDWIDTH 100M
    BWLABEL bits
    BWLABELPOS 50 50
    WIDTH 4

# Add your nodes and links below
# Example:
# NODE router1
#     LABEL Router 1
#     POSITION 100 100
#     ICON images/router.png
#
# NODE router2
#     LABEL Router 2
#     POSITION 300 100
#
# LINK router1-router2
#     NODES router1 router2
#     TARGET zabbix:host:router1:ifHCInOctets:ifHCOutOctets

CONFIG;
    }
    
    /**
     * Get current timestamp
     */
    private function getCurrentTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Duplicate a map
     */
    public function duplicateMap(int $id): ?int
    {
        $map = $this->getMap($id);
        if (!$map) {
            return null;
        }
        
        // Get original config
        $config = $this->getMapConfig($id);
        
        // Create new map with modified title
        $newData = [
            'name' => $map['name'] . ' (Copy)',
            'active' => 0, // Start as inactive
            'group_id' => $map['group_id']
        ];
        
        $newId = $this->createMap($newData);
        
        // Copy the config content
        if ($config) {
            // Update title in config
            $config = preg_replace('/^TITLE\s+.+$/m', 'TITLE ' . $newData['name'], $config);
            $this->saveMapConfig($newId, $config);
        }
        
        return $newId;
    }
    
    /**
     * Get map statistics
     */
    public function getMapStats(): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0
        ];
        
        $result = $this->db->queryOne("SELECT COUNT(*) as total, SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active FROM maps");
        
        if ($result) {
            $stats['total'] = (int) $result['total'];
            $stats['active'] = (int) ($result['active'] ?? 0);
            $stats['inactive'] = $stats['total'] - $stats['active'];
        }
        
        return $stats;
    }
}
