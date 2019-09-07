<?php namespace October\Rain\Network;

/**
 * HTTP Network Access
 *
 * Used as a cURL wrapper for the HTTP protocol.
 *
 * @package october\network
 * @author Alexey Bobkov, Samuel Georges
 *
 * Usage:
 * 
 *   Http::get('http://octobercms.com');
 *   Http::post('...');
 *   Http::delete('...');
 *   Http::patch('...');
 *   Http::put('...');
 *   Http::options('...');
 *
 *   $result = Http::post('http://octobercms.com');
 *   echo $result->content();               // Outputs: <html><head><title>...
 *   echo $result->status();                // Outputs: 200
 *   echo $result->headers['Content-Type']; // Outputs: text/html; charset=UTF-8
 *
 *   Http::post('http://octobercms.com', function($http){
 *
 *       // Sets a HTTP header
 *       $http->header('Rest-Key', '...');
 *
 *       // Set a proxy of type (http, socks4, socks5)
 *       $http->proxy('type', 'host', 'port', 'username', 'password');
 *
 *       // Use basic authentication
 *       $http->auth('user', 'pass');
 *
 *       // Sends data with the request
 *       $http->data('foo', 'bar');
 *       $http->data(['key' => 'value', ...]);
 *
 *       // Disable redirects
 *       $http->noRedirect();
 * 
 *       // Set a user agent
 *       $http->userAgent(Http::makeUserAgent());
 * 
 *       // Check host SSL certificate
 *       $http->verifySSL();
 *
 *       // Sets the timeout duration
 *       $http->timeout(3600);
 *
 *       // Write response to a file
 *       $http->toFile('some/path/to/a/file.txt');
 *
 *       // Sets a cURL option manually
 *       $http->setOption('CURLOPT_SSL_VERIFYHOST', false);
 *
 *   });
 *
 */

