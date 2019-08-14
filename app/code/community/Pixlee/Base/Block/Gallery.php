<?php
class Pixlee_Base_Block_Gallery extends Mage_Core_Block_Template {

  public function _prepareLayout() {
    $product = Mage::registry("current_product");
    if(!empty($product)) {
      $this->setProductSku($product->getSku());
    }
    $this->setTemplate("pixlee/gallery.phtml");
    return parent::_prepareLayout();
  }

  public function getAccountId() {
    return Mage::getStoreConfig('pixlee/pixlee/account_id', Mage::app()->getStore());
  }

  public function getAccountApiKey() {
    return Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
  }

  public function getApiKey() {
    $pixleeApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
    return $pixleeApiKey;
  }

  public function getWidgetId() {
    $pixleeWidgetId = Mage::getStoreConfig('pixlee/widget_options/widget_id', Mage::app()->getStore());
    return $pixleeWidgetId;
  }
}
