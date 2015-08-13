<?php
use \Kevlar_Core_Helper_Factory as Factory;
use \Kevlar_Core_Helper_Factory_Worker as Worker;
use \Kevlar_Core_Api_Cloudflare_Queue as Api;

/**
 * Kevlar Cloudflare factory worker
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Helper_Factory_Worker_Cloudflare extends Worker
{
    /**
     * Retrieve client associated with this worker
     *
     * @return Kevlar_Core_Cloudflare_Queue
     */
    protected function client()
    {
        if (!$this->client) {
            $configuration = Factory::configuration()->providers->{$this->type};

            $this->client = new Api($configuration->server);
            $this->client->setDomain($configuration->domain);
            $this->client->setEmail($configuration->email);
            $this->client->setToken($configuration->token);
            $this->client->setEnvironment(Factory::environment());
        }

        return $this->client;
    }

    /**
     * Retrieve estimated time
     *
     * @return string
     */
    public function getEstimate()
    {
        return (
            count($this->objects) * 2
        );
    }
}
