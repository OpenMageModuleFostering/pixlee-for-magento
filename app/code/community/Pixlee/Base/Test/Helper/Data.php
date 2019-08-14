<?php
class Pixlee_Base_Test_Helper_Data extends EcomDev_PHPUnit_Test_Case {

    /**
     * @loadFixture
     */
    public function testIsActive() {
        $isActive = Mage::helper('pixlee')->isActive();
        $this->assertEquals($isActive, true);
    }

    /**
     * @loadFixture
     */
    public function testIsInactive() {
        $isInactive = Mage::helper('pixlee')->isInactive();
        $this->assertEquals($isInactive, true);
    }

    /**
     * Ensure that we only get products both visible in search/catalog
     * and those which do not already have an album associated with them.
     *
     * @loadFixture
     */
    public function testGetUnexportedProducts() {
        $helper = Mage::helper('pixlee');
        $products = $helper->getUnexportedProducts();
        $this->assertEquals($products->count(), 3);
        // Ensure we get the expected products
        $validNames = array('Shows up 1', 'Shows up 2', 'Shows up 3');
        foreach($products as $product) {
            $this->assertContains($product->getName(), $validNames);
        }
    }

    /**
     * @loadFixture testGetUnexportedProducts
     */
    public function testGetNewPixlee() {
        $helper = Mage::helper('pixlee');
        $pixleeAPI = $this->getMockBuilder('Pixlee_Pixlee')
            ->setMethods(array('createProduct'))
            ->disableOriginalConstructor()
            ->getMock();

        $response = new stdClass();
        $response->data = new stdClass();
        $response->data->album = new stdClass();
        $response->data->album->id = 1;

        $pixleeAPI->expects($this->any())
            ->method('createProduct')
            ->will($this->returnValue($response));

        $helper->_initTesting($pixleeAPI);

        $products = $helper->getUnexportedProducts();
        $this->assertEquals(3, $products->count(), 'There should be 3 products to export to Pixlee');
        foreach($products as $product) {
            $product->setUrl('test'); // Needed to prevent session headers being sent while accessing EAV URL values.
            $this->assertEquals($helper->exportProductToPixlee($product), true);
        }
        $this->assertEquals(0, $helper->getUnexportedProducts(false)->count(), 'There should be no more unexported products after exporting products to Pixlee.');
    }
}
