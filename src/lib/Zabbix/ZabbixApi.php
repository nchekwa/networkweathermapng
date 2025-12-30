<?php
/**
 * Zabbix Weathermap - Zabbix API Client
 */

declare(strict_types=1);

namespace Zabbix;

class ZabbixApi
{
    private string $url;
    private ?string $authToken = null;
    private ?string $apiToken = null;
    private ?string $username = null;
    private ?string $password = null;
    private int $timeout = 30;
    private int $requestId = 1;
    
    public function __construct(
        string $url,
        ?string $apiToken = null,
        ?string $username = null,
        ?string $password = null
    ) {
        $this->url = rtrim($url, '/');
        $this->apiToken = $apiToken;
        $this->username = $username;
        $this->password = $password;
    }
    
    public function authenticate(): bool
    {
        // If API token is provided, use it directly
        if (!empty($this->apiToken)) {
            $this->authToken = $this->apiToken;
            return true;
        }
        
        // Otherwise, authenticate with username/password
        if (empty($this->username) || empty($this->password)) {
            throw new ZabbixApiException('No API token or username/password provided');
        }
        
        $response = $this->request('user.login', [
            'username' => $this->username,
            'password' => $this->password,
        ], false);
        
        if (isset($response['result'])) {
            $this->authToken = $response['result'];
            return true;
        }
        
        return false;
    }
    
    public function isAuthenticated(): bool
    {
        return $this->authToken !== null;
    }
    
    /**
     * Get Zabbix API version
     */
    public function getVersion(): string
    {
        $response = $this->request('apiinfo.version', [], false);
        return $response['result'] ?? 'unknown';
    }
    
    /**
     * Get hosts from Zabbix
     */
    public function getHosts(array $params = []): array
    {
        $defaults = [
            'output' => ['hostid', 'host', 'name', 'status', 'available'],
            'sortfield' => 'name',
        ];
        
        return $this->call('host.get', array_merge($defaults, $params));
    }
    
    /**
     * Get host by ID
     */
    public function getHost(string $hostId): ?array
    {
        $hosts = $this->getHosts([
            'hostids' => $hostId,
            'output' => 'extend',
            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'port', 'type'],
            'selectGroups' => ['groupid', 'name'],
        ]);
        
        return $hosts[0] ?? null;
    }
    
    /**
     * Get items from Zabbix
     */
    public function getItems(array $params = []): array
    {
        $defaults = [
            'output' => ['itemid', 'name', 'key_', 'hostid', 'status', 'value_type', 'units', 'lastvalue'],
            'sortfield' => 'name',
        ];
        
        return $this->call('item.get', array_merge($defaults, $params));
    }
    
    /**
     * Get items for a specific host
     */
    public function getHostItems(string $hostId, array $params = []): array
    {
        return $this->getItems(array_merge(['hostids' => $hostId], $params));
    }
    
    /**
     * Get item by ID
     */
    public function getItem(string $itemId): ?array
    {
        $items = $this->getItems([
            'itemids' => $itemId,
            'output' => 'extend',
        ]);
        
        return $items[0] ?? null;
    }
    
    /**
     * Get item by host and key
     */
    public function getItemByKey(string $hostId, string $key): ?array
    {
        $items = $this->getItems([
            'hostids' => $hostId,
            'filter' => ['key_' => $key],
            'output' => 'extend',
        ]);
        
        return $items[0] ?? null;
    }
    
    /**
     * Get last value for an item
     */
    public function getItemLastValue(string $itemId): ?float
    {
        $item = $this->getItem($itemId);
        
        if ($item && isset($item['lastvalue'])) {
            return (float) $item['lastvalue'];
        }
        
        return null;
    }
    
    /**
     * Get host groups
     */
    public function getHostGroups(array $params = []): array
    {
        $defaults = [
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
        ];
        
        return $this->call('hostgroup.get', array_merge($defaults, $params));
    }
    
    /**
     * Get host interfaces
     */
    public function getHostInterfaces(array $params = []): array
    {
        $defaults = [
            'output' => ['interfaceid', 'hostid', 'ip', 'dns', 'port', 'type', 'main', 'available'],
        ];
        
        return $this->call('hostinterface.get', array_merge($defaults, $params));
    }
    
    /**
     * Get history data for an item
     */
    public function getHistory(string $itemId, int $valueType = 0, int $limit = 1): array
    {
        return $this->call('history.get', [
            'itemids' => $itemId,
            'history' => $valueType,
            'sortfield' => 'clock',
            'sortorder' => 'DESC',
            'limit' => $limit,
            'output' => 'extend',
        ]);
    }
    
    /**
     * Get trends data for an item
     */
    public function getTrends(string $itemId, int $valueType = 0, int $limit = 1): array
    {
        return $this->call('trend.get', [
            'itemids' => $itemId,
            'sortfield' => 'clock',
            'sortorder' => 'DESC',
            'limit' => $limit,
            'output' => 'extend',
        ]);
    }
    
    /**
     * Search hosts by name
     */
    public function searchHosts(string $search): array
    {
        return $this->getHosts([
            'search' => ['name' => $search],
            'searchWildcardsEnabled' => true,
        ]);
    }
    
    /**
     * Search items by name or key
     */
    public function searchItems(string $search, ?string $hostId = null): array
    {
        $params = [
            'search' => ['name' => $search, 'key_' => $search],
            'searchByAny' => true,
            'searchWildcardsEnabled' => true,
        ];
        
        if ($hostId) {
            $params['hostids'] = $hostId;
        }
        
        return $this->getItems($params);
    }
    
    /**
     * Make an API call
     */
    public function call(string $method, array $params = []): array
    {
        $response = $this->request($method, $params);
        
        if (isset($response['error'])) {
            throw new ZabbixApiException(
                $response['error']['message'] . ': ' . ($response['error']['data'] ?? ''),
                $response['error']['code']
            );
        }
        
        return $response['result'] ?? [];
    }
    
    /**
     * Make a raw API request
     */
    private function request(string $method, array $params = [], bool $auth = true): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $this->requestId++,
        ];
        
        $headers = [
            'Content-Type: application/json-rpc',
        ];
        
        // Add authorization header if authenticated
        if ($auth && $this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }
        
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            throw new ZabbixApiException("cURL error: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new ZabbixApiException("HTTP error: {$httpCode}");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ZabbixApiException("JSON decode error: " . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Set request timeout
     */
    public function setTimeout(int $seconds): void
    {
        $this->timeout = $seconds;
    }
}

/**
 * Zabbix API Exception
 */
class ZabbixApiException extends \Exception
{
}
