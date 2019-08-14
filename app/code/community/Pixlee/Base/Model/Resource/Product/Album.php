<?php
class Pixlee_Base_Model_Resource_Product_Album extends Mage_Core_Model_Resource_Db_Abstract {
  protected function _construct() {
    $this->_init('pixlee/product_album', 'id');
  }
}
