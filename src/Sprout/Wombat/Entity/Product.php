<?php

namespace Sprout\Wombat\Entity;

class Product {

	protected $data;
	private $_attached_resources = array('images', 'brand', 'discount_rules', 'custom_fields', 'configurable_fields', 'skus', 'rules', 'option_set', 'options', 'downloads','videos','tax_class');

	public function __construct($data, $type='bc') {
		$this->data[$type] = $data;
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
			'id' => empty($bc_obj->sku) ? $bc_obj->id : $bc_obj->sku, // we should use the SKU to ensure that products created outside of BC are still identifiable
			'name' => $bc_obj->name,
			'sku' => $bc_obj->sku,
			'description' => $bc_obj->description,
			'price' => number_format($bc_obj->price, 2, '.', ''),
			'cost_price' => number_format($bc_obj->cost_price, 2, '.', ''),
			'available_on' => $bc_obj->availability == 'preorder' ? $bc_obj->preorder_release_date : '',
			'permalink' => $bc_obj->custom_url,
			'meta_description' => $bc_obj->meta_description,
			'meta_keywords' => $bc_obj->meta_keywords,
			'shipping_category' => '',
			'taxons' => array(),
			'options' => array(),
			'properties' => (object) array(),
			'images' => array(),
			'variants' => array(),
			'BCID' => $bc_obj->id,
		);
		
		/*** TAXONS ***/
		foreach($bc_obj->_categories as $bc_cat) {
			$wombat_obj->taxons[] = explode('/', 'Categories/' . $bc_cat);
		}
		
		if(!empty($bc_obj->brand) && !empty($bc_obj->brand->name))
			$wombat_obj->taxons[] = array('Brands', $bc_obj->brand->name);
			
		if(!empty($bc_obj->warranty))
			$wombat_obj->taxons[] = array('Warranty', $bc_obj->warranty);
		
		
		/*** IMAGES ***/
		if(!empty($bc_obj->images)) {
			foreach($bc_obj->images as $bc_img) {	$bc_img = (object) $bc_img;
				$wombat_obj->images[] = (object) array(
					'url' => $bc_obj->_store_url . '/product_images/' . $bc_img->image_file,
					'position' => $bc_img->sort_order,
					'title' => $bc_img->description,
					'type' => '',
					'dimensions' => (object) array(
						'height' => '',
						'width' => ''
					)
				);
			}
		}
		
		/*** PROPERTIES ***/
		if(!empty($bc_obj->custom_fields)) {
			foreach($bc_obj->custom_fields as $bc_custom) {
				$key = $bc_custom->name;
				$wombat_obj->properties->$key = $bc_custom->text;
			}
		}
		
		/*** OPTIONS ***/
		if(!empty($bc_obj->options)) {
			foreach($bc_obj->options as $bc_opt) {
				$wombat_obj->options[] = $bc_opt->display_name;
			}
		}
		
