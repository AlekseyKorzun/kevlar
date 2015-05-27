<?php
use \Kevlar_Core_Api_Cloudflare as Cloudflare;

/**
 * CloudFlare provider purge
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Api_Cloudflare_Purge extends Cloudflare
{
    /**
     * Purge site from cache
     *
     * @return bool
     */
    public function purge()
    {
        if (!$this->hasMessages()) {
                $this->post(
                    null,
                    array(
                        'a' => 'fpurge_ts',
                        'tkn' => $this->token,
                        'email' => $this->email,
                        'z' => $this->domain,
                        'v' => 1
                    )
                );

                return (bool) $this->isSuccess();
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
            $body &&
            property_exists($body, 'result') &&
            $body->result == 'success'
        ) {
            return true;
        }

        return false;
    }
}
