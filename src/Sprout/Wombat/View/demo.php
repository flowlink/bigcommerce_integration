<h1><a href="<?= WOMBAT_BASE_URL.'/index'; ?>">Wombat Mapping Demos</a></h1>

<?php
/*
require_once(__DIR__.'/bigcommerce.php');
require_once(__DIR__.'/wombatbc.php');

$wombatbc = new WombatBC();

// OUTPUT DEMOS



// CONNECT
$wombatbc->openBlock();
$wombatbc->connect('athleticapi', 'https://store-pijlvyhy.mybigcommerce.com/api/v2/', 'd1ee45ad7d4a7c7c97b102eea8fd9663bb7ce9c9');
$wombatbc->closeBlock();



// DEMO PRODUCT
require_once(__DIR__.'/Entity/Product.php');
$wombatbc->openBlock('Products');
// Get product from BC
$bc_api_product = $wombatbc->product(78); 
// Send into Wombat Product Class
$wombat_product = new \Sprout\Wombat\Entity\Product($bc_api_product);
// Display comparison
$wombatbc->comparepreview(
	array($wombat_product->getBigCommerceObject(), 'BigCommerce Product'),
	array($wombat_product->getWombatObject(), 'Wombat Product')
);
$wombatbc->closeBlock();



// DEMO ORDER
require_once(__DIR__.'/Entity/Order.php');
$wombatbc->openBlock('Orders');
// Get product from BC
$bc_api_order = $wombatbc->order(103); 
// Send into Wombat Order Class
$wombat_order = new \Sprout\Wombat\Entity\Order($bc_api_order);
// Display comparison
$wombatbc->comparepreview(
	array($wombat_order->getBigCommerceObject(), 'BigCommerce Order'),
	array($wombat_order->getWombatObject(), 'Wombat Order')
);
$wombatbc->closeBlock();



// DEMO CUSTOMER
require_once(__DIR__.'/Entity/Customer.php');
$wombatbc->openBlock('Customers');
// Get product from BC
$bc_api_customer = $wombatbc->customer(2); 
// Send into Wombat Customer Class
$wombat_customer = new \Sprout\Wombat\Entity\Customer($bc_api_customer);
// Display comparison
$wombatbc->comparepreview(
	array($wombat_customer->getBigCommerceObject(), 'BigCommerce Customer'),
	array($wombat_customer->getWombatObject(), 'Wombat Customer')
);
$wombatbc->closeBlock();*/