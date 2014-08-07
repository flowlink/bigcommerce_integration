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

class WombatController {

	protected $request_id;

	/**
	 * Get a list of products from BC
	 */
	public function getProductsAction(Request $request, Application $app) {
		$request_id = $request->request->get('request_id');
		$parameters = $request->request->get('parameters');

		if(empty($parameters->user->access_token)) {
			throw new Exception("$request_id:User access_token required",500);
		}

		//contstruct the url from the user context
		$bc_url = $this->constructBCUrl('/v2/products',$parameters,$app);

		//set up a request client for a get request & send
		$client = $this->getGetRequestClient($bc_url,$parameters,$app);
		$resp = $client->send($req);

		//if our response was ok, construct an array of Wombat-formatted product objects
		if($response->getStatusCode() != 200) {
			throw new Exception("$request_id:Error received from BigCommerce ".$response->getBody(),500);
		} else {
			$bc_data = $response->json();
			$wombat_data = array();
			
			foreach($bc_data as $bc_product) {
				$prod = new Product($bc_product);
				$wombat_data[] = $prod->getWombatData();
			}

			//return our success code & data
			$response = array(
				'request_id' => $request_id,
				'products' => $wombat_data,
				);
			$app->json($response,200);
		}
	}

	/**
	 * Set up a sub-query to get BC images for a product,
	 * using the product's image resource path, supplied with the main
	 * product data
	 */
	private function getProductImages($resource,$parameters) {

	}

	/**
	 * Post a product to BC
	 */
	public function postProductAction(Request $request, Application $app) {
		$request_id = $request->request->get('request_id');
		$parameters = $request->request->get('parameters');

		if(empty($parameters->user->access_token)) {
			throw new Exception("$request_id:User access_token required",500);
		}

		//contstruct the url from the user context
		$bc_url = $this->constructBCUrl('/v2/products',$parameters,$app);


		//construct the BC data from the Wombat
		$wombat_data = $response->json();
		$bc_data = array();
			
		foreach($wombat_data as $wombat_product) {
			$prod = new Product($wombat_product);
			$bc_data[] = $prod->getBigCommerceData();
		}
		$parameters['payload'] = $bc_data;

		//set up a request client for a get request & send
		$client = $this->getPostRequestClient($bc_url,$parameters,$app);
		$resp = $client->send($req);


		if($response->getStatusCode() != 201) {
			throw new Exception("$request_id:Error received from BigCommerce ".$response->getBody(),500);
		} else {
			//return our success code & data
			$response = array(
				'request_id' => $request_id,
				'products' => $wombat_data,
				);
			$app->json($response,200);
		}
	}

	/**
	 * Update a product in BC
	 */
	public function putProductAction(Request $request, Application $app) {
	}
	
	public function getOrdersAction(Request $request, Application $app) {
	}
	public function getShipmentsAction(Request $request, Application $app) {
	}
	public function getCustomersAction(Request $request, Application $app) {
	}

	//add
	
	public function postOrderAction(Request $request, Application $app) {
	}
	public function postShipmentAction(Request $request, Application $app) {
	}
	public function postCustomerAction(Request $request, Application $app) {
	}

	//update
	
	public function putOrderAction(Request $request, Application $app) {
	}
	public function putShipmentAction(Request $request, Application $app) {
	}
	public function putCustomerAction(Request $request, Application $app) {
	}

	//cancel
	public function cancelOrderAction(Request $request, Application $app) {
	}

	//set
	public function setInventoryAction(Request $request, Application $app) {
	}

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