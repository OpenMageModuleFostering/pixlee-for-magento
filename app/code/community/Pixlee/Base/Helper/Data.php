<?php

require_once __DIR__ . '/RavenAutoLoader.php';
\Raven_Autoloader::register();

class Pixlee_Base_Helper_Data extends Mage_Core_Helper_Abstract {

  protected $_unexportedProducts;
  protected $_pixleeAPI;
  protected $_pixleeProductAlbumModel;
  protected $_isTesting;

  /**
   * Used to initialize the Pixlee API with a stub for testing purposes.
   */
  public function _initTesting($pixleeAPI = null, $pixleeProductAlbum = null) {
    if(!empty($pixleeAPI)) {
      $this->_pixleeAPI = $pixleeAPI;
    }
    if(!empty($pixleeProductAlbum)) {
      $this->_pixleeProductAlbumModel = $pixleeProductAlbum;
    }

    $this->_isTesting = true;
  }

  public function isActive() {
    if($this->_isTesting) {
      return true;
    }

    $pixleeAccountId = Mage::getStoreConfig('pixlee/pixlee/account_id', Mage::app()->getStore());
    $pixleeAccountApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
    if(!empty($pixleeAccountId) && !empty($pixleeAccountApiKey)) {
      return true;
    } else {
      return false;
    }
  }

  public function isInactive() {
    return !$this->isActive();
  }

  public function getNewPixlee() {
    if(!empty($this->_pixleeAPI)) {
      return $this->_pixleeAPI;

    } elseif($this->isActive()) {
      $pixleeAccountId = Mage::getStoreConfig('pixlee/pixlee/account_id', Mage::app()->getStore());
      $pixleeAccountApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
      $pixleeAccountSecretKey = Mage::getStoreConfig('pixlee/pixlee/account_secret_key', Mage::app()->getStore());

      try {
        $this->_pixleeAPI = new Pixlee_Pixlee($pixleeAccountApiKey, $pixleeAccountSecretKey);
        return $this->_pixleeAPI;
      }
      catch (Exception $e) {
        Mage::log("PIXLEE ERROR: " . $e->getMessage());
      }

    } else {
      return null;
    }
  }

  public function getPixleeAlbum() {
    if(empty($this->_pixleeProductAlbumModel)) {
      return Mage::getModel('pixlee/product_album');
    }
    return $this->_pixleeProductAlbumModel;
  }

  public function getUnexportedProducts($useCached = true) {
    // NEVERMIND I'm dumb
    // Tee Ming was totally justified in doing what he did. I should never have doubted Tee Ming.
    // My problem is not that I need getUnexportedProducts to return a cached result, but that
    // having converted to distillery, I wasn't correctly parsing out the created album ID
    /*
    if($this->_unexportedProducts && $useCached) {
      return $this->_unexportedProducts;
    }
    */
    $albumTable = Mage::getSingleton('core/resource')->getTableName('pixlee/product_album');
    $collection = Mage::getModel('catalog/product')->getCollection()
      ->addAttributeToFilter('visibility', array('neq' => 1)); // Only grab products that are visible in catalog and/or search
      $collection->getSelect()->joinLeft(
        array('albums' => $albumTable),
        'e.entity_id = albums.product_id'
        )->where(
        'albums.product_id IS NULL'
        );
        $collection->addAttributeToSelect('*');
        $this->_unexportedProducts = $collection;
        return $collection;
      }

      public function getPixleeRemainingText() {
        $c = $this->getUnexportedProducts()->count();
        if($this->isInactive()) {
          return "Save your Pixlee API access information before exporting your products.";
        } elseif($c > 0) {
          return "(Re) Export your products to Pixlee and start collecting photos.";
        } else {
          return "All your products have been exported to Pixlee. Congratulations!";
        }
      }

