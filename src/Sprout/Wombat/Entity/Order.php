<?php

namespace Sprout\Wombat\Entity;

class Order {

	protected $data;

	public function __construct($data) {
		$this->data = $data;
	}


	/*
		Wombat attributes:
		id	Unique identifier for the order	String
		status	Current order status	String
		channel	Location where order was placed	String
		email	Customers email address	String
		currency	Currency ISO code	String
		placed_on	Date & time order was placed (ISO format)	String
		totals	Order value totals	OrderTotal
		line_items	Array of the order’s line items	LineItem Array
		adjustments	Array ot the orders’ adjustments	Adjustment Array
		shipping_address	Customers shipping address	Address
		billing_address	Customers billing address	Address
		payments	Array of the order’s payments	Payment Array
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