<?php

namespace Sprout\Wombat\Entity;

class Customer {

	protected $data;

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
		
		// @todo: real data
		$bc_obj = (object) array(
			'first_name' => 'Some',
			'last_name' => 'Person',
			'email' => 'some.person@example.com',
		);
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}
}