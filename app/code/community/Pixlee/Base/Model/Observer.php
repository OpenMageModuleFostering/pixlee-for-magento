<?php
class Pixlee_Base_Model_Observer {

  const ANALYTICS_BASE_URL = 'https://inbound-analytics.pixlee.com/events/';
  protected $_urls = array();

  public function __construct() {
    // Prepare URLs used to ping Pixlee analytics server
    $this->_urls['addToCart'] = self::ANALYTICS_BASE_URL . 'addToCart';
    $this->_urls['removeFromCart'] = self::ANALYTICS_BASE_URL . 'removeFromCart';
    $this->_urls['checkoutStart'] = self::ANALYTICS_BASE_URL . 'checkoutStart';
    $this->_urls['checkoutSuccess'] = self::ANALYTICS_BASE_URL . 'conversion';

    $raven = Mage::helper('pixlee');
    $pixleeAccountApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
    $raven->ravenize($pixleeAccountApiKey);
  }

  public function isNewProductCheck(Varien_Event_Observer $observer) {
    $productID = $observer->getEvent()->getProduct()->getId();
    $isNew = Mage::registry('pixlee_is_new_product');
    if(!$productID && !$isNew) {
      Mage::register('pixlee_is_new_product', true);
    }
  }

  public function createProductTrigger(Varien_Event_Observer $observer) {
    $helper = Mage::helper('pixlee');
    $product = $observer->getEvent()->getProduct();
    try{
      $helper->exportProductToPixlee($product);
    } catch (Exception $e) {
      Mage::getSingleton("adminhtml/session")->addWarning("Pixlee Magento - You may not have the right API credentials. Please check the plugin configuration.");
      Mage::log("PIXLEE ERROR: " . $e->getMessage());
    }
  }

  public function exportProductsTrigger(Varien_Event_Observer $observer) {
    $helper = Mage::helper('pixlee');
    $products = $helper->getUnexportedProducts();
    $products->getSelect();
    try{
      foreach($products as $product) {
        $ids = $product->getStoreIds();
        if(isset($ids[0])) {
          $product->setStoreId($ids[0]);
        }
        $helper->exportProductToPixlee($product);
      }
    } catch (Exception $e) {
      Mage::log("PIXLEE ERROR: " . $e->getMessage());
    }
  }

  // Analytics

  // ADD PRODUCT TO CART
  public function addToCart(Varien_Event_Observer $observer) {
    if (!$this->_checkAnalyticsEnabled())
        return;

    $product = $observer->getEvent()->getProduct();
    $productData = $this->_extractProduct($product);
    $payload = $this->_preparePayload($productData);
    $this->_sendPayload('addToCart', $payload);
  }

  // REMOVE PRODUCT FROM CART
  public function removeFromCart(Varien_Event_Observer $observer) {
    if (!$this->_checkAnalyticsEnabled())
        return;

    $product = $observer->getEvent()->getQuoteItem();
    $productData = $this->_extractProduct($product);
    $payload = $this->_preparePayload($productData);
    $this->_sendPayload('removeFromCart', $payload);
  }

  // CHECKOUT START
  public function checkoutStart(Varien_Event_Observer $observer) {
    if (!$this->_checkAnalyticsEnabled())
        return;

    $quote = Mage::getModel('checkout/cart')->getQuote();
    $cartData = $this->_extractCart($quote);
    $payload = array('cart' => $cartData);
    $payload = $this->_preparePayload($payload);
    $this->_sendPayload('checkoutStart', $payload);
  }

  // CHECKOUT SUCCESS
  public function checkoutSuccess(Varien_Event_Observer $observer) {
    if (!$this->_checkAnalyticsEnabled())
        return;

    $quote = new Mage_Sales_Model_Order();
    $incrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
    $quote->loadByIncrementId($incrementId);
    $cartData = $this->_extractCart($quote);
    $cartData['type'] = 'magento';
    $customerData = $this->_extractCustomer($quote);
    $payload = array_merge(array('cart' => $cartData), $customerData);
    $payload = $this->_preparePayload($payload);
    $this->_sendPayload('checkoutSuccess', $payload);

    // Magento ticks down the stock inventory as soon as an order is created,
    // so in addition to sending an analytics event, update the product in distillery
    $helper = Mage::helper('pixlee');
    foreach ($quote->getAllVisibleItems() as $item) {
      $product = $helper->_extractActualProduct($item);
      $helper->exportProductToPixlee($product, $update_stock_only = True);
    }
  }

