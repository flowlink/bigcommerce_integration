<?php

$app->get('/', 'Sprout\Wombat\Controller\AppController::indexAction');
$app->get('/index', 'Sprout\Wombat\Controller\AppController::indexAction');

// Register routes.
$app->get('/oauth', 'Sprout\Wombat\Controller\AppController::callbackAction');
$app->get('/load', 'Sprout\Wombat\Controller\AppController::loadAction');
$app->get('/uninstall', 'Sprout\Wombat\Controller\AppController::uninstallAction');


// Wombat webhooks

//get
$app->post('/get_products', 'Sprout\Wombat\Controller\WombatController::getProductsAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/get_orders', 'Sprout\Wombat\Controller\WombatController::getOrdersAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/get_shipments', 'Sprout\Wombat\Controller\WombatController::getShipmentsAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/get_shipment', 'Sprout\Wombat\Controller\WombatController::getShipmentAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/get_customers', 'Sprout\Wombat\Controller\WombatController::getCustomersAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);

//add
$app->post('/add_product', 'Sprout\Wombat\Controller\WombatController::postProductAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/add_order', 'Sprout\Wombat\Controller\WombatController::postOrderAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/add_shipment', 'Sprout\Wombat\Controller\WombatController::postShipmentAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/add_customer', 'Sprout\Wombat\Controller\WombatController::postCustomerAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);

//update
$app->post('/update_product', 'Sprout\Wombat\Controller\WombatController::putProductAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/update_order', 'Sprout\Wombat\Controller\WombatController::putOrderAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/update_shipment', 'Sprout\Wombat\Controller\WombatController::putShipmentAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);
$app->post('/update_customer', 'Sprout\Wombat\Controller\WombatController::putCustomerAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);

//cancel
$app->post('/cancel_order', 'Sprout\Wombat\Controller\WombatController::cancelOrderAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);

//set
$app->post('/set_inventory', 'Sprout\Wombat\Controller\WombatController::setInventoryAction')
		//->before($wombat_auth)
		->before($wombat_includes_bc_auth);






//test routes
$app->get('/hello', 'Sprout\Wombat\Controller\AppController::helloAction');
$app->post('/test/persistuser', 'Sprout\Wombat\Controller\AppController::persistAction');
$app->get('/test/retrieveuser', 'Sprout\Wombat\Controller\AppController::retrieveAction');


