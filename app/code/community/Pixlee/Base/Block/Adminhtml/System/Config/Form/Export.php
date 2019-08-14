<?php
class Pixlee_Base_Block_Adminhtml_System_Config_Form_Export extends Mage_Adminhtml_Block_System_Config_Form_Field {
  protected function _construct() {
    parent::_construct();
    $this->setTemplate('pixlee/system/config/export_button.phtml');
  }

  protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
    return $this->_toHtml();
  }

  public function getAjaxExportUrl()
  {
    return Mage::helper('adminhtml')->getUrl('*/pixlee_export/export');
  }

  public function getButtonHtml() {
    $buttonData = array(
      'id'       => 'pixlee_export_button',
      'label'    => $this->helper('adminhtml')->__('Export Products to Pixlee'),
      'onclick'  => 'javascript:exportToPixlee(\''.$this->getAjaxExportUrl().'\'); return false;'
    );
    if(Mage::helper('pixlee')->isInactive() || Mage::helper('pixlee')->getUnexportedProducts()->count() == 0) {
      $buttonData['class'] = 'disabled';
    }
    $button = $this->getLayout()->createBlock('adminhtml/widget_button')->setData($buttonData);

    return $button->toHtml();
  }

}
