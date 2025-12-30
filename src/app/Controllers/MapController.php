<?php
/**
 * Zabbix Weathermap - Map Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MapService;
use App\Services\DataSourceService;

class MapController extends BaseController
{
    private MapService $mapService;
    private DataSourceService $dataSourceService;

    public function __construct(array $context)
    {
        parent::__construct($context);
        $this->mapService = new MapService($this->database, $this->config);
        $this->dataSourceService = new DataSourceService($this->database);
    }
    public function index(array $params): void
    {
        $this->requireAuth();
        
        $groupId = (int) $this->getInput('group_id', 0);
        
        // Get all maps the user can view
        $maps = $this->getMapsForUser($groupId);
        $groups = $this->getMapGroups();
        
        $this->render('maps/index', [
            'maps' => $maps,
            'groups' => $groups,
            'currentGroup' => $groupId,
            'title' => 'Network Weathermaps',
        ]);
    }
    
    public function view(array $params): void
    {
        $this->requireAuth();
        
        $mapId = (int) ($params['id'] ?? 0);
        $fullscreen = (bool) $this->getInput('fullscreen', false);
        $fit = (bool) $this->getInput('fit', false);
        $mode = (string) $this->getInput('mode', '');
        
        if (!$this->auth->canViewMap($mapId)) {
            http_response_code(403);
            echo '<h1>Access Denied</h1>';
            return;
        }
        
        $map = $this->getMap($mapId);
        
        if (!$map) {
            http_response_code(404);
            echo '<h1>Map Not Found</h1>';
            return;
        }
        
        $outputBase = pathinfo($map['config_file'], PATHINFO_FILENAME);
        // Check if HTML output exists
        $htmlFile = $this->config->getOutputPath() . '/' . $outputBase . '.html';
        $mapHtml = '';

        // Only preview mode is interactive (imagemap). All other views are plain PNG.
        // Preview mode requires the HTML imagemap. Generate it on demand.
        if ($mode === 'preview') {
            // Suppress library warnings during render
            ob_start();
            $this->mapService->renderMap($mapId, true);
            ob_end_clean();
        }

        // Fallback: if preview mode is requested but no HTML imagemap was generated, build it in-memory.
        if ($mode === 'preview' && trim($mapHtml) === '') {
            $mapFile = $this->config->getConfigsPath() . '/' . $map['config_file'];
            if (file_exists($mapFile)) {
                // Suppress library warnings during dynamic generation
                ob_start();
                require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';

                $wmap = new \WeatherMap();
                // context='editor' forces SortedImagemap to include ALL areas, even those with no href/infourl.
                // This is required for preview tooltips to work on links that are monitored but have no click action.
                $wmap->context = 'editor';
                $wmap->ReadConfig($mapFile);

                // We want Hover URL (OVERLIBGRAPH) to be available in the generated <area> tags for preview.
                // WeatherMap only emits overlib-related attributes (e.g. data-hover) when HTMLSTYLE is 'overlib'.
                // Force it for preview generation without changing the user's config file.
                $wmap->htmlstyle = 'overlib';

                // DrawMap('null') populates imagemap regions without writing an image file.
                $wmap->DrawMap('null');

                // MakeHTML uses imageuri when provided.
                $wmap->imageuri = '/map/' . $mapId . '/image?t=' . time();
                $mapHtml = $wmap->MakeHTML('weathermap_imap');
                ob_end_clean();

                // Ensure hover works consistently.
                $mapHtml = preg_replace(
                    '/(<area\b[^>]*?)\bnohref\b([^>]*?\/?>)/i',
                    '$1href="#" $2',
                    $mapHtml
                ) ?? $mapHtml;
            }
        }

        // When the image is scaled to fit the browser, imagemap <area> coords would no longer match.
        // In fit mode, render a plain <img> instead.
        // Fullscreen is allowed for preview, as long as fit is disabled.
        if ($fit || $mode !== 'preview') {
            $mapHtml = '';
        }
        if ($fullscreen && $mode === 'preview' && $fit) {
            $mapHtml = '';
        }
        
        $hours = (int) $this->getInput('hours', 4);
        if ($hours <= 0) {
            $hours = 4;
        }
        $defaultRefresh = $fullscreen ? 120 : 0;
        $autoRefresh = (int) $this->getInput('autorefresh', $defaultRefresh);
        if ($autoRefresh < 0) {
            $autoRefresh = 0;
        }

        $this->render('maps/view', [
            'map' => $map,
            'mapHtml' => $mapHtml,
            'fullscreen' => $fullscreen,
            'fit' => $fit,
            'mode' => $mode,
            'hours' => $hours,
            'autoRefresh' => $autoRefresh,
            'title' => $map['title_cache'] ?: $map['name'],
        ], !$fullscreen);
    }

    public function linkGraph(array $params): void
    {
        $mapId = (int) ($params['id'] ?? 0);
        $linkId = (int) ($params['linkId'] ?? 0);

        if ($this->config->isAuthEnabled() && !$this->auth->canViewMap($mapId)) {
            http_response_code(403);
            exit;
        }

        $map = $this->getMap($mapId);
        if (!$map) {
            http_response_code(404);
            exit;
        }

        $mapFile = $this->config->getConfigsPath() . '/' . $map['config_file'];
        if (!file_exists($mapFile)) {
            http_response_code(404);
            exit;
        }

        require_once $this->config->getLibPath() . '/WeatherMap/bootstrap.php';

        $wmap = new \WeatherMap();
        $wmap->context = 'viewer';
        $wmap->ReadConfig($mapFile);

        $targetLink = null;
        foreach ($wmap->links as $link) {
            if ((int) ($link->id ?? 0) === $linkId) {
                $targetLink = $link;
                break;
            }
        }

        if (!$targetLink || !isset($targetLink->hints) || !is_array($targetLink->hints) || empty($targetLink->hints['datasource'])) {
            http_response_code(404);
            exit;
        }

        $selection = json_decode((string) $targetLink->hints['datasource'], true);
        if (!is_array($selection) || empty($selection['sourceId'])) {
            http_response_code(404);
            exit;
        }

        $minutes = (int) $this->getInput('minutes', 1440);
        if ($minutes <= 0) {
            $minutes = 1440;
        }

        // Cache implementation
        $cacheDir = $this->config->getOutputPath() . '/graphs';
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cacheKey = sprintf('map_%d_link_%d_min_%d', $mapId, $linkId, $minutes);
        $cacheFile = $cacheDir . '/' . $cacheKey . '.png';

        // Check cache validity (5 minutes)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) {
            $this->serveImage($cacheFile, 'image/png');
            return;
        }

        $data = $this->dataSourceService->getLinkBandwidthData((int) $selection['sourceId'], $selection, $minutes);
        $png = $this->renderBandwidthGraphPng($data, $minutes);

        // Save to cache
        file_put_contents($cacheFile, $png);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=300');
        echo $png;
        exit;
    }

    private function serveImage(string $path, string $contentType): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }
        
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=300');
        readfile($path);
        exit;
    }

    private function renderBandwidthGraphPng(array $data, int $minutes): string
    {
        $w = 600;
        $h = 300;
        $padL = 75;
        $padR = 20;
        $padT = 35;
        $padB = 50;

        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);

        $bg = imagecolorallocate($im, 255, 255, 255);
        $grid = imagecolorallocate($im, 230, 230, 230);
        $axis = imagecolorallocate($im, 100, 100, 100);
        $inCol = imagecolorallocate($im, 0, 160, 0);     // Green
        $outCol = imagecolorallocate($im, 0, 0, 220);    // Blue
        $txt = imagecolorallocate($im, 50, 50, 50);

        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $bg);

        $inSeries = (array) (($data['in']['series'] ?? []));
        $outSeries = (array) (($data['out']['series'] ?? []));

        $minT = null;
        $maxT = null;
        $maxV = 0.0;

        foreach ([$inSeries, $outSeries] as $series) {
            foreach ($series as $p) {
                $t = (int) ($p['t'] ?? 0);
                $v = (float) ($p['v'] ?? 0);
                if ($minT === null || $t < $minT) {
                    $minT = $t;
                }
                if ($maxT === null || $t > $maxT) {
                    $maxT = $t;
                }
                if ($v > $maxV) {
                    $maxV = $v;
                }
            }
        }

        if ($minT === null || $maxT === null || $maxT <= $minT) {
            imagestring($im, 4, (int)($w/2 - 30), (int)($h/2), 'No Data', $txt);
            ob_start();
            imagepng($im);
            $out = ob_get_clean();
            imagedestroy($im);
            return $out;
        }

        $maxV = $maxV * 1.1; // Add headroom
        if ($maxV <= 0) $maxV = 1.0;

        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;

        // Helper for units
        $formatVal = function($val) use ($data) {
            $unit = $data['in']['units'] ?? '';
            $prefixes = ['', 'K', 'M', 'G', 'T', 'P'];
            $pow = 0;
            while ($val >= 1000 && $pow < count($prefixes) - 1) {
                $val /= 1000;
                $pow++;
            }
            return round($val, 1) . ' ' . $prefixes[$pow] . $unit;
        };

        // grid horizontal (values)
        for ($i = 0; $i <= 4; $i++) {
            $y = (int) ($padT + ($plotH * $i / 4));
            imageline($im, $padL, $y, $padL + $plotW, $y, $grid);
            
            $val = $maxV * (1 - ($i / 4));
            $label = $formatVal($val);
            imagestring($im, 2, 5, $y - 7, str_pad($label, 10, ' ', STR_PAD_LEFT), $txt);
        }
        
        // grid vertical (time)
        for ($i = 0; $i <= 5; $i++) {
            $x = (int) ($padL + ($plotW * $i / 5));
            imageline($im, $x, $padT, $x, $padT + $plotH, $grid);
            
            $tVal = $minT + (($maxT - $minT) * $i / 5);
            $tLabel = date('H:i', (int)$tVal);
            imagestring($im, 2, $x - 12, $padT + $plotH + 5, $tLabel, $txt);
        }

        // axis box
        imagerectangle($im, $padL, $padT, $padL + $plotW, $padT + $plotH, $axis);

        $drawSeries = function(array $series, int $color) use ($im, $padL, $padT, $plotW, $plotH, $minT, $maxT, $maxV): void {
            $prev = null;
            // Sort by time
            usort($series, fn($a, $b) => ((int)$a['t']) <=> ((int)$b['t']));
            
            foreach ($series as $p) {
                $t = (int) ($p['t'] ?? 0);
                $v = (float) ($p['v'] ?? 0);
                $x = (int) round($padL + (($t - $minT) / max(1, ($maxT - $minT))) * $plotW);
                $y = (int) round($padT + (1 - ($v / $maxV)) * $plotH);
                
                if ($prev !== null) {
                    imageline($im, $prev[0], $prev[1], $x, $y, $color);
                    // Bold line
                    imageline($im, $prev[0], $prev[1]+1, $x, $y+1, $color);
                }
                $prev = [$x, $y];
            }
        };

        $drawSeries($outSeries, $outCol);
        $drawSeries($inSeries, $inCol);

        // Title
        $hostName = $data['hostname'] ?? '';
        $itemName = $data['in']['name'] ?? '';
        
        // Clean up interface name
        if (preg_match('/^Interface\s+(.+?):/i', $itemName, $m)) {
            $itemName = $m[1];
        }
        
        $titlePart = $itemName ?: 'Traffic';
        if ($hostName !== '') {
            $titlePart = $hostName . ' - ' . $titlePart;
        }

        $title = $titlePart;
        $fw = imagefontwidth(4) * strlen($title);
        $titleX = (int) (($w - $fw) / 2);
        imagestring($im, 4, $titleX, 8, $title, $txt);
        
        // Legend
        $lx = $padL + (int)($plotW/2) - 80;
        $ly = $h - 25;
        
        // In
        imagefilledrectangle($im, $lx, $ly, $lx+10, $ly+10, $inCol);
        imagestring($im, 3, $lx+16, $ly-2, "Inbound", $txt);
        
        // Out
        $lx += 100;
        imagefilledrectangle($im, $lx, $ly, $lx+10, $ly+10, $outCol);
        imagestring($im, 3, $lx+16, $ly-2, "Outbound", $txt);

        ob_start();
        imagepng($im);
        $out = ob_get_clean();
        imagedestroy($im);
        return $out;
    }
    
    public function image(array $params): void
    {
        $mapId = (int) ($params['id'] ?? 0);
        $fast = ((string) $this->getInput('fast', '0') === '1');
        
        if ($this->config->isAuthEnabled() && !$this->auth->canViewMap($mapId)) {
            http_response_code(403);
            exit;
        }
        
        $map = $this->getMap($mapId);
        
        if (!$map) {
            http_response_code(404);
            exit;
        }
        
        $format = $this->config->get('MAP_OUTPUT_FORMAT', 'png');
        $outputBase = pathinfo($map['config_file'], PATHINFO_FILENAME);
        $suffix = $fast ? '.fast' : '';
        $imageFile = $this->config->getOutputPath() . '/' . $outputBase . $suffix . '.' . $format;

        // In fast mode we want a quick, cached render WITHOUT datasource queries.
        // In full mode keep current behaviour (render on demand).
        $this->mapService->renderMap($mapId, $fast ? false : true, !$fast);
        
        if (!file_exists($imageFile)) {
            error_log("Map image missing, attempting render for map_id={$mapId}, expected={$imageFile}");
            $this->mapService->renderMap($mapId, $fast ? false : true, !$fast);
            error_log("After render: file exists=" . (file_exists($imageFile) ? 'YES' : 'NO'));
            if (!file_exists($imageFile)) {
                http_response_code(404);
                exit;
            }
        }
        
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
        ];
        
        // Ensure nothing else is appended to the binary response (warnings/whitespace/buffers)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }

        header('Content-Type: ' . ($mimeTypes[$format] ?? 'image/png'));
        if ($fast) {
            header('Cache-Control: public, max-age=300');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        header('Content-Length: ' . filesize($imageFile));
        readfile($imageFile);
        exit;
    }
    
    public function thumbnail(array $params): void
    {
        $mapId = (int) ($params['id'] ?? 0);
        $fast = ((string) $this->getInput('fast', '0') === '1');
        
        if ($this->config->isAuthEnabled() && !$this->auth->canViewMap($mapId)) {
            http_response_code(403);
            exit;
        }
        
        $map = $this->getMap($mapId);
        
        if (!$map) {
            http_response_code(404);
            exit;
        }
        
        $format = $this->config->get('MAP_OUTPUT_FORMAT', 'png');
        $outputBase = pathinfo($map['config_file'], PATHINFO_FILENAME);
        $suffix = $fast ? '.fast' : '';
        $thumbFile = $this->config->getOutputPath() . '/' . $outputBase . $suffix . '.thumb.' . $format;
        $imageFile = $this->config->getOutputPath() . '/' . $outputBase . $suffix . '.' . $format;

        // In fast mode we want a quick, cached render WITHOUT datasource queries.
        // In full mode keep current behaviour (render on demand).
        $this->mapService->renderMap($mapId, $fast ? false : true, !$fast);
        
        if (!file_exists($thumbFile)) {
            // Ensure thumbnail exists (render on demand). If thumb still missing, fall back to main image.
            $this->mapService->renderMap($mapId, $fast ? false : true, !$fast);

            if (!file_exists($thumbFile)) {
                // Some older caches might have a main image but no thumbnail. Force a re-render once.
                if (file_exists($imageFile)) {
                    $this->mapService->renderMap($mapId, $fast ? false : true, !$fast);
                }
            }

            if (!file_exists($thumbFile)) {
                $this->image($params);
                return;
            }
        }
        
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
        ];
        
        // Ensure nothing else is appended to the binary response (warnings/whitespace/buffers)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }

        header('Content-Type: ' . ($mimeTypes[$format] ?? 'image/png'));
        if ($fast) {
            header('Cache-Control: public, max-age=300');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        header('Content-Length: ' . filesize($thumbFile));
        readfile($thumbFile);
        exit;
    }
    
    public function cycle(array $params): void
    {
        $this->requireAuth();
        
        $groupId = (int) ($params['group'] ?? 0);
        $fullscreen = (bool) $this->getInput('fullscreen', false);
        
        $maps = $this->getMapsForUser($groupId);
        
        $this->render('maps/cycle', [
            'maps' => $maps,
            'groupId' => $groupId,
            'fullscreen' => $fullscreen,
            'refreshInterval' => (int) $this->config->get('MAP_REFRESH_INTERVAL', 300),
            'title' => 'Map Cycle',
        ]);
    }
    
    private function getMapsForUser(int $groupId = 0): array
    {
        $sql = "SELECT m.*, g.name as group_name 
                FROM maps m 
                LEFT JOIN map_groups g ON m.group_id = g.id 
                WHERE m.active = 1";
        $params = [];
        
        if ($groupId > 0) {
            $sql .= " AND m.group_id = ?";
            $params[] = $groupId;
        }
        
        $sql .= " ORDER BY g.sort_order, m.sort_order, m.name";
        
        $maps = $this->database->query($sql, $params);
        
        // Filter by permissions if auth is enabled
        if ($this->config->isAuthEnabled() && !$this->auth->isAdmin()) {
            $maps = array_filter($maps, fn($map) => $this->auth->canViewMap((int) $map['id']));
        }
        
        return array_values($maps);
    }
    
    private function getMapGroups(): array
    {
        return $this->database->query(
            "SELECT * FROM map_groups ORDER BY sort_order, name"
        );
    }
    
    private function getMap(int $id): ?array
    {
        return $this->database->queryOne(
            "SELECT m.*, g.name as group_name 
             FROM maps m 
             LEFT JOIN map_groups g ON m.group_id = g.id 
             WHERE m.id = ?",
            [$id]
        );
    }
}
