<?php
/**
 * Zabbix Weathermap - Zabbix Data Source
 * 
 * Fetches data from Zabbix API for weathermap nodes and links
 * 
 * TARGET formats:
 * - zabbix:itemid:12345
 * - zabbix:itemid:12345:12346 (in/out)
 * - zabbix:host:hostname:key:item.key
 * - zabbix:hostid:10084:key:item.key
 * - zabbix:hostid:10084:key:net.if.in[eth0]:net.if.out[eth0]
 */

declare(strict_types=1);

namespace WeatherMap\DataSources;

use Zabbix\ZabbixApi;
use Zabbix\ZabbixApiException;
use App\Core\Config;
use App\Core\Database;
use App\Services\DataSourceService;

class ZabbixDataSource implements DataSourceInterface
{
    private ?ZabbixApi $api = null;
    private array $cache = [];
    private array $registered = [];
    private bool $initialized = false;

    private ?DataSourceService $dataSourceService = null;
    private ?Database $database = null;
    
    public function init(object $map): bool
    {
        if ($this->initialized) {
            return true;
        }

        // Prefer DB-backed datasource service (unified sources).
        try {
            if (defined('APP_ROOT')) {
                $autoload = APP_ROOT . '/vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                }
            }
            if (class_exists(Config::class) && class_exists(Database::class) && class_exists(DataSourceService::class)) {
                $cfg = new Config();
                $this->database = new Database($cfg);
                $this->dataSourceService = new DataSourceService($this->database);
                $this->initialized = true;
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $this->log('No DB-backed data source service available');
        return false;
    }
    
    public function recognise(string $targetString): bool
    {
        return preg_match('/^zabbix:/i', $targetString) === 1;
    }
    
    public function readData(string $targetString, object $map, object $item): array
    {
        if (!$this->initialized) {
            return [-1, -1];
        }
        
        // Check cache first
        if (isset($this->cache[$targetString])) {
            return $this->cache[$targetString];
        }

        try {
            $result = [-1, -1];

            // DB-backed mode: use link/node hint 'datasource' if present
            if ($this->dataSourceService && isset($item->hints) && is_array($item->hints) && isset($item->hints['datasource'])) {
                $selection = json_decode((string) $item->hints['datasource'], true);
                if (is_array($selection) && !empty($selection['sourceId'])) {
                    $sourceId = (int) $selection['sourceId'];
                    $current = $this->dataSourceService->getLinkBandwidthCurrent($sourceId, $selection);
                    if (isset($current['in']) && isset($current['out'])) {
                        $result = [(float) $current['in'], (float) $current['out']];
                    }
                }
            }

            $this->cache[$targetString] = $result;
            return $result;
            
        } catch (ZabbixApiException $e) {
            $this->log("Error fetching data for {$targetString}: " . $e->getMessage());
            return [-1, -1];
        } catch (\Throwable $e) {
            $this->log("Error fetching data for {$targetString}: " . $e->getMessage());
            return [-1, -1];
        }
    }
    
    public function register(string $targetString, object $map, object $item): void
    {
        $this->registered[$targetString] = true;
    }
    
    public function prefetch(): void
    {
        // Could implement batch fetching here for performance
        // For now, data is fetched on-demand in readData()
    }
    
    /**
     * Parse target string and fetch data from Zabbix
     */
    private function parseAndFetch(string $targetString): array
    {
        // Remove 'zabbix:' prefix
        $target = substr($targetString, 7);
        $parts = explode(':', $target);
        
        if (empty($parts)) {
            return [-1, -1];
        }
        
        $type = strtolower($parts[0]);
        
        switch ($type) {
            case 'itemid':
                return $this->fetchByItemId($parts);
                
            case 'host':
                return $this->fetchByHostAndKey($parts);
                
            case 'hostid':
                return $this->fetchByHostIdAndKey($parts);
                
            default:
                $this->log("Unknown Zabbix target type: {$type}");
                return [-1, -1];
        }
    }
    
    /**
     * Fetch by item ID(s)
     * Format: zabbix:itemid:12345 or zabbix:itemid:12345:12346
     */
    private function fetchByItemId(array $parts): array
    {
        $inItemId = $parts[1] ?? null;
        $outItemId = $parts[2] ?? null;
        
        if (!$inItemId) {
            return [-1, -1];
        }
        
        $inValue = $this->getItemValue($inItemId);
        
        if ($outItemId) {
            $outValue = $this->getItemValue($outItemId);
        } else {
            $outValue = $inValue;
        }
        
        return [$inValue, $outValue];
    }
    
    /**
     * Fetch by hostname and item key
     * Format: zabbix:host:hostname:key:itemkey or zabbix:host:hostname:key:inkey:outkey
     */
    private function fetchByHostAndKey(array $parts): array
    {
        $hostname = $parts[1] ?? null;
        
        if (!$hostname || !isset($parts[2]) || $parts[2] !== 'key') {
            return [-1, -1];
        }
        
        $inKey = $parts[3] ?? null;
        $outKey = $parts[4] ?? null;
        
        if (!$inKey) {
            return [-1, -1];
        }
        
        // Find host ID
        $hosts = $this->api->getHosts([
            'filter' => ['host' => $hostname],
            'output' => ['hostid'],
        ]);
        
        if (empty($hosts)) {
            $this->log("Host not found: {$hostname}");
            return [-1, -1];
        }
        
        $hostId = $hosts[0]['hostid'];
        
        return $this->fetchItemsByKey($hostId, $inKey, $outKey);
    }
    
    /**
     * Fetch by host ID and item key
     * Format: zabbix:hostid:10084:key:itemkey or zabbix:hostid:10084:key:inkey:outkey
     */
    private function fetchByHostIdAndKey(array $parts): array
    {
        $hostId = $parts[1] ?? null;
        
        if (!$hostId || !isset($parts[2]) || $parts[2] !== 'key') {
            return [-1, -1];
        }
        
        $inKey = $parts[3] ?? null;
        $outKey = $parts[4] ?? null;
        
        if (!$inKey) {
            return [-1, -1];
        }
        
        return $this->fetchItemsByKey($hostId, $inKey, $outKey);
    }
    
    /**
     * Fetch item values by key
     */
    private function fetchItemsByKey(string $hostId, string $inKey, ?string $outKey): array
    {
        $inValue = $this->getItemValueByKey($hostId, $inKey);
        
        if ($outKey) {
            $outValue = $this->getItemValueByKey($hostId, $outKey);
        } else {
            $outValue = $inValue;
        }
        
        return [$inValue, $outValue];
    }
    
    /**
     * Get item value by item ID
     */
    private function getItemValue(string $itemId): float
    {
        $items = $this->api->getItems([
            'itemids' => $itemId,
            'output' => ['lastvalue', 'value_type'],
        ]);
        
        if (empty($items)) {
            return -1;
        }
        
        return (float) ($items[0]['lastvalue'] ?? -1);
    }
    
    /**
     * Get item value by host ID and key
     */
    private function getItemValueByKey(string $hostId, string $key): float
    {
        $items = $this->api->getItems([
            'hostids' => $hostId,
            'filter' => ['key_' => $key],
            'output' => ['lastvalue', 'value_type'],
        ]);
        
        if (empty($items)) {
            $this->log("Item not found: hostid={$hostId}, key={$key}");
            return -1;
        }
        
        return (float) ($items[0]['lastvalue'] ?? -1);
    }
    
    /**
     * Log a message
     */
    private function log(string $message): void
    {
        error_log("[ZabbixDataSource] {$message}");
    }
}
