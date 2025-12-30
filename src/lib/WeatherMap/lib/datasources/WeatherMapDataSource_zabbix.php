<?php
/*
 * WeatherMap datasource plugin: Zabbix (DB-backed)
 *
 * Recognises TARGET strings beginning with "zabbix:".
 * Data is fetched using the application's DataSourceService (data_sources table).
 *
 * This is a compatibility wrapper for the classic WeatherMap plugin loader
 * (expects class WeatherMapDataSource_* with methods Recognise/ReadData).
 */

class WeatherMapDataSource_zabbix extends WeatherMapDataSource
{
    private ?\App\Core\Database $db = null;
    private ?\App\Services\DataSourceService $svc = null;

    function Init(&$map)
    {
        // Prefer application DB-backed service.
        // We avoid ENV-based Zabbix config entirely.
        try {
            if (defined('APP_ROOT')) {
                $autoload = APP_ROOT . '/vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                }
            }

            if (class_exists('App\\Core\\Config') && class_exists('App\\Core\\Database') && class_exists('App\\Services\\DataSourceService')) {
                $cfg = new \App\Core\Config();
                $this->db = new \App\Core\Database($cfg);
                $this->svc = new \App\Services\DataSourceService($this->db);
                return true;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return false;
    }

    function Recognise($targetstring)
    {
        return (preg_match('/^zabbix:/i', (string) $targetstring) === 1);
    }

    function Register($targetstring, &$map, &$item)
    {
        // no-op (no batching yet)
    }

    function Prefetch()
    {
        // no-op (could batch in future)
    }

    function ReadData($targetstring, &$map, &$item)
    {
        $data_time = time();

        if (!$this->svc) {
            return array(NULL, NULL, $data_time);
        }

        // If the item has a structured datasource selection, prefer that.
        if (isset($item->hints) && is_array($item->hints) && isset($item->hints['datasource'])) {
            $sel = json_decode((string) $item->hints['datasource'], true);
            if (is_array($sel) && !empty($sel['sourceId'])) {
                $sourceId = (int) $sel['sourceId'];
                $current = $this->svc->getLinkBandwidthCurrent($sourceId, $sel);

                if (isset($current['in']) && isset($current['out'])) {
                    return array((float) $current['in'], (float) $current['out'], $data_time);
                }
            }
        }

        // Fallback: return no data (so map can still render).
        return array(NULL, NULL, $data_time);
    }
}