class Http
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_DELETE = 'DELETE';
    const METHOD_PATCH = 'PATCH';
    const METHOD_PUT = 'PUT';
    const METHOD_OPTIONS = 'OPTIONS';

    /**
     * @var string The HTTP address to use.
     */
    protected $url;

    /**
     * @var string The method the request should use.
     */
    protected $method;

    /**
     * @var array The headers to be sent with the request.
     */
    protected $headers = [];

    /**
     * @var string The last response body.
     */
    protected $body = '';

    /**
     * @var string The last response body (without headers extracted).
     */
    protected $rawBody = '';

    /**
     * @var array The last returned HTTP code.
     */
    protected $code;

    /**
     * @var array The cURL response information.
     */
    protected $info;

    /**
     * @var array cURL Options.
     */
    protected $requestOptions;

    /**
     * @var array Request data.
     */
    protected $requestData;

    /**
     * @var array Request headers.
     */
    protected $requestHeaders;

    /**
     * @var string Argument separator.
     */
    protected $argumentSeparator = '&';

    /**
     * @var string If writing response to a file, which file to use.
     */
    protected $streamFile;

    /**
     * @var string If writing response to a file, which write filter to apply.
     */
    protected $streamFilter;

    /**
     * @var int The maximum redirects allowed.
     */
    protected $maxRedirects = 10;

    /**
     * @var int Internal counter
     */
    protected $redirectCount = null;

    /**
     * Make the object with common properties
     * @param string   $url     HTTP request address
     * @param string   $method  Request method (GET, POST, PUT, DELETE, etc)
     * @param callable $callback Callable helper function to modify the object
     * @return self
     */
    public static function make($url, $method, $callback = null)
    {
        $http = new self;
        $http->url = $url;
        $http->method = $method;

        if ($callback && is_callable($callback)) {
            $callback($http);
        }

        return $http;
    }

    /**
     * Make a HTTP GET call.
     * @param string $url
     * @param callable $callback Callable helper function to modify the object
     * @return Response
     */
    public static function get($url, $callback = null)
    {
        return self::make($url, self::METHOD_GET, $callback)->send();
    }

    /**
     * Make a HTTP POST call.
     * @param string $url
     * @param callable $callback Callable helper function to modify the object
     * @return Response
     */
    public static function post($url, $callback = null)
    {
        return self::make($url, self::METHOD_POST, $callback)->send();
    }

    /**
     * Make a HTTP DELETE call.
     * @param string $url
     * @param callable $callback Callable helper function to modify the object
     * @return Response
     */
    public static function delete($url, $callback = null)
    {
        return self::make($url, self::METHOD_DELETE, $callback)->send();
    }

    /**
     * Make a HTTP PATCH call.
     * @param string $url
     * @param callable $callback Callable helper function to modify the object
     * @return Response
     */
    public static function patch($url, $callback = null)
    {
        return self::make($url, self::METHOD_PATCH, $callback)->send();
    }

    /**
     * Make a HTTP PUT call.
     * @param string $url
     * @param callable $callback Callable helper function to modify the object
     * @return Response
     */
    public static function put($url, $callback = null)
    {
        return self::make($url, self::METHOD_PUT, $callback)->send();
    }

    /**
     * Make a HTTP OPTIONS call.
     * @param string $url
     * @param callable $callback Callable helper function to modify the object
     * @return Response
     */
    public static function options($url, $callback = null)
    {
        return self::make($url, self::METHOD_OPTIONS, $callback)->send();
    }

    /**
     * Execute the HTTP request.
     * @return Response response
     */
    public function send()
    {
        if (!function_exists('curl_init')) {
            echo 'cURL PHP extension required.'.PHP_EOL;
            exit(1);
        }

        /*
         * Create and execute the cURL Resource
         */
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        if (defined('CURLOPT_FOLLOWLOCATION') && !ini_get('open_basedir')) {
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, $this->maxRedirects);
        }

        if ($this->requestOptions && is_array($this->requestOptions)) {
            curl_setopt_array($curl, $this->requestOptions);
        }

        /*
         * Set request method
         */
        if ($this->method == self::METHOD_POST) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, '');
        }
        elseif ($this->method !== self::METHOD_GET) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->method);
        }

        /*
         * Set request data
         */
        if ($this->requestData) {
            $requestDataQuery = http_build_query($this->requestData, '', $this->argumentSeparator);

            if (in_array($this->method, [self::METHOD_POST, self::METHOD_PATCH, self::METHOD_PUT])) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $requestDataQuery);
            }
            elseif ($this->method == self::METHOD_GET) {
                curl_setopt($curl, CURLOPT_URL, $this->url . '?' . $requestDataQuery);
            }
        }

        /*
         * Set request headers
         */
        if ($this->requestHeaders) {
            $requestHeaders = [];
            foreach ($this->requestHeaders as $key => $value) {
                $requestHeaders[] = $key . ': ' . $value;
            }

            curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeaders);
        }

        /*
         * Handle output to file
         */
        if ($this->streamFile) {
            $stream = fopen($this->streamFile, 'w');
            if ($this->streamFilter) {
                stream_filter_append($stream, $this->streamFilter, STREAM_FILTER_WRITE);
            }
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_FILE, $stream);
            curl_exec($curl);
        }
        /*
         * Handle output to variable
         */
        else {
            $response = $this->rawBody = curl_exec($curl);
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $this->headers = $this->headerToArray(substr($response, 0, $headerSize));
            $this->body = substr($response, $headerSize);
        }

        $this->info = curl_getinfo($curl);
        $this->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        /*
         * Close resources
         */
        curl_close($curl);

        if ($this->streamFile) {
            fclose($stream);
        }

        /*
         * Emulate FOLLOW LOCATION behavior
         */
        if (!defined('CURLOPT_FOLLOWLOCATION') || ini_get('open_basedir')) {
            if ($this->redirectCount === null) {
                $this->redirectCount = $this->maxRedirects;
            }
            if (in_array($this->code, [301, 302])) {
                $this->url = array_get($this->info, 'url');
                if (!empty($this->url) && $this->redirectCount > 0) {
                    $this->redirectCount -= 1;
                    return $this->send();
                }
            }
        }

        return Response::create($this->body, $this->code, $this->headers);
    }

    /**
     * Turn a header string into an array.
     * @param string $header
     * @return array
     */
    protected function headerToArray($header)
    {
        $headers = [];
        $parts = explode("\r\n", $header);
        foreach ($parts as $singleHeader) {
            $delimiter = strpos($singleHeader, ': ');
            if ($delimiter !== false) {
                $key = substr($singleHeader, 0, $delimiter);
                $val = substr($singleHeader, $delimiter + 2);
                $headers[$key] = $val;
            }
            else {
                $delimiter = strpos($singleHeader, ' ');
                if ($delimiter !== false) {
                    $key = substr($singleHeader, 0, $delimiter);
                    $val = substr($singleHeader, $delimiter + 1);
                    $headers[$key] = $val;
                }
            }
        }

        return $headers;
    }

    /**
     * Add a data to the request.
     * @param array|string $key
     * @param string $value
     * @return self
     */
    public function data($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $_key => $_value) {
                $this->data($_key, $_value);
            }
            return $this;
        }

        $this->requestData[$key] = $value;

        return $this;
    }

    /**
     * Add a header to the request.
     * @param array|string $key
     * @param string $value
     * @return self
     */
    public function header($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $_key => $_value) {
                $this->header($_key, $_value);
            }
            return;
        }

        $this->requestHeaders[$key] = $value;

        return $this;
    }

    /**
     * Sets a proxy to use with this request
     * @param string $type
     * @param string $host
     * @param string $port
     * @param string $username
     * @param string $password
     * @return self
     */
    public function proxy($type, $host, $port, $username = null, $password = null)
    {
        if ($type === 'http')
            $this->setOption(CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        elseif ($type === 'socks4')
            $this->setOption(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        elseif ($type === 'socks5')
            $this->setOption(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);

        $this->setOption(CURLOPT_PROXY, $host . ':' . $port);

        if ($username && $password)
            $this->setOption(CURLOPT_PROXYUSERPWD, $username . ':' . $password);

        return $this;
    }

    /**
     * Sets a user agent
     * @param string $userAgent
     * @return self
     */
    public function userAgent($userAgent)
    {
        $this->setOption(CURLOPT_USERAGENT, $userAgent);

        return $this;
    }

    /**
     * Adds authentication to the comms.
     * @param string $user
     * @param string $pass
     * @return self
     */
    public function auth($user, $pass = null)
    {
        if (strpos($user, ':') !== false && !$pass)
            list($user, $pass) = explode(':', $user);

        $this->setOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->setOption(CURLOPT_USERPWD, $user . ':' . $pass);

        return $this;
    }

    /**
     * Disable follow location (redirects)
     * @return self
     */
    public function noRedirect()
    {
        $this->setOption(CURLOPT_FOLLOWLOCATION, false);

        return $this;
    }

    /**
     * Enable SSL verification
     * @return self
     */
    public function verifySSL()
    {
        $this->setOption(CURLOPT_SSL_VERIFYPEER, true);
        $this->setOption(CURLOPT_SSL_VERIFYHOST, true);

        return $this;
    }

    /**
     * Sets the request timeout.
     * @param string $timeout
     * @return self
     */
    public function timeout($timeout)
    {
        $this->setOption(CURLOPT_CONNECTTIMEOUT, $timeout);
        $this->setOption(CURLOPT_TIMEOUT, $timeout);

        return $this;
    }

    /**
     * Write the response to a file
     * @param  string $path   Path to file
     * @param  string $filter Stream filter as listed in stream_get_filters()
     * @return self
     */
    public function toFile($path, $filter = null)
    {
        $this->streamFile = $path;

        if ($filter) {
            $this->streamFilter = $filter;
        }

        return $this;
    }

    /**
     * Add a single option to the request.
     * @param string $option
     * @param string $value
     * @return self
     */
    public function setOption($option, $value = null)
    {
        if (is_array($option)) {
            foreach ($option as $_option => $_value) {
                $this->setOption($_option, $_value);
            }
            return;
        }

        $this->requestOptions[$option] = $value;

        return $this;
    }

    /**
     * Handy if this object is called directly.
     * @return string The last response.
     */
    public function __toString()
    {
        return (string) $this->body;
    }

    /**
     * Make a user agent
     *
     * @param bool $random
     * @return string
     */
    public static function makeUserAgent($random = false)
    {
        if ($random === false) {
            return 'Mozilla/5.0 (Windows NT 6.1; rv:31.0) Gecko/20100101 Firefox/31.0';
        }

        //list of browsers
        $agentBrowser = [
            'Firefox',
            'Safari',
            'Opera',
            'Flock',
            'Internet Explorer',
            'Seamonkey',
            'Konqueror',
            'GoogleBot'
        ];

        //list of operating systems
        $agentOS = [
            'Windows 3.1',
            'Windows 95',
            'Windows 98',
            'Windows 2000',
            'Windows NT',
            'Windows XP',
            'Windows Vista',
            'Redhat Linux',
            'Ubuntu',
            'Fedora',
            'AmigaOS',
            'OS 10.5'
        ];

        //randomly generate UserAgent
        return $agentBrowser[rand(0, 7)] . '/' . rand(1, 8) . '.' . rand(0, 9) . ' (' . $agentOS[rand(0, 11)] . ' ' . rand(1, 7) . '.' . rand(0, 9) . '; en-US;)';
    }
}
