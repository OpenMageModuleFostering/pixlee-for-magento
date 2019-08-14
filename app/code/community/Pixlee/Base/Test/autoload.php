<?php
$path = realpath(dirname(__FILE__) . '/../../../../../../');
define('MAGENTO_ROOT', $path);

$mageFilename = MAGENTO_ROOT . '/app/Mage.php';
if (!file_exists($mageFilename)) {
    echo $mageFilename." was not found";
    exit;
}

require_once $mageFilename;

Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1);

Mage::app(0);
Mage::app()->cleanCache();
