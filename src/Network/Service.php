<?php namespace October\Rain\Network;

class Service
{
    /** @var string */
    private $name;

    /** @var string */
    private $product;

    /** @var string */
    private $version;

    /**
     * Service constructor.
     *
     * @param string $name
     * @param string $product
     * @param string $version
     */
    public function __construct(string $name, string $product, string $version)
    {
        $this->setName($name);
        $this->setProduct($product);
        $this->setVersion($version);
    }

    /**
     * Create the object with common properties
     * @param string $name
     * @param string $product
     * @param string $version
     * @return self
     */
    public static function make(string $name, string $product, string $version)
    {
        return new self($name, $product, $version);
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function product()
    {
        return $this->product;
    }

    /**
     * @param string $product
     * @return self
     */
    public function setProduct(string $product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return string
     */
    public function version()
    {
        return $this->version;
    }

    /**
     * @param string $version
     * @return self
     */
    public function setVersion(string $version)
    {
        $this->version = $version;

        return $this;
    }
}
