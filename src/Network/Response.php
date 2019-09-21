<?php namespace October\Rain\Network;

use Illuminate\Http\Response as ResponseBase;

class Response extends ResponseBase
{

    /** @var array */
    private $info;

    /** @var string */
    private $exitIP;


    /**
     * Factory method for chainability.
     *
     * Example:
     *
     *     return Response::create($body, 200)
     *         ->setSharedMaxAge(300);
     *
     * @param mixed $content The response content, see setContent()
     * @param int   $status  The response status code
     * @param array $headers An array of response headers
     * @param array $info    An array of response info
     *
     * @return self
     */
    public static function create($content = '', $status = 200, $headers = array(), $info = array())
    {
        $response = parent::create($content, $status, $headers);
        $response->setInfo($info);

        return $response;
    }

    /**
     * @return string
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * @param array $info
     * @return self
     */
    public function setInfo($info)
    {
        $this->info = $info;

        return $this;
    }

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