<?php namespace October\Rain\Network;

use Illuminate\Http\Response as ResponseBase;

class Response extends ResponseBase
{

    /** @var string */
    private $exitIP;

    /**
     * @return string
     */
    public function getExitIP()
    {
        return $this->exitIP;
    }

    /**
     * @param string $exitIP
     * @return self
     */
    public function setExitIP($exitIP)
    {
        $this->exitIP = $exitIP;

        return $this;
    }

    /**
     * Returns the Response as an HTTP string.
     *
     * The string representation of the Response is the same as the
     * one that will be sent to the client only if the prepare() method
     * has been called before.
     *
     * @return string The Response as an HTTP string
     *
     * @see prepare()
     */
    public function __toString()
    {
        $this->headers->set('Remote IP', $this->exitIP);

        return
            sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText) . "\r\n" .
            $this->headers . "\r\n" .
            $this->getContent();
    }
}