<?php

namespace Sprout\Wombat\Entity;

class Product {

	protected $data;

	public function __construct($data) {
		$this->data = $data;
	}


	/**
	 * Get a Wombat-formatted set of data from a BigCommerce one.
	 */
	public function getWombatObject() {
		if(!$this->data) {
			return false;
		}

		$wombat = new stdClass();

		$wombat->id 								= $data->id;
		$wombat->name 							= $data->name;
		$wombat->description 				= $data->description;
		$wombat->price 							= $data->price;
		$wombat->cost_price 				= $data->cost_price;
		$wombat->available_on 			= $data->availability == 'preorder'?$data->preorder_release_date:"";
		$wombat->permalink 					= ""; //todo: construct from store url / id?
		$wombat->meta_description 	= $data->meta_description;
		$wombat->meta_keywords			= $data->meta_keywords;
		$wombat->shipping_category	= "Default"; //todo: is_free_shipping?

		//todo: these are all objects, figure out how to map them properly
		$wombat->taxons 						= $data->categories;
		$wombat->options 						= new stdClass();
		$wombat->properties 				= new stdClass();
		$wombat->images 						= new stdClass();
		$wombat->variants 					= new stdClass();

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

		//required to create
		$bc->name 				= $data->name;
		$bc->price 				= $data->price;
		$bc->categories 	= array(1);			//todo: category ID(s) needed
		$bc->availability = "available"; 	//todo: map
		$bc->weight 			= "1.0";				//todo: map
		$bc->type 				= "physical";		//todo: map

		//optional
		$bc->description = $data->description;
		$bc->cost_price = $data->cost_price;
		$bc->meta_description = $data->meta_description;
		$bc->meta_keywords = $data->meta_keywords;

		if(!empty($data->available_on) && strtotime($data->available_on) > now()) {
			$bc->availability = 'preorder';
			$bc->preorder_release_date = $data->available_on;
		}

		//if updating
		if($action == 'update') {
			$bc->id = $data->id;
		}

		return $bc;
	}

}