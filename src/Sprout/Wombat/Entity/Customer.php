<?php

namespace Sprout\Wombat\Entity;

class Customer {

	protected $data;

	public function __construct($data) {
		$this->data = $data;
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
		if(!$this->data) {
			return false;
		}
		
		$bc_obj = $this->data;
		
		/*** WOMBAT OBJECT ***/
		$wombat_obj = (object) array(
			'id' => $bc_obj->id,
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

		return $wombat_obj;
	}

	/**
	 * Get a BigCommerce-formatted set of data from a Wombat one.
	 */
	public function getBigCommerceObject($action = 'create') {
		if(!$this->data) {
			return false;
		}

		$bc = new \stdClass();

		return $this->data;
	}
}