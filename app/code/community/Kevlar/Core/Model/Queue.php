<?php
include_once('Mage/Core/Model/Abstract.php');

/**
 * Cache queue model
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Model_Queue extends Mage_Core_Model_Abstract
{
    /**
     * Type of queue
     *
     * @var string
     */
    protected $type;

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('kevlar/queue');
    }

    /**
     * Get queued up items
     *
     * @return Varien_Data_Collection_Db
     */
    public function getAll()
    {
        return Mage::getResourceModel('kevlar/queue_collection');
    }

    /**
     * Get pending queue items
     *
     * @return Varien_Data_Collection_Db
     */
    public function getPending()
    {
        return Mage::getResourceModel('kevlar/queue_collection')
            ->addFilter('is_pending', 1);
    }

    /**
     * Get queue items that has been processed
     *
     * @return Varien_Data_Collection_Db
     */
    public function getProcessed()
    {
        return Mage::getResourceModel('kevlar/queue_collection')
            ->addFilter('is_pending', 0);
    }
}
