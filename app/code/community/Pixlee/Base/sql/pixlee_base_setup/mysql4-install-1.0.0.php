<?php
$installer = $this;
$installer->startSetup();
$installer->send_to_pardot();

$table = $installer->getConnection()->newTable($installer->getTable('pixlee/product_album'))
  ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => false,
    'primary' => true,
    'identity' => true,
  ))
  ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => false,
    'primary' => false,
    'identity' => false,
  ), 'Magento product entity ID')
  ->addColumn('pixlee_album_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => false,
    'primary' => false,
    'identity' => false,
  ), 'Pixlee album ID')
  ;

$installer->getConnection()->createTable($table);

$installer->getConnection()
  ->addKey(
    $installer->getTable('pixlee/product_album'), 
    'IDX_PX_PRODUCT_ID', 
    'product_id'
  );

$installer->endSetup();
