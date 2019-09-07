<?php namespace October\Rain\Network;

class Port
{
    const STATE_OPEN = 'open';
    const STATE_CLOSED = 'closed';

    /** @var int */
    private $number;

    /** @var string */
    private $protocol;

    /** @var string */
    private $state;

    /** @var Service */
    private $service;

    /**
     * Port constructor.
     *
     * @param int     $number
     * @param string  $protocol
     * @param string  $state
     * @param Service $service
     */
    public function __construct(int $number, string $protocol, string $state, Service $service)
    {
        $this->setNumber($number);
        $this->setProtocol($protocol);
        $this->setState($state);
        $this->setSservice($service);
    }

    /**
     * Create the object with common properties
     * @param int     $number
     * @param string  $protocol
     * @param string  $state
     * @param Service $service
     * @return self
     */
    public static function make(int $number, string $protocol, string $state, Service $service)
    {
        return new self($number, $protocol, $state, $service);
    }

    /**
     * @return integer
     */
    public function number()
    {
        return $this->number;
    }

    /**
     * @param int $number
     * @return self
     */
    public function setNumber(int $number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @return string
     */
    public function protocol()
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     * @return self
     */
    public function setProtocol(string $protocol)
    {
        $this->protocol = $protocol;

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
     * @return boolean
     */
    public function isOpen()
    {
        return self::STATE_OPEN === $this->state;
    }

    /**
     * @return boolean
     */
    public function isClosed()
    {
        return self::STATE_CLOSED === $this->state;
    }

    /**
     * @return Service
     */
    public function service()
    {
        return $this->service;
    }

    /**
     * @param Service $service
     * @return self
     */
    public function setService(Service $service)
    {
        $this->service = $service;

        return $this;
    }
}
