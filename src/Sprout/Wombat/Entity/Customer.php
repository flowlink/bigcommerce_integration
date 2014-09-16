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
			'id' => $bc_obj->id,
			'firstname' => $bc_obj->first_name,
			'lastname' => $bc_obj->last_name,
			'email' => $bc_obj->email,
			'BCID' => $bc_obj->id,
		);

		if(!empty($bc_obj->_addresses)) {
			$address = $bc_obj->_addresses[0];
			$wombat_obj->billing_address = (object) array(
				'firstname' => $address->first_name,
				'lastname' 	=> $address->last_name,
				'address1' 	=> $address->street_1,
				'address2' 	=> $address->street_2,
				'zipcode' 	=> $address->zip,
				'city' 			=> $address->city,
				'state' 		=> $address->state,
				'country' 	=> $address->country_code,
				'phone' 		=> $address->phone,
				'BCID' 			=> $address->id,
			);
		}

		if(!empty($bc_obj->_addresses) && count($bc_obj->_addresses) > 1) {
			$address = $bc_obj->_addresses[1];
			$wombat_obj->shipping_address = (object) array(
				'firstname' => $address->first_name,
				'lastname' 	=> $address->last_name,
				'address1' 	=> $address->street_1,
				'address2' 	=> $address->street_2,
				'zipcode' 	=> $address->zip,
				'city' 			=> $address->city,
				'state' 		=> $address->state,
				'country' 	=> $address->country_code,
				'phone' 		=> $address->phone,
				'BCID' 			=> $address->id,
			);
		}

		echo print_r($wombat_obj);


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
		if(!empty($this->data['wombat']['BCID'])) {
			return $this->data['wombat']['BCID'];
		}

		//if no BCID stored, query BC for the email
		$email = $this->data['wombat']['email'];
		
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
				if(strpos($resource->url,'/0.json') === FALSE) {				
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
		if(!empty($this->data['bc']->addresses)) {
			$this->data['bc']->_addresses = array();

			//if the customer has >2 address, take the last two, otherwise take whatever they have
			if(count($this->data['bc']->addresses) > 2) {
				$i = count($this->data['bc']->addresses)-2;
			} else {
				$i = 0;
			}
			
			for($i; $i<count($this->data['bc']->addresses); $i++) {
				$address = $this->data['bc']->addresses[$i];
				$this->data['bc']->_addresses[] = $address;
			}
			
		}
	}
}