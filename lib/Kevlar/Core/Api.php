<?php
use \Exception;

/**
 * Api service provider
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
abstract class Kevlar_Core_Api
{
    /**
     * Production environment
     *
     * @var string
     */
    const ENVIRONMENT_PRODUCTION = 'production';

    /**
     * Staging environment
     *
     * @var string
     */
    const ENVIRONMENT_STAGING = 'staging';

    /**
     * Post method
     *
     * @var int
     */
    const METHOD_POST = 1;

    /**
     * Get method
     *
     * @var int
     */
    const METHOD_GET = 2;

    /**
     * User agent to use during the request
     *
     * @var string
     */
    const USER_AGENT = 'Kevlar v1.0.0';

    /**
     * Environment
     *
     * @var string
     */
    protected $environment;

    /**
     * Flag for JSON encoding
     *
     * @var bool
     */
    protected $isJson = true;

    /**
     * API end point
     *
     * @var string
     */
    protected $server;

    /**
     * Username for API authentication
     *
     * @var string
     */
    protected $username;

    /**
     * Password for API authentication
     *
     * @var string
     */
    protected $password;

    /**
     * Resource
     *
     * @var string
     */
    protected $resource;

    /**
     * Request parameters
     *
     * @var string[]
     */
    protected $request;

    /**
     * Request method
     *
     * @var int
     */
    protected $method;

    /**
     * Number of attempts made to authenticate
     *
     * @var int
     */
    protected $attempts;

    /**
     * Response header
     *
     * @var string
     */
    protected $header;

    /**
     * Response body
     *
     * @var string
     */
    protected $body;

    /**
     * Instance of cURL
     *
     * @var cURL
     */
    protected $curl;

    /**
     * Stores messages
     *
     * @var string[]
     */
    public $messages;

    /**
     * Class constructor
     *
     * @throws Exception
     * @param string $server
     */
    public function __construct($server)
    {
        if (!$server) {
            throw new Exception('Please set server URI for this provider');
        }

        $this->server = $server;

        // Default to staging environment
        $this->setEnvironment(self::ENVIRONMENT_STAGING);
    }

    /**
     * Performs cURL request
     *
     * @throws Exception
     */
    protected function request()
    {
        $resource = $this->resource;

        // Initialize cURL
        $this->curl = curl_init();

        switch ($this->method) {
            case self::METHOD_POST:
                curl_setopt($this->curl, CURLOPT_POST, true);

                if ($this->isJson) {
                    $this->request = json_encode($this->request);
                }

                curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->request);
                break;
            default:
                if ($this->request) {
                    $parameters = array();
                    foreach ($this->request as $request => $value) {
                        $parameters[] = $request . '=' . $value;
                    }

                    $resource .= '?' . implode('&', $parameters);
                }
        }

        // If extending provider hsa preRequest requirement, fulfill it
        if (method_exists($this, 'preRequest')) {
            $this->preRequest();
        }

        if ($this->isJson) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        }

        curl_setopt($this->curl, CURLOPT_URL, $this->server . $resource);
        curl_setopt($this->curl, CURLOPT_HEADER, 1);
        curl_setopt($this->curl, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);

        // Perform request
        $response = curl_exec($this->curl);

        if (substr_count($response, "\r\n\r\n") == 2) {
            $response = substr($response, strpos($response, "\r\n\r\n") + 4, strlen($response));
        }

        list($this->header, $this->body) = explode(
            "\r\n\r\n",
            $response
        );

        if ($this->hasClientError()) {
            throw new Exception(
                'Unable to process this request. cURL error: ' . $this->getClientError()
            );
        }

        if ($this->isJson) {
            $this->body = json_decode($this->body);
        }

        // If extending provider hsa postRequest requirement, fulfill it
        if (method_exists($this, 'postRequest')) {
            $this->postRequest();
        }

        curl_close($this->curl);
    }

    /**
     * Construct POST request
     *
     * @param string $resource
     * @param string[] $request
     */
    public function post($resource, array $request)
    {
        $this->method = self::METHOD_POST;
        $this->resource = $resource;
        $this->request = $request;
        $this->request();
    }

    /**
     * Construct GET request
     *
     * @param string $resource
     * @param string[] $request
     */
    public function get($resource, array $request)
    {
        $this->method = self::METHOD_GET;
        $this->resource = $resource;
        $this->request = $request;
        $this->request();
    }

    /**
     * Ping URL
     *
     * @param string $url
     */
    public function ping($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * Set username
     *
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Set password
     *
     * @param string $uri
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Set server
     *
     * @param string $uri
     */
    public function setServer($uri)
    {
        $this->server = $uri;
    }

    /**
     * Setup cURL port for connections
     *
     * @param int $port
     */
    public function setPort($port)
    {
        curl_setopt($this->curl, CURLOPT_PORT, (int)$port);
    }

    /**
     * Retrieve header
     *
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Retrieve response body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Add new message
     *
     * @param string $message
     */
    public function addMessage($message)
    {
        $this->messages[] = $message;
    }

    /**
     * Retrieve existing messages and flush them out
     *
     * @return string[]
     */
    public function messages()
    {
        $messages = $this->messages;

        // Reset data
        $this->messages = array();

        return $messages;
    }

    /**
     * Check if we have any messages
     *
     * @return bool
     */
    public function hasMessages()
    {
        return !empty($this->messages);
    }

    /**
     * Check if client thrown an error
     *
     * @return bool
     */
    public function hasClientError()
    {
        if (!is_resource($this->curl)) {
            return false;
        }

        return (bool)curl_errno($this->curl) > 0;
    }

    /**
     * Retrieve client error
     *
     * @return string
     */
    public function getClientError()
    {
        if ($this->hasClientError()) {
            return (string)ucfirst(curl_error($this->curl) . '.');
        }

        return null;
    }

    /**
     * Set target environment for our request
     *
     * @param string $environment
     */
    public function setEnvironment($environment)
    {
        switch ($environment) {
            case self::ENVIRONMENT_STAGING:
            case self::ENVIRONMENT_PRODUCTION:
                $this->environment = $environment;
                break;
            default:
                $this->environment = self::ENVIRONMENT_STAGING;
        }
    }
}
