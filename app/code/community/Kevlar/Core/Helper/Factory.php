<?php
use \Exception;

/**
 * Kevlar factory
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Helper_Factory extends Mage_Core_Helper_Abstract
{
    /**
     * Pool of factory workers
     *
     * @var object[]
     */
    protected static $workers = array();

    /**
     * Factory constructor
     *
     * @throws Exception
     */
    public function __construct()
    {
        $providers = (array)self::configuration()->providers;

        if ($providers) {
            foreach ($providers as $provider => $configuration) {

                if ((string)$configuration->enabled != '1') {
                    continue;
                }

                $worker = 'Kevlar_Core_Helper_Factory_Worker_' . $provider;

                if (!class_exists($worker)) {
                    continue;
                }

                self::$workers[$provider] = new $worker;
                self::$workers[$provider]->setType($provider);
            }
        }
    }

    /**
     * Retrieve factory workers
     *
     * @return object[]
     */
    public function workers()
    {
        return self::$workers;
    }

    /**
     * Check if factory has workers
     *
     * @return bool
     */
    public function hasWorkers()
    {
        return (bool)count(self::$workers);
    }

    /**
     * Route calls directly to every factory worker
     *
     * @param string $method
     * @param mixed[] $arguments
     */
    public function __call($method, $arguments)
    {
        foreach ($this->workers() as $worker) {
            call_user_func(array($worker, 'flushErrors'));
            call_user_func_array(array($worker, $method), $arguments);
        }
    }

    /**
     * Check if any of the factory workers have errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        foreach ($this->workers() as $worker) {
            if (call_user_func(array($worker, 'hasErrors'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve errors from factory workers
     *
     * @return string[]
     */
    public function errors()
    {
        $errors = array();

        foreach ($this->workers() as $worker) {
            $errors = array_merge(
                $errors,
                call_user_func(array($worker, 'errors'))
            );
        }

        return $errors;
    }

    /**
     * Retrieve estimates from factory workers
     *
     * @return string[]
     */
    public function estimates()
    {
        $estimates = array();

        foreach ($this->workers() as $worker) {
            $estimates[] = (int)call_user_func(array($worker, 'getEstimate'));
        }

        return $estimates;
    }

    /**
     * Retrieve configuration
     *
     * @throws Exception
     * @return Mage_Core_Model_Config_Element
     */
    public static function configuration()
    {
        $configuration = Mage::getConfig()->getNode('kevlar');
        if (!$configuration) {
            throw new Exception(
                'Unable to retrieve Kevlar configuration.'
            );
        }

        return $configuration;
    }

    /**
     * Retrieve application environment
     *
     * @return string
     */
    public static function environment()
    {
        return (string)self::configuration()->environment;
    }

    /**
     * Log messages
     *
     * @param string $message
     */
    public static function log($message)
    {
        if ((string)self::configuration()->log == '1') {
            Mage::log(
                $message,
                Zend_Log::NOTICE,
                'kevlar.log'
            );
        }
    }

    /**
     * Notify contact
     *
     * @throws Exception
     * @return bool
     */
    public static function notify($subject, $text)
    {
        $email = (string)self::configuration()->email;
        if (!$email) {
            return false;
        }

        $reply = (string)self::configuration()->reply;

        $mail = Mage::getModel('core/email');
        $mail->setToEmail($email);
        $mail->setSubject($subject);
        $mail->setBody($text);
        if ($reply) {
            $mail->setFromEmail($reply);
        }
        $mail->setFromName('Kevlar');
        $mail->setType('text');

        try {
            return (bool)$mail->send();
        } catch (Exception $exception) {
        }

        return false;
    }
}
