# BigCommerce Wombat



Connect your BigCommerce store to Wombat

###Installation

Run Composer:
```shell
php composer.phar install
```

###Configuration:
In order to store BigCommerce object IDs in Wombat on creation, add your Wombat authorization tokens to app/config.php
```php
$app['wombat_tokens'] 	= array(
	'your store id' => 'your store access token',
	);
```