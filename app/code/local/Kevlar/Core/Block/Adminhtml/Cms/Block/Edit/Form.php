<?php

/**
 * Overwrite of CMS block edit page to provide a checkbox that triggers cache purge
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Block_Adminhtml_Cms_Block_Edit_Form extends Mage_Adminhtml_Block_Cms_Block_Edit_Form
{
    /**
     * Prepare form
     *
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    protected function _prepareForm()
    {
        $model = Mage::registry('cms_block');

        $form = new Varien_Data_Form(
            array('id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post')
        );

        $form->setHtmlIdPrefix('block_');

        $fieldset = $form->addFieldset(
            'base_fieldset',
            array('legend' => Mage::helper('cms')->__('General Information'), 'class' => 'fieldset-wide')
        );

        if ($model->getBlockId()) {
            $fieldset->addField(
                'block_id',
                'hidden',
                array(
                    'name' => 'block_id',
                )
            );
        }

        $fieldset->addField(
            'title',
            'text',
            array(
                'name' => 'title',
                'label' => Mage::helper('cms')->__('Block Title'),
                'title' => Mage::helper('cms')->__('Block Title'),
                'required' => true,
            )
        );

        $fieldset->addField(
            'identifier',
            'text',
            array(
                'name' => 'identifier',
                'label' => Mage::helper('cms')->__('Identifier'),
                'title' => Mage::helper('cms')->__('Identifier'),
                'required' => true,
                'class' => 'validate-xml-identifier',
            )
        );

        /**
         * Check is single store mode
         */
        if (!Mage::app()->isSingleStoreMode()) {
            $field = $fieldset->addField(
                'store_id',
                'multiselect',
                array(
                    'name' => 'stores[]',
                    'label' => Mage::helper('cms')->__('Store View'),
                    'title' => Mage::helper('cms')->__('Store View'),
                    'required' => true,
                    'values' => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true),
                )
            );
            $renderer = $this->getLayout()->createBlock('adminhtml/store_switcher_form_renderer_fieldset_element');
            $field->setRenderer($renderer);
        } else {
            $fieldset->addField(
                'store_id',
                'hidden',
                array(
                    'name' => 'stores[]',
                    'value' => Mage::app()->getStore(true)->getId()
                )
            );
            $model->setStoreId(Mage::app()->getStore(true)->getId());
        }

        $fieldset->addField(
            'is_active',
            'select',
            array(
                'label' => Mage::helper('cms')->__('Status'),
                'title' => Mage::helper('cms')->__('Status'),
                'name' => 'is_active',
                'required' => true,
                'options' => array(
                    '1' => Mage::helper('cms')->__('Enabled'),
                    '0' => Mage::helper('cms')->__('Disabled'),
                ),
            )
        );
        if (!$model->getId()) {
            $model->setData('is_active', '1');
        }

        $fieldset->addField(
            'content',
            'editor',
            array(
                'name' => 'content',
                'label' => Mage::helper('cms')->__('Content'),
                'title' => Mage::helper('cms')->__('Content'),
                'style' => 'height:36em',
                'required' => true,
                'config' => Mage::getSingleton('cms/wysiwyg_config')->getConfig()
            )
        );

        // Enable flush checkbox
        $fieldset->addField(
            'flush',
            'checkbox',
            array(
                'label' => Mage::helper('cms')->__('Flush Cache'),
                'title' => Mage::helper('cms')->__('Flush Cache'),
                'name' => 'flush',
                'onclick' => 'this.value = this.checked ? 1 : 0;',
                'checked' => 0
            )
        );

        $form->setValues($model->getData());
        $form->setUseContainer(true);
        $this->setForm($form);

        return Mage_Adminhtml_Block_Widget_Form::_prepareForm();
    }
}
