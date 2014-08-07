<?php

namespace Sprout\Wombat\Entity;

class Shipment {

	protected $data;

	public function __construct($data) {
		$this->data = $data;
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