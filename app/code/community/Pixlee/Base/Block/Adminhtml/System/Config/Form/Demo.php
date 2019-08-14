<?php
class Pixlee_Base_Block_Adminhtml_System_Config_Form_Demo extends Mage_Adminhtml_Block_System_Config_Form_Field {
  protected function _construct() {
    parent::_construct();
    $this->setTemplate('pixlee/system/config/demo_button.phtml');
  }

  protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
    return $this->_toHtml();
  }

  public function getButtonHtml() {
    $buttonData = array(
      'id'       => 'request_demo_button',
      'label'    => $this->helper('adminhtml')->__('Request Access'),
      'onclick'  => 'javascript:requestDemo(); return false;'
    );
    
    $button = $this->getLayout()->createBlock('adminhtml/widget_button')->setData($buttonData);

    return $button->toHtml();
  }

}