  // CANCEL ORDER
  public function cancelOrder(Varien_Event_Observer $observer) {
    if (!$this->_checkAnalyticsEnabled())
        return;

    // When an order is cancelled, Magento ticks the stock inventory back up
    $helper = Mage::helper('pixlee');
    $order = $observer->getEvent()->getOrder();
    foreach ($order->getAllVisibleItems() as $item) {
      $product = $helper->_extractActualProduct($item);
      $helper->exportProductToPixlee($product, $update_stock_only = True);
    }
  }

  // VALIDATE CREDENTIALS
  public function validateCredentials(Varien_Event_Observer $observer){
    $pixleeAccountApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
    $pixleeAccountSecretKey = Mage::getStoreConfig('pixlee/pixlee/account_secret_key', Mage::app()->getStore());

    $this->_pixleeAPI = new Pixlee_Pixlee($pixleeAccountApiKey, $pixleeAccountSecretKey);
    try{
      $this->_pixleeAPI->getAlbums();
    }catch(Exception $e){
      Mage::getSingleton("adminhtml/session")->addWarning("The API credentials seem to be wrong. Please check again.");
    }
  }


  // Helper functions

  // Shorthand for having to check the config every time
  protected function _checkAnalyticsEnabled(){
    return Mage::getStoreConfig('pixlee/advanced/enable_analytics', Mage::app()->getStore());
  }

  protected function _getPixleeCookie() {
    if(isset($_COOKIE['pixlee_analytics_cookie'])){
      if($cookie = $_COOKIE['pixlee_analytics_cookie']) {
        // Return the decoded cookie as an associative array, not a PHP object
        // as json_decode prefers.
        return json_decode($cookie, true);
      }
    }
    return false;
  }

  /**
   * Build a payload from the Pixlee provided cookie, appending extra data not
   * provided by the cookie by default (e.g. API key and User ID).
   **/
  protected function _preparePayload($extraData = array()) {
    $helper = Mage::helper('pixlee');

    Mage::log("* In _preparePayload");
    if(($payload = $this->_getPixleeCookie()) && $helper->isActive()) {
      // Append all extra data to the payload
      foreach($extraData as $key => $value) {
        // Don't accidentally overwrite existing data.
        if(!isset($payload[$key])) {
          $payload[$key] = $value;
        }
      }
      Mage::log("** Before building payload");
      // Required key/value pairs not in the payload by default.
      $payload['API_KEY']= Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
      $payload['uid'] = $payload['CURRENT_PIXLEE_USER_ID'];
      Mage::log("** After building payload");
      $payload['ecommerce_platform'] = 'magento_1';
      $payload['ecommerce_platform_version'] = '2.0.0';
      $payload['version_hash'] = $this->_getVersionHash();
      return json_encode($payload);
    }
    return false; // No cookie exists,
  }

  protected function _sendPayload($event, $payload) {
    Mage::log("* In _sendPayload");
    Mage::log("** Payload: $payload");
    if($payload && isset($this->_urls[$event])) {
      // I'm reading that curl won't actually raise an exception, but rather
      // it'll just return false - however, this couldn't hurt
      try {
        $ch = curl_init($this->_urls[$event]);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // If the Pixlee server doesn't respond after 3 seconds, close the connection
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3000);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));

        // Set User Agent
        if(isset($_SERVER['HTTP_USER_AGENT'])){
          curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }

        $response   = curl_exec($ch);
        $responseInfo   = curl_getinfo($ch);
        $responseCode   = $responseInfo['http_code'];
        curl_close($ch);