      public function _extractActualProduct($product) {
        Mage::log("*** Before _extractActualProduct");
        Mage::log("Name: {$product->getName()}");
        Mage::log("ID: {$product->getId()}");
        Mage::log("SKU: {$product->getSku()}");
        Mage::log("Type: {$product->getTypeId()}");
        $mainProduct = $product;
        $temp_product_id = Mage::getModel('catalog/product')->getIdBySku($product->getSku());
        $parent_ids = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($temp_product_id);
        Mage::log("Parent IDs are");
        Mage::log($parent_ids);
        if($parent_ids) {
          $mainProduct = Mage::getModel('catalog/product')->load($parent_ids[0]);
        } else if($product->getTypeId() == "bundle") {
          $mainProduct = Mage::getModel('catalog/product')->load($product->getId()); // Get original sku as stated in product catalog
        }
        $mainProductClass = get_class($mainProduct);
        Mage::log("*** After _extractActualProduct");
        Mage::log("Name: {$mainProduct->getName()}");
        Mage::log("ID: {$mainProduct->getId()}");
        Mage::log("SKU: {$mainProduct->getSku()}");
        Mage::log("Type: {$mainProduct->getTypeId()}");
        Mage::log("Class: {$mainProductClass}");
        return $mainProduct;
      }

      // Sum up the stock numbers of all the children products
      // EXPECTS A 'configurable' TYPE PRODUCT!
      // If we wanted to be more robust, we could pass the argument to the _extractActualProduct
      // function, but as of 2016/03/11, getAggregateStock is only called after _extractActualProduct
      // has already been called
      public function getAggregateStock($actualProduct) {


        Mage::log("*** In getAggregateStock");
        $aggregateStock = NULL;

        // If after calling _extractActualProduct, there is no 'configurable' product, and only
        // a 'simple' product, we won't get anything back from
        // getModel('catalog/product_type_configurable')
        if ($actualProduct->getTypeId() == "simple") {
          Mage::log("Product Type: Simple");
          // If the product's not keeping track of inventory, we'll error out when we try
          // to call the getQty() function on the output of getStockItem()
          if (is_null($actualProduct->getStockItem())) {
            $aggregateStock = NULL;
          } else {
            $aggregateStock = max(0, $actualProduct->getStockItem()->getQty());
          }
        } else {
          // 'grouped' type products have 'associated products,' which presumably
          // point to simple products
          if ($actualProduct->getTypeId() == "grouped") {
            Mage::log("Product Type: Grouped");
            $childProducts = $actualProduct->getTypeInstance(true)->getAssociatedProducts($actualProduct);
          // And finally, my original assumption that all 'simple' products are
          // under the umbrella of some 'configurable' product
          } else if ($actualProduct->getTypeId() == "configurable") {
            if (!is_a($actualProduct, "Mage_Catalog_Model_Product")) {
                Mage::log("Defaulting to empty children array, actualProduct is " . get_class($actualProduct));
                $childProducts = array();
            } else {
                Mage::log("Product Type: Mage_Catalog_Model_Product");
                $childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$actualProduct);
            }
          } else {
            Mage::log("Product Type: None");
            $childProducts = array();
          }

          Mage::log("Child products of length " . sizeof($childProducts));

          foreach ($childProducts as $child) {
            // Sometimes Magento gives a negative inventory quantity
            // I don't want that to affect the overall count
            // TODO: There is probably a good reason why it goes negative
            Mage::log("Child Name: {$child->getName()}");
            Mage::log("Child SKU: {$child->getSku()}");
            if (is_null($child->getStockItem())) {
              Mage::log("Child product not tracking stock, setting to NULL");
            } else {
              Mage::log("Child Stock: {$child->getStockItem()->getQty()}");
              if (is_null($aggregateStock)) {
                $aggregateStock = 0;
              }
              $aggregateStock += max(0, $child->getStockItem()->getQty());
            }
          }
        }
        Mage::log("Returning aggregateStock: {$aggregateStock}");
        return $aggregateStock;
      }

      public function getVariantsDict($actualProduct) {

        Mage::log("*** In getVariantsDict");
        $variantsDict = array();

        // If after calling _extractActualProduct, there is no 'configurable' product, and only
        // a 'simple' product, we won't get anything back from
        // getModel('catalog/product_type_configurable')
        if ($actualProduct->getTypeId() == "simple") {
          if (is_null($actualProduct->getStockItem())) {
            $variantStock = NULL;
          } else {
            $variantStock = max(0, $actualProduct->getStockItem()->getQty());
          }
          $variantsDict[$actualProduct->getId()] = array(
            'variant_stock' => $variantStock,
            'variant_sku' => $actualProduct->getSku(),
          );
        } else {
          // 'grouped' type products have 'associated products,' which presumably
          // point to simple products
          if ($actualProduct->getTypeId() == "grouped") {
            $childProducts = $actualProduct->getTypeInstance(true)->getAssociatedProducts($actualProduct);
          // And finally, my original assumption that all 'simple' products are
          // under the umbrella of some 'configurable' product
          } else if ($actualProduct->getTypeId() == "configurable") {
            if (!is_a($actualProduct, "Mage_Catalog_Model_Product")) {
                Mage::log("Defaulting to empty children array, actualProduct is " . get_class($actualProduct));
                $childProducts = array();
            } else {
                $childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$actualProduct);
            }
          } else {
            $childProducts = array();
          }

          Mage::log("Child products of length " . sizeof($childProducts));

          foreach ($childProducts as $child) {
            // Sometimes Magento gives a negative inventory quantity
            // I don't want that to affect the overall count
            // TODO: There is probably a good reason why it goes negative
            $variantId = $child->getId();

            if (is_null($child->getStockItem())) {
              $variantStock = NULL;
            } else {
              $variantStock = max(0, $child->getStockItem()->getQty());
            }

            $variantsDict[$variantId] = array(
              'variant_stock' => $variantStock,
              'variant_sku' => $child->getSku(),
            );
          }
        }
        
        if (empty($variantsDict)) {
          return "{}";
        } else {
          return json_encode($variantsDict);
        }
      }

      // getter function to retrieve the category_ids and category names for a product
      // one product can have more than one category, hence its a list
      public function getCategories($product) {
        $categoriesList = array();
        $cats = $product->getCategoryIds();

        foreach ($cats as $category_id) {
          $_cat = Mage::getModel('catalog/category')->load($category_id);
          if (is_null($_cat)) {
            $fields = array();
          } else {
            $fields = array(
              'category_id' => (int) $_cat->getId(),
              'category_name' => $_cat->getName()
            );
          }

          $categoriesList[] = $fields;
        }

        return $categoriesList;
      }

      // Verify that an image exists for this product
      private function getImage($product) {
          $image_name = '';
          if ($product->getImage() != "no_selection") {
              $image_name = $product->getImage();
          } else if ($product->getSmallImage() != "no_selection") {
              $image_name = $product->getSmallImage();
          } else if ($product->getThumbnail() != "no_selection") {
              $image_name = $product->getThumbnail();
          }
          return Mage::getModel('catalog/product_media_config')->getMediaUrl($image_name);
      }

      // Construct some stuff to pass to 'extra_fields'
      public function getExtraFields($actualProduct) {
        Mage::log("Constructing product 'extra_fields'");

        // If we failed earlier in _extractActualProduct, and still have a
        // Mage_Sales_Model_Order_Item class instance here, we'll error out
        // when trying to call getProductOptionCollection
        if (!is_a($actualProduct, 'Mage_Catalog_Model_Product')) {
            $extraFields = '';
        } else {

            // Magento's definition of "custom options"
            $customOptionsDict = array();

            // Each $child here is basically an individual row
            $options = Mage::getModel('catalog/product_option')->getProductOptionCollection($actualProduct);
            foreach ($options as $child) {
              Mage::log("* Product has an option");
              // $child at this point is a PHP object, ->getValues() converts it
              // to an array that we can JSONify and pass to our servers
              foreach ($child->getValues() as $v) {
                  Mage::log("* An option:");
                  Mage::log($v->getData());
                  $customOptionsDict[] = $v->getData();
              }
            }

            // Additionally, save all photos associated with the product
            $productPhotos = array();
            foreach ($actualProduct->getMediaGalleryImages() as $image) {
                array_push($productPhotos, $image->getUrl());
            }

            // Only configurable products can do the following
            // NOTE: If it gets called on a non-configurable product WE WILL FATAL ERROR
            // The try/catch will NOT handle the fatal error!
            $configurableAttributes = null;
            try {
              if ($actualProduct->isConfigurable()) {
                $configurableAttributes = $actualProduct->getTypeInstance(true)->getConfigurableAttributesAsArray($actualProduct);
              }
            } catch (Exception $e) {
              Mage::log("Got error trying to get default color, setting to null: " . $e->getMessage());
            }

            // In addition to Magento's "custom options", we also want to throw
            // a few things in here, for our own sake
            $extraFields = json_encode(array(
                'magento_custom_options' => $customOptionsDict,
                'magento_product_type' => $actualProduct->getTypeId(),
                'magento_product_visibility' => $actualProduct->getVisibility(),
                'magento_sku' => $actualProduct->getSku(),
                'magento_configurable_attributes' => $configurableAttributes,
                'product_photos' => $productPhotos,
                'categories' => $this->getCategories($actualProduct)
            ));

        }

        Mage::log("Made 'extra_fields'");
        Mage::log($extraFields);
        return $extraFields;
      }

      // Whether creating a product, updating a product, or just exporting a product,
      // this function gets called
      public function exportProductToPixlee($product, $update_stock_only = False) {
        Mage::log("*** In exportProductToPixlee");

        // Whether or not to export child products in addition to the parent
        $separateVariants = Mage::getStoreConfig('pixlee/advanced/export_variants_separately', Mage::app()->getStore());
        // Whether or not to export products when there is no associated image
        $imagelessProducts = Mage::getStoreConfig('pixlee/advanced/export_imageless_products', Mage::app()->getStore());

        $pixlee = $this->getNewPixlee();
        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        // Always export visible products, regardless of whether or not
        // 'pixlee/advanced/export_variants_separately' is set
        if($product->getVisibility() != 1) { // Make sure the product is visible in search or catalog
          try {

            $product = $this->_extractActualProduct($product);
            $productName = $product->getName();
            if($this->isInactive() || !isset($productName)) {
              return false;
            }

            $aggregateStock = $this->getAggregateStock($product);
            Mage::log("Total stock is: {$aggregateStock}");
            $variantsDict = $this->getVariantsDict($product);
            Mage::log("Variants dict is");
            Mage::log($variantsDict);

            // In case we're a visible simple product, and instead of actually having
            // 'associated products' here for 'child' products, we have 'custom options' instead
            $extraFields = $this->getExtraFields($product);

            $product_mediaurl = $this->getImage($product);
            if ($imagelessProducts) {
                // Could be more concise, but I just wanted to make it clear I'm intentionally
                // passing through here when imagelessProducts is set to true
            } else if ($product_mediaurl == Mage::getModel('catalog/product_media_config')->getMediaUrl('')) {
                Mage::log("PIXLEE ERROR: Could not find a valid image url for {$product->getName()}, SKU: {$product->getSku()}");
                return false;
            }
            // getPrice() returns a string, need to convert to double before going to distillery
            // PHP regex found here: http://stackoverflow.com/a/18747995
            $product_price = floatval(preg_replace('/[^\d.]/', '', $product->getPrice()));

            $response = $pixlee->createProduct($product->getName(),
                $product->getSku(),
                $product->getProductUrl(),
                $product_mediaurl,
                $product->getId(),
                $product_price,
                (int) $aggregateStock,
                $variantsDict,
                $extraFields,
                $currencyCode,
                $update_stock_only);

            $albumId = 0;

            // Distillery returns the product album on the 'create' verb
            if(isset($response->id)) {
              // Treat $response->id as the created album_id (because that's what it is)
              $album = $this->getPixleeAlbum();
              $album->setProductId($product->getId())->setPixleeAlbumId($response->id);
              $album->save();
            } else {
              return false;
            }
          } catch (Exception $e) {
            Mage::log("PIXLEE ERROR: " . $e->getMessage());
            return false;
          }

        // But if 'pixlee/advanced/export_variants_separately' is set, export even 'invisible' product
        // MOST IMPORTANTLY, use the variant-specific SKU and NAME values
        } elseif ($separateVariants) {

            // Still need the parent for product url
            $parentProduct = $this->_extractActualProduct($product);

            // $product (NOT parentProduct) at this point will return 0 for ->getQty()
            // Instead: http://stackoverflow.com/a/12937631
            $stockItem = Mage::getModel('cataloginventory/stock_item')
               ->loadByProduct($product->getId());

            // If the variant doesn't have its own image, use the parent's
            if ($product->getImage() == "no_selection") {
                $product_mediaurl = $this->getImage($parentProduct);
            } else {
                $product_mediaurl = $this->getImage($product);
            }

            if ($imagelessProducts) {
                // Could be more concise, but I just wanted to make it clear I'm intentionally
                // passing through here when imagelessProducts is set to true
            } else if ($product_mediaurl == Mage::getModel('catalog/product_media_config')->getMediaUrl('')) {
                Mage::log("PIXLEE ERROR: Could not find a valid image url for {$product->getName()}, SKU: {$product->getSku()}");
                return false;
            }


            // Some convenience fields when we're an invisible child product
            // (Probably not going to be used)
            $extraFields = $this->getExtraFields($product);

            // getPrice() returns a string, need to convert to double before going to distillery
            // PHP regex found here: http://stackoverflow.com/a/18747995
            $product_price = floatval(preg_replace('/[^\d.]/', '', $product->getPrice()));

            // Most things we get from the $product itself, except
            // 1) The product URL is wrong, and we need to get it from the 'configurable' product
            // 2) We need to jump through some hoops to get the stock information
            $response = $pixlee->createProduct($product->getName(),
                $product->getSku(),
                $parentProduct->getProductUrl(),
                $product_mediaurl,
                $product->getId(),
                $product_price,
                (int) $stockItem->getQty(),
                null, // If this is a variant, don't create or send the $variantsDict
                $extraFields,
                $currencyCode,
                $update_stock_only
            );

            $albumId = 0;

            // Distillery returns the product album on the 'create' verb
            if(isset($response->id)) {
              // Treat $response->id as the created album_id (because that's what it is)
              $album = $this->getPixleeAlbum();
              $album->setProductId($product->getId())->setPixleeAlbumId($response->id);
              $album->save();
            } else {
              return false;
            }
        }

        return true;
      }

      public function ravenize($apiKey = null) {
        if (is_null($apiKey)) {
          // If I have a '$this->apiKey' property, then I'm probably an instance of
          // \Pixlee\Pixlee\Helper\Pixlee, and can just use that
          if (property_exists($this, 'apiKey')) {
            $apiKey = $this->apiKey;
          }
          // If I am a class that has the 'getApiKey' function, then I'm probably
          // and instance of \Pixlee\Pixlee\Helper\Data, and can call it directly
          elseif (method_exists($this, 'getApiKey')) {
            $apiKey = $this->getApiKey();
          }
          // Most classes using this trait (our Observers) have a $this->_pixleeData,
          // which is an instance of \Pixlee\Pixlee\Helper\Data, and we can call
          // that instance's 'getApiKey' function
          elseif (property_exists($this, '_pixleeData')) {
            $apiKey = $this->_pixleeData->getApiKey();
          }
          // If we didn't find a way to define apiKey, just return here and give
          // up on trying to instantiate a Raven Handler
          else {
            return;
          }
        }
        // Ask this endpoint, which we expect to remain available, for a Sentry URL
        $urlToHit = "https://distillery.pixlee.com/api/v1/getSentryUrl?api_key="
                    . $apiKey . "&team=Pixlee&project=Magento+1";
        if (property_exists($this, '_logger') && !is_null($this->_logger)) {
          $this->_logger->addDebug("Asking Pixlee Distillery for Sentry URL at: "
                                     . $urlToHit);
        }
        // Make the API call
        $ch = curl_init( $urlToHit );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
          )
        );
        $response   = curl_exec($ch);
        // We expect the response to look something like this:
        //      {"url":"https://<PUBKEY>:<PRIVKEY>@sentry.io/118103"}
        $sentryUrl = json_decode($response)->{'url'};
        $this->_sentryClient = new \Raven_Client($sentryUrl);
        $this->_sentryClient->install();
      }
}

// Try/catch only works on thrown exceptions
// Need this to report fatal errors
function shutDownFunction() {
    $error = error_get_last();
    // fatal error, E_ERROR === 1
    if ($error['type'] === E_ERROR) {
        Mage::log("PIXLEE FATAL ERROR: ");
        Mage::log($error);
    }
}
register_shutdown_function('shutDownFunction');
