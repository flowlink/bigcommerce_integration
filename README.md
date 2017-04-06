# BigCommerce FlowLink



Connect your BigCommerce store to FlowLink

###Installation

Run Composer:
```shell
php composer.phar install
```

###Configuration:
In order to store BigCommerce object IDs in FlowLink on creation, add your FlowLink authorization tokens to app/config.php
```php
$app['flowlink_tokens'] 	= array(
	'your store id' => 'your store access token',
	);
```

# About FlowLink

[FlowLink](http://flowlink.io/) allows you to connect to your own custom integrations.
Feel free to modify the source code and host your own version of the integration
or better yet, help to make the official integration better by submitting a pull request!

This integration is 100% open source an licensed under the terms of the New BSD License.

![FlowLink Logo](http://flowlink.io/wp-content/uploads/logo-1.png)
