<?php
require_once('Enterprise/Cms/controllers/Adminhtml/Cms/Page/RevisionController.php');

/**
 * Overwrite of CMS page revision controller
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Adminhtml_Cms_Page_RevisionController extends Enterprise_Cms_Adminhtml_Cms_Page_RevisionController
{
    /**
     * Publishing revision
     */
    public function publishAction()
    {
        $revision = $this->_initRevision();

        try {
            $revision->publish();

            // Update cache
            Mage::dispatchEvent(
                'cms_page_revision_publish',
                array(
                    'page_id' => $revision->getPageId()
                )
            );

            // Display success message
            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('enterprise_cms')->__('The revision has been published.')
            );
            $this->_redirect('*/cms_page/edit', array('page_id' => $revision->getPageId()));
            return;
        } catch (Exception $e) {
            // Display error message
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());

            // Redirect to edit form
            $this->_redirect(
                '*/*/edit',
                array(
                    'page_id' => $this->getRequest()->getParam('page_id'),
                    'revision_id' => $this->getRequest()->getParam('revision_id')
                )
            );
            return;
        }
    }
}
