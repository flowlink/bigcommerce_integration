<?php

$app['bc_auth_service']	= "https://login.bigcommerce.com";
$app['client_id'] 			= "dzpjcpk8qgbfkb3ddfxwaz8annm2uf3";
$app['client_secret'] 	= "5ibdx94oike7uwrywpa69km7n1z4l4m";
$app['callback_url']		= "http://walrusk.net/oauth";

$app['bc_api_base'] 		= "https://api.bigcommerce.com";

//For the basic file-based user persistence, set a local directory to save in
$app['user.persist_path'] = '/tmp/sprout';