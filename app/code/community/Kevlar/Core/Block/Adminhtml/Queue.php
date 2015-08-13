<?php

/**
 * Admin queue page block
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Block_Adminhtml_Queue extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_controller = 'adminhtml_queue';
        $this->_blockGroup = 'kevlar';
        $this->_headerText = Mage::helper('kevlar')->__('Kevlar Cache Queue');

        parent::__construct();

        $this->_removeButton('add');
    }
}
