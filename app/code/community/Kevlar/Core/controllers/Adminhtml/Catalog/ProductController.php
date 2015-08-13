<?php
require_once 'Mage/Adminhtml/controllers/Catalog/ProductController.php';


/**
 * Overwrite of catalog product controller
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Adminhtml_Catalog_ProductController extends Mage_Adminhtml_Catalog_ProductController
{
    /**
     * Save product action
     */
    public function saveAction()
    {
        $storeId = $this->getRequest()->getParam('store');
        $redirectBack = $this->getRequest()->getParam('back', false);
        $productId = $this->getRequest()->getParam('id');
        $isEdit = (int)($this->getRequest()->getParam('id') != null);

        $data = $this->getRequest()->getPost();
        if ($data) {
            $this->_filterStockData($data['product']['stock_data']);

            $product = $this->_initProductSave();

            try {
                $product->save();

                Mage::dispatchEvent(
                    'catalog_product_save',
                    array(
                        'product' => $product
                    )
                );

                $productId = $product->getId();

                if (isset($data['copy_to_stores'])) {
                    $this->_copyAttributesBetweenStores($data['copy_to_stores'], $product);
                }

                $this->_getSession()->addSuccess($this->__('The product has been saved.'));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage())
                    ->setProductData($data);
                $redirectBack = true;
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($e->getMessage());
                $redirectBack = true;
            }
        }

        if ($redirectBack) {
            $this->_redirect(
                '*/*/edit',
                array(
                    'id' => $productId,
                    '_current' => true
                )
            );
        } elseif ($this->getRequest()->getParam('popup')) {
            $this->_redirect(
                '*/*/created',
                array(
                    '_current' => true,
                    'id' => $productId,
                    'edit' => $isEdit
                )
            );
        } else {
            $this->_redirect('*/*/', array('store' => $storeId));
        }
    }

    /**
     * Update product(s) status action
     */
    public function massStatusAction()
    {
        $productIds = (array)$this->getRequest()->getParam('product');
        $storeId = (int)$this->getRequest()->getParam('store', 0);
        $status = (int)$this->getRequest()->getParam('status');

        try {
            $this->_validateMassStatus($productIds, $status);
            Mage::getSingleton('catalog/product_action')
                ->updateAttributes($productIds, array('status' => $status), $storeId);

            // Update cache records
            Mage::dispatchEvent(
                'catalog_product_save',
                array(
                    'product' => $productIds
                )
            );

            $this->_getSession()->addSuccess(
                $this->__('Total of %d record(s) have been updated.', count($productIds))
            );
        } catch (Mage_Core_Model_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()
                ->addException($e, $this->__('An error occurred while updating the product(s) status.'));
        }

        $this->_redirect('*/*/', array('store' => $storeId));
    }

    /**
     * Mass delete product action
     */
    public function massDeleteAction()
    {
        $productIds = $this->getRequest()->getParam('product');
        if (!is_array($productIds)) {
            $this->_getSession()->addError($this->__('Please select product(s).'));
        } else {
            if (!empty($productIds)) {
                try {
                    // Update cache records
                    Mage::dispatchEvent(
                        'catalog_product_save',
                        array(
                            'product' => $productIds
                        )
                    );

                    foreach ($productIds as $productId) {
                        $product = Mage::getSingleton('catalog/product')->load($productId);

                        Mage::dispatchEvent(
                            'catalog_controller_product_delete',
                            array(
                                'product' => $product
                            )
                        );

                        $product->delete();
                    }
                    $this->_getSession()->addSuccess(
                        $this->__('Total of %d record(s) have been deleted.', count($productIds))
                    );
                } catch (Exception $e) {
                    $this->_getSession()->addError($e->getMessage());
                }
            }
        }
        $this->_redirect('*/*/index');
    }


    /**
     * Delete product action
     */
    public function deleteAction()
    {
        if ($id = $this->getRequest()->getParam('id')) {
            $product = Mage::getModel('catalog/product')
                ->load($id);
            $sku = $product->getSku();
            try {

                // Update cache records
                Mage::dispatchEvent(
                    'catalog_product_save',
                    array(
                        'product' => $product
                    )
                );

                $product->delete();
                $this->_getSession()->addSuccess($this->__('The product has been deleted.'));
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
        }
        $this->getResponse()
            ->setRedirect($this->getUrl('*/*/', array('store' => $this->getRequest()->getParam('store'))));
    }
}
