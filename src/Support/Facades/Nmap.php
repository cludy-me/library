<?php namespace October\Rain\Support\Facades;

use October\Rain\Support\Facade;

/**
 * Network Nmap Facade
 *
 * @package october\support
 * @author Alexey Bobkov, Samuel Georges
 */
class Nmap extends Facade
{
    /**
     * Get the registered name of the component.
     * 
     * Resolves to:
     * - October\Rain\Network\Nmap
     * 
     * @return string
     */
    protected static function getFacadeAccessor() { return 'network.nmap'; }
}
