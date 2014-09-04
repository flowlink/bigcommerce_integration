<?php

namespace Sprout\Wombat\Entity;

class Order {

	protected $data;
	private $_attached_resources = array('products','shipping_addresses','coupons');

	public function __construct($data, $type='bc') {
		$this->data[$type] = $data;
	}

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
			'status' => strtolower($bc_obj->status),
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
				'id' => $bc_obj->_shipping_address->id,
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
				'id' => $bc_obj->_shipping_address->id,
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
			$new_line_item = (object) array(
				'product_id' => empty($bc_prod->sku) ? $bc_obj->id : $bc_prod->sku,
				'name' => $bc_prod->name,
				'quantity' => $bc_prod->quantity,
				'price' => number_format($bc_prod->price_ex_tax, 2, '.', '')
			);
			
			// add chosen product options to line item
			if(!empty($bc_prod->product_options)) {
				$new_line_item->options = array();
				foreach($bc_prod->product_options as $bc_option) {
					$option_key = $bc_option->display_name;
					$option_val = $bc_option->display_value;
					$new_option = (object) array(
						$option_key => $option_val
					);
					$new_line_item->options[] = $new_option;
				}
			}
			
			$wombat_obj->line_items[] = $new_line_item;
		}

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
			'products' => array(
				(object) array(
					'product_id' => 107,
					'quantity' => rand(1,10),
					),
				(object) array(
					'product_id' => 84,
					'quantity' => rand(1,10),
					),

				),
			'billing_address' => (object) array(
		    'first_name' => 'Some',
		    'last_name' => 'Person',
		    'company' => '',
		    'street_1' => '123 Some St',
		    'street_2' => '',
		    'city' => 'Austin',
		    'state' => 'Texas',
		    'zip' => '78757',
		    'country' => 'United States',
		    'country_iso2' => 'US',
		    'phone' => '',
		    'email' => 'some.person@example.com',
  		),
		);
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}
	
	public function loadAttachedResources($client)
	{
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
		
		
		// organize extra resources (not really in API)
		
		/* First shipping address */
		if(!empty($this->data['bc']->shipping_addresses)) {
			$this->data['bc']->_shipping_address = $this->data['bc']->shipping_addresses[0];
		}

	}
}