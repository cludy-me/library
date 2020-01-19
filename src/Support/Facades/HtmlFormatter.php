<?php namespace October\Rain\Support\Facades;

use October\Rain\Support\Facade;

class HtmlFormatter extends Facade
{
    /**
     * Get the registered name of the component.
     * 
     * Resolves to:
     * - October\Rain\Html\HtmlFormatter
     * 
     * @return string
     */
    protected static function getFacadeAccessor() { return 'html.formatter'; }
}
