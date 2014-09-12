<?php

namespace Sprout\Wombat\Entity;

class Customer {

	protected $data;
	private $_attached_resources = array('addresses');

	public function __construct($data, $type='bc') {
		$this->data[$type] = $data;
	}


	/*
		Wombat attributes:
		id	Unique identifier for the shipment	String
		email	Customers email address	String
		firstname	Customers first name	String
		lastname	Customers last name	String
		billing_address	Customers shipping address	Address
		shipping_address	Customers shipping address	Address
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
			'id' => $bc_obj->email,
			'firstname' => $bc_obj->first_name,
			'lastname' => $bc_obj->last_name,
			'email' => $bc_obj->email,
			'shipping_address' => (object) array(
				'address1' => '',
				'address2' => '',
				'zipcode' => '',
				'city' => '',
				'state' => '',
				'country' => '',
				'phone' => '',
			),
			'billing_address' => (object) array(
				'address1' => '',
				'address2' => '',
				'zipcode' => '',
				'city' => '',
				'state' => '',
				'country' => '',
				'phone' => '',
			)
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
		
		// @todo: add addresses
		$bc_obj = (object) array(
			'first_name' => $wombat_obj->firstname,
			'last_name' => $wombat_obj->lastname,
			'email' => $wombat_obj->email,
		);
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Get the BigCommerce ID for a customer by fetching customers filtered by email address
	 */
	public function getBCID($client,$request_data) {
		$email = $this->data['wombat']['id'];
		
		try {
			$response = $client->get('customers',array('query'=>array('email'=>$email)));
			$data = $response->json(array('object'=>TRUE));
			
			return $data[0]->id;
		} catch (Exception $e) {
			throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching resource \"$resource_name\" for product \"".$this->data['bc']->sku."\": ".$e->getMessage(),500);
		}
	}

	/**
	 * Load any attached resources from BigCommerce
	 */
	public function loadAttachedResources($client) {
		// request attached resources		
		foreach($this->_attached_resources as $resource_name) {
			if(isset($this->data['bc']->$resource_name)) {
				$resource = $this->data['bc']->$resource_name;
			
				// don't load in resources with id 0 (they don't exist)
				if(strpos($resource->url,'\/0.json') === FALSE) {				
					// replace request shell with loaded resource
					$response = $client->get($resource->url);
					
					if(intval($response->getStatusCode()) === 200)
						$this->data['bc']->$resource_name = $response->json(array('object'=>TRUE));
					else
						$this->data['bc']->$resource_name = NULL;
				}
			}
		}
		//echo print_r($this->data['bc'],true).PHP_EOL;

		// organize extra resources (not really in API)
		
		/*  _categories 	- (contains category paths)
		*/
		// if(!empty($this->data['bc']->addresses)) {
		// 	$this->data['bc']->_categories = array();
		// 	foreach($this->data['bc']->categories as $cat_id) {
		// 		$category = $client->get( 'categories/'.$cat_id )->json(array('object'=>TRUE));
		// 		$category_path = array();
		// 		foreach($category->parent_category_list as $parent_cat_id) {
		// 			$parent_category = $client->get( 'categories/'.$parent_cat_id )->json(array('object'=>TRUE));
		// 			$category_path[] = $parent_category->name;
		// 		}
		// 		$this->data['bc']->_categories[] = implode('/',$category_path);
		// 	}
		// }
	}
}