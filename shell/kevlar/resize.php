<?php
use \Exception;

/**
 * Product image cache generator
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */

set_time_limit(86400);
error_reporting(E_ALL);

try {
    $file = dirname(dirname(__DIR__)) . '/app/Mage.php';

    if (!file_exists($file)) {
        print 'Application was not found, please check your path';
        exit(1);
    }

    require_once $file;

    // Check if Magento was installed
    if (!Mage::isInstalled()) {
        throw new Exception(
            'Application is not installed yet, please complete install wizard first'
        );
    }

    umask(0);

    Mage::app('default');

    $collection = Mage::getModel('catalog/product')->getCollection()
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToSelect('id');

    $i = 0;
    $count = $collection->count();
    foreach ($collection->getAllIds() as $id) {
        $product = Mage::getModel('catalog/product')->load($id);

        if (!$product) {
            continue;
        }

        print "[*] Working on $resource: " . $id . " (" . $i . " of " . $count . ")\n";

        if (Kevlar_Core_Model_Observer::catalogProductImageSave($product)) {
            ++$i;
            continue;
        }

        print "[!] Can't process this product\n";
    }

} catch (Exception $exception) {
    print $exception->getMessage() . "\n";
    exit(1);
}