		/*** VARIANTS ***/
		if(!empty($bc_obj->skus)) // BC SKUS
		{
			foreach($bc_obj->skus as $bc_sku) {
				$new_variant = (object) array(
					'sku' => $bc_sku->sku,
					'price' => number_format($bc_obj->price, 2, '.', ''),
					'cost_price' => number_format($bc_sku->cost_price, 2, '.', ''),
					'options' => (object) array(),
					'quantity' => 1,
					'images' => array()
				);
				
				// Add options to variant
				$variant_options_added = array();
				foreach($bc_obj->_skus[ $bc_sku->id ]->options as $_bc_option) {
					list($option_key, $option_val) = array($_bc_option->product_option, $_bc_option->option_value);
					$new_variant->options->$option_key = $option_val;
					$variant_options_added[ $_bc_option->product_option_id ] = $_bc_option->option_value_id;
				}
				
				// APPLY RULES TO NEW VARIANT
				if(!empty($bc_obj->rules)) // BC RULES
				{
					foreach($bc_obj->rules as $bc_rule) {
						// basic conditions
						$rule_passes_conditions = $bc_rule->is_enabled && !$bc_rule->is_stop;
						
						// sku/option conditions
						if($rule_passes_conditions) { // passing?
							foreach($bc_rule->conditions as $rule_condition) {
							
								// fail if SKU is filled in and does not match
								if(!empty($rule_condition->sku_id)) { // ignore if condition field empty
									$rule_passes_conditions = $rule_condition->sku_id === $bc_sku->id;
								}
								
								// fail if Option is filled in and does not match
								if($rule_passes_conditions && !empty($rule_condition->product_option_id)) { // ignore if condition field empty
									list($po_id, $po_val_id) = array($rule_condition->product_option_id,$rule_condition->option_value_id);
									$rule_passes_conditions = isset($variant_options_added[$po_id]) && $po_val_id === $variant_options_added[$po_id];
								}
							}
						}
						
						if($rule_passes_conditions) { // PASSED!! Apply rule
							// Price adjuster
							if(isset($bc_rule->price_adjuster->adjuster)) {
								switch($bc_rule->price_adjuster->adjuster) {
									case 'absolute':
										$new_variant->price = number_format($bc_rule->price_adjuster->adjuster_value, 2, '.', '');
										break;
									case 'relative':
										$new_variant->price += number_format($bc_rule->price_adjuster->adjuster_value, 2, '.', '');
										break;
									case 'percentage':
										$new_variant->price += $new_variant->price * ($bc_rule->price_adjuster->adjuster_value/100);
										break;
								}
							}
							
							if(!empty($bc_rule->image_file)) {
								$new_variant->images[] = (object) array(
									'url' => $bc_obj->_store_url . '/product_images/' . $bc_rule->image_file,
									'position' => 1,
									'title' => '',
									'type' => '',
									'dimensions' => (object) array(
										'height' => '',
										'width' => ''
									)
								);
							}
						}
					} // foreach rule
				}
				
				$wombat_obj->variants[] = $new_variant;
			}
		}
		
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
		
		/*
			BC: required to create:
			name
			price
			categories
			type
			availability
			weight
		*/
		
		$bc_obj = (object) array(
			'sku' => $wombat_obj->id, //store this so we can use it as a primary key in Wombat
			'name' => $wombat_obj->name,
			'price' => (String)number_format($wombat_obj->price,2,'.',''),
			'description' => $wombat_obj->description,
			'categories' => array(20),
			'type' => 'physical',
			'availability' => 'available',
			'weight' => (string)1,
		);
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Send data to BigCommerce that's handled separately from the main product object:
	 * custom fields, skus
	 * NB: options can't be set directly on the product - it has to be done through the option set???
	 */
	public function pushAttachedResources($client,$request_data) {
		$wombat_obj = (object) $this->data['wombat'];

		//$bc_id = $this->getBCID($client,$request_data);
		$bc_id = 78;
		
		//map Wombat properties onto BC custom fields
		if(!empty($wombat_obj->properties)) {
			
			foreach($wombat_obj->properties as $name => $value) {
				$data = (object) array(
					'name' => $name, 
					'text' => $value,
				);
				$client_options = array(
					'headers'=>array('Content-Type'=>'application/json'),
					'body' => (string)json_encode($data),
						//'debug'=>fopen('debug.txt', 'w')
				);
				echo print_r($client_options,true).PHP_EOL;
				// try {
				// 	$client->post("products/$bc_id/custom_fields",$client_options);
				// } catch (Exception $e) {
				// 	throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				// }
			}

		} 

		//Map Wombat variants onto BC SKUs
		if(!empty($wombat_obj->variants)) {
			echo print_r($wombat_obj->variants,true).PHP_EOL;

			foreach($wombat_obj->variants as $variant) {
				$data = (object) array(
					'sku' => 							$variant['sku'],
					'cost_price' => 			$variant['cost_price'],
					'inventory_level' =>	$variant['quantity'], // @todo: only if stock tracking for parent product is set to 'sku'
					);

				$sku_options = $this->getProductOptions($bc_id,$client,$request_data);

				foreach($sku_options as $sku_option) {
					$data['options'][] = (object) array(
						'product_option_id' => 	$sku_option['product_option_id'],
						'option_value_id' => 		$sku_option['option_value_id'],
						);
				}
				echo print_r($data,true).PHP_EOL;
				// try {
				// 	$client->post("products/{product_id}/skus",$client_options);
				// } catch (Exception $e) {
				// 	throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				// }
			}
		}

	}

