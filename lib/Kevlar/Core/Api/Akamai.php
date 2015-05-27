<?php
use \Kevlar_Api as Api;

/**
 * Akamai CCU provider base
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @link https://api.ccu.akamai.com/ccu/v2/docs/
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Api_Akamai extends Api
{
    /**
     * Pre-request hook
     */
    protected function preRequest()
    {
        curl_setopt($this->curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
    }
}
