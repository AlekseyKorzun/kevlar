<?php
use \Kevlar_Core_Helper_Factory as Factory;
use \Kevlar_Core_Helper_Factory_Worker as Worker;
use \Kevlar_Core_Helper_Factory_Worker_Varnish_Pool as Pool;

/**
 * Kevlar Varnish factory worker
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Helper_Factory_Worker_Varnish extends Worker
{
    /**
     * Retrieve client associated with this worker
     *
     * @return Kevlar_Core_Helper_Factory_Worker_Varnish_Pool
     */
    protected function client()
    {
        if (!$this->client) {
            $this->client = new Pool(
                Factory::configuration()->providers->{$this->type}->domains
            );
            $this->client->setEnvironment(Factory::environment());
        }

        return $this->client;
    }

    /**
     * Retrieve array of properly formatted objects
     *
     * @return string[]
     */
    public function getUrls()
    {
        return $this->getObjects();
    }

    /**
     * Calculate limit
     *
     * @throws Exception
     * @return int
     */
    public function getLimit()
    {
        $configuration = Factory::configuration()->providers->{$this->getType()};

        return (int)$configuration->limit;
    }

    /**
     * Retrieve estimated time
     *
     * @return int
     */
    public function getEstimate()
    {
        return (
            (count($this->objects) + count($this->client())) * 2
        );
    }
}
