<?php

/**
 * Queue controller
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Adminhtml_Kevlar_QueueController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Initialization
     *
     * @return Kevlar_Core_Adminhtml_Kevlar_QueueController
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('kevlar/queue')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Kevlar'),
                Mage::helper('adminhtml')->__('Queue')
            );

        return $this;
    }

    /**
     * Queue page
     */
    public function indexAction()
    {
        $this->_initAction();
        $this->_addContent(
            $this->getLayout()->createBlock('kevlar/adminhtml_queue')
        );
        $this->renderLayout();
    }
}
