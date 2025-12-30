<?php
/**
 * NetworkWeathermapNG - API Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DataSourceService;
use App\Services\DataSourceException;

class ApiController extends BaseController
{
    private DataSourceService $dataSourceService;
    
    public function __construct(array $context)
    {
        parent::__construct($context);
        $this->dataSourceService = new DataSourceService($this->database);
    }
    
    public function maps(array $params): void
    {
        $this->requireAuth();
        
        $maps = $this->database->query(
            "SELECT id, name, config_file, group_id, active, title_cache, thumb_width, thumb_height 
             FROM maps 
             WHERE active = 1 
             ORDER BY sort_order, name"
        );
        
        // Filter by permissions
        if ($this->config->isAuthEnabled() && !$this->auth->isAdmin()) {
            $maps = array_filter($maps, fn($map) => $this->auth->canViewMap((int) $map['id']));
            $maps = array_values($maps);
        }
        
        $this->json(['success' => true, 'data' => $maps]);
    }
    
    public function map(array $params): void
    {
        $this->requireAuth();
        
        $mapId = (int) ($params['id'] ?? 0);
        
        if (!$this->auth->canViewMap($mapId)) {
            $this->json(['success' => false, 'error' => 'Access denied'], 403);
            return;
        }
        
        $map = $this->database->queryOne(
            "SELECT * FROM maps WHERE id = ?",
            [$mapId]
        );
        
        if (!$map) {
            $this->json(['success' => false, 'error' => 'Map not found'], 404);
            return;
        }
        
        $this->json(['success' => true, 'data' => $map]);
    }

    // Data Source API endpoints
    
    public function dataSources(array $params): void
    {
        $this->requireAuth();
        
        $sources = $this->dataSourceService->getActiveSources();
        
        // Remove sensitive data
        $sources = array_map(function($s) {
            unset($s['password'], $s['api_token']);
            return $s;
        }, $sources);
        
        $this->json(['success' => true, 'data' => $sources]);
    }
    
    public function dataSourceHosts(array $params): void
    {
        $this->requireAuth();
        
        $sourceId = (int) ($params['sourceId'] ?? 0);
        $search = $this->getInput('search', '');
        
        if ($sourceId <= 0) {
            $this->json(['success' => false, 'error' => 'Source ID required'], 400);
            return;
        }
        
        try {
            $hosts = $this->dataSourceService->getHosts($sourceId, ['search' => $search]);
            $this->json(['success' => true, 'data' => $hosts]);
        } catch (DataSourceException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'debug' => $e->getDebug()], 500);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function dataSourceItems(array $params): void
    {
        $this->requireAuth();
        
        $sourceId = (int) ($params['sourceId'] ?? 0);
        $hostId = $params['hostId'] ?? '';
        $search = $this->getInput('search', '');
        
        if ($sourceId <= 0 || empty($hostId)) {
            $this->json(['success' => false, 'error' => 'Source ID and Host ID required'], 400);
            return;
        }
        
        try {
            $items = $this->dataSourceService->getItems($sourceId, $hostId, ['search' => $search]);
            $this->json(['success' => true, 'data' => $items]);
        } catch (DataSourceException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'debug' => $e->getDebug()], 500);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function dataSourceInterfaces(array $params): void
    {
        $this->requireAuth();
        
        $sourceId = (int) ($params['sourceId'] ?? 0);
        $hostId = $params['hostId'] ?? '';
        
        if ($sourceId <= 0 || empty($hostId)) {
            $this->json(['success' => false, 'error' => 'Source ID and Host ID required'], 400);
            return;
        }
        
        try {
            $interfaces = $this->dataSourceService->getHostInterfaces($sourceId, $hostId);
            $this->json(['success' => true, 'data' => $interfaces]);
        } catch (DataSourceException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'debug' => $e->getDebug()], 500);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function dataSourceBandwidths(array $params): void
    {
        $this->requireAuth();

        $sourceId = (int) ($params['sourceId'] ?? 0);
        $hostId = $params['hostId'] ?? '';

        if ($sourceId <= 0 || empty($hostId)) {
            $this->json(['success' => false, 'error' => 'Source ID and Host ID required'], 400);
            return;
        }

        try {
            $bandwidths = $this->dataSourceService->getInterfaceBandwidthOptions($sourceId, $hostId);
            $this->json(['success' => true, 'data' => $bandwidths]);

        } catch (DataSourceException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'debug' => $e->getDebug()], 500);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function dataSourceLinkBandwidth(array $params): void
    {
        $this->requireAuth();

        $sourceId = (int) ($params['sourceId'] ?? 0);

        if ($sourceId <= 0) {
            $this->json(['success' => false, 'error' => 'Source ID required'], 400);
            return;
        }

        $selectionJson = $this->getInput('selection', '');
        if ($selectionJson === '') {
            $this->json(['success' => false, 'error' => 'Selection required'], 400);
            return;
        }

        $selection = json_decode($selectionJson, true);
        if (!is_array($selection)) {
            $this->json(['success' => false, 'error' => 'Invalid selection JSON'], 400);
            return;
        }

        $minutes = (int) $this->getInput('minutes', '1440');
        if ($minutes <= 0) {
            $minutes = 1440;
        }

        try {
            $data = $this->dataSourceService->getLinkBandwidthData($sourceId, $selection, $minutes);
            $this->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
