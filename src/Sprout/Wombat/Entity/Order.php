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
		
		$bc_obj = $this->data;
		
		/*** WOMBAT OBJECT ***/
		$wombat_obj = (object) array(
			'id' => $bc_obj->id,
			'status' => $bc_obj->status,
			'channel' => is_null($bc_obj->external_source) ? $bc_obj->order_source : $bc_obj->external_source,
			'email' => $bc_obj->billing_address->email,
			'currency' => $bc_obj->currency_code,
			'placed_on' => date('c',strtotime($bc_obj->date_created)),
			'totals' => (object) array(
				'item' => number_format($bc_obj->subtotal_ex_tax, 2, '.', ''),
				'adjustment' => 0,
				'tax' => number_format($bc_obj->total_tax, 2, '.', ''),
				'shipping' => number_format($bc_obj->shipping_cost_ex_tax, 2, '.', ''),
				'payment' => number_format($bc_obj->total_inc_tax, 2, '.', ''),
				'order' => number_format($bc_obj->total_inc_tax, 2, '.', ''),
				
			),
			'line_items' => array(),
			'adjustments' => array(),
			'shipping_address' => (object) array(
				'firstname' => $bc_obj->_shipping_address->first_name,
				'lastname' => $bc_obj->_shipping_address->last_name,
				'address1' => $bc_obj->_shipping_address->street_1,
				'address2' => $bc_obj->_shipping_address->street_2,
				'zipcode' => $bc_obj->_shipping_address->zip,
				'city' => $bc_obj->_shipping_address->city,
				'state' => $bc_obj->_shipping_address->state,
				'country' => $bc_obj->_shipping_address->country_iso2,
				'phone' => $bc_obj->_shipping_address->phone
			),
			'billing_address' => (object) array(
				'firstname' => $bc_obj->billing_address->first_name,
				'lastname' => $bc_obj->billing_address->last_name,
				'address1' => $bc_obj->billing_address->street_1,
				'address2' => $bc_obj->billing_address->street_2,
				'zipcode' => $bc_obj->billing_address->zip,
				'city' => $bc_obj->billing_address->city,
				'state' => $bc_obj->billing_address->state,
				'country' => $bc_obj->billing_address->country_iso2,
				'phone' => $bc_obj->billing_address->phone
			),
			'payments' => array()
		);

		/*** LINE_ITEMS ***/
		foreach($bc_obj->products as $bc_prod) {
			$wombat_obj->line_items[] = (object) array(
				'product_id' => empty($bc_prod->sku) ? $bc_obj->id : $bc_prod->sku,
				'name' => $bc_prod->name,
				'quantity' => $bc_prod->quantity,
				'price' => number_format($bc_prod->price_ex_tax, 2, '.', '')
			);
		}

		/*** ADJUSTMENTS per LINE_ITEM ***/
		/*foreach($bc_obj->products as $bc_prod) {
			$line_tax = $bc_prod->total_tax + $bc_prod->wrapping_cost_tax;
			if($line_tax > 0) { // TAX
				$wombat_obj->adjustments[] = (object) array(
					'name' => 'Tax',
					'value' => number_format($line_tax, 2, '.', '')
				);
				$wombat_obj->totals->adjustment += $line_tax;
			}
		}
		
		foreach($bc_obj->products as $bc_prod) {
			if($bc_prod->wrapping_cost_ex_tax > 0) { // GIFT WRAPPING
				$wombat_obj->adjustments[] = (object) array(
					'name' => 'Gift Wrapping',
					'value' => number_format($bc_prod->wrapping_cost_ex_tax, 2, '.', '')
				);
				$wombat_obj->totals->adjustment += $bc_prod->wrapping_cost_ex_tax;
			}
		}*/

		/*** ADJUSTMENTS ***/
		if($bc_obj->total_tax > 0) { // TAX
			$wombat_obj->adjustments[] = (object) array(
				'name' => 'Tax',
				'value' => number_format($bc_obj->total_tax, 2, '.', '')
			);
			$wombat_obj->totals->adjustment += $bc_obj->total_tax;
		}
		
		if($bc_obj->wrapping_cost_ex_tax > 0) { // GIFT WRAPPING
			$wombat_obj->adjustments[] = (object) array(
				'name' => 'Gift Wrapping',
				'value' => number_format($bc_obj->wrapping_cost_ex_tax, 2, '.', '')
			);
			$wombat_obj->totals->adjustment += $bc_obj->wrapping_cost_ex_tax;
		}
		
		if($bc_obj->shipping_cost_ex_tax > 0) { // SHIPPING
			$wombat_obj->adjustments[] = (object) array(
				'name' => 'Shipping',
				'value' => number_format($bc_obj->shipping_cost_ex_tax, 2, '.', '')
			);
			$wombat_obj->totals->adjustment += $bc_obj->shipping_cost_ex_tax;
		}
		if($bc_obj->handling_cost_ex_tax > 0) { // HANDLING
			$wombat_obj->adjustments[] = (object) array(
				'name' => 'Handling',
				'value' => number_format($bc_obj->handling_cost_ex_tax, 2, '.', '')
			);
			$wombat_obj->totals->adjustment += $bc_obj->handling_cost_ex_tax;
		}
		if($bc_obj->coupon_discount > 0) { // COUPONS
			$wombat_obj->adjustments[] = (object) array(
				'name' => 'Coupons',
				'value' => number_format($bc_obj->coupon_discount * -1, 2, '.', '')
			);
			$wombat_obj->totals->adjustment += ($bc_obj->coupon_discount * -1);
		}
		
		/*** PAYMENTS ***/
		$wombat_obj->payments[] = (object) array(
			'number' => $bc_obj->payment_provider_id,
			'status' => $bc_obj->payment_status,
			'amount' => number_format($bc_obj->total_inc_tax, 2, '.', ''),
			'payment_method' => $bc_obj->payment_method
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

		//$bc = new \stdClass();

		return $this->data;
	}
}