<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Sprout\Wombat\Entity\UserPersister;

/**
 * Persister service
 */
$app['user.persister'] = $app->share(function ($app) {
    return new UserPersister($app['user.persist_path']);
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
    $parts = explode(":", $e->getMessage());

    $response = array(
    	'request_id' => isset($parts[0]) ? $parts[0] : NULL,
    	'summary' => isset($parts[1]) ? $parts[1] : NULL,
    	);
    	
    return $app->json($response,$code); 
});
return $app;