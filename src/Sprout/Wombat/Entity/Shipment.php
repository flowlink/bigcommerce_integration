<?php

namespace Sprout\Wombat\Entity;

class Shipment {

	protected $data;

	public function __construct($data, $type='bc') {
		$this->data[$type] = $data;
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
			'id' => $bc_obj->id,
			'order_id' => $bc_obj->order_id,
			'email' => !empty($bc_obj->shipping_address->email)?$bc_obj->shipping_address->email:$bc_obj->billing_address->email, // @todo: maybe just one of these, or get from customer profile
			'shipping_method' => $bc_obj->shipping_method,
			'updated_at' => $bc_obj->date_created,
			'shipped_at' => $bc_obj->date_created,
			'BCID' => $bc_obj->id,
			);

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
			'order_address_id' => $wombat_obj->_order_address_id,
			'tracking_number' => $wombat_obj->tracking,
			'comments' => '',
			);

		foreach($wombat_obj->items as $item) {
			$bc_obj->items[] = (object) array(
				'order_product_id' => $item['_order_product_id'],
				'quantity' => $item['quantity'],
			);
		}

		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	public function prepareBCResources($client) {

		//retrieve shipping addresses already associated with the order_id
		$wombat_obj = (object)$this->data['wombat'];
		$order_id = $wombat_obj->order_id;
		
		$response = $client->get("orders/$order_id/shipping_addresses");
					
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

		// get order products for the order_product_id
		$response = $client->get("orders/$order_id/products");

		if(intval($response->getStatusCode()) === 200)
			//$this->data['bc']->$resource_name = $response->json(array('object'=>TRUE));
			$products = $response->json(array('object'=>TRUE));
		else
			//$this->data['bc']->$resource_name = NULL;
			$products = NULL;

		// go through the resulting products and match the product_ids to get the order_product_id
		if(is_array($products)) {
			foreach($products as $product) {
				
				foreach($this->data['wombat']['items'] as $index => $item) {
					if($product->product_id == $item['product_id']) {
						$this->data['wombat']['items'][$index]['_order_product_id'] = $product->id;
					}
				}

			}
		}
	}
}