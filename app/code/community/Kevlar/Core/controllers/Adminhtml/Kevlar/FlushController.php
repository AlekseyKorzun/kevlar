<?php

/**
 * Admin cache flush controller
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Adminhtml_Kevlar_FlushController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Emergency flag
     *
     * @var bool
     */
    protected $isEmergency = false;

    /**
     * Initialization
     *
     * @return Kevlar_Core_Adminhtml_Kevlar_FlushController
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('kevlar/flush')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Kevlar'),
                Mage::helper('adminhtml')->__('Flush')
            );

        return $this;
    }

    /**
     * Cache flush page
     */
    public function indexAction()
    {
        $this->_initAction();


        if ($this->getRequest()->isPost()) {
            // Toggle emergency
            $this->isEmergency = (bool)($this->getRequest()->getPost('is_emergency') == '1');

            $objects = preg_split(
                '/(\r?\n)+/',
                $this->getRequest()->getPost('urls')
            );


            // Add objects to queue
            $factory = new Kevlar_Core_Helper_Factory();
            $factory->setObjects($objects);

            // Emergency flushes by pass internal queue
            if ($this->isEmergency) {
                $factory->purge();

                // Notify parties
                $factory->notify(
                    'Emergency cache flush requested',
                    $this->getRequest()->getPost('urls')
                );
            } else {
                $factory->queue();
            }

            if ($factory->hasErrors()) {
                foreach ($factory->errors() as $error) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__($error)
                    );
                }
            } else {
                if ($this->isEmergency) {
                    $message =
                        'Emergency flush has been completed. Estimated time for provision is: ' .
                        (int)array_sum($factory->estimates()) . ' seconds.';

                } else {
                    $message =
                        'We have queued up your updates, please review cache <a href="' .
                        Mage::helper('adminhtml')->getUrl('adminhtml/kevlar_queue') .
                        '">queue</a> to keep track of the progress.';
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__($message)
                );
            }
        }

        $this->_initLayoutMessages('adminhtml/session');
        $this->renderLayout();
    }
}
