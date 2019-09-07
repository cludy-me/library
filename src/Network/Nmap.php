<?php namespace October\Rain\Network;

use October\Rain\Support\Collection;
use Symfony\Component\Process\Process;

/**
 * Nmap
 *
 * Used as a Nmap wrapper for network scan.
 *
 * @package october\network
 * @author Alexey Bobkov, Samuel Georges
 *
 * Usage:
 * 
 *   Nmap::scan('http://octobercms.com');
 *
 *   Nmap::scan('http://octobercms.com', function($nmap) {
 *
 *       // Enable os detection
 *       $nmap->enableOsDetection();
 *
 *       // Disable reverse dns
 *       $nmap->disableReverseDNS();
 *
 *   });
 *
 */

class Nmap
{
    /** @var string */
    private $outputFile;

    /** @var bool */
    private $enableOsDetection = false;

    /** @var bool */
    private $enableServiceInfo = true;

    /** @var bool */
    private $enableVerbose = true;

    /** @var bool */
    private $disablePortScan = false;

    /** @var bool */
    private $disableReverseDNS = false;

    /** @var bool */
    private $treatHostsAsOnline = true;

    /** @var string */
    private $executable = '/usr/bin/nmap';

    /**
     * @param string $outputFile
     * @throws \InvalidArgumentException
     */
    public function __construct($outputFile = null)
    {
        $this->outputFile = $outputFile ?: sys_get_temp_dir() . '/nmap.xml';

        // If executor returns anything else than 0 (success exit code), throw an exeption since $executable is not executable.
        if ($this->execute($this->executable . ' -h') !== 0) {
            throw new \InvalidArgumentException(sprintf('`%s` is not executable.', $this->executable));
        }
    }

    /**
     * @param string|array $targets
     * @param array        $ports
     * @param callable     $callback Callable helper function to modify the object
     * @return Collection
     */
    public static function scan($targets, array $ports = [], $callback = null)
    {
        $nmap = new self;
        
        if ($callback && is_callable($callback)) {
            $callback($nmap);
        }

        return $nmap->doScan($targets, $ports);
    }

    /**
     * @param boolean $enable
     * @return self
     */
    public function enableOsDetection($enable = true)
    {
        $this->enableOsDetection = $enable;

        return $this;
    }

    /**
     * @param boolean $enable
     * @return self
     */
    public function enableServiceInfo($enable = true)
    {
        $this->enableServiceInfo = $enable;

        return $this;
    }

    /**
     * @param boolean $enable
     * @return self
     */
    public function enableVerbose($enable = true)
    {
        $this->enableVerbose = $enable;

        return $this;
    }

    /**
     * @param boolean $disable
     * @return self
     */
    public function disablePortScan($disable = true)
    {
        $this->disablePortScan = $disable;

        return $this;
    }

    /**
     * @param boolean $disable
     * @return self
     */
    public function disableReverseDNS($disable = true)
    {
        $this->disableReverseDNS = $disable;

        return $this;
    }

    /**
     * @param boolean $disable
     * @return self
     */
    public function treatHostsAsOnline($disable = true)
    {
        $this->treatHostsAsOnline = $disable;

        return $this;
    }

    /**
     * @param string $outputFile
     * @return self
     */
    public function setOutputFile($outputFile)
    {
        $this->outputFile = $outputFile;

        return $this;
    }

    /**
     * @param string|array $targets
     * @param array        $ports
     * @return Collection
     */
    private function doScan($targets, array $ports = [])
    {
        $options = [];
        if (true === $this->enableOsDetection) {
            $options[] = '-O';
        }

        if (true === $this->enableServiceInfo) {
            $options[] = '-sV';
        }

        if (true === $this->enableVerbose) {
            $options[] = '-v';
        }

        if (true === $this->disablePortScan) {
            $options[] = '-sn';
        } else if (!empty($ports)) {
            $options[] = '-p ' . implode(',', $ports);
        }

        if (true === $this->disableReverseDNS) {
            $options[] = '-n';
        }

        if (true == $this->treatHostsAsOnline) {
            $options[] = '-Pn';
        }

        $options[] = '-oX';
        $command = sprintf(
            '%s %s %s %s',
            $this->executable,
            implode(' ', $options),
            $this->outputFile,
            implode(' ', is_array($targets) ? $targets : (array) $targets)
        );

        $this->execute($command);

        if (!file_exists($this->outputFile)) {
            throw new \RuntimeException(sprintf('Output file not found ("%s")', $this->outputFile));
        }

        return $this->parseOutputFile($this->outputFile);
    }

    /**
     * @param $xmlFile
     * @return Collection
     */
    private function parseOutputFile($xmlFile)
    {
        $xml = simplexml_load_file($xmlFile);

        $hosts = [];
        foreach ($xml->host as $host) {
            /** @var $host \SimpleXMLElement */
            $hosts[] = new Host(
                (string) $host->address->attributes()->addr,
                (string) $host->status->attributes()->state,
                isset($host->ports) ? $this->parsePorts($host->ports->port) : [],
                isset($host->os->osmatch) ? $host->os->osmatch->attributes()->name : ""
            );
        }

        return collect($hosts);
    }

    /**
     * @param \SimpleXMLElement $xmlPorts
     * @return Collection
     */
    private function parsePorts(\SimpleXMLElement $xmlPorts)
    {
        $ports = [];
        foreach ($xmlPorts as $port) {
            /**
             * @var $port \SimpleXMLElement
             */
            $ports[] = new Port(
                (int) $port->attributes()->portid,
                (string) $port->attributes()->protocol,
                (string) $port->state->attributes()->state,
                new Service(
                    (string) $port->service->attributes()->name . (isset($port->service->attributes()->tunnel) ? 's' : ''),
                    (string) $port->service->attributes()->product,
                    (string) $port->service->attributes()->version
                )
            );
        }

        return collect($ports);
    }

    /**
     * @param string $command The command to execute.
     * @return integer
     */
    private function execute($command)
    {
        $process = new Process($command, null, null, null, 600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Failed to execute "%s"' . PHP_EOL . '%s',
                $command,
                $process->getErrorOutput()
            ));
        }

        return $process->getExitCode();
    }
}
