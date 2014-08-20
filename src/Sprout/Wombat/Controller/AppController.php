<?php

namespace Sprout\Wombat\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client as Client;

use Sprout\Wombat\Entity\User;

class AppController {

	private $payload;

	/**
	 * Receive a BigCommerce authorization callback request, will contain:
	 * code:	Temporary code to exchange for an access token
	 * scope:	List of authorization scopes
	 * context:	Base path for the authorized store context, in the format: stores/{store_hash}
	 */
	public function callbackAction(Request $request, Application $app) {

		// construct our payload to send to BC to receive a long-term token
		$this->payload = array(
			'client_id' => $app['client_id'],
			'client_secret' => $app['client_secret'],
			'redirect_uri' => $app['callback_url'],
			'grant_type' => 'authorization_code',
			'code' => $request->get('code'),
			'scope' => $request->get('scope'),
			'context' => $request->get('context'),
		);
		
		//set up a client to send the request
		$client = new Client();
		
		$resp = $client->post($app['bc_auth_service'].'/oauth2/token', array(
			'body' => $this->payload,
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json'
			)
		));

		if ($resp->getStatusCode() == 200) {

			echo '<textarea>';
			echo $resp->getBody();
			echo '</textarea>';

			//response was good. Get the data and store it for later
			$data = $resp->json();
			
			
			
			// list($context, $storeHash) = explode('/', $data['context'], 2);
			// $key = getUserKey($storeHash, $data['user']['email']);

			// $redis = new Credis_Client('localhost');
			// $redis->set($key, json_encode($data['user'], true));

			$info = new User($data);
			$persister = $app['user.persister'];
			$persister->persist($info);

			return $app->json($data);
		} else {
			return 'Something went wrong... ['.$resp->getStatusCode().'] '.$resp->getBody();	
		}
		
	}

	public function loadAction(Request $request, Application $app) {
		
		$signed_payload = $request->get('signed_payload');
		
		if(is_null($signed_payload)) {
			return 'Invalid signed_payload.';
		}
		
		$data = $this->parse_signed_request($signed_payload, $app);
		
		if (empty($data)) {
			return 'Invalid signed_payload.';
		}
		
		// $redis = new Credis_Client('localhost');
		// $key = getUserKey($data['store_hash'], $data['user']['email']);
		// $user = json_decode($redis->get($key), true);
		$persister = $app['user.persister'];
		$user = $persister->retrieve();

		if (empty($user)) {
			error_log('Invalid user.');
		}
		return 'Welcome '.json_encode($user);
	}

	public function uninstallAction(Request $request, Application $app) {
		//todo: delete user's data
		return $app->json("Ok",200);
	}

	/**
	 * Test actions
	 */
	
	public function helloAction(Request $request, Application $app) {
		$page = $request->query->get('page', 1);

		$response = array(
			'hello' => 'Hello there! '.$page,
			);

		return $app->json($response,200);

	}

	public function indexAction(Request $request, Application $app)
	{
		include(WOMBAT_VIEW_ROOT.'/header.php');
		
		include(WOMBAT_VIEW_ROOT.'/index.php');
		
		include(WOMBAT_VIEW_ROOT.'/footer.php');
		
		return $app->json(array('Ok'),200);
	}
	

	public function persistAction(Request $request, Application $app) {
		
		$attributes = array(
			'access_token' => $request->request->get('access_token'),
			'scope' => $request->request->get('scope'),
			'user' => $request->request->get('user'),
			'context' => $request->request->get('context'),
		);

		$user = new User($attributes);
		//return $app->json(print_r($user->getAttributes(),true),200);		
		$persister = $persister = $app['user.persister'];
		$persister->persist($user);

		return $app->json(array('Ok'),200);

	}

	public function retrieveAction(Request $request, Application $app) {
		$persister = $app['user.persister'];
		$user = $persister->retrieve();

		return $app->json($user->getAttributes(),200);
	}

	private function parse_signed_request($signed_request, $app)
	{
		list($this->payload, $encoded_sig) = explode('.', $signed_request, 2); 
		
		// decode the data
		$sig = base64_decode($encoded_sig);
		$data = json_decode(base64_decode($this->payload), true);
		
		// confirm the signature
		$expected_sig = hash_hmac('sha256', $this->payload, $app['client_secret'], $raw = true);
		if ($this->time_strcmp($sig, $expected_sig)) {
			error_log('Bad Signed JSON signature!');
			return null;
		}

		return $data;
	}

	private function time_strcmp($str1, $str2)
	{
		$res = $str1 ^ $str2;
		$ret = strlen($str1) ^ strlen($str2); //not the same length, then fail ($ret != 0)
		for($i = strlen($res) - 1; $i >= 0; $i--)
			$ret += ord($res[$i]);
	  	return !$ret;
	}
}