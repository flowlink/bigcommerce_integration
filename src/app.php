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
    	'request_id' => $parts[0],
    	'summary' => $parts[1],
    	);
    return $app->json($response,$code); 
});
return $app;