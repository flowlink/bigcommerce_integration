<?php

namespace Sprout\Wombat\Entity;

class Shipment {

	/**
	 * @var array $data Hold the JSON object data retrieved from the source
	 */
	protected $data;
	
	/**
	 * @var array $client Http client object to perform additional requests
	 */
	private $client;

	/**
	 * @var array $request_data Data about the request that we've been sent
	 */
	private $request_data;

	/**
	 * @var array $order_products The products associated with the order, fetched from BigCommerce
	 */
	private $order_products;

	public function __construct($data, $type='bc',$client,$request_data) {
		$this->data[$type] = $data;
		$this->client = $client;
		$this->request_data = $request_data;
	}


	/**
	 * Push this data to BigCommerce (BC)
	 */
	public function push() {
		$client = $this->client;
		$request_data = $this->request_data;
		
		$order_id = $this->getBCID('order');
		$id = $this->getBCID('shipment');

		//format our data for BC	
		$action = ($id)? 'update':'create';
		$bc_data = $this->getBigCommerceObject($action);
		$options = array(
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		//if there's an existing BC ID, then update
		if($id) {
			try {
				$response = $client->put("orders/$order_id/shipments/$id",$options);
			} catch (\Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce:::::".$e->getResponse()->getBody(),500);
			}
		} else {

			//no ID found, so create a new shipment
			try {
				$response = $client->post("orders/$order_id/shipments",$options);
			} catch (\Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce:::::".$e->getResponse()->getBody(),500);
			}
		}

		$shipment = $response->json(array('object'=>TRUE));
		$return_data = $this->getWombatResponse($shipment);

		//$this->updateOrderStatus($order_id);

		$result = "The shipment was ".($id ? 'updated' : 'created')." in BigCommerce";
		return $result;

	}

	/**
	 * Update the order status after creating or updating a shipment
	 */
	public function updateOrderStatus($order_id) {
		$client = $this->client;

		try {
			$response = $client->get("orders/{$order_id}/products");
		}
		catch (\Exception $e) {
			$this->doException($e, 'fetching order line items to check order fulfillment status');
		}

		$items = $response->json(array('object'=>TRUE));
		$count = 0; 		//total items
		$shipped = 0;		//shipped items

		foreach ($items as $item) {
			$count += $item->quantity;
			if($item->quantity == $item->quantity_shipped) {
				$shipped++;
			}
		}

		$statuses = $this->getOrderStatuses();

		if($shipped >= $count) {
			$status_id = $statuses['Shipped'];
		} else if ($shipped > 0 && $shipped < $count) {
			$status_id = $statuses['Partially Shipped'];
		}

		$order_update = (object) array(
			'status_id' => $status_id,
		);
		$client_options = array(
			'body' => json_encode($order_update),
			);
		try {
			$response = $client->put("orders/{$order_id}",$client_options);
		}
		catch (\Exception $e) {
			$this->doException($e, 'updating order status');
		}
	}

	/**
	 * Get all order statuses
	 */
	public function getOrderStatuses() {
		$client = $this->client;

		try {
			$response = $client->get("order_statuses");
		}
		catch (\Exception $e) {
			$this->doException($e, 'fetching order statuses');
		}

		$statuses = $response->json(array('object'=>TRUE));
		$output = array();

		foreach ($statuses as $status) {
			$output[$status->name] = $status;
		}

		return $output;
	}


	/**
	 * Get a response object to send back to Wombat after creating an item in BC
	 */
	public function getWombatResponse($bc_obj) {
		$wombat_original = $this->data['wombat'];
		$wombat_response = new \stdClass();
		
		foreach ($wombat_original as $key => $value) {
			
			if($key == 'items') {
				$wombat_response->items = array();
				foreach($value as $item) {
					$wombat_response->items[] = (object) $item;
				}
			} else if($key == 'shipping_address') {
				$wombat_response->shipping_address = (object) $value;
				$wombat_response->shipping_address->bigcommerce_id = $bc_obj->order_address_id;
			} else {
				$wombat_response->{$key} = $value;
			}
		}
		$wombat_response->bigcommerce_id = $bc_obj->id;
		$wombat_response->updated_at = (new \DateTime())->format('c');
		return $wombat_response;
	}

	/*
		Wombat attributes:
		id	Unique identifier for the shipment	String
		order_id	Unique indentifier for the order	String
		email	Customers email address	String
		cost	TODO: Total cost for the shipment	String
		status	Current shipment status	String
		stock_location	The stock location from where to ship	String
		shipping_method	The chosen shipping method	String
		tracking	Tracking number for the package	String
		updated_at	When the shipment was last updated	Date
		shipped_at	When the package was shipped	Date
		shipping_address	Customers shipping address	Address
		items	Array of the package’s items	LineItem Array
	*/

	/**
	 * Get a Wombat-formatted set of data from a BigCommerce one.
	 */
	public function getWombatObject() {
		if(isset($this->data['wombat']))
			return $this->data['wombat'];
		else if(isset($this->data['bc']))
			$bc_obj = (object) $this->data['bc'];
		else
			return false;

		/*** WOMBAT OBJECT ***/
		$wombat_obj = (object) array(
			'id' 							=> strtoupper($this->getHashId($bc_obj->id)),
			'order_id'				=> strtoupper($this->getHashId($bc_obj->order_id)),
			'email' 					=> !empty($bc_obj->shipping_address->email)?$bc_obj->shipping_address->email:$bc_obj->billing_address->email, // @todo: maybe just one of these, or get from customer profile
			'shipping_method'	=> $bc_obj->shipping_method,
			'updated_at' 			=> date(\DateTime::ISO8601,strtotime($bc_obj->date_created)),
			'shipped_at'			=> date(\DateTime::ISO8601,strtotime($bc_obj->date_created)),
			'status'					=> 'ready',
			'stock_location'	=> 'default',
			'bigcommerce_id'	=> $bc_obj->id,
			);

		$wombat_obj->shipping_address = (object) array(
			'firstname'				=> $bc_obj->shipping_address->first_name,
      'lastname'				=> $bc_obj->shipping_address->last_name,
      'address1'				=> $bc_obj->shipping_address->street_1,
      'address2'				=> $bc_obj->shipping_address->street_2,
      'city'						=> $bc_obj->shipping_address->city,
      'state'						=> $bc_obj->shipping_address->state,
      'country' 				=> $bc_obj->shipping_address->country_iso2,
      'phone' 					=> $bc_obj->shipping_address->phone,
      'bigcommerce_id' 	=> $bc_obj->order_address_id,
			);

		$items = $this->getOrderProducts($bc_obj->order_id);
		foreach($items as $item) {
			$wombat_obj->items[] = (object) array(
				'name' 										=> $item->name,
        'product_id' 							=> $item->sku,
        'quantity' 								=> $item->quantity,
        'price' 									=> (float) number_format($item->total_inc_tax, 2, '.', ''),
        'options'									=> new \stdClass(), // @todo: map this? wombat docs seem to ignore it
        'bigcommerce_id'					=> $item->id,
        'bigcommerce_product_id'	=> $item->product_id,
				);
		}

		$this->data['wombat'] = $wombat_obj;
		return $wombat_obj;

	}

	/**
	 * Get a BigCommerce-formatted set of data from a Wombat one.
	 */
	public function getBigCommerceObject($action = 'create') {
		if(isset($this->data['bc']))
			return $this->data['bc'];
		else if(isset($this->data['wombat']))
			$wombat_obj = (object) $this->data['wombat'];
		else
			return false;

		/*
			BC required:

			order_address_id
			items
		*/

		$bc_obj = (object) array(
			'order_address_id' => $this->getOrderAddressId($wombat_obj->shipping_address),
			'tracking_number' => $wombat_obj->tracking,
			'shipping_method' => $wombat_obj->shipping_method,
			'comments' => '',
			);
		
		// BC forbids items on upate
		if($action == 'create') {
			foreach($wombat_obj->items as $item) {
				$bc_obj->items[] = $this->prepareBCLineItem($item);
			}
		}

		$this->data['bc'] = $bc_obj;
		
		return $bc_obj;
	}

	/**
	 * process a Wombat line item into a BC one
	 */
	public function prepareBCLineItem($item) {
		
		if(!empty($item['bigcommerce_id'])) {
			$order_product_id = $item['bigcommerce_id'];
		} else {
			$order_product_id = $this->getBCOrderProductID($item);
		}

		if(!$order_product_id) {
			$this->doException(null,"Could not find an order_product_id (line item id) matching item {$item->product_id}");
		}

		return (object) array(
				'order_product_id' => $order_product_id,
				'quantity' => $item['quantity'],
			);
	}

	/**
	 * Get a BC order_product_id (line item id) when it hasn't been included in our data
	 */
	public function getBCOrderProductID($item) {
		$order_products = $this->getOrderProducts();
		$id = 0;

		foreach($order_products as $order_product) {
			if($item['product_id'] == $order_product->sku) {
				$id = $order_product->id;
			}
		}

		return $id;
	}

	/**
	 * Get a BC order address ID from a Wombat shipping address
	 *
	 * Either extract a passed bigcommerce_id, or fetch address from BC & match
	 */
	public function getOrderAddressId($address) {
		if(!empty($address['bigcommerce_id'])) {
			
			$address_id = $address['bigcommerce_id'];

		} else {
			
			$client = $this->client;

			$order_id = $this->getBCID('order');
		
			try {
				$response = $client->get("orders/$order_id/shipping_addresses");
			} catch (\Exception $e) {
				$this->doException($e,'retrieving shipping addresses');
			}

			if(intval($response->getStatusCode()) === 200) {
				$addresses = $response->json(array('object'=>TRUE));
			} else {
				$this->doException(null,'could not find shipping addresses in BigCommerce, and none was provided');
			}

			foreach($addresses as $address) {
				if(
					$address->first_name 		== $wombat_obj->shipping_address['firstname']	&&
					$address->last_name 		== $wombat_obj->shipping_address['lastname']	&&
					$address->street_1 			== $wombat_obj->shipping_address['address1']	&&
					$address->street_2 			== $wombat_obj->shipping_address['address2']	&&
					$address->city 					== $wombat_obj->shipping_address['city']			&&
					$address->state 				== $wombat_obj->shipping_address['state']			&&
					$address->country_iso2 	== $wombat_obj->shipping_address['country']		&&
					$address->zip 					== $wombat_obj->shipping_address['zipcode']
					) {
					$address_id = $address->id;
				}
				
			}
		}

		return $address_id;
	}
	/**
	 * get required IDs from BigCommerce to be able to push a new shipment
	 */
	public function prepareBCResources() {
		$client = $this->client;
		$request_data = $this->request_data;

		//retrieve shipping addresses already associated with the order_id
		$wombat_obj = (object)$this->data['wombat'];
		$order_id = $this->getBCID('order');
		
		try {
			$response = $client->get("orders/$order_id/shipping_addresses");
		} catch (\Exception $e) {
			$this->doException($e,'retrieving shipping addresses');
		}
					
		if(intval($response->getStatusCode()) === 200)
			//$this->data['bc']->$resource_name = $response->json(array('object'=>TRUE));
			$addresses = $response->json(array('object'=>TRUE));
		else
			//$this->data['bc']->$resource_name = NULL;
			$addresses = NULL;

		//check each address against the one we've been passed, use the ID from the first one that matches
				
		if(is_array($addresses)) {
			foreach($addresses as $address) {
				if(
					$address->first_name 		== $wombat_obj->shipping_address['firstname']	&&
					$address->last_name 		== $wombat_obj->shipping_address['lastname']	&&
					$address->street_1 			== $wombat_obj->shipping_address['address1']	&&
					$address->street_2 			== $wombat_obj->shipping_address['address2']	&&
					$address->city 					== $wombat_obj->shipping_address['city']			&&
					$address->state 				== $wombat_obj->shipping_address['state']			&&
					$address->country_iso2 	== $wombat_obj->shipping_address['country']		&&
					$address->zip 					== $wombat_obj->shipping_address['zipcode']
					) {
					$this->data['wombat']['_order_address_id'] = $address->id;
				}
				
			}
		}
		
		if(empty($this->data['wombat']['_order_address_id'])) {
			$this->doException($e,'matching provided shipping address');
		}

		// get order products for the order_product_id
		$products = $this->getOrderProducts($order_id);

		// go through the resulting products and match the product_ids to get the order_product_id
		if(!empty($products)) {
			foreach($products as $product) {
				
				foreach($this->data['wombat']['items'] as $index => $item) {
					if($product->product_id == $item['product_id']) {
						$this->data['wombat']['items'][$index]['_order_product_id'] = $product->id;
					}
				}

			}
		}
	}

	/**
	 * Fetch the order's order products (line items) from BC
	 */
	public function getOrderProducts($order_id = 0) {
		$client = $this->client;
		
		if(!$order_id) {
			$order_id = $this->getBCID('order');
		}

		if(empty($this->order_products)) {
			$this->order_products = array();

			try {
				$response = $client->get("orders/$order_id/products");
			} catch (\Exception $e) {
				$this->doException($e,'retrieving line items');
			}

			if(intval($response->getStatusCode()) === 200) {
				
				$this->order_products = $response->json(array('object'=>TRUE));
			}
		}

		return $this->order_products;
		
	}

	/**
	 * Return the BigCommerceID for this object
	 */
	public function getBCID($fetch = "shipment") {
		
		$hash = $this->request_data['hash'];
		if($fetch == 'shipment') {
			$id = 0;
			if(!empty($this->data['wombat']['bigcommerce_id'])) {
				$id = $this->data['wombat']['bigcommerce_id'];
			}
			if(!$id) {
				$wombat_id = $this->data['wombat']['id'];
				
				//If id ends in _S, this is a fake ID created during create_shipments. (The ID is actually the order_id in this case)
				if(substr($wombat_id, -2) != '-S' && (stripos($wombat_id, $hash) !== false) &&(strlen($wombat_id) >= strlen($hash))) {
					$id = str_ireplace($hash.'-', '', $wombat_id);
				}
			}

			//check whether the order has any shipments already
			if(!$id) {
				$order_id = $this->getBCID('order');
				$this->getBCIDFromOrder($order_id);
			}
		} else {
			if(!empty($this->data['wombat']['bigcommerce_order_id'])) {
				$id =  $this->data['wombat']['bigcommerce_order_id'];
			} else {
				$id = $this->data['wombat']['order_id'];	
				
				if((stripos($id, $hash) !== false) &&(strlen($id) >= strlen($hash))) {
					$id = str_ireplace($hash.'-', '', $id);
					
					if (substr($id, -2) == '-S') {
						$id = substr($id,0,-2);
					}
				}
			}
		}
		return $id;
	}

	/**
	 * Fetch any existing shipments for the order, and compare products to see if this shipment matches
	 */
	public function getBCIDFromOrder($order_id) {
		$client = $this->client;
		$id = 0;
		$wombat_obj = (object) $this->data['wombat'];
		
		try {
			$response = $client->get("orders/$order_id/shipments");
		} catch (\Exception $e) {
			$this->doException($e,'retrieving existing shipments');
		}		

		if($response->getStatusCode() != 204) {
			$shipments = $response->json(array('object'=>TRUE));

			foreach ($shipments as $shipment) {
				$items = $shipment->items;
				$match = false;

				foreach ($items as $item) {
					foreach($wombat_obj->items as $wombat_item) {
						if($item->order_product_id == $wombat_item['bigcommerce_id']) {
							$match = true;
						}
					}
				}

				if($match) {
					$id = $shipment->id;
					break;
				}
			}
		}
	}


	/**
	 * Add the store hash to the object ID
	 */
	public function getHashId($id) {
		$hash = $this->request_data['hash'];
		
		return $hash.'-'.$id;
	}

	/**
	 * Thow an exception in our format
	 */
	protected function doException($e,$action) {
		$wombat_obj = (object) $this->data['wombat'];

		$response_body = "";
		if(!is_null($e)) {
			$reponse_body = ":::::".$e->getResponse()->getBody();
		}
		throw new \Exception($this->request_data['request_id'].":::::Error received: {$action} for shipment {$wombat_obj->id}, order {$wombat_obj->order_id}{$response_body}",500);
	}
}