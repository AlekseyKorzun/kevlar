<?php

/**
 * Cache observer
 *
 * @category Kevlar
 * @package Kevlar_Core
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Model_Observer extends Varien_Object
{
    /**
     * List of URLS that we need to flush
     *
     * @var string[]
     */
    protected $urls = array();

    /**
     * Supported product image sizes
     *
     * @var string[]
     */
    protected static $productImageSizes = array(
        'small_image' => array(
            'original' => array(
                '85x',
                '114x151',
                '230x306',
                '320x',
                '480x642',
                '960x1284',
                '1248x1669'
            )
        ),
        'thumbnail' => array(
            'original' => array(
                '114x151'
            )
        )
    );

    /**
     * Supported child product image sizes
     *
     * @var string[]
     */
    protected static $productChildImageSizes = array(
        'small_image' => array(
            'original' => array(
                '85x',
                '114x151',
                '230x306',
                '320x',
                '480x642',
                '960x1284',
                '1248x1669'
            )
        ),
        'thumbnail' => array(
            'original' => array(
                '114x151'
            )
        )
    );

    /**
     * Triggers cache update when block is edited
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function cmsBlockSave(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();

        $isFlushed = $block->getFlush();
        if ($isFlushed != '1') {
            return true;
        }

        $categoryCollection = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('url_key')
            ->addUrlRewriteToResult()
            ->addIsActiveFilter();

        foreach ($categoryCollection as $category) {
            $this->extractCategoryUrl($category);
        }

        $this->urls[] = '';

        return $this->purge();
    }

    /**
     * Triggers cache update when page revision is published
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function cmsPageRevisionPublish(Varien_Event_Observer $observer)
    {
        $pageUrl = Mage::helper('cms/page')->getPageUrl($observer->getPageId());

        $this->urls[] = str_replace(
            'index.php/',
            null,
            substr($pageUrl, strpos($pageUrl, 'index.php/'), strlen($pageUrl))
        );

        return $this->purge();
    }

    /**
     * Triggers cache update when product is saved
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function catalogProductSave(Varien_Event_Observer $observer)
    {
        $products = $observer->getProduct();

        // Detect multiple products
        if (!is_array($products)) {
            $products = array($products);
        }

        if (!$products) {
            return false;
        }

        foreach ($products as $product) {
            // Convert numeric product ids into models
            if (is_numeric($product)) {
                $product = Mage::getModel('catalog/product')->load($product);
            }

            $this->urls[] = $product->getUrlKey();

            // Retrieve categories associated with this product
            $productCategoryIds = $product->getCategoryIds();
            if ($productCategoryIds) {
                // Extract and purge category URI's
                $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('url_key')
                    ->addAttributeToFilter('entity_id', $productCategoryIds)
                    ->addUrlRewriteToResult()
                    ->addIsActiveFilter();

                foreach ($categoryCollection as $category) {
                    // Process category URLs
                    $this->extractCategoryUrl($category);
                }
            }
        }

        return $this->purge();
    }

    /**
     * Triggers cache update when page revision is published
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function catalogCategorySave(Varien_Event_Observer $observer)
    {
        $category = $observer->getCategory();

        $this->extractCategoryUrl($category);

        return $this->purge();
    }

    /**
     * Triggers product and category updates when item becomes sold out
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function afterOrderSave(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return false;
        }

        foreach ($order->getItemsCollection() as $item) {
            $product = $item->getProduct();

            // Skip invalid products
            if (!$product instanceof Mage_Catalog_Model_Product) {
                return false;
            }

            if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                return false;
            }

            // Retrieve stock for the product in the cart
            $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

            if ($stock->getQty() < 1) {
                Mage::dispatchEvent(
                    'catalog_product_save',
                    array(
                        'product' => $product->getId()
                    )
                );
            }
        }

        return true;
    }

    /**
     * Purge cache
     *
     * @return bool
     */
    protected function purge()
    {
        array_walk(
            $this->urls,
            function (&$url, $key, $domain) {
                if ($url && (substr($url, -5) !== '.html')) {
                    $url .= '.html';
                }

                $url = $domain . $url;
            },
            '/'
        );

        // Add urls to purge queue
        $factory = new Kevlar_Core_Helper_Factory();
        $factory->setObjects(array_unique($this->urls));
        $factory->queue();

        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('adminhtml')->__(
                'We have queued up your updates, please review <a href="' .
                Mage::helper('adminhtml')->getUrl('adminhtml/kevlar_queue') .
                '">queue</a> to keep track of the progress.'
            )
        );

        return true;
    }

    /**
     * Extract URL's from category model
     *
     * @param Mage_Catalog_Model_Category $category
     */
    protected function extractCategoryUrl($category)
    {
        $resource = Mage::getResourceSingleton('enterprise_catalog/category');
        $rewrite = $resource->getRewriteByCategoryId($category->getId(), 1);
        if ($rewrite && isset($rewrite['request_path'])) {
            $this->urls[] = $rewrite['request_path'];

            // Take care of addition category views
            foreach (self::getCategoryViews() as $view) {
                $this->urls[] = $rewrite['request_path'] . '/show/' . $view;
            }
        }

        // Handle user configurable URL
        if ($category->getPreferredUrlAttribute()) {
            $this->urls[] = $category->getPreferredUrlAttribute();

            // Take care of addition category views
            foreach (self::getCategoryViews() as $view) {
                $this->urls[] = $category->getPreferredUrlAttribute() . '/show/' . $view;
            }
        }
    }

    /**
     * Retrieve configured category views
     *
     * @return string[]
     */
    public static function getCategoryViews()
    {
        return explode(',', Mage::getStoreConfig('catalog/frontend/grid_per_page_values'));
    }

    /**
     * Automatically cache all of the product images based
     *
     * @param Mage_Core_Model_Abstract $product
     * @return bool
     */
    public static function catalogProductImageSave(Mage_Core_Model_Abstract $product)
    {
        $productStoreIds = $product->getStoreIds();
        if (!$productStoreIds) {
            return false;
        }

        // Retrieve gallery images
        $galleryImages = $product->getMediaGalleryImages();

        // Get child products
        $productChildren = false;

        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $productTypeConfigurable = Mage::getModel('catalog/product_type_configurable');
            if ($productTypeConfigurable && method_exists($productTypeConfigurable, 'getUsedProducts')) {
                $productChildren = $productTypeConfigurable->getUsedProducts(null, $product);
            }
        }

        $currentStoreId = Mage::app()->getStore()->getId();

        foreach ($productStoreIds as $productStoreId) {
            // Switch temporary
            Mage::app()->setCurrentStore($productStoreId);

            if ($galleryImages) {
                foreach ($galleryImages as $galleryImage) {
                    Mage::helper('kevlar/resize')->resizeImageToArraySizes(
                        $product,
                        self::$productImageSizes,
                        $galleryImage->getFile()
                    );
                }
            }

            // Process child products
            if (!$productChildren) {
                continue;
            }

            foreach ($productChildren as $productChild) {
                if (
                    !$productChild->getData('thumbnail') ||
                    ($productChild->getData('thumbnail') === 'no_selection')
                ) {
                    continue;
                }

                Mage::helper('kevlar/resize')->resizeImageToArraySizes(
                    $productChild,
                    self::$productChildImageSizes
                );
            }
        }

        // Back to original store id
        Mage::app()->setCurrentStore($currentStoreId);

        return true;
    }
}
