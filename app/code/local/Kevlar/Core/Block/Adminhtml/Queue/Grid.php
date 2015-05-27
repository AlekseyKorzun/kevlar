<?php
use \DateTime;
use \DateTimeZone;

/**
 * Admin cache queue page grid block
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Block_Adminhtml_Queue_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('kevlarQueueGrid');

        // Initialize default sorting
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Prepare collection
     *
     * @return bool
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('kevlar/queue')
            ->getCollection()
            ->setOrder('id', 'DESC');

        $now = new DateTime();
        $now->setTimeZone(new DateTimeZone('EST'));

        foreach ($collection as $item) {
            // Update time stamp zone
            $created = new DateTime($item->getCreated());
            $created->setTimeZone(new DateTimeZone('EST'));
            $item->setCreated($created->format('Y-m-d g:i:s A'));

            // Pending state and ETA logic
            if ($item->getIsPending() == 1) {
                $item->setEta('Waiting');
            } else {
                if ($item->getIsPending() == 0 && $item->getEta() == '') {
                    $item->setEta('Skipped');
                } else {
                    // Compare ETA to current time
                    $eta = new DateTime($item->getEta());
                    $eta->setTimeZone(new DateTimeZone('EST'));

                    $item->setEta($eta->format('Y-m-d g:i:s A'));

                    // Switch to done upon completion
                    if ($now >= $eta) {
                        $item->setEta('Done');
                    }
                }
            }

            // Combine URI's
            $urls = json_decode($item->getUrl());
            if ($urls) {
                $temporary = null;

                foreach ($urls as $url) {
                    $temporary .= $url . ' ';
                }

                $item->setUrl($temporary);
            }

            $item->setType(
                ucfirst($item->getType())
            );
        }

        // Transfer collection
        $this->setCollection(
            $collection
        );

        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return bool
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'created',
            array(
                'header' => Mage::helper('kevlar')->__('Created'),
                'align' => 'center',
                'width' => '20px',
                'index' => 'created',
            )
        );

        $this->addColumn(
            'type',
            array(
                'header' => Mage::helper('kevlar')->__('Provider'),
                'align' => 'center',
                'width' => '10px',
                'index' => 'type',
            )
        );

        $this->addColumn(
            'url',
            array(
                'header' => Mage::helper('kevlar')->__('Page'),
                'align' => 'center',
                'width' => '50px',
                'index' => 'url',
            )
        );

        $this->addColumn(
            'eta',
            array(
                'header' => Mage::helper('kevlar')->__('ETA'),
                'align' => 'center',
                'width' => '20px',
                'index' => 'eta',
            )
        );

        $this->addColumn(
            'attempt',
            array(
                'header' => Mage::helper('kevlar')->__('Attempts'),
                'align' => 'center',
                'width' => '10px',
                'index' => 'attempt',
            )
        );

        $this->addColumn(
            'is_pending',
            array(
                'header' => Mage::helper('kevlar')->__('Pending'),
                'align' => 'center',
                'width' => '20px',
                'index' => 'is_pending',
                'type' => 'options',
                'options' => array(
                    1 => 'Yes',
                    0 => 'No'
                ),
            )
        );

        return parent::_prepareColumns();
    }
}

