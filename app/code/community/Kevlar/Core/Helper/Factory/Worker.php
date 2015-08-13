<?php
use \DateTime;
use \Exception;
use \Kevlar_Core_Helper_Factory as Factory;

/**
 * Kevlar factory worker
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
abstract class Kevlar_Core_Helper_Factory_Worker extends Mage_Core_Helper_Abstract
{
    /**
     * Type of worker
     *
     * @var string
     */
    protected $type;

    /**
     * Worker specific errors
     *
     * @var string[]
     */
    protected $errors = array();

    /**
     * Worker specific objects
     *
     * @var string[]
     */
    protected $objects = array();

    /**
     * Pool of nodes associated with this worker
     *
     * @var Kevlar_Core_Api
     */
    protected $client;

    /**
     * Set workers objects
     *
     * @param string[] $objects
     */
    public function setObjects(array $objects)
    {
        array_walk(
            $objects,
            function (&$value) {
                // Re-format objects
                if (strpos($value, '/') !== 0) {
                    $parsed = parse_url($value);

                    if (isset($parsed['path'])) {
                        $value = $parsed['path'];

                        if (strpos($parsed['path'], '/') !== 0) {
                            $value = '/' . $value;
                        }

                        if (isset($parsed['query'])) {
                            $value = $value . '?' . $parsed['query'];
                        }
                    }
                }

                // Remove wildcards
                if (strpos($value, '*') !== false) {
                    $value = null;
                }
            }
        );

        $this->objects = (array)array_flip(array_flip($objects));
    }

    /**
     * Retrieve objects
     *
     * @return string[]
     */
    public function getObjects()
    {
        return $this->objects;
    }

    /**
     * Retrieve worker type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set worker type
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Queue up current objects
     *
     * @return bool
     */
    public function queue()
    {
        if (!$this->objects) {
            return false;
        }

        foreach ($this->objects as $object) {
            $this->log(
                'Queued: ' . $object
            );
        }

        $date = new DateTime();

        $queue = Mage::getModel('kevlar/queue/' . $this->getType());
        $queue->setCreated($date->format('Y-m-d H:i:s'));
        $queue->setUrl(json_encode($this->objects));
        $queue->setType($this->getType());

        if (!$queue->save()) {
            $this->addError('Failed to queue up objects');
        }
    }

    /**
     * Purge objects using our node pool
     *
     * @return bool
     */
    public function purge()
    {
        if (!$this->objects) {
            return false;
        }

        // Auto warm cache
        if ((string)Factory::configuration()->autoWarm == '1') {
            // Retrieve category views
            $categoryViews = (array)Kevlar_Core_Model_Observer::getCategoryViews();

            foreach ($this->getBackends() as $backend) {
                foreach ($categoryViews as $categoryView) {
                    if (strpos($backend, $categoryView . '.html') !== false) {
                        $this->log(
                            'Skipping warm up of: ' . $backend
                        );

                        continue 2;
                    }
                }

                $this->log(
                    'Attempting to warm up: ' . $backend
                );

                $this->client()->ping($backend);
            }
        }

        foreach ($this->objects as $object) {
            $this->log(
                'Purging: ' . $object
            );
        }

        $result = (bool)$this->client()->purge(
            $this->getUrls()
        );

        if (!$result) {
            $this->addError(
                'Could not complete purge request'
            );

            $header = $this->client()->getHeader();
            if ($header) {
                $this->addError($header);
            }

            $body = $this->client()->getBody();
            if ($body) {
                $this->addError($body);
            }

            $error = $this->client()->getClientError();
            if ($error) {
                $this->addError($error);
            }
        }

        return $result;
    }

    /**
     * Retrieve array of properly formatted objects
     *
     * @throws Exception
     * @return string[]
     */
    public function getUrls()
    {
        $domains = Factory::configuration()->providers->{$this->getType()}->domains;

        if ($domains) {
            $urls = array();

            foreach ((array)$domains as $domain) {
                $domain = (string)$domain->live;
                if ($domain) {
                    foreach ($this->objects as $object) {
                        $urls[] = $domain . $object;
                    }
                }
            }

            return $urls;
        }

        throw new Exception(
            'Provider ' . ucfirst($this->getType()) . ' is improperly configured'
        );
    }

    /**
     * Retrieve array of this workers cache backends
     *
     * @return string[]
     */
    public function getBackends()
    {
        $urls = array();

        $domains = Factory::configuration()->providers->{$this->getType()}->domains;
        if ($domains) {
            foreach ((array)$domains as $domain) {
                $domain = (string)$domain->backend;
                if ($domain) {
                    foreach ($this->objects as $object) {
                        $urls[] = $domain . $object;
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * Retrieve pool of nodes associated with this worker
     *
     * @return Kevlar_Core_Api|Kevlar_Helper_Factory_Worker_Varnish_Pool
     */
    abstract protected function client();

    /**
     * Retrieve estimated time for cache purge to take place
     *
     * @return int
     */
    abstract public function getEstimate();

    /**
     * Calculate limit
     *
     * @throws Exception
     * @return int
     */
    public function getLimit()
    {
        $configuration = Factory::configuration()->providers->{$this->getType()};

        $limit = (int)$configuration->limit;
        $domains = count((array)$configuration->domains);

        if (!$limit) {
            $limit = 100;
        }

        if ($domains > $limit) {
            throw Exception(
                'The amount of domains exceeds providers request limit'
            );
        }

        return (int)round($limit / $domains, 0, PHP_ROUND_HALF_DOWN);
    }

    /**
     * Retrieve pending items associated with this worker
     *
     * @return mixed
     */
    public function getPending()
    {
        return Mage::getModel('kevlar/queue_' . $this->getType())->getPending();
    }

    /**
     * Retrieve processed items associated with this worker
     *
     * @return mixed
     */
    public function getProcessed()
    {
        return Mage::getModel('kevlar/queue_' . $this->getType())->getProcessed();
    }

    /**
     * Check if worker has errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        return (bool)count($this->errors);
    }

    /**
     * Flush workers errors
     */
    public function flushErrors()
    {
        $this->errors = array();
    }

    /**
     * Retrieve worker errors
     *
     * @return string[]
     */
    public function errors()
    {
        return (array)$this->errors;
    }

    /**
     * Add new error for this worker
     *
     * @param string $message
     */
    public function addError($message)
    {
        // Handle JSON
        if (is_object($message)) {
            $message = json_encode($message);
        }

        $this->log('Error: ' . $message);

        $this->errors[] = $message;
    }

    /**
     * Log
     *
     * @param string $message
     */
    public function log($message)
    {
        Factory::log(
            '[' . ucfirst($this->getType()) . '] ' . $message
        );
    }
}
