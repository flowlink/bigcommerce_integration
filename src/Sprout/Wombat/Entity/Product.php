<?php

namespace Sprout\Wombat\Entity;

class Product {

	/**
	 * @var array $data Hold the JSON object data retrieved from the source
	 */
	protected $data;

	/**
	 * @var array $_attached_resources Field names for data not contained in the main object that will need to be retrieved
	 */
	private $_attached_resources = array('images', 'brand', 'discount_rules', 'custom_fields', 'configurable_fields', 'skus', 'rules', 'option_set', 'options', 'downloads','videos','tax_class');

	/**
	 * @var array $product_options Cache product options retrieved from BigCommerce, so we don't repeat calls for each variant
	 */
	private $product_options;

	/**
	 * @var array $option_sets Cache option sets retrieved from BigCommerce, so we don't repeat calls for each variant
	 */
	private $option_sets;

	/**
	 * @var array $options Cache options retrieved from BigCommerce, so we don't repeat calls for each variant
	 */
	private $options;

	/**
	 * @var array $option_values Cache options values retrieved from BigCommerce, so we don't repeat calls for each variant
	 */
	private $option_values = array();

	/**
	 * @var array $client Http client object to perform additional requests
	 */
	private $client;

	/**
	 * @var array $request_data Data about the request that we've been sent
	 */
	private $request_data;

	public function __construct($data, $type='bc',$client = null,$request_data = null) {
		if(func_num_args() > 2) {
			$this->data[$type] = $data;
			$this->client = $client;
			$this->request_data = $request_data;	
		} else {
			$this->client = func_get_arg(0);
			$this->request_data = func_get_arg(1);
		}

	}

	/**
	 * Add or override object data after creation
	 */
	public function addData($data, $type) {
		$this->data[$type] = $data;
	}

	/**
	 * Fetch product data by SKU from BC
	 */
	public function getBySku($sku) {
		$client = $this->client;
		//get the newly created product
		try {
			$response = $client->get('products',array('query'=>array('sku'=>$sku)));
		} catch (\Exception $e) {
			$this->doException($e,'fetching created product data');
		}

		$product = $response->json(array('object'=>TRUE));
		$product = $product[0];
		
		$product->_store_url = $this->request_data['store_url'];

		$this->data['bc'] = $product;
		
		$this->loadAttachedResources();
	}


