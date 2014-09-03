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
			);

		$this->data['wombat'] = $wombat_obj;
		return $wombat_obj;

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