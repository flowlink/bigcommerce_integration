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
			'id' => empty($bc_obj->sku) ? $bc_obj->id : $bc_obj->sku,
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
			'variants' => array()
		);
		
		/*** TAXONS ***/
		foreach($bc_obj->_categories as $bc_cat) {
			$wombat_obj->taxons[] = explode('/', 'Categories/' . $bc_cat);
		}
		
		if(!empty($bc_obj->brand))
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
		
		$bc_obj = (object) array(
			'id' => $wombat_obj->id
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