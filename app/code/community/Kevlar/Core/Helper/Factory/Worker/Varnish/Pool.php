<?php
use \Exception;
use \ArrayObject;
use \Kevlar_Core_Api_Varnish_Queue as Api;

/**
 * Kevlar Varnish worker pool
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Helper_Factory_Worker_Varnish_Pool extends ArrayObject
{
    /**
     * Local instance of configuration
     *
     * @var string[]
     */
    protected $pool;

    /**
     * Pool storage
     *
     * @var string[]
     */
    protected $storage = array();

    /**
     * Pool constructor
     */
    public function __construct(array $pool)
    {
        $this->pool = $pool;
    }

    /**
     * Initialization routine
     *
     * @throws Exception
     */
    protected function initialize()
    {
        if (!$this->count()) {
            foreach ((array)$this->pool as $server) {
                $server = (string)$server->live;
                if (!$server) {
                    continue;
                }

                $this->append(
                    new Api($server)
                );
            }
        }

        $this->storage = array();
    }

    /**
     * Purge objects using servers in this pool
     *
     * @param string[] $objects
     * @return bool
     */
    public function purge(array $objects)
    {
        $this->initialize();

        try {
            foreach ($this as $server) {
                $isPurged = $server->purge($objects);

                // Store headers and responses for later
                $this->storage[] = array(
                    'header' => $server->getHeader(),
                    'body' => $server->getBody(),
                    'error' => $server->getClientError()
                );

                if (!$isPurged) {
                    return false;
                }
            }
        } catch (Exception $exception) {
            // Record error
            $this->storage[] = array(
                'error' => $server->getClientError()
            );

            return false;
        }

        return true;
    }

    /**
     * Ping url
     *
     * @param string $url
     */
    public function ping($url)
    {
        $this->initialize();

        if ($this->offsetExists(0)) {
            $this->offsetGet(0)->ping($url);
        }
    }

    /**
     * Set target environment for our pool
     *
     * @param string $environment
     */
    public function setEnvironment($environment)
    {
        $this->initialize();

        foreach ($this as $server) {
            $server->setEnvironment($environment);
        }
    }

    /**
     * Get client error information across our pool
     *
     * @return string
     */
    public function getClientError()
    {
        $error = null;
        foreach ($this->storage() as $result) {
            $error .= $result['error'];
        }

        return $error;
    }

    /**
     * Get header information across our pool
     *
     * @return string
     */
    public function getHeader()
    {
        $header = null;
        foreach ($this->storage() as $result) {
            $header .= $result['header'];
        }

        return $header;
    }

    /**
     * Get body information across our pool
     *
     * @return string
     */
    public function getBody()
    {
        $body = null;
        foreach ($this->storage() as $result) {
            $body .= $result['body'];
        }

        return $body;
    }

    /**
     * Retrieve storage
     *
     * @return string[]
     */
    public function storage()
    {
        return $this->storage;
    }
}