        if( !$this->isBetween($responseCode, 200, 299) ) {
          Mage::log("HTTP $responseCode response from Pixlee API", Zend_Log::ERR);
        } elseif ( is_object($response) && is_null( $response->status ) ) {
          Mage::log("Pixlee did not return a status", Zend_Log::ERR);
        } elseif( is_object($response) && !$this->isBetween( $response->status, 200, 299 ) ) {
          $errorMessage   = implode(',', (array)$response->message);
          Mage::log("$response->status - $errorMessage ", Zend_Log::ERR);
        } else {
          return true;
        }
      } catch (Exception $e) {
        Mage::log("PIXLEE ERROR: " . $e->getMessage());
      }
    }
    return false;
  }

  protected function _extractProduct($product) {
    $productData = array();
    Mage::log("* In _extractProduct");
    if(is_a($product, 'Mage_Sales_Model_Quote_Item')) {
      $productData['quantity'] = (int) $product->getQty();
      $product = $product->getProduct();
    } else if(is_a($product, 'Mage_Sales_Model_Order_Item')) {
      // BUGZ-1081: We used to have getQtyToInvoice() here, but it seems Goorin
      // has maybe...and auto-invoice extension maybe?
      $productData['quantity'] = (int) $product->getQtyOrdered();
      $product = $product->getProduct();
    } else {
      $productData['quantity'] = (int) $product->getQty();
    }

    if($product->getId()) {
      $productData['variant_id'] = (int) $product->getIdBySku($product->getSku());
      $productData['variant_sku'] = $product->getSku();
      $productData['price'] = Mage::helper('core')->currency($product->getPrice(), true, false); // Get price in the main currency of the store. (USD, EUR, etc.)
      $productData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
    }

    // Moving _extractActualProduct into the helper, because I want to use it
    // in product exports (when a 'simple' type product is modified in the dashboard)
    // but don't want to duplicate code
    $helper = Mage::helper('pixlee');

    // Here we need to know whether to pass back the parent product SKU
    // or the variant product SKU, based on 'export_variants_separately'
    $separateVariants = Mage::getStoreConfig('pixlee/advanced/export_variants_separately', Mage::app()->getStore());

    if ($separateVariants) {
      $productData['product_id'] = (int) $product->getIdBySku($product->getSku());
      $productData['product_sku'] = $product->getSku();
    } else {
      $product = $helper->_extractActualProduct($product);
      $productData['product_id'] = (int) $product->getId();
      $productData['product_sku'] = $product->getSku();
    }
    return $productData;
  }

  protected function _extractCart($quote) {
    $cartData = array('contents' => array());

    if(is_a($quote, 'Mage_Sales_Model_Quote')) {
      foreach ($quote->getAllVisibleItems() as $item) {
        $cartData['contents'][] = $this->_extractProduct($item);
      }
      $cartData['total'] = $cartData['total'] = Mage::helper('core')->currency($quote->getGrandTotal(), true, false);
      $cartData['total_quantity'] = round($quote->getItemsQty());
      return $cartData;
    } else if(is_a($quote, 'Mage_Sales_Model_Order')) {
      foreach ($quote->getAllVisibleItems() as $item) {
        $cartData['contents'][] = $this->_extractProduct($item);
      }
      $cartData['total'] = Mage::helper('core')->currency($quote->getGrandTotal(), true, false);
      $cartData['total_quantity'] = round($quote->getTotalQtyOrdered());
      return $cartData;
    }

    return false;
  }

  protected function _extractCustomer($quote) {
    if(is_a($quote, 'Mage_Sales_Model_Quote') || is_a($quote, 'Mage_Sales_Model_Order')) {

      $result = array();

      if (method_exists($quote, 'getShippingAddress')) {
        $shippingAddress = $quote->getShippingAddress();
        if (!empty($shippingAddress)) {
          $email = $shippingAddress->getEmail();
          if (!$email && method_exists($quote, 'getCustomerEmail')) $email = $quote->getCustomerEmail();
        }
        $result['email'] = $email;
      }

      if (method_exists($quote, 'getCustomerId')) { $
        $result['customer_id'] = $quote->getCustomerId();
      }

      if (method_exists($quote, 'getRealOrderId')) {
        $result['order_id'] = $quote->getRealOrderId();
      }

      if (method_exists($quote, 'getOrderCurrencyCode')) {
        $result['currency'] = $quote->getOrderCurrencyCode();
      }

      return $result;
    } else {
      return false;
    }
  }

  protected function isBetween($theNum, $low, $high){
    if($theNum >= $low && $theNum <= $high) {
      return true;
    } else {
      return false;
    }
  }

  protected function _getVersionHash() {
    $version_hash = file_get_contents(Mage::getModuleDir('', 'Pixlee_Base').'/version.txt');
    $version_hash = str_replace(array("\r", "\n"), '', $version_hash);
    return $version_hash;
  }

}
