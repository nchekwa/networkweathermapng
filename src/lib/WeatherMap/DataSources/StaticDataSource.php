<?php
/**
 * Zabbix Weathermap - Static Data Source
 * 
 * Returns static values for testing or fixed displays
 * 
 * TARGET formats:
 * - static:100
 * - static:100:200
 */

declare(strict_types=1);

namespace WeatherMap\DataSources;

class StaticDataSource implements DataSourceInterface
{
    public function init(object $map): bool
    {
        return true;
    }
    
    public function recognise(string $targetString): bool
    {
        return preg_match('/^static:/i', $targetString) === 1;
    }
    
    public function readData(string $targetString, object $map, object $item): array
    {
        // Remove 'static:' prefix
        $values = substr($targetString, 7);
        $parts = explode(':', $values);
        
        $inValue = (float) ($parts[0] ?? 0);
        $outValue = (float) ($parts[1] ?? $inValue);
        
        return [$inValue, $outValue];
    }
    
    public function register(string $targetString, object $map, object $item): void
    {
        // No registration needed for static values
    }
    
    public function prefetch(): void
    {
        // No prefetch needed for static values
    }
}
