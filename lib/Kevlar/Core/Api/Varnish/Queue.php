<?php
use \Kevlar_Core_Api_Varnish as Varnish;

/**
 * Varnish provider queue
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Api_Varnish_Queue extends Varnish
{
    /**
     * Purge objects from cache
     *
     * @param string[] $objects
     * @return bool
     */
    public function purge(array $objects)
    {
        $processed = count($objects);

        foreach ($objects as $object) {
            $this->resource = $object;
            $this->request();

            if ($this->isSuccess()) {
                --$processed;
            }
        }

        if (!$processed) {
            return true;
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
        $header = explode("\r\n", $this->getHeader());
        if (isset($header[0]) && $header[0] == 'HTTP/1.1 200 Purged') {
            return true;
        }

        return false;
    }

    /**
     * Pre-request hook
     */
    protected function preRequest()
    {
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PURGE');
    }
}