	/**
	 * after creating an object in BigCommerce, return the IDs to Wombat
	 */
	public function getWombatResponse() {
		$wombat_obj = (object) $this->data['wombat'];
		$bc_obj = (object) $this->data['bc'];

		// echo "W: ".print_r($wombat_obj,true).PHP_EOL;
		// echo "B: ".print_r($bc_obj,true).PHP_EOL;
		
		$product_id = $this->getBCID();

		$product = new \stdClass();
		
		$complex_fields = array('images','variants');

		foreach($wombat_obj as $key => $value) {
			if(!in_array($key, $complex_fields)) {
				$product->{$key} = $value;
			}
		}

		//find images, property_ids and variants, convert back to objects as necessary
		if(!empty($wombat_obj->images)) {
			$images = array();
			foreach($wombat_obj->images as $image) {
				// echo "image: ".print_r($image,true).PHP_EOL;
				$image = (object) $image;
				$image->dimensions = (object) $image->dimensions;
				$images[] = $image;
			}
			$product->images = $images;
		}
		
		if(!empty($wombat_obj->variants)) {
			$variants = array();
			foreach ($wombat_obj->variants as $variant) {
				// $variant = (object) array(
				// 	'bigcommerce_id'=> $variant['bigcommerce_id'],
				// 	'bigcommerce_rule_id'=> $variant['bigcommerce_rule_id'],
				// 	);
				$variant = (object) $variant;
				$variant->options = (object) $variant->options;
				foreach($variant->images as $image) {
					$image = (object) $image;
					$image->dimensions = (object) $image->dimensions;
				}
				$variants[] = $variant;
			}
			$product->variants = $variants;
		}

		$product->bigcommerce_id = $product_id;

		return $product;

		
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
					),
					'bigcommerce_id' => $bc_img->id,
				);
			}
		}
		
		/*** PROPERTIES ***/
		if(!empty($bc_obj->custom_fields)) {
			$property_ids = array();
			foreach($bc_obj->custom_fields as $bc_custom) {
				$key = $bc_custom->name;
				$wombat_obj->properties->$key = $bc_custom->text;
				$property_ids[$key] = $bc_custom->id;
			}
			$wombat_obj->bigcommerce_property_ids = $property_ids;
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
					'images' => array(),
					'bigcommerce_id' => $bc_sku->id,
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
							$new_variant->bigcommerce_rule_id = $bc_rule->id;
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
			'sku' 								=> $wombat_obj->id, //store this so we can use it as a primary key in Wombat
			'name' 								=> $wombat_obj->name,
			'price' 							=> (String)number_format($wombat_obj->price,2,'.',''),
			'description' 				=> $wombat_obj->description,
			'categories' 					=> $this->getCategories($wombat_obj->taxons),
			'type' 								=> 'physical',
			'availability' 				=> 'available',
			'weight' 							=> (string)1,
			'custom_url' 					=> $this->processPermalink($wombat_obj->permalink),
			'meta_description'		=> $wombat_obj->meta_description,
			'meta_keywords'				=> $wombat_obj->meta_keywords,
			'inventory_tracking' 	=> empty($wombat_obj->options)? 'simple':'sku',
		);

		//if these are present, we'll need to find an option set
		if(!empty($wombat_obj->variants) && !empty($wombat_obj->options)) {
			$bc_obj->option_set_id = $this->getOptionSetId();
		}

		$brand_id = $this->getBrandId();
		if($brand_id) {
			$bc_obj->brand_id = $brand_id;
		}
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Send data to BigCommerce that's handled separately from the main product object:
	 * custom fields, skus
	 * NB: options can't be set directly on the product - it has to be done through the option set???
	 */
	public function pushAttachedResources($action = 'create') {
		$client = $this->client;
		$request_data = $this->request_data;
		$wombat_obj = (object) $this->data['wombat'];

		$bc_id = $this->getBCID();

		$wombat_obj->bigcommerce_id = $bc_id;
		
		//map Wombat images
		if(!empty($wombat_obj->images)) {
			foreach($wombat_obj->images as &$image) {
				if(stripos($image['url'], 'product_images/../app/assets/img/sample_images'))  {
					//can't edit sample images in BigCommerce
					continue;
				}
				$data = (object) array(
					'image_file' 		=> $this->processImageURL($image['url']),
					'description' 	=> (empty($image['title']))?$wombat_obj->name:$image['title'],
					'is_thumbnail' 	=> ($image['type'] == 'thumbnail')?'true':'false',
					'sort_order'		=> $image['position'],
					);
				
				$client_options = array(
					'headers'=>array('Content-Type'=>'application/json'),
					'body' => (string)json_encode($data),
						//'debug'=>fopen('debug.txt', 'w')
				);
				try {
					if($action == 'create') {
						$response = $client->post("products/$bc_id/images",$client_options);
					} else if($action == 'update' && !empty($image['bigcommerce_id'])) {
						$image_id = $image['bigcommerce_id'];
						$response = $client->put("products/$bc_id/images/$image_id",$client_options);
					}
				} catch (\Exception $e) {
					$this->deleteProduct($bc_id);
					$this->doException($e,'pushing product images');
				}

				$created_image = $response->json(array('object'=>TRUE));
				$image['bigcommerce_id'] = $created_image->id;

			}
		}
		
		//map Wombat properties onto BC custom fields
		if(!empty($wombat_obj->properties)) {
			$bigcommerce_property_ids = array();
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
				
				try {
					if($action == 'create') {
						$response = $client->post("products/$bc_id/custom_fields",$client_options);
					} else if($action == 'update' && !empty($wombat_obj->bigcommerce_property_ids[$name])) {
						$custom_field_id = $wombat_obj->bigcommerce_property_ids[$name];
						$response = $client->put("products/$bc_id/custom_fields/$custom_field_id",$client_options);
					}
				} catch (\Exception $e) {
					$this->deleteProduct($bc_id);
					$this->doException($e,'pushing resource "properties"');
				}

				$custom_field = $response->json(array('object'=>TRUE));
				$bigcommerce_property_ids[$name] = $custom_field->id;
			}
			$wombat_obj->bigcommerce_property_ids = $bigcommerce_property_ids;
		}

		//Map Wombat variants onto BC SKUs & rules
		if(!empty($wombat_obj->variants) && !empty($wombat_obj->options)) {
			
			foreach($wombat_obj->variants as &$variant) {
				//Wombat by default includes a variant matching the master product. Don't attempt to add this as a SKU
				if($variant['sku'] == $wombat_obj->sku) {
					continue;
				}
				$data = (object) array(
					'sku' => 							$variant['sku'],
					'cost_price' => 			$variant['cost_price'],
					'inventory_level' =>	(empty($variant['quantity']))? 1 : $variant['quantity'], // @todo: only if stock tracking for parent product is set to 'sku'
					);

				
				$data->options = $this->getSkuOptions($bc_id,$variant['options']);

				$client_options = array(
					'headers'=>array('Content-Type'=>'application/json'),
					'body' => (string)json_encode($data),
						//'debug'=>fopen('debug.txt', 'w')
				);
				try {
					if($action == 'create') {
						$response = $client->post("products/$bc_id/skus",$client_options);
					} else if($action == 'update' && !empty($variant['bigcommerce_id'])) {
						$sku_id = $variant['bigcommerce_id'];
						$response = $client->put("products/$bc_id/skus/$sku_id",$client_options);
					}
				} catch (\Exception $e) {
					$this->deleteProduct($bc_id);
					$this->doException($e,'pushing variant '.$variant['sku']);
				}

				$sku = $response->json(array('object'=>TRUE));
				$variant['bigcommerce_id'] = $sku->id;

				//handle price & images via rule
				$rule = (object) array(
					'sort_order'			=> 0,
					'is_enabled'			=> true,
					'is_stop'					=> false,
					'price_adjuster'	=> (object) array(
						'adjuster'				=> 'absolute',
						'adjuster_value'	=> $variant['price'],
						),
					'is_purchasing_disabled' 			=> false,
					'purchasing_disabled_message'	=> '',
					'is_purchasing_hidden'				=> false,
					'weight_adjuster'							=> null,
					);

				//adding the rule conditions again on an update will result in the same condition being added with an 'or'
				if($action != 'update') {
					$rule->conditions			= array(
						(object) array(
							"product_option_id"	=> null,
    					"option_value_id"		=> null,
    					"sku_id"						=> $sku->id
							),
						);
				}

				if(!empty($variant['images'])) {
					$rule->image_file = $this->processImageURL($variant['images'][0]['url']);
				}
				
				$client_options = array(
					'headers'=>array('Content-Type'=>'application/json'),
					'body' => (string)json_encode($rule),
						//'debug'=>fopen('debug.txt', 'w')
				);
				
				try {
					if($action == 'create') {
						$response = $client->post("products/$bc_id/rules",$client_options);
					} else if($action == 'update' && !empty($variant['bigcommerce_rule_id'])) {
						$rule_id = $variant['bigcommerce_rule_id'];
						$response = $client->put("products/$bc_id/rules/$rule_id",$client_options);
					}
				} catch (\Exception $e) {
					$this->deleteProduct($bc_id);
					$this->doException($e,'pushing rules for variant '.$variant['sku']);
				}
				$bigcommerce_rule = $response->json(array('object'=>TRUE));
				$variant['bigcommerce_rule_id'] = $bigcommerce_rule->id;

			}
		}
		
		$this->data['wombat'] = $wombat_obj;
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

				//for the highest level category, it shouldn't have a parent
				$parent_id = 0;
				
				//Cycle through the category hierarchy we've been given and create each level if it doesn't exist
				for($i = 1; $i<count($taxon); $i++) {
					$categoryname = $taxon[$i];

					try {
						$response = $client->get("categories",array('query'=>array('name'=>$categoryname,'parent_id'=>$parent_id)));
					} catch (\Exception $e) {
						$this->doException($e,'fetching categories');
					}

					if($response->getStatusCode() == 204) {

						$new_category = (object) array(
							'parent_id' => $parent_id,
							'name' => $categoryname,
							);
						$client_options = array(
							'body' => json_encode($new_category),
							);

						try {
							$response = $client->post('categories',$client_options);
						} catch (\Exception $e) {
							$this->doException($e,'creating category $categoryname');
						}
						$category = $response->json(array('object'=>TRUE));

					} else if($response->getStatusCode() == 200) {
						$categories = $response->json(array('object'=>TRUE));
						$category = $categories[0];
					}

					if($i == count($taxon)-1) {
						$category_ids[] = $category->id;
					}
					$parent_id = $category->id;
				}
			}
		}

		// if(empty($category_ids)) {
		// 	$category_ids[] = 20; // @todo: figure out a default
		// }

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
					
					//find if the brand exists
					try {
						$response = $client->get("brands",array('query'=>array('name'=>$brandname)));
					} catch (\Exception $e) {
						$this->doException($e,'fetching brands');
					}
					if($response->getStatusCode() == 204) {
						$new_brand = (object) array(
							'name' => $brandname,
							);
						try {
							$response = $client->post("brands",array('body'=>json_encode($new_brand)));
						} catch (\Exception $e) {
							$this->doException($e,"creating brand: $brandname");
						}
						$brand = $response->json(array('object'=>TRUE));
						$brand_id = $brand->id;
					} else if($response->getStatusCode() == 200) {
						$brands = $response->json(array('object'=>TRUE));
						$brand = $brands[0];
						$brand_id = $brand->id;
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
		sort($options); //sort the items so we can compare 
		
		if(count($option_sets)) {
			//for each option set, construct an array of option names to match against the Wombat array
			foreach ($option_sets as $option_set) {
				$set_options = array();
				if(!empty($option_set->options)) {
					foreach($option_set->options as $set_option)	{
						$set_options[] = strtoupper($set_option->display_name);
					}
					
					sort($set_options);

					if($set_options == $options) {
						$output = $option_set->id;
					}
				}
			}
		}

		if(empty($output)) {
			$output = $this->createOptionSet();
		}
		
		return $output;	
	}

	/**
	 * Pull all option names and variant option values from Wombat data and create a new option set with them
	 */
	public function createOptionSet() {
		$wombat_obj = (object) $this->data['wombat'];
		$client = $this->client;
		$request_data = $this->request_data;

		$options = $wombat_obj->options;
		$variants = $wombat_obj->variants;
		$bigcommerce_options = $this->getOptions();

		//we need a name for the option set: set it to the combined names of the options
		$option_set_name = implode('_', $options);

		//create the option set & get its ID so we can add options to it
		
		$new_option_set = (object) array(
			'name' => $option_set_name,
			);
		$client_options = array(
			'body'=> json_encode($new_option_set),
			);
		
		try {
			$response = $client->post('option_sets',$client_options);
		} catch (\Exception $e) {
			$this->doException($e,'creating option set');
		}
		$option_set = $response->json(array('object'=>TRUE));

		// add this set to our cached option sets
		if(!empty($this->option_sets)) {
			$this->option_sets[] = $option_set;
		}

		
		foreach ($options as $option) {
			
			$option_name = strtoupper($option);
			$bc_option_id = false; //if we don't find a match in the existing options, we'll create one
			
			foreach($bigcommerce_options as $bc_option) {
				
				$bc_name = strtoupper($bc_option->name);
				if($option_name == $bc_name) {
					$bc_option_id = $bc_option->id;
				}
			}

			if(!$bc_option_id) {
				$bc_option_id = $this->createOption($option)->id;
			}

			$new_option_set_option = (object) array(
				'option_id' => $bc_option_id,
				);
			$client_options = array(
				'body'=> json_encode($new_option_set_option),
			);
			try {
				$response = $client->post('option_sets/'.$option_set->id.'/options',$client_options);
			} catch (\Exception $e) {
				$this->doException($e,"assigning option $option");
			}
		}

		return $option_set->id;
	}

	/**
	 * Create a new option
	 */
	public function createOption($name) {
		$client = $this->client;
		$request_data = $this->request_data;

		$new_option = (object) array(
			"name" 					=> $name,
  		"display_name" 	=> ucfirst(strtolower($name)),
  		"type" 					=> "S" // @todo: this gives us a basic dropdown menu - do we want to be able to decide this?
			);

		$client_options = array(
				'body'	=> json_encode($new_option),
			);
		try {
			$response = $client->post('options',$client_options);
		} catch (\Exception $e) {
			$this->doException($e,"creating option $name");
		}

		$option = $response->json(array('object'=>TRUE));
		
		//reset our cached options
		if(!empty($this->options)) {
			$this->options = array();
		}
		return $option;
	}

	/**
	 * Create a new value for an option
	 */
	public function createOptionValue($option, $option_value) {
		$client = $this->client;
		// echo "CREATE $option_value for: ".print_r($option,true).PHP_EOL;
		$new_value = (object) array(
			'value' => $option_value,
			'label' => ucfirst(strtolower($option_value)),
			);

		$client_options = array(
				'body'	=> json_encode($new_value),
			);

		try {
			$response = $client->post('options/'.$option->id.'/values',$client_options);
		} catch (\Exception $e) {
			$this->doException($e,"creating option value {$option_value} for option {$option->name}");
		}

		$value = $response->json(array('object'=>TRUE));
		
		//reset  our cached options
		if(!empty($this->option_values)) {
			$this->option_values = array();
		}
		
		return $value;
	}

	/**
	 * Get option sets & their values from BigCommerce
	 */
	public function getOptionSets() {
		$client = $this->client;
		$request_data = $this->request_data;

		if(empty($this->option_sets)) {

			$this->option_sets = array();
			//get the option sets from BigCommerce
			try {
				$response = $client->get("option_sets");
			} catch (\Exception $e) {
				$this->doException($e,'fetching product options');
			}

			$results = $response->json(array('object'=>TRUE));
			
			if(count($results)) {
				foreach($results as $option_set) {
					//$option_set->_processed = false;
					$resource = substr($option_set->options->resource,1);

					//retrieve the option set's options & add them to it
					try {
						$response = $client->get($resource);
					} catch (\Exception $e) {
						$this->doException($e,'fetching product options');
					}

					$results = $response->json(array('object'=>TRUE));
					
					$option_set->options = $results;
					$this->option_sets[$option_set->id] = $option_set;
				}
			}
		}
		return $this->option_sets;
	}

	/**
	 * Get all options from the store
	 */
	public function getOptions() {

		//if we haven't previously fetched the store options, do so
		if(empty($this->options)) {
			$client = $this->client;
			$request_data = $this->request_data;
			$options = array();
			
			try {
				$response = $client->get('options');
			} catch (\Exception $e) {
				$this->doException($e,'fetching store options');
			}

			if($response->getStatusCode() != 204) {
				$options = $response->json(array('object'=>TRUE));
			}


			$this->options = $options;
			
		}

		return $this->options;
	}

	/**
	 * Get a list of options assigned to a product
	 *
	 * The id here will be the product_option_id we need for variant/SKU creation
	 */

	public function getProductOptions($product_id) {
		$client = $this->client;

		if(empty($this->product_options)) {
			try {
				$response = $client->get("products/$product_id/options");
			} catch (\Exception $e) {
				$this->doException($e,'sku options');
			}
			
			$product_options = $response->json(array('object'=>TRUE));
			foreach($product_options as $option) {
				$this->product_options[$option->option_id] = $option;
			}
		}

		return $this->product_options;

	} 

	/**
	 * Get option values for a given option
	 */
	public function getOptionValues($option) {
		
		if(empty($this->option_values[$option->id])) {
			
			$client = $this->client;
			$request_data = $this->request_data;

			$resource = substr($option->values->resource,1);
			
			//get the option's values
			try {
				$response = $client->get($resource);
			} catch (\Exception $e) {
				$this->doException($e,'fetching option values');
			}

			$values = $response->json(array('object'=>TRUE));
			$this->option_values[$option->id] = $values;
		}

		return $this->option_values[$option->id];
	}

	/**
	 * Retrieve a product's options from BigCommerce and match it against provided Wombat variant options
	 */
	public function getSkuOptions($product_id,$variant_options) {
		$client = $this->client;
		$request_data = $this->request_data;

		// $options = $this->getOptions();
		// foreach ($options as $option) {

		// }

		$product_options = $this->getProductOptions($product_id);
		$options = $this->getOptions();
		// echo "PRODUCT_OPTIONS: ".print_r($product_options,true).PHP_EOL;
		// echo "OPTIONS: ".print_r($options,true).PHP_EOL;

		$sku_options = array();

		foreach($variant_options as $variant_opt_name => $variant_opt_value) {
			$sku_option = array();
			// echo "VAR OPT: $variant_opt_name $variant_opt_value".PHP_EOL;
			foreach($options as $option) {
				// echo "OPT: {$option->name}".PHP_EOL;
				if(strtoupper($variant_opt_name) == strtoupper($option->name)) {
					
					if(array_key_exists($option->id, $product_options)) {
						$sku_option['product_option_id'] = $product_options[$option->id]->id;
					}
					$values = $this->getOptionValues($option);

					// echo "OPT VAL: ".print_r($values,true).PHP_EOL;
					
					$value_id = 0;
					if(count($values)) {
						foreach($values as $value) {
							if(strtoupper($variant_opt_value) == strtoupper($value->value)) {
								$value_id = $value->id;
							}
						}
					}
					if(!$value_id) {

						//we didn't find a value ID, so create the value for this option
						$new_value = $this->createOptionValue($option,$variant_opt_value);
						// echo "CREATE VALUE: ".print_r($new_value,true).PHP_EOL;
						$value_id = $new_value->id;
					}

					$sku_option['option_value_id'] = $value_id;
				}
			}
			if(empty($sku_option['product_option_id']) || empty($sku_option['option_value_id'])) {
				$this->deleteProduct($product_id);
				$this->doException(null,"Could not match variant options against BigCommerce options. Check that the option names are not misspelt and that variant options names agree with master product option list.");
			}
			// echo "SKU OPT: ".print_r($sku_option,true).PHP_EOL;
			$sku_options[] = (object)$sku_option;
		}

		// foreach($product_options as $product_option) {

		// 	//If we haven't retrieved additional option info & values, do so
		// 	if(!$product_option->_processed) {

		// 		//Get additional option info from the main option object
		// 		try {
		// 			$response = $client->get("options/".$product_option->option_id);
		// 		} catch (\Exception $e) {
		// 			$this->doException($e,'fetching option info');
		// 		}
		// 		$option = $response->json(array('object'=>TRUE));
		// 		$resource = substr($option->values->resource,1);

		// 		//get the option's values
		// 		try {
		// 			$response = $client->get($resource);
		// 		} catch (\Exception $e) {
		// 			$this->doException($e,'fetching option values');
		// 		}

		// 		$values = $response->json(array('object'=>TRUE));

		// 		$option->values = $values;

		// 		//merge addtional option info into the product_option
		// 		$product_option = (object) array_merge((array)$product_option,(array)$option);

		// 		//cache the additional data
		// 		$product_option->_processed = true;
		// 		$this->product_options[$product_option->option_id] = $product_option;
		// 	}

		// 	$sku_option = array();
		// 	//loop through the product options, match name
		// 	foreach($variant_options as $variant_opt_name => $variant_opt_value) {
		// 		if(strtoupper($variant_opt_name) == strtoupper($product_option->name)) {

		// 			$sku_option['product_option_id'] = $product_option->product_option_id;

		// 			//once found, loop through to match value
		// 			foreach($product_option->values as $opt_value) {

		// 				if(strtoupper($variant_opt_value) == strtoupper($opt_value->label)) {
		// 					$sku_option['option_value_id'] = $opt_value->id;
		// 				}
		// 			}
		// 		}
		// 	}
		// 	$sku_options[] = (object)$sku_option;
		// }
		
		return $sku_options;
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
		$sku = $wombat_obj->id;
		
		try {
			$response = $client->get('products',array('query'=>array('sku'=>$sku)));
		} catch (\Exception $e) {
			$this->doException($e,'fetching bigcommerce_id');
		}

		if($response->getStatusCode() == 204) {
			$this->doException(null, "No product could be found for ID: {$sku}, and no bigcommerce_id was provided.");
		}

		$data = $response->json(array('object'=>TRUE));

		return $data[0]->id;
	}

	/**
	 * Delete a product in BigCommerce
	 */
	public function deleteProduct($product_id) {
		$client = $this->client;

		try {
			$response = $client->delete("products/{$product_id}");
		} catch( \Exception $e ) {
			$this->doException($e, "deleting product after error");
		}

		// @todo: response should have 204 code on success - do anything with that?
	}
	
	/**
	 * Perform any sub-requests to load additional resources
	 */
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
					} catch (\Exception $e) {
						$this->doException($e,"fetching resource $resource_name");

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

	/**
	 * Make sure the permalink has slashes: /example/
	 */
	private function processPermalink($permalink) {
		if(substr($permalink, 0,1) != '/') {
			$permalink = '/'.$permalink;
		}
		if(substr($permalink, strlen($permalink)-1,1) != '/') {
			$permalink .= '/';
		}
		return $permalink;
	}

	/**
	 * Strip any query variables off of image URLs
	 */
	private function processImageURL($url) {
		$parts = preg_split('/&|\?/',$url);
		return $parts[0];
	}

	/**
	 * Thow an exception in our format
	 */
	protected function doException($e,$action) {
		$wombat_obj = (object) $this->data['wombat'];

		$response_body = "";
		if(!is_null($e)) {
			$response_body = ":::::".$e->getResponse()->getBody();
			$message = ":::::Error received from BigCommerce while {$action} for Product: {$wombat_obj->id}";
		} else {
			$message = ":::::".$action." Product: {$wombat_obj->sku}";
		}
		throw new \Exception($this->request_data['request_id'].$message.$response_body,500);
	}
	
}