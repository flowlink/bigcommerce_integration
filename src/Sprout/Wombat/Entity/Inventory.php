<?php

namespace Sprout\Wombat\Entity;

class Inventory {

	protected $data;

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
	
}