<?php

namespace Sprout\Wombat\Entity;

class Inventory {

	/**
	 * @var array $data Hold the JSON object data retrieved from the source
	 */
	protected $data;

	public function __construct($data, $type='bc',$client,$request_data) {
		$this->data[$type] = $data;
		$this->client = $client;
		$this->request_data = $request_data;
	}

	/**
	 * Get a Wombat-formatted set of data from a BigCommerce one.
	 */
	public function getWombatObject()
	{
		
		if(isset($this->data['wombat']))
			return $this->data['wombat'];
		else if(isset($this->data['bc']))
			$bc_obj = (object) $this->data['bc'];
		else
			return false;
		
		/*** WOMBAT OBJECT ***/
		$wombat_obj = (object) array(
			'bigcommerce_id' => $bc_obj->id,
		);

		$this->data['wombat'] = $wombat_obj;
		return $wombat_obj;
	}

	/**
	 * Get a BigCommerce-formatted set of data from a Wombat one.
	 */
	public function getBigCommerceObject($action = 'update') {
		if(isset($this->data['bc']))
			return $this->data['bc'];
		else if(isset($this->data['wombat']))
			$wombat_obj = (object) $this->data['wombat'];
		else
			return false;
		

		$bc_obj = (object) array(
			'inventory_level' => $wombat_obj->quantity,
		);
		
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Check if the product has inventory tracking on
	 */
	public function checkInventoryTracking() {
		$client =  $this->client;
		$request_data = $this->request_data;
		
		$product_id = $this->getBCID();

		try {
			$response = $client->get("products/{$product_id}");
		} catch (\Exception $e) {
			$this->doException($e,"checking product's inventory tracking status");
		}

		$product = $response->json(array('object'=>TRUE));

		/*
			none
			simple
			sku
		*/

		// @todo : figure out how to handle sku
		if($product->inventory_tracking != 'simple' ) {
			$this->doException(null,"This product does not have inventory tracking enabled.");
		}

	}

	/**
	 * Return the BigCommerceID for this object
	 */
	public function getBCID() {
		$client = $this->client;
		$request_data = $this->request_data;
		$wombat_obj = (object) $this->data['wombat'];
		
		if(!empty($wombat_obj->bigcommerce_id)) {
			return $wombat_obj->bigcommerce_id;
		}

		//if no BCID stored, query BigCommerce for the SKU
		$sku = $wombat_obj->product_id;
		
		try {
			$response = $client->get('products',array('query'=>array('sku'=>$sku)));
			$data = $response->json(array('object'=>TRUE));

			return $data[0]->id;
		} catch (\Exception $e) {
			$this->doException($e,'fetching bigcommerce_id');
		}
	}
	
	/**
	 * Thow an exception in our format
	 */
	protected function doException($e,$action) {
		$wombat_obj = (object) $this->data['wombat'];

		$response_body = "";
		if(!is_null($e)) {
			$response_body = ":::::".$e->getResponse()->getBody();
			$message = ":::::Error received from BigCommerce while {$action} for Inventory \"".$wombat_obj->id."\"";
		} else {
			$message = ":::::".$action;
		}
		throw new \Exception($this->request_data['request_id'].$message.$response_body,500);
	}
}