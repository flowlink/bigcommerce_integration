<?php

// Register routes.
$app->get('/oath/callback', 'Sprout\Wombat\Controller\AppController::callbackAction');
$app->get('/load', 'Sprout\Wombat\Controller\AppController::loadAction');
$app->get('/uninstall', 'Sprout\Wombat\Controller\AppController::uninstallAction');


// Wombat webhooks

//get
$app->post('/get_products', 'Sprout\Wombat\Controller\WombatController::getProductsAction');
$app->post('/get_orders', 'Sprout\Wombat\Controller\WombatController::getOrdersAction');
$app->post('/get_shipments', 'Sprout\Wombat\Controller\WombatController::getShipmentsAction');
$app->post('/get_customers', 'Sprout\Wombat\Controller\WombatController::getCustomersAction');

//add
$app->post('/add_product', 'Sprout\Wombat\Controller\WombatController::postProductAction');
$app->post('/add_order', 'Sprout\Wombat\Controller\WombatController::postOrderAction');
$app->post('/add_shipment', 'Sprout\Wombat\Controller\WombatController::postShipmentAction');
$app->post('/add_customer', 'Sprout\Wombat\Controller\WombatController::postCustomerAction');

//update
$app->post('/update_product', 'Sprout\Wombat\Controller\WombatController::putProductAction');
$app->post('/update_order', 'Sprout\Wombat\Controller\WombatController::putOrderAction');
$app->post('/update_shipment', 'Sprout\Wombat\Controller\WombatController::putShipmentAction');
$app->post('/update_customer', 'Sprout\Wombat\Controller\WombatController::putCustomerAction');

//cancel
$app->post('/cancel_order', 'Sprout\Wombat\Controller\WombatController::cancelOrderAction');

//set
$app->post('/set_inventory', 'Sprout\Wombat\Controller\WombatController::setInventoryAction');






//test routes
$app->get('/hello', 'Sprout\Wombat\Controller\AppController::helloAction');
$app->post('/test/persistuser', 'Sprout\Wombat\Controller\AppController::persistAction');
$app->get('/test/retrieveuser', 'Sprout\Wombat\Controller\AppController::retrieveAction');
