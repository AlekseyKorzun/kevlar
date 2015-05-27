<?php
use \Kevlar_Core_Api_Akamai as Akamai;

/**
 * Akamai CCU provider queue
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Api_Akamai_Queue extends Akamai
{
    /**
     * Purge objects from cache
     *
     * @param string[] $objects
     * @return bool
     */
    public function purge(array $objects)
    {
        if (!$this->hasMessages()) {
            $this->post(
                '/ccu/v2/queues/default',
                array(
                    'objects' => $objects,
                    'domain' => $this->environment
                )
            );

            if ($this->isSuccess()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request was successful
     *
     * @return bool
     */
    public function isSuccess()
    {
        $body = $this->getBody();

        if (
            property_exists($body, 'httpStatus') &&
            $body->httpStatus == '201'
        ) {
            return true;
        }

        return false;
    }
}
