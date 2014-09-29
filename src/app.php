<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Sprout\Wombat\Entity\UserPersister;

// set the timezone to UTC to avoid timezone warnings
date_default_timezone_set('UTC');

/**
 * Persister service
 */
$app['user.persister'] = $app->share(function ($app) {
    return new UserPersister($app['user.persist_path']);
});

/**
* Get an access token for a given wombat store
*/
class WombatToken
{
    private $tokens; 

    function __construct($tokens) {
        $this->tokens = $tokens;
    }
    function getToken($store) {
        return $this->tokens[$store];
    }
}

$app['wombat.token'] = function ($app) {
    return new WombatToken($app['wombat_tokens']);
};

/**
 * Get a BC store hash from a store URL
 */
$app['bc.storehash'] = $app->protect(function ($store_url) {
    
    $parts = explode('.', str_replace('https://', '', $store_url));
    $hash = str_replace('store-','',$parts[0]);

    return $hash;
});

/**
 * Check for Wombat's authorization headers
 */
$wombat_auth = function (Request $request) use ($app){

    $wombat_store = $app['wombat_store'];
    $wombat_token = $app['wombat_token'];
    
    if($wombat_store != $request->headers->get('X-Hub-Store') ||
         $wombat_token != $request->headers->get('X-Hub-Token')) {
        throw new \Exception('Unauthorized!', 401);
    }
    
};

/**
 * Check that a request from Wombat includes non-empty parameters to connect to BC
 */
$wombat_includes_bc_auth = function(Request $request) {
    $parameters = $request->request->get('parameters');
    if( empty($parameters['api_username']) ||
        empty($parameters['api_path']) ||
        empty($parameters['api_token'])) {
        throw new \Exception($request->request->get('request_id').':Missing authorization values', 500);
    }
};

/**
 * Set up requests to automatically decode json if header present
 */
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

/**
 * Errors
 */

$app->error(function (\Exception $e, $code) use($app) {
		if ($app['debug']) {
        return;
    }
    $parts = explode(":::::", $e->getMessage());

    $message = isset($parts[1]) ? $parts[1] : NULL;
    $external_response = (count($parts)>2)?json_decode($parts[2]):array();
    //echo "EXT: ".print_r($external_response,true).PHP_EOL;

    $additional_details = "";
    if(count($external_response)) {
        $additional_details .= " Additional details: ";
    }
    foreach ($external_response as $key => $ext) {
        $additional_details .= "$key : ";
        if(isset($ext->status)) {
            $additional_details .= "Status: $ext->status ";
        }
        if(isset($ext->message)) {
            $additional_details .= "Message: $ext->message ";   
        }
        if(isset($ext->details)) {
            $additional_details .= "Details: ";
            if(is_array($ext->details) || is_object($ext->details)) {
                $details = (array) $ext->details;
                foreach($details as $k => $v) {
                    $additional_details .= "$k : $v ";
                }
            }
        }
    }

    $response = array(
    	'request_id' => isset($parts[0]) ? $parts[0] : NULL,
    	'summary' => $message.$additional_details,
    	);
    	
    return $app->json($response,$code); 
});
return $app;