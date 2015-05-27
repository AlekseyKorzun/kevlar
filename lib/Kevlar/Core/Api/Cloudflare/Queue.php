<?php
use \Kevlar_Core_Api_Cloudflare as Cloudflare;

/**
 * CloudFlare provider queue
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Api_Cloudflare_Queue extends Cloudflare
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
            foreach ($objects as $object) {

                $this->post(
                    null,
                    array(
                        'a' => 'zone_file_purge',
                        'tkn' => $this->token,
                        'email' => $this->email,
                        'z' => $this->domain,
                        'url' => $object
                    )
                );

                if ($this->isSuccess()) {
                    continue;
                }


                return false;
            }

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
