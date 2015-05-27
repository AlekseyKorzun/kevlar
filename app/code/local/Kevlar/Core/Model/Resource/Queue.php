<?php
include_once('Mage/Core/Model/Resource/Db/Abstract.php');

/**
 * Queue resource
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Model_Resource_Queue extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Set main entity table name and primary key field name
     */
    protected function _construct()
    {
        $this->_init('kevlar/queue', 'id');
    }
}
