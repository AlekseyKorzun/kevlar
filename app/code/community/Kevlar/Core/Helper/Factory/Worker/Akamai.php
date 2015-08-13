<?php
use \Kevlar_Core_Helper_Factory as Factory;
use \Kevlar_Core_Helper_Factory_Worker as Worker;
use \Kevlar_Core_Api_Akamai_Queue as Api;

/**
 * Kevlar Akamai factory worker
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Helper_Factory_Worker_Akamai extends Worker
{
    /**
     * Retrieve client associated with this worker
     *
     * @return Kevlar_Core_Api_Akamai_Queue
     */
    protected function client()
    {
        if (!$this->client) {
            $configuration = Factory::configuration()->providers->{$this->type};

            $this->client = new Api($configuration->server);
            $this->client->setUsername($configuration->username);
            $this->client->setPassword($configuration->password);
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
        $body = $this->client()->getBody();

        if (property_exists($body, 'estimatedSeconds')) {
            return ($body->estimatedSeconds / 60);
        }

        return 0;
    }
}
