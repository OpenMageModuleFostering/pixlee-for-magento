<?php
class Pixlee_Base_Block_CategoryGallery extends Mage_Core_Block_Template {

  public function _prepareLayout() {
    $this->setTemplate("pixlee/category_gallery.phtml");
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

  public function getCategoryWidgetId() {
    $pixleeWidgetId = Mage::getStoreConfig('pixlee/widget_options/category_widget_id', Mage::app()->getStore());
    return $pixleeWidgetId;
  }
}
