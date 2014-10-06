<?php

namespace Sprout\Wombat\Entity;

class Inventory {

	/**
	 * @var array $data Hold the JSON object data retrieved from the source
	 */
	protected $data;

	/**
	 * @var int/array $bc_id Hold the BigCommerce ID data so we don't repeat calls to BC
	 */
	protected $bc_id;

	public function __construct($data, $type='bc',$client,$request_data) {
		$this->data[$type] = $data;
		$this->client = $client;
		$this->request_data = $request_data;
	}

	public function push() {

		$this->checkInventoryTracking();

		$wombat_obj = (object) $this->data['wombat'];
		$bc_data = $this->getBigCommerceObject();
		$bc_id = $this->getBCID();

		
		

		if(is_array($bc_id)) {
			$response = $this->pushSku($bc_id,$bc_data);
		} else {
			$response = $this->pushMaster($bc_id,$bc_data);
		}

		if($response->getStatusCode() != 200) {
			$this->doException(null,"Error while pushing inventory {$wombat_obj->id}:::::".$response->getBody());
		}

		if(is_array($bc_id)) {
			$wombat_obj->bigcommerce_parent_id = $bc_id['parent_id'];
			$wombat_obj->bigcommerce_sku_id = $bc_id['sku_id'];
		} else {
			$wombat_obj->bigcommerce_id = $bc_id;
		}

		return array(
			'message' => "The Inventory {$wombat_obj->id} for product: {$wombat_obj->product_id} was updated in BigCommerce",
			'objects' => array($wombat_obj),
			);
	}

	/**
	 * Push a sku/variant
	 */
	public function pushSku($id_array,$data) {
		$client = $this->client;
		$sku_id = $id_array['sku_id'];
		$parent_id = $id_array['parent_id'];
		
		$client_options = array(
			'body' => json_encode($data),
			//'debug' => fopen('debug.txt','w'),
			);

		try {
			$response = $client->put("products/{$parent_id}/skus/{$sku_id}",$client_options);
		} catch (\Exception $e) {
			$this->doException($e,"pushing the variant quantity");
		}

		return $response;
	}

