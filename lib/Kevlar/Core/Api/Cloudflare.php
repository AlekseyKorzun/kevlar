<?php
use \stdClass;
use \Kevlar_Core_Api as Api;

/**
 * CloudFlare provider base
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @link https://www.cloudflare.com/docs/client-api.html
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Api_Cloudflare extends Api
{
    /**
     * Overwrite JSON flag
     *
     * @var bool
     */
    protected $isJson = false;

    /**
     * CloudFlare email
     *
     * @var string
     */
    protected $email;

    /**
     * CloudFlare API token
     *
     * @link https://www.cloudflare.com/my-account
     * @var string
     */
    protected $token;

    /**
     * CloudFlare domain
     *
     * @var string
     */
    protected $domain;

    /**
     * Set CloudFlare token
     *
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * Set CloudFlare email
     *
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Set CloudFlare domain
     *
     * @param string $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * Retrieve response body and decode it.
     *
     * @return stdClass
     */
    public function getBody()
    {
        $body = json_decode(parent::getBody());
        if (!$body) {
            $body = parent::getBody();
        }

        return $body;
    }
}