	/**
	 * Retrieve a product's options from BigCommerce and match it against provided Wombat variant options
	 */
	public function getProductOptions($product_id,$client,$request_data) {
		try {
			$response = $client->get("products/$product_id/options");
		} catch (Exception $e) {
			throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching product options for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
		}
		echo "RESPONSE".PHP_EOL.print_r($response->json(array('object'=>TRUE)),true).PHP_EOL;
		$sku_options = array();
		// if(!empty($this->data['wombat']['variants'])) {
		// 	foreach ($this->data['wombat']['variants'] as $variant) {
		// 		foreach ($variant['options'] as $name => $value) {

		// 		}
		// 	}
		// }
		return $sku_options;
	}
	
	public function getBCID($client,$request_data) {
		$sku = $this->data['wombat']['id'];
		
		try {
			$response = $client->get('products',array('query'=>array('sku'=>$sku)));
			$data = $response->json(array('object'=>TRUE));

			return $data[0]->id;
		} catch (Exception $e) {
			throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching resource \"$resource_name\" for product \"".$this->data['bc']->sku."\": ".$e->getMessage(),500);
		}
	}
	
	public function loadAttachedResources($client,$request_data)
	{
		// request attached resources		
		foreach($this->_attached_resources as $resource_name) {
			if(isset($this->data['bc']->$resource_name)) {
				$resource = $this->data['bc']->$resource_name;
				
				// don't load in resources with id 0 (they don't exist)
				if(strpos($resource->url,'/0.json') === FALSE) {				
					// replace request shell with loaded resource
					try {
						$response = $client->get($resource->url);
					} catch (Exception $e) {
						//throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce: ".$e->getMessage(),500);
						// @todo: find a way to insert the request_id here:
						throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching resource \"$resource_name\" for product \"".$this->data['bc']->sku."\": ".$e->getMessage(),500);
					}
					
					if(intval($response->getStatusCode()) === 200)
						$this->data['bc']->$resource_name = $response->json(array('object'=>TRUE));
					else
						$this->data['bc']->$resource_name = NULL;
				}
			}
		}
		
		
		// organize extra resources (not really in API)
		
		/*  _categories 	- (contains category paths)
		*/
		if(!empty($this->data['bc']->categories)) {
			$this->data['bc']->_categories = array();
			foreach($this->data['bc']->categories as $cat_id) {
				$category = $client->get( 'categories/'.$cat_id )->json(array('object'=>TRUE));
				$category_path = array();
				foreach($category->parent_category_list as $parent_cat_id) {
					$parent_category = $client->get( 'categories/'.$parent_cat_id )->json(array('object'=>TRUE));
					$category_path[] = $parent_category->name;
				}
				$this->data['bc']->_categories[] = implode('/',$category_path);
			}
		}
		
		
		/*  _skus 	- (contains skus with text options)
		*/
		if(!empty($this->data['bc']->options) && !empty($this->data['bc']->skus)) {
			
			// Build list of Option names by Option id
			$options_by_id = array();
			foreach($this->data['bc']->options as $bc_option)
				$options_by_id[ $bc_option->id ] = $bc_option;
		
			$this->data['bc']->_skus = array();
			foreach($this->data['bc']->skus as $bc_sku) {
				// get option data
				$_options = array();
				foreach($bc_sku->options as $bc_option) {
					$bc_option = (object) $bc_option;
					
					$option_id = $options_by_id[ $bc_option->product_option_id ]->option_id;
					$option_value = $client->get( 'options/'.$option_id.'/values/'. $bc_option->option_value_id)->json(array('object'=>TRUE));

					$_options[] = (object) array(
						'product_option_id' => $bc_option->product_option_id,
						'product_option' => $options_by_id[ $bc_option->product_option_id ]->display_name,
						'option_value_id' => $bc_option->option_value_id,
						'option_value' => $option_value->label
					);
				}
				// save option data
				$this->data['bc']->_skus[ $bc_sku->id ] = (object) array(
					'options' => $_options
				);
			}
		}
		
	}
	
}