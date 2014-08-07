<?php

$app['bc_auth_service']	= "https://login.bigcommerce.com";
$app['client_id'] 			= "4icfr35o64ss4rdifqeedqt8o8u9pos";
$app['client_secret'] 	= "g8ujaxw6visl5mknsq0uym1fshp275a";
$app['callback_url']		= "http://107.170.78.240/auth/callback";

$app['bc_api_base'] 		= "https://api.bigcommerce.com";


//For the basic file-based user persistence, set a local directory to save in
$app['user.persist_path'] = '/tmp/sprout';