<?php

namespace Sprout\Wombat\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

use Sprout\Wombat\Entity\User;
use Sprout\Wombat\Entity\Product;

class WombatController {

	protected $request_id;

	//get

	public function getProductsAction(Request $request, Application $app) {
		$request_id = $request->request->get('request_id');
		$parameters = $request->request->get('parameters');

		if(empty($parameters->user->access_token)) {
			throw new Exception("$request_id:User access_token required",500);
		}

		//contstruct the url from the user context
		$bc_url = $app['bc_api_base'].$parameters->user->context.'/v2/products';

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

	private function getProductImages($resource,$parameters) {

	}
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
	public function getOrdersAction(Request $request, Application $app) {
	}
	public function getShipmentsAction(Request $request, Application $app) {
	}
	public function getCustomersAction(Request $request, Application $app) {
	}

	//add
	public function postProductAction(Request $request, Application $app) {
	}
	public function postOrderAction(Request $request, Application $app) {
	}
	public function postShipmentAction(Request $request, Application $app) {
	}
	public function postCustomerAction(Request $request, Application $app) {
	}

	//update
	public function putProductAction(Request $request, Application $app) {
	}
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

}