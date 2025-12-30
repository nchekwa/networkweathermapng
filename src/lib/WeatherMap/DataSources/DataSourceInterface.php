<?php
/**
 * Zabbix Weathermap - Data Source Interface
 */

declare(strict_types=1);

namespace WeatherMap\DataSources;

interface DataSourceInterface
{
    /**
     * Initialize the data source
     * 
     * @param object $map The WeatherMap instance
     * @return bool True if initialization successful
     */
    public function init(object $map): bool;
    
    /**
     * Check if this data source can handle the given target string
     * 
     * @param string $targetString The TARGET string from config
     * @return bool True if this data source handles this target
     */
    public function recognise(string $targetString): bool;
    
    /**
     * Read data from the source
     * 
     * @param string $targetString The TARGET string
     * @param object $map The WeatherMap instance
     * @param object $item The map item (node or link)
     * @return array [in_value, out_value] or [-1, -1] on error
     */
    public function readData(string $targetString, object $map, object $item): array;
    
    /**
     * Pre-register a target for batch processing
     * 
     * @param string $targetString The TARGET string
     * @param object $map The WeatherMap instance
     * @param object $item The map item
     */
    public function register(string $targetString, object $map, object $item): void;
    
    /**
     * Prefetch all registered targets
     */
    public function prefetch(): void;
}
