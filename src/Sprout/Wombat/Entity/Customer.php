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

		$wombat = new stdClass();

		$wombat->id 								= $data->id;
		//todo - other fields

		return $wombat;

	}

	/**
	 * Get a BigCommerce-formatted set of data from a Wombat one.
	 */
	public function getBigCommerceObject($action = 'create') {
		if(!$this->data) {
			return false;
		}

		$bc = new stdClass();

		return $bc;
	}
}