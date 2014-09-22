<?php

namespace Sprout\Wombat\Entity;

class Product {

	protected $data;
	private $_attached_resources = array('images', 'brand', 'discount_rules', 'custom_fields', 'configurable_fields', 'skus', 'rules', 'option_set', 'options', 'downloads','videos','tax_class');

	private $product_options; //cache the product options
	private $option_sets; //cache option sets

	private $client;
	private $request_data;

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
			'id' 								=> empty($bc_obj->sku) ? $bc_obj->id : $bc_obj->sku, // we should use the SKU to ensure that products created outside of BC are still identifiable
			'name' 							=> $bc_obj->name,
			'sku' 							=> $bc_obj->sku,
			'description' 			=> $bc_obj->description,
			'price' 						=> (float) number_format($bc_obj->price, 2, '.', ''),
			'cost_price' 				=> (float) number_format($bc_obj->cost_price, 2, '.', ''),
			'available_on' 			=> $bc_obj->availability == 'preorder' ? $bc_obj->preorder_release_date : '',
			'permalink' 				=> $bc_obj->custom_url,
			'meta_description' 	=> $bc_obj->meta_description,
			'meta_keywords' 		=> $bc_obj->meta_keywords,
			'shipping_category'	=> '',
			'taxons' 						=> array(),
			'options' 					=> array(),
			'properties' 				=> (object) array(),
			'images' 						=> array(),
			'variants' 					=> array(),
			'bigcommerce_id' 		=> $bc_obj->id,
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
					'price' => (float) number_format($bc_obj->price, 2, '.', ''),
					'cost_price' => (float) number_format($bc_sku->cost_price, 2, '.', ''),
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
										$new_variant->price = (float) number_format($bc_rule->price_adjuster->adjuster_value, 2, '.', '');
										break;
									case 'relative':
										$new_variant->price += (float) number_format($bc_rule->price_adjuster->adjuster_value, 2, '.', '');
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
			'sku' 							=> $wombat_obj->id, //store this so we can use it as a primary key in Wombat
			'name' 							=> $wombat_obj->name,
			'price' 						=> (String)number_format($wombat_obj->price,2,'.',''),
			'description' 			=> $wombat_obj->description,
			'categories' 				=> $this->getCategories($wombat_obj->taxons),
			'type' 							=> 'physical',
			'availability' 			=> 'available',
			'weight' 						=> (string)1,
			'custom_url' 				=> $wombat_obj->permalink,
			'meta_description'	=> $wombat_obj->meta_description,
			'meta_keywords'			=> $wombat_obj->meta_keywords,
		);

		//if these are present, we'll need to find an option set
		if(!empty($wombat_obj->variants) && !empty($wombat_obj->options)) {
			$bc_obj->option_set_id = $this->getOptionSetId();
		}

		$brand_id = $this->getBrandId();
		if($brand_id) {
			$bc_obj->brand_id = $brand_id;
		}
		 // echo "OBJ: ".print_r($bc_obj,true).PHP_EOL;
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Send data to BigCommerce that's handled separately from the main product object:
	 * custom fields, skus
	 * NB: options can't be set directly on the product - it has to be done through the option set???
	 */
	public function pushAttachedResources() {
		$client = $this->client;
		$request_data = $this->request_data;
		$wombat_obj = (object) $this->data['wombat'];

		$bc_id = $this->getBCID();
		//echo "PRODUCT ID: $bc_id".PHP_EOL;
		//$bc_id = 183;
		//return print_r("STOPPING EARLY FOR TESTING",true);
		
		
		//map Wombat images
		if(!empty($wombat_obj->images)) {
			foreach($wombat_obj->images as $image) {
				// echo print_r($image,true).PHP_EOL;
				$data = (object) array(
					'image_file' 		=> $image['url'],
					'description' 	=> $image['title'],
					'is_thumbnail' 	=> ($image['type'] == 'thumbnail')?'true':'false',
					'sort_order'		=> $image['position'],
					);
				// echo 'image: '.print_r($data,true).PHP_EOL;
				$client_options = array(
					'headers'=>array('Content-Type'=>'application/json'),
					'body' => (string)json_encode($data),
						//'debug'=>fopen('debug.txt', 'w')
				);
				try {
					$client->post("products/$bc_id/images",$client_options);
				} catch (Exception $e) {
					throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				}
			}
		}
		
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
				// echo print_r($client_options,true).PHP_EOL;
				try {
					$client->post("products/$bc_id/custom_fields",$client_options);
				} catch (Exception $e) {
					throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				}
			}

		}

		//Map Wombat variants onto BC SKUs & rules
		if(!empty($wombat_obj->variants)) {
			// echo print_r($wombat_obj->variants,true).PHP_EOL;

			foreach($wombat_obj->variants as $variant) {
				$data = (object) array(
					'sku' => 							$variant['sku'],
					'cost_price' => 			$variant['cost_price'],
					'inventory_level' =>	$variant['quantity'], // @todo: only if stock tracking for parent product is set to 'sku'
					);

			
				$data->options = $this->getSkuOptions($bc_id,$variant['options']);
				// echo print_r($data,true).PHP_EOL;

				$client_options = array(
					'headers'=>array('Content-Type'=>'application/json'),
					'body' => (string)json_encode($data),
						//'debug'=>fopen('debug.txt', 'w')
				);
				try {
					$response = $client->post("products/$bc_id/skus",$client_options);
				} catch (Exception $e) {
					throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				}

				$sku = $response->json(array('object'=>TRUE));

				//echo "SKU: ".print_r($sku,true).PHP_EOL;
				//handle price & images via rule
				$rule = (object) array(
					'sort_order'			=> 0,
					'is_enabled'			=> true,
					'is_stop'					=> false,
					'price_adjuster'	=> (object) array(
						'adjuster'				=> 'absolute',
						'adjuster_value'	=> $variant['price'],
						),
					'conditions'			=> array(
						(object) array(
							"product_option_id"	=> null,
    					"option_value_id"		=> null,
    					"sku_id"						=> $sku->id
							),
						),
					'is_purchasing_disabled' 			=> false,
					'purchasing_disabled_message'	=> '',
					'is_purchasing_hidden'				=> false,
					'weight_adjuster'							=> null,
					);
				if(!empty($variant['images'])) {
					$rule->image_file = $variant['images'][0]['url'];
				}
				// echo "RULE: ".print_r($rule,true).PHP_EOL;
				$client_options = array(
					'headers'=>array('Content-Type'=>'application/json'),
					'body' => (string)json_encode($rule),
						//'debug'=>fopen('debug.txt', 'w')
				);
				// echo "RULE: ".print_r($client_options['body'],true).PHP_EOL;
				try {
					$response = $client->post("products/$bc_id/rules",$client_options);
				} catch (\Exception $e) {
					throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getResponse(),500);
				}
			}
		}

	}

	/**
	 * Get categories from Wombat taxons
	 */
	public function getCategories($taxons) {
		$client = $this->client;
		$request_data = $this->request_data;
		$category_ids = array(); 

		foreach($taxons as $taxon) {
			if(strtoupper($taxon[0]) == 'CATEGORIES') {
				// echo print_r($taxon,true).PHP_EOL;
				$categoryname = $taxon[count($taxon)-1];
				// echo $categoryname.PHP_EOL;

				try {
						$response = $client->get("categories",array('query'=>array('name'=>$categoryname)));
					} catch (Exception $e) {
						throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
					}
					// echo $response->getStatusCode().PHP_EOL;
					if($response->getStatusCode() == 204) {
						//create category?
					} else if($response->getStatusCode() == 200) {
						$categories = $response->json(array('object'=>TRUE));
						$category = $categories[0];
						$category_ids[] = $category->id;
						// echo "categories: ".print_r($categories,true).PHP_EOL;
					}
			}
		}

		if(empty($category_ids)) {
			$category_ids[] = 20; // @todo: figure out a default
		}

		return $category_ids;
	}

	/**
	 * Find the brand ID from the Wombat taxons
	 */
	public function getBrandId() {
		$wombat_obj = (object) $this->data['wombat'];
		$client = $this->client;
		$request_data = $this->request_data;

		$brand_id = 0;
		//map Wombat taxons onto brands
		if(!empty($wombat_obj->taxons)) {
			$taxons = $wombat_obj->taxons;

			foreach($taxons as $taxon) {
				if(strtoupper($taxon[0]) == 'BRANDS') {
					$brandname = $taxon[1];
					// echo $brandname.PHP_EOL;
					//find if the brand exists
					try {
						$response = $client->get("brands",array('query'=>array('name'=>$brandname)));
					} catch (Exception $e) {
						throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while pushing resource \"properties/custom_fields\" for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
					}
					if($response->getStatusCode() == 204) {
						//create brand?
					} else if($response->getStatusCode() == 200) {
						$brands = $response->json(array('object'=>TRUE));
						$brand = $brands[0];
						$brand_id = $brand->id;
						// echo "BRANDS: ".print_r($brands,true).PHP_EOL;
					}
				}
			}
		}

		return $brand_id;
	}

	/**
	 * Match a set of Wombat options agains BC Option Sets
	 */

	public function getOptionSetId() {
		$wombat_obj = (object) $this->data['wombat'];
		$option_sets = $this->getOptionSets();
		
		$output = '';

		//uppercase the Wombat options for matching
		$options = array_map("strtoupper",$wombat_obj->options);
		
		//for each option set, construct an array of option names to match against the Wombat array
		foreach ($option_sets as $option_set) {
			$set_options = array();
			foreach($option_set->options as $set_option)	{
				$set_options[] = strtoupper($set_option->display_name);
			}
			if($set_options == $options) {
				$output = $option_set->id;
			}
		}
		
		return $output;	
	}

	/**
	 * Get option sets & their values from BigCommerce
	 */
	public function getOptionSets() {
		$client = $this->client;
		$request_data = $this->request_data;

		if(empty($this->option_sets)) {
			//get the option sets from BigCommerce
			try {
				$response = $client->get("option_sets");
			} catch (Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching product options for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
			}

			$results = $response->json(array('object'=>TRUE));
			//echo "RESPONSE".PHP_EOL.print_r($results,true).PHP_EOL;
			foreach($results as $option_set) {
				//$option_set->_processed = false;
				$resource = substr($option_set->options->resource,1);

				//retrieve the option set's options & add them to it
				try {
					$response = $client->get($resource);
				} catch (Exception $e) {
					throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching product options for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				}

				$results = $response->json(array('object'=>TRUE));
				//echo "RESPONSE".PHP_EOL.print_r($results,true).PHP_EOL;
				$option_set->options = $results;
				$this->option_sets[$option_set->id] = $option_set;
			}
		}
		return $this->option_sets;
	}

	/**
	 * Retrieve a product's options from BigCommerce and match it against provided Wombat variant options
	 */
	public function getSkuOptions($product_id,$variant_options) {
		$client = $this->client;
		$request_data = $this->request_data;

		//check whether we've already retrieved the product's options
		if(empty($this->product_options)) {
			try {
				$response = $client->get("products/$product_id/options");
			} catch (Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching product options for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
			}
			// echo "RESPONSE".PHP_EOL.print_r($response->json(array('object'=>TRUE)),true).PHP_EOL;
			$product_options = $response->json(array('object'=>TRUE));
			foreach($product_options as $option) {
				$option->_processed = false;
				$option->product_option_id = $option->id; //this will later get overridden by the option's ID when we merge with the expanded option
				$this->product_options[$option->option_id] = $option;
			}
		} else {
			$product_options = $this->product_options;
		}


		$sku_options = array();

		foreach($product_options as $product_option) {

			//If we haven't retrieved additional option info & values, do so
			if(!$product_option->_processed) {

				//Get additional option info from the main option object
				try {
					$response = $client->get("options/".$product_option->option_id);
				} catch (Exception $e) {
					throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching product options for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				}
				$option = $response->json(array('object'=>TRUE));
				$resource = substr($option->values->resource,1);
				
				// echo "OPTION".PHP_EOL.print_r($option,true).PHP_EOL;

				//get the option's values
				try {
					$response = $client->get($resource);
				} catch (Exception $e) {
					throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching product options for product \"".$wombat_obj->sku."\": ".$e->getMessage(),500);
				}

				$values = $response->json(array('object'=>TRUE));

				// echo "VALUES".PHP_EOL.print_r($values,true).PHP_EOL;

				$option->values = $values;

				//merge addtional option info into the product_option
				$product_option = (object) array_merge((array)$product_option,(array)$option);

				//cache the additional data
				$product_option->_processed = true;
				$this->product_options[$product_option->option_id] = $product_option;
			}

			
			// echo "PRODUCT OPTION:".$product_option->option_id.PHP_EOL.print_r($product_option,true).PHP_EOL;

			$sku_option = array();
			//loop through the product options, match name
			foreach($variant_options as $variant_opt_name => $variant_opt_value) {
				if(strtoupper($variant_opt_name) == strtoupper($product_option->name)) {

					$sku_option['product_option_id'] = $product_option->product_option_id;

					//once found, loop through to match value
					foreach($product_option->values as $opt_value) {

						if(strtoupper($variant_opt_value) == strtoupper($opt_value->label)) {
							$sku_option['option_value_id'] = $opt_value->id;
						}
					}
				}
			}
			$sku_options[] = (object)$sku_option;
		}
		
		return $sku_options;
	}
	
	public function getBCID() {
		$client = $this->client;
		$request_data = $this->request_data;

		if(!empty($this->data['wombat']['bigcommerce_id'])) {
			return $this->data['wombat']['bigcommerce_id'];
		}

		//if no BCID stored, query BigCommerce for the SKU
		$sku = $this->data['wombat']['id'];
		
		try {
			$response = $client->get('products',array('query'=>array('sku'=>$sku)));
			$data = $response->json(array('object'=>TRUE));

			return $data[0]->id;
		} catch (Exception $e) {
			throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching resource \"$resource_name\" for product \"".$this->data['bc']->sku."\": ".$e->getMessage(),500);
		}
	}
	
	public function loadAttachedResources() {
		$client = $this->client;
		$request_data = $this->request_data;

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