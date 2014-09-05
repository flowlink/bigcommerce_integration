<?php

/**
 * WombatController:
 *
 * Respond to Wombat webhooks
 *
 * todo: split into subclasses for data types
 */

namespace Sprout\Wombat\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

use Sprout\Wombat\Entity\User;
use Sprout\Wombat\Entity\Product;
use Sprout\Wombat\Entity\Order;
use Sprout\Wombat\Entity\Customer;
use Sprout\Wombat\Entity\Shipment;

class WombatController {

	protected $request_id;

	/*
	
		PRODUCT ACTIONS

	*/

	/**
	 * Get a list of products from BC
	 */
	public function getProductsAction(Request $request, Application $app) {
		
		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);
		$response = $client->get('products', array('query' => $request_data['parameters']));
		$response_status = intval($response->getStatusCode());

		// Response
		if($response_status === 200) {
			
			// get products
			$bc_data = $response->json(array('object'=>TRUE));
			$wombat_data = array();
			if(!empty($bc_data)) {
				foreach($bc_data as $bc_product) {
					$bc_product->_store_url = $request_data['store_url'];
					$wombatModel = new Product($bc_product, 'bc');
					$wombatModel->loadAttachedResources($client);
					$wombat_data[] = $wombatModel->getWombatObject();
				}
			}

			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'request_results' => count($wombat_data),
				'parameters' => $request_data['parameters'],
				'products' => $wombat_data
			);
			return $app->json($response, 200);
			
		} else if($response_status === 204) { // successful but empty (no results)
		
			return $app->json($this->emptyResponse($request_data,'products'), 200);
			
		} else { // error
			
			throw new \Exception($request_data['request_id'].': Error received from BigCommerce '.$response->getBody(),500);
			
		}
	}

	/**
	 * Post a product to BC
	 */
	public function postProductAction(Request $request, Application $app) {
		
		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('product');
		
		$bcModel = new Product($wombat_data,'wombat');
		$bc_data = $bcModel->getBigCommerceObject('create');

		
		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);
		
		$response = $client->post('products',$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 201) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The product $bc_data->name was created in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/**
	 * Update a product in BC
	 */
	public function putProductAction(Request $request, Application $app) {

		$request_data = $this->initRequestData($request);

		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('product');

		$bcModel = new Product($wombat_data,'wombat');
		$bc_data = $bcModel->getBigCommerceObject('update');

		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		$response = $client->put('products/'.$wombat_data['id'],$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 200) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The product $bc_data->name was updated in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/**
	 * Set inventory level for a product in BigCommerce
	 *
	 * Note: Wombat has a separate Inventory object, while BigCommerce tracks inventory in the Product object
	 *
	 * @todo: check that the product has inventory_tracking set in BC before attempting to update
	 */
	public function setInventoryAction(Request $request, Application $app) {
		$request_data = $this->initRequestData($request);

		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('inventory');

		$bcModel = new Product($wombat_data,'wombat');
		$bc_data = $bcModel->getBigCommerceObject('set_inventory');

		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		$response = $client->put('products/'.$wombat_data['product_id'],$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 200) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The inventory level for product ID: ".$wombat_data['product_id']." was updated in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/*
	
		ORDER ACTIONS

	*/
	
	/**
	 * Get a list of orders from BC
	 */
	public function getOrdersAction(Request $request, Application $app) {
		
		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);
		$response = $client->get('orders', array('query' => $request_data['parameters']));
		$response_status = intval($response->getStatusCode());

		// Response
		if($response_status === 200) {
			
			// get orders
			$bc_data = $response->json(array('object'=>TRUE));
			$wombat_data = array();
			if(!empty($bc_data)) {
				foreach($bc_data as $bc_order) {
					$bc_order->_store_url = $request_data['store_url'];
					$wombatModel = new Order($bc_order, 'bc');
					$wombatModel->loadAttachedResources($client);
					$wombat_data[] = $wombatModel->getWombatObject();
				}
			}

			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'request_results' => count($wombat_data),
				'parameters' => $request_data['parameters'],
				'orders' => $wombat_data
			);
			return $app->json($response, 200);
			
		} else if($response_status === 204) { // successful but empty (no results)
		
			return $app->json($this->emptyResponse($request_data,'orders'), 200);
			
		} else { // error
			throw new \Exception($request_data['request_id'].': Error received from BigCommerce '.$response->getBody(),500);			
		}
	}

	public function postOrderAction(Request $request, Application $app) {

		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('order');
		
		$bcModel = new Order($wombat_data,'wombat');
		$bc_data = $bcModel->getBigCommerceObject('create');

		
		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		//return $options['body'].PHP_EOL;
		
		$response = $client->post('orders',$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 201) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The order $bc_data->name was created in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/**
	 * Update an order in BC
	 */
	public function putOrderAction(Request $request, Application $app) {

		$request_data = $this->initRequestData($request);

		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('order');

		$bcModel = new Order($wombat_data,'wombat');
		$bc_data = $bcModel->getBigCommerceObject('update');

		//return print_r($bc_data,true);

		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		$response = $client->put('orders/'.$wombat_data['id'],$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 200) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The order ".$wombat_data['id']." was updated in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/*
	
		CUSTOMER ACTIONS

	*/

	/**
	 * Get a list of customers from BigCommerce
	 */
	public function getCustomersAction(Request $request, Application $app) {

		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);
		$response = $client->get('customers', array('query' => $request_data['parameters']));
		$response_status = intval($response->getStatusCode());

		// Response
		if($response_status === 200) {
			
			// get customers
			$bc_data = $response->json(array('object'=>TRUE));
			$wombat_data = array();
			if(!empty($bc_data)) {
				foreach($bc_data as $bc_customer) {
					$bc_customer->_store_url = $request_data['store_url'];
					$wombatModel = new Customer($bc_customer, 'bc');
					//$wombatModel->loadAttachedResources($client);
					$wombat_data[] = $wombatModel->getWombatObject();
				}
			}

			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'request_results' => count($wombat_data),
				'parameters' => $request_data['parameters'],
				'customers' => $wombat_data
			);
			return $app->json($response, 200);
			
		} else if($response_status === 204) { // successful but empty (no results)
		
			return $app->json($this->emptyResponse($request_data,'customers'), 200);
			
		} else { // error
			throw new \Exception($request_data['request_id'].': Error received from BigCommerce '.$response->getBody(),500);			
		}
	}

	public function postCustomerAction(Request $request, Application $app) {
		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('customer');
		
		$bcModel = new Customer($wombat_data,'wombat');
		$bc_data = $bcModel->getBigCommerceObject('create');

		
		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		//return $options['body'].PHP_EOL;
		
		$response = $client->post('customers',$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 201) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The customer $bc_data->first_name $bc_data->last_name was created in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/**
	 * Update a customer in BC
	 */
	public function putCustomerAction(Request $request, Application $app) {

		$request_data = $this->initRequestData($request);

		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('customer');

		$bcModel = new Customer($wombat_data,'wombat');
		$bc_data = $bcModel->getBigCommerceObject('update');

		//return print_r($bc_data,true);

		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		$response = $client->put('customers/'.$wombat_data['id'],$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 200) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The customer ".$wombat_data['firstname']." ".$wombat_data['lastname']." was updated in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/*
	
		SHIPMENT ACTIONS

	*/

	/**
	 * Get a list of shipments from BigCommerce
	 *
	 * Can be filtered by Order ID
	 */
	public function getShipmentsAction(Request $request, Application $app) {
		
		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$order_ids = array();
		$shipments = array();

		//if we've been given an order_id, just grab shipments for that order, otherwise, construct a list of orders
		// @todo: call the getOrdersAction to do this?
		if(!array_key_exists('order_id', $request_data['parameters'])) {

			//	Grab lists of orders that are 'shipped' or 'partially shipped' and merge them
			//	(BC API doesn't have filter logic, so we have to do them separately)
			

			// @todo: get the status IDs from the BC API?
			$request_data['parameters']['status_id'] = '2'; // shipped
			$response = $client->get('orders', array('query' => $request_data['parameters']));
			if(intval($response->getStatusCode()) === 200) {
				$bc_data = $response->json(array('object'=>TRUE));
				if(!empty($bc_data)) {
					foreach($bc_data as $bc_order) {
						$order_ids[] = $bc_order->id;
					}
				}
			}

			$request_data['parameters']['status_id'] = '3'; // partially shipped
			$response = $client->get('orders', array('query' => $request_data['parameters']));
			if(intval($response->getStatusCode()) === 200) {
				$bc_data = $response->json(array('object'=>TRUE));
				if(!empty($bc_data)) {
					foreach($bc_data as $bc_order) {
						if(!in_array($bc_order->id, $order_ids)) { // double check we don't have duplicates
							$order_ids[] = $bc_order->id;
						}
					}
				}
			}

		} else {
			$order_ids[] = $request_data['parameters']['order_id'];
		}
		
		foreach($order_ids as $order_id) {

			$response = $client->get('/api/v2/orders/'.$order_id.'/shipments', array('query' => $request_data['parameters']));
			$response_status = intval($response->getStatusCode());

			// Response
			if($response_status === 200) {
				
				// get customers
				$bc_data = $response->json(array('object'=>TRUE));
				$wombat_data = array();
				if(!empty($bc_data)) {
					foreach($bc_data as $bc_shipment) {
						$bc_shipment->_store_url = $request_data['store_url'];
						$wombatModel = new Shipment($bc_shipment, 'bc');
						//$wombatModel->loadAttachedResources($client);
						$wombat_data[] = $wombatModel->getWombatObject();
					}
					
					$shipments = array_merge($shipments,$wombat_data);
					
				}
				
			} else if($response_status === 204) { // successful but empty (no results)
				
				// do nothing?
				// @todo: rework response status logic checking
				
			} else { // error
				throw new \Exception($request_data['request_id'].': Error received from BigCommerce '.$response->getBody(),500);			
			}
		}

		if(!empty($shipments)) {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'request_results' => count($shipments),
				'parameters' => $request_data['parameters'],
				'shipments' => $shipments
			);
			return $app->json($response, 200);
		} else {
			return $app->json($this->emptyResponse($request_data,'shipments'), 200);
		}
	}

	/**
	 * Get an individual shipment from BC
	 *
	 * Unlike getShipmentsAction, this function targets an individual shipment, and must receive an Order and Shipment ID
	 *
	 * This is for internal debugging, and has no Wombat translation function
	 */
	public function getShipmentAction(Request $request, Application $app) {
		
		$request_data = $this->initRequestData($request);
		$order_id = 		$request->request->get('order_id');
		$shipment_id = 	$request->request->get('shipment_id');

		$client = $this->legacyAPIClient($request_data['legacy_api_info']);
		$response = $client->get("orders/$order_id/shipments/$shipment_id", array('query' => $request_data['parameters']));
		$response_status = intval($response->getStatusCode());

		// Response
		if($response_status === 200) {
			
			// get products
			$bc_data = $response->json(array('object'=>TRUE));
			// $wombat_data = array();
			// if(!empty($bc_data)) {
			// 	foreach($bc_data as $bc_product) {
			// 		$bc_product->_store_url = $request_data['store_url'];
			// 		$wombatModel = new Shipment($bc_product, 'bc');
			// 		//$wombatModel->loadAttachedResources($client);
			// 		$wombat_data[] = $wombatModel->getWombatObject();
			// 	}
			// }

			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'request_results' => 1,
				'parameters' => $request_data['parameters'],
				'shipment' => $bc_data
			);
			return $app->json($response, 200);
			
		} else if($response_status === 204) { // successful but empty (no results)
		
			return $app->json($this->emptyResponse($request_data,'products'), 200);
			
		} else { // error
			
			throw new \Exception($request_data['request_id'].': Error received from BigCommerce '.$response->getBody(),500);
			
		}
	}

	public function postShipmentAction(Request $request, Application $app) {
		$request_data = $this->initRequestData($request);
		
		$client = $this->legacyAPIClient($request_data['legacy_api_info']);

		$wombat_data = $request->request->get('shipment');
		
		$bcModel = new Shipment($wombat_data,'wombat');
		$bcModel->loadAttachedResources($client);
		$bc_data = $bcModel->getBigCommerceObject('create');

		
		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		//return $options['body'].PHP_EOL;
		
		$response = $client->post("/api/v2/orders/$bc_data->order_id/shipments",$options);
		// @todo: the Guzzle client will intervene with its own error response before we get to our error below,
		// make it not do that or catch an exception rather than checking code

		if($response->getStatusCode() != 201) {
			throw new Exception($request_data['request_id'].":Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_data['request_id'],
				'summary' => "The shipment for order $bc_data->order_id was created in BigCommerce",
				);
			return $app->json($response,200);
		}
	}

	/*

		SUPPORT FUNCTIONS

	*/

	/**
	 * Perform common initialization tasks for actions
	 */
	private function initRequestData(Request $request) {
		// Input
		$request_id = $request->request->get('request_id');
		$parameters = $request->request->get('parameters');

		// Legacy API connection
		$legacy_api_info = array(
			'username' => urldecode($parameters['api_username']),
			'path' => urldecode($parameters['api_path']),
			'token' => urldecode($parameters['api_token'])
		);
		foreach(array('api_username','api_path','api_token') as $api_info) 
			unset($parameters[$api_info]);
		$store_url = str_replace(array('/api/v2/','/api/v2'),'',$legacy_api_info['path']);

		return array(
			'request_id' => $request_id,
			'parameters' => $parameters,
			'legacy_api_info' => $legacy_api_info,
			'store_url' => $store_url,
			);
	}

	/**
	 * Return a response for successful but empty queries
	 */
	private function emptyResponse($request_data, $type) {
		//return our success code & data
		$response = array(
			'request_id' => $request_data['request_id'],
			'request_results' => 0,				
			'parameters' => $request_data['parameters'],
			$type => array()
		);
		return $response;
	}

	/**
	 * Check the request headers for proper authorization tokens from Wombat
	 */
	private function authorizeWombat(Request $request, Application $app) {
		$wombat_store = $app['wombat_store'];
		$wombat_token = $app['wombat_token'];

		if($wombat_store != $request->headers->get('X-Hub-Store') ||
			 $wombat_token != $request->headers->get('X-Hub-Token')) {
			throw new \Exception('Unauthorized!', 401);
		}
	}

	private function legacyAPIClient($connection)
	{
		// legacy connection data
		$connection = (object) $connection; // support arrays

		//set up a request client
		$client = new Client(array(
			'base_url' => rtrim($connection->path,'/').'/',
			'defaults' => array(
				'auth' => array($connection->username, $connection->token),
				'headers' => array( 'Accept' => 'application/json' )
			)
		));
		
		return $client;
	}
	
	private function testLegacyAPIClient($client)
	{
		$response = $client->get('time');

		if($response->getStatusCode() != 200) {
			throw new Exception("$request_id:Error received from BigCommerce ".$response->getBody(),500);
		}
	}

	/*

		UNIMPLEMENTED ACTIONS

	*/

	//add
	
	//update

	public function putShipmentAction(Request $request, Application $app) {
	}

	//cancel
	public function cancelOrderAction(Request $request, Application $app) {
	}

	//set

	/**
	 * Construct a BC request URL
	 */

	private function constructBCUrl($path,$parameters,$app) {
		return $app['bc_api_base'].$parameters->user->context.$path;
	}
	/**
	 * Set up a get request via Guzzle, using the parameters we need to connect to BC
	 *
	 * todo: consider moving this to a BC helper class, use injection via the main app
	 */

	private function getGetRequestClient($url,$parameters,$app) {
		//set up a client to send the request
		$client = new Client();
		$req = $client->get($url, array(
			'query' => $parameters['query'], //todo: make sure query params match BC ones
			'headers' => array(
				'X-Auth-Client' => $app['client_id'],
				'X-Auth-Token' => $parameters->user->access_token,
				'Accept' => 'application/json',
				),
		));
	}

}