	/**
	 * Push a master/simple product
	 */
	public function pushMaster($id,$data) {
		$client = $this->client;

		$client_options = array(
			'body' => json_encode($data),
			);

		try {
			$response = $client->put("products/{$id}",$client_options);
		} catch (\Exception $e) {
			$this->doException($e,"pushing the variant quantity");
		}

		return $response;
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
			// todo : pulling inventory not in spec yet
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
		if(!empty($wombat_obj->bigcommerce_inventory_warning_level)) {
			$bc_obj->inventory_warning_level = $wombat_obj->bigcommerce_inventory_warning_level;
		}
		
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Check if the product has inventory tracking on
	 */
	public function checkInventoryTracking() {
		$client =  $this->client;
		$request_data = $this->request_data;
		
		$id = $this->getBCID();

		$product_id = (is_array($id))?$id['parent_id']:$id;
		
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
		
		if($product->inventory_tracking == 'none' ) {
			$this->doException(null,"This product does not have inventory tracking enabled.");
		}

	}

	/**
	 * Return the BigCommerceID for this object
	 */
	public function getBCID() {
		if(empty($this->bc_id)) {

			$client = $this->client;
			$request_data = $this->request_data;
			$wombat_obj = (object) $this->data['wombat'];
			
			$id = 0;

			if(!empty($wombat_obj->bigcommerce_id)) {
				$id = $wombat_obj->bigcommerce_id;
			}

			//if the parent_id is set, then use that to find the variant ID
			if(!$id && !empty($wombat_obj->bigcommerce_parent_id)) {
				if(!empty($wombat_obj->bigcommerce_sku_id)) {
					$sku_id = $wombat_obj->bigcommerce_sku_id;
				} else {
					$sku_id = $this->getSkuFromProduct($wombat_obj->bigcommerce_parent_id,$wombat_obj->product_id);
				}
				$id = array(
					'sku_id' => $sku_id,
					'parent_id' => $wombat_obj->bigcommerce_parent_id,
				);
			} 
			
			//if the parent SKU is set, use that to find the variant ID
			if(!$id && !empty($wombat_obj->bigcommerce_parent_sku)) {
				$parent_id = $this->getProduct($wombat_obj->bigcommerce_parent_sku);
				
				if($parent_id) {
					
					if(!empty($wombat_obj->bigcommerce_sku_id)) {
						$sku_id = $wombat_obj->bigcommerce_sku_id;
					} else {
						$sku_id = $this->getSkuFromProduct($parent_id,$wombat_obj->product_id);
					}

					if($sku_id) {
						$id = array(
							'parent_id' => $parent_id,
							'sku_id'		=> $sku_id,
							);
					}
				}
			}

			

			// If none of the above have worked, we're either not looking at a variant, or it's missing data.
			// See if the Wombat ID matches a BC parent SKU
			if(empty($id)) {
				$sku = $wombat_obj->product_id;
				
				
				try {
					$response = $client->get('products',array('query'=>array('sku'=>$sku)));
				} catch (\Exception $e) {
					$this->doException($e,'fetching parent ID from Wombat ID');
				}

				$data = $response->json(array('object'=>TRUE));

				if($response->getStatusCode() != 204) {
					$id = $data[0]->id;
					
				} else {
					//$this->doException(null, "No product could be found for ID: {$sku}, and no bigcommerce_id, bigcommerce_parent_id, or bigcommerce_parent_sku was provided.");
				}

			}

			// If still empty, get all products from store, and search through them to find a variant SKU
			if(empty($id)) {
				$product_count = $this->getProductCount();

				$id = $this->scanProducts($wombat_obj->product_id,$product_count);
			}

			if(empty($id)) {
				$this->doException(null, "No product could be found for ID: {$sku}, and no bigcommerce_id, bigcommerce_parent_id, or bigcommerce_parent_sku was provided.");
			}
			
			$this->bc_id = $id;
		}

		return $this->bc_id;
	}

	/**
	 * Get a count of all store products
	 */
	public function getProductCount() {
		$client = $this->client;
		$count = 0;

		try {
			$response = $client->get("products/count");
		} catch (\Exception $e) {
			$this->doException($e,'fetching bigcommerce product count');
		}

		$data = $response->json(array('object'=>TRUE));

		$count = $data->count;

		return $count;
	}

	/**
	 * Scan through store products for a variant SKU
	 */
	public function scanProducts($variant_id,$product_count) {
		$client = $this->client;
		$page_size = 10;
		$page = 1;
		$id = false;

		while(true) {
			try {
				$response = $client->get("products",array('query'=>array('limit'=>$page_size,'page'=>$page)));
			} catch (\Exception $e) {
				$this->doException($e,'scanning bigcommerce products');
			}

			if($response->getStatusCode() == 204) {
				break;
			}

			$data = $response->json(array('object'=>TRUE));
			
			foreach($data as $product) {
				
				if(!empty($product->option_set_id)) {
					$sku_id = $this->getSkuFromProduct($product->id,$variant_id);
					if($sku_id) {
						$id = array(
							'parent_id'=>$product->id,
							'sku_id' => $sku_id
							);
						break;
					}

				}
				
			}

			$page++;
		}

		return $id;
	}

	/**
	 * Get a parent product from its SKU
	 */
	public function getProduct($sku) {
		$client = $this->client;
		$product_id = 0;

		try {
			$response = $client->get("products",array('query'=>array('sku'=>$sku)));
		} catch (\Exception $e) {
			$this->doException($e,'fetching bigcommerce_parent_sku products');
		}

		if($response->getStatusCode() != 204) {
			
			$data = $response->json(array('object'=>TRUE));
			
			$product_id = $data[0]->id;
		}

		return $product_id;
	}

	/**
	 * Get SKUs for a given parent product
	 */
	public function getSkuFromProduct($product_id,$sku) {
		$client = $this->client;
		$id = 0;
		
		try {
			$response = $client->get("products/{$product_id}/skus",array('query'=>array('sku'=>$sku)));
		} catch (\Exception $e) {
			$this->doException($e,'fetching bigcommerce_parent_id skus');
		}

		if($response->getStatusCode() != 204) {
			
			$data = $response->json(array('object'=>TRUE));
			
			$id = $data[0]->id;
			

		}	

		return $id;
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