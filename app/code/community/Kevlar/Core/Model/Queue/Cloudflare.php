<?php
include_once('Mage/Core/Model/Abstract.php');

/**
 * Cloudflare cache queue model
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Model_Queue_Cloudflare extends Kevlar_Core_Model_Queue
{
    /**
     * Get queued up items
     *
     * @return Varien_Data_Collection_Db
     */
    public function getAll()
    {
        return parent::getAll()->addFilter('type', 'cloudflare');
    }

    /**
     * Get pending queue items
     *
     * @return Varien_Data_Collection_Db
     */
    public function getPending()
    {
        return parent::getPending()
            ->addFilter('type', 'cloudflare')
            ->setPageSize(100)
            ->setCurPage(1);
    }

    /**
     * Get queue items that has been processed
     *
     * @return Varien_Data_Collection_Db
     */
    public function getProcessed()
    {
        return parent::getProcessed()->addFilter('type', 'cloudflare');
    }
}
