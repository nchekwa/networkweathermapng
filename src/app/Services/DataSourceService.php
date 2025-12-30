<?php
/**
 * NetworkWeathermapNG - Data Source Service
 * Manages data sources (Zabbix API, etc.) for fetching network data
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class DataSourceException extends \Exception
{
    private array $debug;

    public function __construct(string $message, array $debug = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->debug = $debug;
    }

    public function getDebug(): array
    {
        return $this->debug;
    }
}

class DataSourceService
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    public function getAllSources(): array
    {
        return $this->db->query("SELECT * FROM data_sources ORDER BY name");
    }
    
    public function getActiveSources(): array
    {
        return $this->db->query("SELECT * FROM data_sources WHERE active = 1 ORDER BY name");
    }
    
    public function getSource(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM data_sources WHERE id = ?", [$id]);
    }
    
    public function getSourceByName(string $name): ?array
    {
        return $this->db->queryOne("SELECT * FROM data_sources WHERE name = ?", [$name]);
    }
    
    public function createSource(array $data): int
    {
        $insertData = [
            'name' => $data['name'],
            'type' => $data['type'] ?? 'zabbix',
            'url' => $data['url'],
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ? $this->encryptPassword($data['password']) : null,
            'api_token' => $data['api_token'] ?? null,
            'active' => $data['active'] ?? 1,
            'settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
            'status' => 'unknown',
        ];
        
        return $this->db->insert('data_sources', $insertData);
    }
    
    public function updateSource(int $id, array $data): bool
    {
        $updateData = [
            'name' => $data['name'],
            'type' => $data['type'] ?? 'zabbix',
            'url' => $data['url'],
            'username' => $data['username'] ?? null,
            'active' => $data['active'] ?? 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        if (!empty($data['password'])) {
            $updateData['password'] = $this->encryptPassword($data['password']);
        }
        
        if (isset($data['api_token'])) {
            $updateData['api_token'] = $data['api_token'];
        }
        
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings']);
        }
        
        return $this->db->update('data_sources', $updateData, 'id = ?', [$id]) > 0;
    }
    
    public function deleteSource(int $id): bool
    {
        // Delete cache entries first
        $this->db->delete('data_source_cache', 'source_id = ?', [$id]);
        return $this->db->delete('data_sources', 'id = ?', [$id]) > 0;
    }
    
    public function testConnection(int $id): array
    {
        $source = $this->getSource($id);
        if (!$source) {
            return ['success' => false, 'error' => 'Data source not found'];
        }
        
        $client = $this->getClient($source);
        if (!$client) {
            return ['success' => false, 'error' => 'Unsupported data source type'];
        }
        
        try {
            $result = $client->testConnection();
            
            // Update status
            $this->db->update('data_sources', [
                'status' => $result['success'] ? 'connected' : 'error',
                'last_check' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            
            $debug = $client->getDebugInfo();
            if ($debug) {
                $result['debug'] = $debug;
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->db->update('data_sources', [
                'status' => 'error',
                'last_check' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            
            $response = ['success' => false, 'error' => $e->getMessage()];
            $debug = $client->getDebugInfo();
            if ($debug) {
                $response['debug'] = $debug;
            }
            
            return $response;
        }
    }
    
    public function getHosts(int $sourceId, array $filters = []): array
    {
        $source = $this->getSource($sourceId);
        if (!$source) {
            return [];
        }
        
        $client = $this->getClient($source);
        if (!$client) {
            return [];
        }

        try {
            return $client->getHosts($filters);
        } catch (\Exception $e) {
            throw new DataSourceException($e->getMessage(), $client->getDebugInfo(), 0, $e);
        }
    }
    
    public function getHostInterfaces(int $sourceId, string $hostId): array
    {
        $source = $this->getSource($sourceId);
        if (!$source) {
            return [];
        }
        
        $client = $this->getClient($source);
        if (!$client) {
            return [];
        }

        try {
            return $client->getHostInterfaces($hostId);
        } catch (\Exception $e) {
            throw new DataSourceException($e->getMessage(), $client->getDebugInfo(), 0, $e);
        }
    }
    
    public function getItems(int $sourceId, string $hostId, array $filters = []): array
    {
        $source = $this->getSource($sourceId);
        if (!$source) {
            return [];
        }
        
        $client = $this->getClient($source);
        if (!$client) {
            return [];
        }

        try {
            return $client->getItems($hostId, $filters);
        } catch (\Exception $e) {
            throw new DataSourceException($e->getMessage(), $client->getDebugInfo(), 0, $e);
        }
    }
    
    public function getItemValue(int $sourceId, string $itemId): ?array
    {
        $source = $this->getSource($sourceId);
        if (!$source) {
            return null;
        }
        
        $client = $this->getClient($source);
        if (!$client) {
            return null;
        }

        try {
            return $client->getItemValue($itemId);
        } catch (\Exception $e) {
            throw new DataSourceException($e->getMessage(), $client->getDebugInfo(), 0, $e);
        }
    }
    
    public function getInterfaceBandwidthOptions(int $sourceId, string $hostId): array
    {
        $source = $this->getSource($sourceId);
        if (!$source) {
            return [];
        }
        
        $client = $this->getClient($source);
        if (!$client) {
            return [];
        }

        try {
            return $client->getInterfaceBandwidthOptions($hostId);
        } catch (\Exception $e) {
            throw new DataSourceException($e->getMessage(), $client->getDebugInfo(), 0, $e);
        }
    }
    
    public function getLinkBandwidthData(int $sourceId, array $selection, int $minutes = 1440): array
    {
        $source = $this->getSource($sourceId);
        if (!$source) {
            return [];
        }
        
        $client = $this->getClient($source);
        if (!$client) {
            return [];
        }

        try {
            return $client->getBandwidthData($selection, $minutes);
        } catch (\Exception $e) {
            throw new DataSourceException($e->getMessage(), $client->getDebugInfo(), 0, $e);
        }
    }

    public function getLinkBandwidthCurrent(int $sourceId, array $selection): array
    {
        $source = $this->getSource($sourceId);
        if (!$source) {
            return [];
        }

        $client = $this->getClient($source);
        if (!$client) {
            return [];
        }

        try {
            return $client->getBandwidthCurrent($selection);
        } catch (\Exception $e) {
            throw new DataSourceException($e->getMessage(), $client->getDebugInfo(), 0, $e);
        }
    }
    
    private function getClient(array $source): ?DataSourceClientInterface
    {
        switch ($source['type']) {
            case 'zabbix':
                return new ZabbixClient($source, $this);
            default:
                return null;
        }
    }
    
    private function encryptPassword(string $password): string
    {
        // Simple base64 encoding for now - in production use proper encryption
        return base64_encode($password);
    }
    
    public function decryptPassword(string $encrypted): string
    {
        return base64_decode($encrypted);
    }
    
    // Cache methods
    public function getCache(int $sourceId, string $key): ?string
    {
        $result = $this->db->queryOne(
            "SELECT data FROM data_source_cache WHERE source_id = ? AND cache_key = ? AND expires_at > datetime('now')",
            [$sourceId, $key]
        );
        return $result ? $result['data'] : null;
    }
    
    public function setCache(int $sourceId, string $key, string $data, int $ttl = 300): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        
        $this->db->execute(
            "INSERT OR REPLACE INTO data_source_cache (source_id, cache_key, data, expires_at, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$sourceId, $key, $data, $expiresAt]
        );
    }
    
    public function clearCache(int $sourceId): void
    {
        $this->db->delete('data_source_cache', 'source_id = ?', [$sourceId]);
    }
}

interface DataSourceClientInterface
{
    public function testConnection(): array;
    public function getHosts(array $filters = []): array;
    public function getHostInterfaces(string $hostId): array;
    public function getItems(string $hostId, array $filters = []): array;
    public function getItemValue(string $itemId): ?array;
    public function getInterfaceBandwidthOptions(string $hostId): array;
    public function getBandwidthCurrent(array $selection): array;
    public function getBandwidthData(array $selection, int $minutes = 1440): array;
    public function getDebugInfo(): array;
}

class ZabbixClient implements DataSourceClientInterface
{
    private array $source;
    private DataSourceService $service;
    private ?string $authToken = null;
    private array $lastRequest = [];
    private mixed $lastResponse = null;
    private ?string $lastCurlCommand = null;
    
    public function __construct(array $source, DataSourceService $service)
    {
        $this->source = $source;
        $this->service = $service;
    }
    
    public function testConnection(): array
    {
        try {
            $token = $this->authenticate();
            if ($token) {
                // Get API version
                $version = $this->apiRequest('apiinfo.version', [], false);
                return [
                    'success' => true,
                    'message' => 'Connected successfully',
                    'version' => $version
                ];
            }
            return ['success' => false, 'error' => 'Authentication failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getHosts(array $filters = []): array
    {
        $params = [
            'output' => ['hostid', 'host', 'name', 'status'],
            'sortfield' => 'name',
        ];
        
        if (!empty($filters['search'])) {
            $params['search'] = ['name' => $filters['search']];
        }
        
        if (!empty($filters['groupids'])) {
            $params['groupids'] = $filters['groupids'];
        }
        
        $result = $this->apiRequest('host.get', $params);
        return $result ?: [];
    }
    
    public function getHostInterfaces(string $hostId): array
    {
        $params = [
            'output' => ['interfaceid', 'ip', 'dns', 'port', 'type', 'main'],
            'hostids' => $hostId,
        ];
        
        $result = $this->apiRequest('hostinterface.get', $params);
        return $result ?: [];
    }
    
    public function getItems(string $hostId, array $filters = []): array
    {
        $params = [
            'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units', 'value_type'],
            'hostids' => $hostId,
            'sortfield' => 'name',
        ];
        
        if (!empty($filters['search'])) {
            $params['search'] = ['name' => $filters['search']];
        }
        
        // Filter for network traffic items by default
        if (!empty($filters['key_pattern'])) {
            $params['search']['key_'] = $filters['key_pattern'];
        }
        
        $result = $this->apiRequest('item.get', $params);
        return $result ?: [];
    }
    
    public function getItemValue(string $itemId): ?array
    {
        $params = [
            'output' => ['itemid', 'name', 'lastvalue', 'lastclock', 'units'],
            'itemids' => $itemId,
        ];
        
        $result = $this->apiRequest('item.get', $params);
        return $result[0] ?? null;
    }

    public function getInterfaceBandwidthOptions(string $hostId): array
    {
        // In many setups (SNMP templates) item keys do not contain interface names.
        // Example keys:
        //   net.if.in[ifHCInOctets.2]
        //   net.if.out[ifHCOutOctets.2]
        // The human interface name is present in item.name, and pairing can be done by index (.2).

        $params = [
            'output' => ['itemid', 'key_', 'name'],
            'hostids' => $hostId,
            'search' => ['key_' => 'net.if.'],
            'searchByAny' => true,
            'limit' => 2000,
        ];

        $items = $this->apiRequest('item.get', $params);
        if (!$items) {
            return [];
        }

        $byIndex = [];
        foreach ($items as $item) {
            $key = (string) ($item['key_'] ?? '');
            $name = (string) ($item['name'] ?? '');

            // Prefer high-capacity octets (SNMP) for bandwidth
            if (preg_match('/^net\\.if\\.in\\[ifHCInOctets\\.(\\d+)\\]$/', $key, $m)) {
                $idx = $m[1];
                $byIndex[$idx] ??= ['idx' => $idx, 'label' => '', 'in' => '', 'out' => ''];
                $byIndex[$idx]['in'] = $key;
                if ($byIndex[$idx]['label'] === '') {
                    $byIndex[$idx]['label'] = $this->extractInterfaceLabelFromItemName($name);
                }
                continue;
            }
            if (preg_match('/^net\\.if\\.out\\[ifHCOutOctets\\.(\\d+)\\]$/', $key, $m)) {
                $idx = $m[1];
                $byIndex[$idx] ??= ['idx' => $idx, 'label' => '', 'in' => '', 'out' => ''];
                $byIndex[$idx]['out'] = $key;
                if ($byIndex[$idx]['label'] === '') {
                    $byIndex[$idx]['label'] = $this->extractInterfaceLabelFromItemName($name);
                }
                continue;
            }

            // Fallback: old-style agent keys net.if.in[eth0] / net.if.out[eth0]
            if (preg_match('/^net\\.if\\.(in|out)\\[(.+)\\]$/', $key, $m)) {
                $dir = $m[1];
                $args = trim((string) $m[2]);
                $firstArg = $args;
                $commaPos = strpos($args, ',');
                if ($commaPos !== false) {
                    $firstArg = substr($args, 0, $commaPos);
                }
                $ifname = trim($firstArg);
                $ifname = trim($ifname, " \t\n\r\0\x0B\"'");
                if ($ifname === '') {
                    continue;
                }
                $byIndex[$ifname] ??= ['idx' => $ifname, 'label' => $ifname, 'in' => '', 'out' => ''];
                $byIndex[$ifname][$dir] = $key;
            }
        }

        $options = [];
        foreach ($byIndex as $idx => $row) {
            if (($row['in'] ?? '') === '' || ($row['out'] ?? '') === '') {
                continue;
            }
            $label = (string) ($row['label'] ?? '');
            if ($label === '') {
                $label = (string) $idx;
            }
            $options[] = [
                'interfaceId' => (string) $idx,
                'label' => $label,
                'txKey' => (string) $row['out'],
                'rxKey' => (string) $row['in'],
            ];
        }

        usort($options, fn($a, $b) => strcmp((string) $a['label'], (string) $b['label']));
        return $options;
    }

    private function extractInterfaceLabelFromItemName(string $name): string
    {
        // Typical: "Interface ether2(): Bits received" or "Interface VLAN410(Core): Bits sent"
        if (preg_match('/^Interface\s+(.+?):/i', $name, $m)) {
            $label = trim($m[1]);
            if ($label !== '') {
                return $label;
            }
        }
        return '';
    }

    public function getBandwidthData(array $selection, int $minutes = 1440): array
    {
        $hostId = (string) ($selection['hostId'] ?? '');
        $inKey = (string) ($selection['inKey'] ?? '');
        $outKey = (string) ($selection['outKey'] ?? '');

        if ($hostId === '' || $inKey === '' || $outKey === '') {
            return [];
        }

        $inItem = $this->getItemByHostAndKey($hostId, $inKey);
        $outItem = $this->getItemByHostAndKey($hostId, $outKey);

        if (!$inItem || !$outItem) {
            return [];
        }

        $hostName = $this->getHostName($hostId);

        $timeFrom = time() - ($minutes * 60);

        $inSeries = $this->getHistorySeries((string) $inItem['itemid'], (int) ($inItem['value_type'] ?? 0), $timeFrom);
        $outSeries = $this->getHistorySeries((string) $outItem['itemid'], (int) ($outItem['value_type'] ?? 0), $timeFrom);

        return [
            'hostname' => $hostName,
            'in' => [
                'itemid' => (string) $inItem['itemid'],
                'name' => (string) ($inItem['name'] ?? ''),
                'units' => (string) ($inItem['units'] ?? ''),
                'lastvalue' => (string) ($inItem['lastvalue'] ?? ''),
                'series' => $inSeries,
            ],
            'out' => [
                'itemid' => (string) $outItem['itemid'],
                'name' => (string) ($outItem['name'] ?? ''),
                'units' => (string) ($outItem['units'] ?? ''),
                'lastvalue' => (string) ($outItem['lastvalue'] ?? ''),
                'series' => $outSeries,
            ],
        ];
    }

    public function getBandwidthCurrent(array $selection): array
    {
        $hostId = (string) ($selection['hostId'] ?? '');
        $inKey = (string) ($selection['inKey'] ?? '');
        $outKey = (string) ($selection['outKey'] ?? '');

        if ($hostId === '' || $inKey === '' || $outKey === '') {
            return [];
        }

        $inItem = $this->getItemByHostAndKey($hostId, $inKey);
        $outItem = $this->getItemByHostAndKey($hostId, $outKey);

        if (!$inItem || !$outItem) {
            return [];
        }

        return [
            'in' => (float) ($inItem['lastvalue'] ?? 0),
            'out' => (float) ($outItem['lastvalue'] ?? 0),
        ];
    }
    
    private function authenticate(): ?string
    {
        if ($this->authToken) {
            return $this->authToken;
        }
        
        // Check for API token first
        if (!empty($this->source['api_token'])) {
            $this->authToken = $this->source['api_token'];
            return $this->authToken;
        }
        
        // Use username/password authentication
        $encryptedPassword = (string) ($this->source['password'] ?? '');
        $password = $encryptedPassword !== ''
            ? $this->service->decryptPassword($encryptedPassword)
            : '';
        
        $params = [
            // See docs/zabbix-api-hosts.md
            'username' => (string) ($this->source['username'] ?? ''),
            'password' => $password,
        ];
        
        $result = $this->apiRequest('user.login', $params, false);
        
        if ($result) {
            $this->authToken = $result;
            return $this->authToken;
        }
        
        return null;
    }
    
    private function apiRequest(string $method, array $params = [], bool $auth = true): mixed
    {
        $baseUrl = rtrim((string) ($this->source['url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new \Exception('Zabbix URL is empty');
        }

        // Allow either base URL (ending with /zabbix) or full API endpoint URL
        $url = str_ends_with($baseUrl, 'api_jsonrpc.php')
            ? $baseUrl
            : ($baseUrl . '/api_jsonrpc.php');
        
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ];
        $this->lastRequest = $this->sanitizeRequestLog($request);
        
        $token = null;
        if ($auth) {
            // We always use Bearer token header (docs/zabbix-api-hosts.md)
            // because newer Zabbix API endpoints reject JSON-RPC "auth" in body.
            $token = $this->authenticate();
            if (!$token) {
                throw new \Exception('Authentication required');
            }
        }
        
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json-rpc'];
        if ($auth && $token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // For development - enable in production
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("CURL error: $error");
        }
        
        $data = json_decode($response, true);
        $this->lastResponse = $data;
        $this->lastCurlCommand = $this->buildCurlCommand($url, $this->lastRequest);
        
        if (isset($data['error'])) {
            $msg = $data['error']['data'] ?? $data['error']['message'] ?? 'API error';

            throw new \Exception($msg);
        }
        
        return $data['result'] ?? null;
    }

    private function getHostName(string $hostId): string
    {
        $params = [
            'output' => ['name'],
            'hostids' => $hostId,
        ];
        $result = $this->apiRequest('host.get', $params);
        return $result[0]['name'] ?? '';
    }

    private function getItemByHostAndKey(string $hostId, string $key): ?array
    {
        $params = [
            'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units', 'value_type'],
            'hostids' => $hostId,
            'filter' => ['key_' => $key],
            'limit' => 1,
        ];

        $result = $this->apiRequest('item.get', $params);
        return $result[0] ?? null;
    }

    private function getHistoryTypeFromValueType(int $valueType): int
    {
        // Zabbix: value_type -> history
        // 0 float -> 0
        // 1 char -> 1
        // 2 log -> 2
        // 3 uint -> 3
        // 4 text -> 4
        return match ($valueType) {
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            default => 0,
        };
    }

    private function getHistorySeries(string $itemId, int $valueType, int $timeFrom): array
    {
        $history = $this->getHistoryTypeFromValueType($valueType);

        $params = [
            'output' => 'extend',
            'history' => $history,
            'itemids' => [$itemId],
            'sortfield' => 'clock',
            'sortorder' => 'ASC',
            'time_from' => $timeFrom,
        ];

        $result = $this->apiRequest('history.get', $params);
        if (!$result) {
            return [];
        }

        $series = [];
        foreach ($result as $row) {
            $series[] = [
                't' => (int) ($row['clock'] ?? 0),
                'v' => (float) ($row['value'] ?? 0),
            ];
        }

        return $series;
    }

    private function sanitizeRequestLog(array $request): array
    {
        $sanitized = $request;
        if (isset($sanitized['params']['password'])) {
            $sanitized['params']['password'] = '***';
        }
        return $sanitized;
    }

    private function buildCurlCommand(string $url, array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return sprintf(
            "curl -X POST '%s' -H 'Content-Type: application/json-rpc' -d '%s'",
            $url,
            $json
        );
    }

    public function getDebugInfo(): array
    {
        return [
            'request' => $this->lastRequest,
            'response' => $this->lastResponse,
            'curl' => $this->lastCurlCommand,
        ];
    }
}
