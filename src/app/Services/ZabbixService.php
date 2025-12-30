<?php
/**
 * Zabbix Weathermap - Zabbix Service
 * 
 * High-level service for interacting with Zabbix API
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Zabbix\ZabbixApi;
use Zabbix\ZabbixApiException;

class ZabbixService
{
    private ZabbixApi $api;
    private Database $database;
    private Config $config;
    private int $cacheTtl = 60; // seconds
    
    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
        
        $zabbixConfig = $config->getZabbixConfig();
        
        $this->api = new ZabbixApi(
            $zabbixConfig['url'],
            $zabbixConfig['token'] ?: null,
            $zabbixConfig['user'] ?: null,
            $zabbixConfig['password'] ?: null
        );
    }
    
    public function connect(): bool
    {
        try {
            return $this->api->authenticate();
        } catch (ZabbixApiException $e) {
            error_log("Zabbix connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function isConnected(): bool
    {
        return $this->api->isAuthenticated();
    }
    
    /**
     * Get data for a weathermap target string
     * 
     * Formats:
     * - zabbix:itemid:12345
     * - zabbix:host:hostname:key:item.key
     * - zabbix:hostid:10084:key:item.key
     */
    public function getTargetData(string $target): ?array
    {
        if (!preg_match('/^zabbix:/', $target)) {
            return null;
        }
        
        // Check cache first
        $cached = $this->getFromCache($target);
        if ($cached !== null) {
            return $cached;
        }
        
        $parts = explode(':', $target);
        array_shift($parts); // Remove 'zabbix' prefix
        
        $data = null;
        
        try {
            if (!$this->isConnected()) {
                $this->connect();
            }
            
            $type = $parts[0] ?? '';
            
            switch ($type) {
                case 'itemid':
                    $data = $this->getItemData($parts[1] ?? '');
                    break;
                    
                case 'host':
                    $hostname = $parts[1] ?? '';
                    $key = $parts[3] ?? '';
                    $data = $this->getItemDataByHostAndKey($hostname, $key);
                    break;
                    
                case 'hostid':
                    $hostId = $parts[1] ?? '';
                    $key = $parts[3] ?? '';
                    $data = $this->getItemDataByHostIdAndKey($hostId, $key);
                    break;
            }
            
            if ($data) {
                $this->saveToCache($target, $data);
            }
            
        } catch (ZabbixApiException $e) {
            error_log("Zabbix API error for target {$target}: " . $e->getMessage());
        }
        
        return $data;
    }
    
    private function getItemData(string $itemId): ?array
    {
        $item = $this->api->getItem($itemId);
        
        if (!$item) {
            return null;
        }
        
        return [
            'value' => (float) ($item['lastvalue'] ?? 0),
            'units' => $item['units'] ?? '',
            'name' => $item['name'] ?? '',
            'key' => $item['key_'] ?? '',
            'timestamp' => time(),
        ];
    }
    
    private function getItemDataByHostAndKey(string $hostname, string $key): ?array
    {
        // First, find the host
        $hosts = $this->api->getHosts([
            'filter' => ['host' => $hostname],
            'output' => ['hostid'],
        ]);
        
        if (empty($hosts)) {
            return null;
        }
        
        return $this->getItemDataByHostIdAndKey($hosts[0]['hostid'], $key);
    }
    
    private function getItemDataByHostIdAndKey(string $hostId, string $key): ?array
    {
        $item = $this->api->getItemByKey($hostId, $key);
        
        if (!$item) {
            return null;
        }
        
        return [
            'value' => (float) ($item['lastvalue'] ?? 0),
            'units' => $item['units'] ?? '',
            'name' => $item['name'] ?? '',
            'key' => $item['key_'] ?? '',
            'timestamp' => time(),
        ];
    }
    
    /**
     * Get bandwidth data for interface (in/out)
     */
    public function getInterfaceBandwidth(string $hostId, string $interface): ?array
    {
        $inKey = "net.if.in[{$interface}]";
        $outKey = "net.if.out[{$interface}]";
        
        $inData = $this->getItemDataByHostIdAndKey($hostId, $inKey);
        $outData = $this->getItemDataByHostIdAndKey($hostId, $outKey);
        
        if (!$inData && !$outData) {
            return null;
        }
        
        return [
            'in' => $inData['value'] ?? 0,
            'out' => $outData['value'] ?? 0,
            'in_units' => $inData['units'] ?? 'bps',
            'out_units' => $outData['units'] ?? 'bps',
            'timestamp' => time(),
        ];
    }
    
    /**
     * Get all hosts for picker
     */
    public function getAllHosts(): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        return $this->api->getHosts([
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectGroups' => ['groupid', 'name'],
            'sortfield' => 'name',
        ]);
    }
    
    /**
     * Get all host groups
     */
    public function getAllHostGroups(): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        return $this->api->getHostGroups([
            'output' => ['groupid', 'name'],
            'selectHosts' => 'count',
            'sortfield' => 'name',
        ]);
    }
    
    /**
     * Get items for a host (for picker)
     */
    public function getHostItemsForPicker(string $hostId): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        return $this->api->getHostItems($hostId, [
            'output' => ['itemid', 'name', 'key_', 'units', 'value_type'],
            'filter' => ['status' => 0], // Only enabled items
            'sortfield' => 'name',
        ]);
    }
    
    private function getFromCache(string $key): ?array
    {
        $cacheKey = md5($key);
        
        $cached = $this->database->queryOne(
            "SELECT data, expires_at FROM zabbix_cache WHERE cache_key = ?",
            [$cacheKey]
        );
        
        if (!$cached) {
            return null;
        }
        
        if (strtotime($cached['expires_at']) < time()) {
            // Cache expired
            $this->database->delete('zabbix_cache', 'cache_key = ?', [$cacheKey]);
            return null;
        }
        
        return json_decode($cached['data'], true);
    }
    
    private function saveToCache(string $key, array $data): void
    {
        $cacheKey = md5($key);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->cacheTtl);
        
        $this->database->execute(
            "INSERT OR REPLACE INTO zabbix_cache (cache_key, data, expires_at) VALUES (?, ?, ?)",
            [$cacheKey, json_encode($data), $expiresAt]
        );
    }
    
    public function setCacheTtl(int $seconds): void
    {
        $this->cacheTtl = $seconds;
    }
    
    public function clearCache(): void
    {
        $this->database->execute("DELETE FROM zabbix_cache");
    }
}
