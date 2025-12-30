<?php
/**
 * NetworkWeathermapNG - Visual Editor Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MapService;

class EditorController extends BaseController
{
    private MapService $mapService;
    private string $configPath;
    private int $gridSnap = 0;
    
    public function __construct(array $context)
    {
        parent::__construct($context);
        $this->mapService = new MapService($this->database, $this->config);
        $this->configPath = $this->config->getConfigsPath();
    }
    
    public function index(array $params): void
    {
        $this->requireAdmin();
        $maps = $this->mapService->getAllMaps();
        
        $this->render('editor/index', [
            'maps' => $maps,
            'title' => 'Map Editor',
        ]);
    }
    
    /**
     * Visual map editor - main entry point
     */
    public function edit(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('error', 'Invalid map ID');
            $this->redirect('/editor');
            return;
        }
        
        $map = $this->mapService->getMap($id);
        if (!$map) {
            $this->flash('error', 'Map not found');
            $this->redirect('/editor');
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        // Load WeatherMap to get map data
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        require_once $this->config->getLibPath() . '/WeatherMap/editor.inc.php';
        
        // Suppress OVERLIBGRAPH warnings in editor context
        $GLOBALS['weathermap_error_suppress'] = array_values(array_unique(array_merge(
            (array)($GLOBALS['weathermap_error_suppress'] ?? []),
            ['WMWARN41']
        )));
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        
        if (file_exists($mapFile)) {
            $wmap->ReadConfig($mapFile);
        }
        
        // Get image lists
        $basePath = $this->config->getPublicPath();
        $imageList = get_imagelist($basePath, 'objects');
        $bgList = get_imagelist($basePath, 'backgrounds');
        
        $mapConfig = $this->mapService->getMapConfig($id) ?? '';

        $this->render('editor/visual', [
            'map' => $map,
            'mapId' => $id,
            'mapFile' => $map['config_file'],
            'wmap' => $wmap,
            'imageList' => $imageList,
            'bgList' => $bgList,
            'mapConfig' => $mapConfig,
            'title' => 'Edit Map: ' . $map['name'],
        ], false); // No layout - fullscreen editor
    }

    public function config(array $params): void
    {
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'Invalid map ID'], 400);
            return;
        }

        $map = $this->mapService->getMap($id);
        if (!$map) {
            $this->json(['success' => false, 'error' => 'Map not found'], 404);
            return;
        }

        $content = $this->mapService->getMapConfig($id);
        if ($content === null) {
            $this->json(['success' => false, 'error' => 'Config not found'], 404);
            return;
        }

        $this->json(['success' => true, 'config' => $content]);
    }
    
    /**
     * Draw map image for editor
     */
    public function draw(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $selected = $_GET['selected'] ?? '';
        
        $map = $this->mapService->getMap($id);
        if (!$map) {
            http_response_code(404);
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        header('Content-type: image/png');
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->htmlstyle = 'editor';
        
        if (file_exists($mapFile)) {
            // Clear file status cache to ensure we read the latest config
            clearstatcache(true, $mapFile);
            $GLOBALS['weathermap_error_suppress'] = array_values(array_unique(array_merge(
                (array)($GLOBALS['weathermap_error_suppress'] ?? []),
                ['WMWARN41']
            )));
            ob_start();
            $wmap->ReadConfig($mapFile);
            ob_end_clean();
        }
        
        // Mark selected item
        if ($selected != '') {
            if (substr($selected, 0, 5) == 'NODE:') {
                $nodename = substr($selected, 5);
                if (isset($wmap->nodes[$nodename])) {
                    $wmap->nodes[$nodename]->selected = 1;
                }
            }
            if (substr($selected, 0, 5) == 'LINK:') {
                $linkname = substr($selected, 5);
                if (isset($wmap->links[$linkname])) {
                    $wmap->links[$linkname]->selected = 1;
                }
            }
        }

        // Load current data from datasources so the rendered image reflects live values
        $tmpFile = tempnam(sys_get_temp_dir(), 'wm_editor_');
        if ($tmpFile === false) {
            http_response_code(500);
            return;
        }
        $tmpPng = $tmpFile . '.png';
        @unlink($tmpFile);

        ob_start();
        $wmap->ReadData();
        $wmap->sizedebug = false;
        $wmap->DrawMap($tmpPng, '', 250, true, false, false);
        ob_end_clean();

        if (!is_file($tmpPng)) {
            http_response_code(500);
            return;
        }

        readfile($tmpPng);
        @unlink($tmpPng);
    }
    
    /**
     * Get both area and JS data from the same WeatherMap instance
     */
    public function getMapData(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $map = $this->mapService->getMap($id);
        if (!$map) {
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->htmlstyle = 'editor';
        
        if (file_exists($mapFile)) {
            clearstatcache(true, $mapFile);
            $GLOBALS['weathermap_error_suppress'] = array_values(array_unique(array_merge(
                (array)($GLOBALS['weathermap_error_suppress'] ?? []),
                ['WMWARN41']
            )));
            ob_start();
            $wmap->ReadConfig($mapFile);
            $wmap->DrawMap('null');
            $wmap->PreloadMapHTML();
            ob_end_clean();
        }
        
        // Return both area HTML and JS data as JSON
        header('Content-type: application/json');
        echo json_encode([
            'area' => $wmap->SortedImagemap('weathermap_imap'),
            'js' => $wmap->asJS()
        ]);
    }
    
    /**
     * Get map area data (imagemap)
     */
    public function getAreaData(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $map = $this->mapService->getMap($id);
        if (!$map) {
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->htmlstyle = 'editor';
        
        if (file_exists($mapFile)) {
            $GLOBALS['weathermap_error_suppress'] = array_values(array_unique(array_merge(
                (array)($GLOBALS['weathermap_error_suppress'] ?? []),
                ['WMWARN41']
            )));
            ob_start();
            $wmap->ReadConfig($mapFile);
            ob_end_clean();
        }

        ob_start();
        $wmap->DrawMap('null');
        $wmap->PreloadMapHTML();
        ob_end_clean();
        
        echo $wmap->SortedImagemap('weathermap_imap');
    }
    
    /**
     * Get map JavaScript data (nodes/links)
     */
    public function getMapJS(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $map = $this->mapService->getMap($id);
        if (!$map) {
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->htmlstyle = 'editor';
        
        if (file_exists($mapFile)) {
            // Clear file status cache to ensure we read the latest config
            clearstatcache(true, $mapFile);
            $GLOBALS['weathermap_error_suppress'] = array_values(array_unique(array_merge(
                (array)($GLOBALS['weathermap_error_suppress'] ?? []),
                ['WMWARN41']
            )));
            ob_start();
            $wmap->ReadConfig($mapFile);
            // Initialize node properties by drawing to null
            $wmap->DrawMap('null');
            ob_end_clean();
        }
        
        header('Content-type: application/javascript');
        echo $wmap->asJS();
    }
    
    /**
     * Handle editor actions (add_node, move_node, add_link, delete, etc.)
     */
    public function action(array $params): void
    {
        ob_start();
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $action = $_REQUEST['action'] ?? '';
        
        $map = $this->mapService->getMap($id);
        if (!$map) {
            ob_end_clean();
            $this->json(['success' => false, 'error' => 'Map not found']);
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        require_once $this->config->getLibPath() . '/WeatherMap/editor.inc.php';
        
        switch ($action) {
            case 'add_node':
                $this->addNode($mapFile);
                return; // addNode handles its own JSON response
            case 'move_node':
                $this->moveNode($mapFile);
                return; // moveNode handles its own JSON response
            case 'delete_node':
                $this->deleteNode($mapFile);
                return; // deleteNode handles its own JSON response
            case 'clone_node':
                $this->cloneNode($mapFile);
                return; // cloneNode handles its own JSON response
            case 'set_node_properties':
                $this->setNodeProperties($mapFile);
                break;
            case 'add_link':
            case 'add_link2':
                $this->addLink($mapFile);
                return; // addLink handles its own JSON response
            case 'delete_link':
                $this->deleteLink($mapFile);
                break;
            case 'set_link_properties':
                $this->setLinkProperties($mapFile);
                break;
            case 'via_link':
                $this->viaLink($mapFile);
                break;
            case 'link_tidy':
                $this->tidyLink($mapFile);
                break;
            case 'set_map_properties':
                $this->setMapProperties($mapFile);
                break;
            case 'load_area_data':
                $this->loadAreaData($params);
                return; // loadAreaData handles its own output
            case 'load_map_javascript':
                $this->loadMapJavascript($params);
                return; // loadMapJavascript handles its own output
            case 'place_legend':
                $this->placeLegend($mapFile);
                break;
            case 'place_stamp':
                $this->placeStamp($mapFile);
                break;
            default:
                ob_end_clean();
                $this->json(['success' => false, 'error' => 'Unknown action: ' . $action]);
                return;
        }
        
        // Default success response for handlers that don't return their own JSON
        ob_end_clean();
        $this->json(['success' => true]);
    }
    
    private function addNode(string $mapFile): void
    {
        $x = snap((int) ($_REQUEST['x'] ?? 0), $this->gridSnap);
        $y = snap((int) ($_REQUEST['y'] ?? 0), $this->gridSnap);
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        if (file_exists($mapFile)) {
            $wmap->ReadConfig($mapFile);
        }
        
        $newnodename = sprintf('node%05d', time() % 10000);
        while (array_key_exists($newnodename, $wmap->nodes)) {
            $newnodename .= 'a';
        }
        
        $node = new \WeatherMapNode();
        $node->name = $newnodename;
        $node->template = 'DEFAULT';
        $node->Reset($wmap);
        $node->x = $x;
        $node->y = $y;
        $node->defined_in = $wmap->configfile;
        
        if (isset($wmap->seen_zlayers[$node->zorder])) {
            array_push($wmap->seen_zlayers[$node->zorder], $node);
        }
        
        if ($wmap->nodes['DEFAULT']->label == $wmap->nodes[':: DEFAULT ::']->label) {
            $node->label = 'Node';
        }
        
        $wmap->nodes[$node->name] = $node;
        $wmap->WriteConfig($mapFile);
        
        // Force file system sync
        if (function_exists('fsync')) {
            $file = fopen($mapFile, 'r');
            if ($file) {
                fsync($file);
                fclose($file);
            }
        }
        
        // Initialize the map to get proper IDs
        $wmap->DrawMap('null');
        
        // Generate area HTML from the same instance
        $wmap->htmlstyle = 'editor';
        $wmap->PreloadMapHTML();
        $areaHtml = $wmap->SortedImagemap('weathermap_imap');
        
        // Get the complete JS data from this instance
        $jsData = $wmap->asJS();
        
        // Debug: Verify node ID is set
        error_log("WeatherMap: Created node with ID: " . $node->id . ", Name: " . $node->name);
        error_log("WeatherMap: Config file size after write: " . filesize($mapFile));
        
        // Return the new node data and complete JS arrays
        ob_end_clean();
        $this->json([
            'success' => true,
            'node' => [
                'id' => 'N' . $node->id,
                'name' => $node->name,
                'x' => $node->x,
                'y' => $node->y
            ],
            'jsData' => $jsData,
            'areaHtml' => $areaHtml
        ]);
        return;
    }
    
    private function moveNode(string $mapFile): void
    {
        $x = snap((int) ($_REQUEST['x'] ?? 0), $this->gridSnap);
        $y = snap((int) ($_REQUEST['y'] ?? 0), $this->gridSnap);
        $nodeName = wm_editor_sanitize_name($_REQUEST['node_name'] ?? $_REQUEST['param'] ?? '');
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        $wmap = new \WeatherMap();
        $wmap->ReadConfig($mapFile);
        
        if (isset($wmap->nodes[$nodeName])) {
            $wmap->nodes[$nodeName]->x = $x;
            $wmap->nodes[$nodeName]->y = $y;
            $wmap->WriteConfig($mapFile);
            
            // Return updated position for frontend
            ob_end_clean();
            $this->json([
                'success' => true,
                'node' => [
                    'name' => $nodeName,
                    'x' => $x,
                    'y' => $y
                ]
            ]);
            return;
        }
        
        ob_end_clean();
        $this->json(['success' => false, 'error' => 'Node not found']);
    }
    
    private function deleteNode(string $mapFile): void
    {
        $target = wm_editor_sanitize_name($_REQUEST['param'] ?? '');
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        $wmap = new \WeatherMap();
        $wmap->ReadConfig($mapFile);
        
        if (isset($wmap->nodes[$target])) {
            // Delete links connected to this node
            foreach ($wmap->links as $link) {
                if (isset($link->a)) {
                    if (($target == $link->a->name) || ($target == $link->b->name)) {
                        unset($wmap->links[$link->name]);
                    }
                }
            }
            unset($wmap->nodes[$target]);
            $wmap->WriteConfig($mapFile);
            
            // Return success for frontend
            ob_end_clean();
            $this->json([
                'success' => true,
                'node' => [
                    'name' => $target
                ]
            ]);
            return;
        }
        
        ob_end_clean();
        $this->json(['success' => false, 'error' => 'Node not found']);
    }
    
    private function cloneNode(string $mapFile): void
    {
        $target = wm_editor_sanitize_name($_REQUEST['param'] ?? '');
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        if (isset($wmap->nodes[$target])) {
            $newnodename = $target;
            do {
                $newnodename = $newnodename . '_copy';
            } while (isset($wmap->nodes[$newnodename]));
            
            $node = new \WeatherMapNode();
            $node->Reset($wmap);
            $node->CopyFrom($wmap->nodes[$target]);
            $node->template = $wmap->nodes[$target]->template;
            $node->name = $newnodename;
            $node->x += 30;
            $node->y += 30;
            $node->defined_in = $mapFile;
            
            $wmap->nodes[$newnodename] = $node;
            if (isset($wmap->seen_zlayers[$node->zorder])) {
                array_push($wmap->seen_zlayers[$node->zorder], $node);
            }
            $wmap->WriteConfig($mapFile);
            
            // Force file system sync
            if (function_exists('fsync')) {
                $file = fopen($mapFile, 'r');
                if ($file) {
                    fsync($file);
                    fclose($file);
                }
            }
            
            // Initialize the map to get proper IDs
            $wmap->DrawMap('null');
            
            // Generate area HTML from the same instance
            $wmap->htmlstyle = 'editor';
            $wmap->PreloadMapHTML();
            $areaHtml = $wmap->SortedImagemap('weathermap_imap');
            
            // Get the complete JS data from this instance
            $jsData = $wmap->asJS();
            
            // Return the cloned node data and complete JS arrays
            ob_end_clean();
            $this->json([
                'success' => true,
                'node' => [
                    'id' => 'N' . $node->id,
                    'name' => $newnodename,
                    'x' => $node->x,
                    'y' => $node->y
                ],
                'jsData' => $jsData,
                'areaHtml' => $areaHtml
            ]);
            return;
        }
    }
    
    private function setNodeProperties(string $mapFile): void
    {
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        $nodeName = $_REQUEST['node_name'] ?? '';
        $newNodeName = $_REQUEST['node_new_name'] ?? $nodeName;
        
        // Handle rename
        if ($nodeName != $newNodeName && strpos($newNodeName, ' ') === false) {
            if (!isset($wmap->nodes[$newNodeName])) {
                $newnode = $wmap->nodes[$nodeName];
                $newnode->name = $newNodeName;
                $wmap->nodes[$newNodeName] = $newnode;
                unset($wmap->nodes[$nodeName]);
                
                // Update references
                foreach ($wmap->links as $link) {
                    if (isset($link->a)) {
                        if ($link->a->name == $nodeName) {
                            $wmap->links[$link->name]->a = $newnode;
                        }
                        if ($link->b->name == $nodeName) {
                            $wmap->links[$link->name]->b = $newnode;
                        }
                    }
                }
            } else {
                $newNodeName = $nodeName;
            }
        }
        
        $wmap->nodes[$newNodeName]->label = wm_editor_sanitize_string($_REQUEST['node_label'] ?? '');
        $wmap->nodes[$newNodeName]->infourl[IN] = wm_editor_sanitize_string($_REQUEST['node_infourl'] ?? '');
        
        $urls = preg_split('/\s+/', trim($_REQUEST['node_hover'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $wmap->nodes[$newNodeName]->overliburl[IN] = $urls;
        $wmap->nodes[$newNodeName]->overliburl[OUT] = $urls;
        
        $wmap->nodes[$newNodeName]->x = (int) ($_REQUEST['node_x'] ?? $wmap->nodes[$newNodeName]->x);
        $wmap->nodes[$newNodeName]->y = (int) ($_REQUEST['node_y'] ?? $wmap->nodes[$newNodeName]->y);
        
        $iconfile = $_REQUEST['node_iconfilename'] ?? '';
        if ($iconfile == '--NONE--') {
            $wmap->nodes[$newNodeName]->iconfile = '';
        } elseif ($iconfile != '--AICON--' && $iconfile != '') {
            $wmap->nodes[$newNodeName]->iconfile = stripslashes($iconfile);
        }
        
        $wmap->WriteConfig($mapFile);
        
        // Invalidate opcache to ensure fresh read
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($mapFile, true);
        }
        clearstatcache(true, $mapFile);
    }
    
    private function addLink(string $mapFile): void
    {
        $a = $_REQUEST['param'] ?? '';
        $b = $_REQUEST['param2'] ?? '';

        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);

        if ($a != $b && isset($wmap->nodes[$a]) && isset($wmap->nodes[$b])) {
            $newlink = new \WeatherMapLink();
            $newlink->Reset($wmap);
            $newlink->a = $wmap->nodes[$a];
            $newlink->b = $wmap->nodes[$b];
            $newlink->width = $wmap->links['DEFAULT']->width;

            $newlinkname = "$a-$b";
            while (array_key_exists($newlinkname, $wmap->links)) {
                $newlinkname .= 'a';
            }

            $newlink->name = $newlinkname;
            $newlink->defined_in = $wmap->configfile;
            $wmap->links[$newlinkname] = $newlink;

            if (isset($wmap->seen_zlayers[$newlink->zorder])) {
                array_push($wmap->seen_zlayers[$newlink->zorder], $newlink);
            }

            $wmap->WriteConfig($mapFile);

            // Initialize the map to get proper IDs
            $wmap->DrawMap('null');
            
            // Generate area HTML from the same instance
            $wmap->htmlstyle = 'editor';
            $wmap->PreloadMapHTML();
            $areaHtml = $wmap->SortedImagemap('weathermap_imap');
            
            // Get the complete JS data from this instance
            $jsData = $wmap->asJS();

            // Return the new link data and complete JS arrays
            ob_end_clean();
            $this->json([
                'success' => true,
                'link' => [
                    'id' => 'L' . $newlink->id,
                    'name' => $newlinkname,
                    'a' => $a,
                    'b' => $b
                ],
                'jsData' => $jsData,
                'areaHtml' => $areaHtml
            ]);
            return;
        }

        ob_end_clean();
        $this->json(['success' => false, 'error' => 'Failed to create link - invalid nodes']);
        return;
    }
    
    private function deleteLink(string $mapFile): void
    {
        $target = wm_editor_sanitize_name($_REQUEST['param'] ?? $_REQUEST['link_name'] ?? '');
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        if (isset($wmap->links[$target])) {
            unset($wmap->links[$target]);
            $wmap->WriteConfig($mapFile);
        }
    }
    
    private function setLinkProperties(string $mapFile): void
    {
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        $linkName = $_REQUEST['link_name'] ?? '';
        
        if (strpos($linkName, ' ') === false && isset($wmap->links[$linkName])) {
            $wmap->links[$linkName]->width = (float) ($_REQUEST['link_width'] ?? $wmap->links[$linkName]->width);
            $wmap->links[$linkName]->infourl[IN] = wm_editor_sanitize_string($_REQUEST['link_infourl'] ?? '');
            $wmap->links[$linkName]->infourl[OUT] = wm_editor_sanitize_string($_REQUEST['link_infourl'] ?? '');
            
            $urls = preg_split('/\s+/', $_REQUEST['link_hover'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
            $wmap->links[$linkName]->overliburl[IN] = $urls;
            $wmap->links[$linkName]->overliburl[OUT] = $urls;
            
            $wmap->links[$linkName]->comments[IN] = wm_editor_sanitize_string($_REQUEST['link_commentin'] ?? '');
            $wmap->links[$linkName]->comments[OUT] = wm_editor_sanitize_string($_REQUEST['link_commentout'] ?? '');
            
            $targets = preg_split('/\s+/', trim($_REQUEST['link_target'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $newTargetList = [];
            foreach ($targets as $target) {
                $newtarget = [$target, 'traffic_in', 'traffic_out', 0, $target];
                if (preg_match('/(.*\.rrd):([\-a-zA-Z0-9_]+):([\-a-zA-Z0-9_]+)$/i', $target, $matches)) {
                    $newtarget[0] = trim($matches[1]);
                    $newtarget[1] = trim($matches[2]);
                    $newtarget[2] = trim($matches[3]);
                }
                $newTargetList[] = $newtarget;
            }
            $wmap->links[$linkName]->targets = $newTargetList;

            $datasource = trim((string) ($_REQUEST['link_datasource'] ?? ''));
            if ($datasource !== '') {
                $wmap->links[$linkName]->hints['datasource'] = $datasource;
            } else {
                unset($wmap->links[$linkName]->hints['datasource']);
            }
            
            $bwin = $_REQUEST['link_bandwidth_in'] ?? '';
            $bwout = $_REQUEST['link_bandwidth_out'] ?? '';
            
            if (isset($_REQUEST['link_bandwidth_out_cb']) && $_REQUEST['link_bandwidth_out_cb'] == 'symmetric') {
                $bwout = $bwin;
            }
            
            if (wm_editor_validate_bandwidth($bwin)) {
                $wmap->links[$linkName]->max_bandwidth_in_cfg = $bwin;
                $wmap->links[$linkName]->max_bandwidth_in = unformat_number($bwin, $wmap->kilo);
            }
            if (wm_editor_validate_bandwidth($bwout)) {
                $wmap->links[$linkName]->max_bandwidth_out_cfg = $bwout;
                $wmap->links[$linkName]->max_bandwidth_out = unformat_number($bwout, $wmap->kilo);
            }
            
            $wmap->WriteConfig($mapFile);
            
            // Invalidate opcache to ensure fresh read
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($mapFile, true);
            }
            clearstatcache(true, $mapFile);
        }
    }
    
    private function viaLink(string $mapFile): void
    {
        $x = (int) ($_REQUEST['x'] ?? 0);
        $y = (int) ($_REQUEST['y'] ?? 0);
        $linkName = wm_editor_sanitize_name($_REQUEST['link_name'] ?? '');
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        if (isset($wmap->links[$linkName])) {
            $wmap->links[$linkName]->vialist = [[0 => $x, 1 => $y]];
            $wmap->WriteConfig($mapFile);
        }
    }
    
    private function tidyLink(string $mapFile): void
    {
        $target = wm_editor_sanitize_name($_REQUEST['param'] ?? '');
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        if (isset($wmap->links[$target])) {
            $wmap->DrawMap('null');
            tidy_link($wmap, $target);
            $wmap->WriteConfig($mapFile);
        }
    }
    
    private function setMapProperties(string $mapFile): void
    {
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        $wmap->title = wm_editor_sanitize_string($_REQUEST['map_title'] ?? $wmap->title);
        $wmap->keytext['DEFAULT'] = wm_editor_sanitize_string($_REQUEST['map_legend'] ?? '');
        $wmap->stamptext = wm_editor_sanitize_string($_REQUEST['map_stamp'] ?? '');
        $wmap->width = (int) ($_REQUEST['map_width'] ?? $wmap->width);
        $wmap->height = (int) ($_REQUEST['map_height'] ?? $wmap->height);
        
        $bgfile = $_REQUEST['map_bgfile'] ?? '';
        if ($bgfile == '--NONE--') {
            $wmap->background = '';
        } elseif ($bgfile != '') {
            $wmap->background = wm_editor_sanitize_file(stripslashes($bgfile), ['png', 'jpg', 'gif', 'jpeg']);
        }
        
        $wmap->WriteConfig($mapFile);
    }
    
    private function placeLegend(string $mapFile): void
    {
        $x = snap((int) ($_REQUEST['x'] ?? 0), $this->gridSnap);
        $y = snap((int) ($_REQUEST['y'] ?? 0), $this->gridSnap);
        $scalename = wm_editor_sanitize_name($_REQUEST['param'] ?? 'DEFAULT');
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        $wmap->keyx[$scalename] = $x;
        $wmap->keyy[$scalename] = $y;
        
        $wmap->WriteConfig($mapFile);
    }
    
    private function placeStamp(string $mapFile): void
    {
        $x = snap((int) ($_REQUEST['x'] ?? 0), $this->gridSnap);
        $y = snap((int) ($_REQUEST['y'] ?? 0), $this->gridSnap);
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        $wmap->ReadConfig($mapFile);
        
        $wmap->timex = $x;
        $wmap->timey = $y;
        
        $wmap->WriteConfig($mapFile);
    }
    
    /**
     * Save map configuration (text mode)
     */
    public function save(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $config = $this->getInput('config', '');
        
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'Invalid map ID'], 400);
            return;
        }
        
        $map = $this->mapService->getMap($id);
        if (!$map) {
            $this->json(['success' => false, 'error' => 'Map not found'], 404);
            return;
        }
        
        if ($this->mapService->saveMapConfig($id, $config)) {
            $this->json(['success' => true, 'message' => 'Configuration saved successfully']);
        } else {
            $this->json(['success' => false, 'error' => 'Failed to save configuration'], 500);
        }
    }
    
    /**
     * Render map preview
     */
    public function preview(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'Invalid map ID'], 400);
            return;
        }
        
        $result = $this->mapService->renderMap($id, true);
        
        if ($result && !isset($result['error'])) {
            $this->json([
                'success' => true,
                'image' => '/output/' . basename($result['image']),
                'cached' => $result['cached']
            ]);
        } else {
            $this->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to render map'
            ], 500);
        }
    }
    
    /**
     * Load HTML imagemap areas for the editor
     */
    public function loadAreaData(array $params): void
    {
        $this->requireAdmin();
        
        $mapName = $_GET['mapname'] ?? '';
        if (!$mapName) {
            return;
        }
        
        $map = $this->mapService->getMapByName($mapName);
        if (!$map) {
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        
        if (file_exists($mapFile)) {
            $wmap->ReadConfig($mapFile);
        }
        
        // Output HTML imagemap
        echo $wmap->MakeHTML();
    }
    
    /**
     * Load JavaScript data (Nodes, NodeIDs, Links) for the editor
     */
    public function loadMapJavascript(array $params): void
    {
        $this->requireAdmin();
        
        $mapName = $_GET['mapname'] ?? '';
        if (!$mapName) {
            return;
        }
        
        $map = $this->mapService->getMapByName($mapName);
        if (!$map) {
            return;
        }
        
        $mapFile = $this->configPath . '/' . $map['config_file'];
        
        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';
        
        $wmap = new \WeatherMap();
        $wmap->context = 'editor';
        
        if (file_exists($mapFile)) {
            $wmap->ReadConfig($mapFile);
        }
        
        header('Content-type: text/javascript');
        
        echo "var Nodes = new Array();\n";
        echo "var NodeIDs = new Array();\n";
        
        foreach ($wmap->nodes as $node) {
            echo $node->asJS();
        }
        
        foreach ($wmap->links as $link) {
            echo $link->asJS();
        }
    }
}
