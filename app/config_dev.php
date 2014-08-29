<?php

$app['bc_auth_service']	= "https://login.bigcommerce.com";
$app['client_id'] 			= "dzpjcpk8qgbfkb3ddfxwaz8annm2uf3";
$app['client_secret'] 	= "5ibdx94oike7uwrywpa69km7n1z4l4m";
$app['callback_url']		= "http://walrusk.net/oauth";

$app['bc_api_base'] 		= "https://api.bigcommerce.com";

//Wombat authentication tokens
$app['wombat_store']		= "53cffa32776f6d6e34740000";
$app['wombat_token']		= "db4ebe193caad7c75589bc9a865a4d6925dad15f50e4ca69";

//For the basic file-based user persistence, set a local directory to save in
$app['user.persist_path'] = '/tmp/sprout';