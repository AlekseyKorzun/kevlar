<?php

/**
 * Resize helper
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Helper_Resize extends Mage_Core_Helper_Abstract
{
    /**
     * Resizes an image into the desired sizes
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string[] $specifications image specifications
     * @param string $file
     */
    public function resizeImageToArraySizes(Mage_Catalog_Model_Product $product, array $specifications, $file = null)
    {
        foreach ($specifications as $type => $variants) {
            foreach ($variants as $variant => $sizes) {
                foreach ($sizes as $size) {
                    // Initialize catalog image helper
                    $helper = Mage::helper('catalog/image')->init(
                        $product,
                        $type,
                        $file
                    );

                    list($width, $height) = explode('x', $size);

                    if (!$height) {
                        $height = null;
                    }

                    // Remove frame
                    if ($variant === 'frame') {
                        $helper->keepFrame(false);
                    }

                    $helper->resize($width, $height)->__toString();
                }
            }
        }
    }
}
