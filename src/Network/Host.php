<?php namespace October\Rain\Network;

use October\Rain\Support\Collection;

class Host
{
    const STATE_UP = 'up';
    const STATE_DOWN = 'down';

    /** @var string */
    private $address;

    /** @var string */
    private $state;

    /** @var Collection */
    private $ports;

    /** @var string */
    private $os;

    /**
     * Host constructor.
     *
     * @param string           $address
     * @param string           $state
     * @param array|Collection $ports
     * @param string           $os
     */
    public function __construct(string $address, string $state, $ports, string $os)
    {
        $this->setAddress($address);
        $this->setState($state);
        $this->setPorts($ports);
        $this->setOs($os);
    }

    /**
     * Create the object with common properties
     * @param string           $address
     * @param string           $state
     * @param array|Collection $ports
     * @param string           $os
     * @return self
     */
    public static function make(string $address, string $state, $ports, string $os)
    {
        return new self($address, $state, $ports, $os);
    }

    /**
     * @return string
     */
    public function address()
    {
        return $this->address;
    }

    /**
     * @param string $address
     * @return self
     */
    public function setAddress(string $address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return string
     */
    public function state()
    {
        return $this->state;
    }

    /**
     * @param string $state
     * @return self
     */
    public function setState(string $state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return Collection
     */
    public function ports()
    {
        return $this->ports;
    }

    /**
     * @param array|Collection $ports
     * @return self
     */
    public function setPorts($ports)
    {
        $this->ports = ($ports instanceof Collection) ? $ports : collect($ports);

        return $this;
    }

    /**
     * @return string
     */
    public function os()
    {
        return $this->os;
    }

    /**
     * @param string $os
     * @return self
     */
    public function setOs(string $os)
    {
        $this->os = $os;

        return $this;
    }

    /**
     * @return Collection
     */
    public function openPorts()
    {
        return $this->ports->filter(function ($port) {
            /** @var $port Port */
            return $port->isOpen();
        });
    }

    /**
     * @return Collection
     */
    public function closedPorts()
    {
        return $this->ports->filter(function ($port) {
            /** @var $port Port */
            return $port->isClosed();
        });
    }
}
