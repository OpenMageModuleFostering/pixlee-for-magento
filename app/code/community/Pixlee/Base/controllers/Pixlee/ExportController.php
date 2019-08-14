<?php
class Pixlee_Base_Pixlee_ExportController extends Mage_Adminhtml_Controller_Action {

  public function exportAction() {

    // The original author of this class relied on limiting selects to 10 and calling
    // the getUnexportedProducts function until it returned no unexported products
    // Instead, we're now going to export the entire product set every time
    // Every 100 exports, sleep for 1 second
    $export_chunk_size = 100;
    $export_sleep_sec = 1;

    $helper = Mage::helper('pixlee');
    $json = array();

    $raven = Mage::helper('pixlee');
    $pixleeAccountApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
    $raven->ravenize($pixleeAccountApiKey);

    if($helper->isActive()) {

      $separateVariants = Mage::getStoreConfig('pixlee/advanced/export_variants_separately', Mage::app()->getStore());

      if ($separateVariants) {
        $products = Mage::getModel('catalog/product')->getCollection();
      } else {
        $products = Mage::getModel('catalog/product')->getCollection()->addAttributeToFilter('visibility', array('neq' => 1));
      }
      $products->getSelect();
      $products->addAttributeToSelect('*');

      // Manually throttle our exports
      $counter = 0;

      foreach($products as $product) {
        $ids = $product->getStoreIds();
        if(isset($ids[0])) {
          $product->setStoreId($ids[0]);
        }
        $counter += 1;
        if (is_null($product)) {
           // Just a check to be extra sure. We had a customer where $product was null and caused FATAL errors
           continue;
        }
        $helper->exportProductToPixlee($product);

        // Now that we don't have the ->getSelect()->limit(10) to keep ourselves from
        // overloading stuff, we'll have to manage it ourselves
        if($counter % $export_chunk_size == 0) {
            sleep($export_sleep_sec);
        }
      }

      $json = array('action' => 'success');
    }

    $json['pixlee_remaining_text'] = $helper->getPixleeRemainingText();
    $this->getResponse()->setHeader('Content-type', 'application/json');
    $this->getResponse()->setBody(json_encode($this->utf8_converter($json)));
  }

  protected function _isAllowed() {
    return Mage::getSingleton('admin/session')->isAllowed('pixlee');
  }

  public function utf8_converter($array)
  {
      array_walk_recursive($array, function(&$item, $key){
          if(!mb_detect_encoding($item, 'utf-8', true)){
                  $item = utf8_encode($item);
          }
      });
   
      return $array;
  }

}
