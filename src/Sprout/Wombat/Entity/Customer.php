<?php

namespace Sprout\Wombat\Entity;

class Customer {

	protected $data;
	private $_attached_resources = array('addresses');
	private $client;
	private $request_data;

	public function __construct($data, $type='bc',$client,$request_data) {
		$this->data[$type] = $data;
		$this->client = $client;
		$this->request_data = $request_data;
	}


	/*
		Wombat attributes:
		id	Unique identifier for the shipment	String
		email	Customers email address	String
		firstname	Customers first name	String
		lastname	Customers last name	String
		billing_address	Customers shipping address	Address
		shipping_address	Customers shipping address	Address
	*/

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
			'id' => $this->getHashId($bc_obj->id),
			'firstname' => $bc_obj->first_name,
			'lastname' => $bc_obj->last_name,
			'email' => $bc_obj->email,
			'bigcommerce_id' => $bc_obj->id,
		);

		if(!empty($bc_obj->_addresses)) {
			$address = $bc_obj->_addresses[0];
			$wombat_obj->billing_address = (object) array(
				'firstname' => $address->first_name,
				'lastname' 	=> $address->last_name,
				'address1' 	=> $address->street_1,
				'address2' 	=> $address->street_2,
				'zipcode' 	=> $address->zip,
				'city' 			=> $address->city,
				'state' 		=> $address->state,
				'country' 	=> $address->country_iso2,
				'phone' 		=> $address->phone,
				'bigcommerce_id' 			=> $address->id,
			);
		}

		if(!empty($bc_obj->_addresses) && count($bc_obj->_addresses) > 1) {
			$address = $bc_obj->_addresses[1];
			$wombat_obj->shipping_address = (object) array(
				'firstname' => $address->first_name,
				'lastname' 	=> $address->last_name,
				'address1' 	=> $address->street_1,
				'address2' 	=> $address->street_2,
				'zipcode' 	=> $address->zip,
				'city' 			=> $address->city,
				'state' 		=> $address->state,
				'country' 	=> $address->country_iso2,
				'phone' 		=> $address->phone,
				'bigcommerce_id' 			=> $address->id,
			);
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
		
		// @todo: add addresses
		$bc_obj = (object) array(
			'first_name' => $wombat_obj->firstname,
			'last_name' => $wombat_obj->lastname,
			'email' => $wombat_obj->email,
		);
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Get the BigCommerce ID for a customer by fetching customers filtered by email address
	 */
	public function getBCID() {
		$client = $this->client;
		$request_data = $this->request_data;

		if(!empty($this->data['wombat']['bigcommerce_id'])) {
			return $this->data['wombat']['bigcommerce_id'];
		}

		// //if no BCID stored, query BC for the email
		// $email = $this->data['wombat']['email'];
		
		// try {
		// 	$response = $client->get('customers',array('query'=>array('email'=>$email)));
		// 	$data = $response->json(array('object'=>TRUE));
			
		// 	return $data[0]->id;
		// } catch (Exception $e) {
		// 	throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching resource \"$resource_name\" for product \"".$this->data['bc']->sku."\": ".$e->getMessage(),500);
		// }
		$hash = $this->request_data['hash'];
		$id = $this->data['wombat']['id'];

		if(strlen($id) >= strlen($hash)) {
			$id = str_replace($hash.'_', '', $id);
		}
		return $id;
	}

	/**
	 * Load any attached resources from BigCommerce
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
					$response = $client->get($resource->url);
					
					if(intval($response->getStatusCode()) === 200)
						$this->data['bc']->$resource_name = $response->json(array('object'=>TRUE));
					else
						$this->data['bc']->$resource_name = NULL;
				}
			}
		}

		// organize extra resources (not really in API)
		
		/*  _addresses
		*/
		if(!empty($this->data['bc']->addresses)) {
			$this->data['bc']->_addresses = array();

			//if the customer has >2 address, take the last two, otherwise take whatever they have
			if(count($this->data['bc']->addresses) > 2) {
				$i = count($this->data['bc']->addresses)-2;
			} else {
				$i = 0;
			}
			
			for($i; $i<count($this->data['bc']->addresses); $i++) {
				$address = $this->data['bc']->addresses[$i];
				$this->data['bc']->_addresses[] = $address;
			}
			
		}
	}

	/**
	 * Send data to BigCommerce that's handled separately from the main customer object:
	 * addresses
	 */
	public function pushAttachedResources($action = 'create') {
		$client = $this->client;
		$request_data = $this->request_data;
		$wombat_obj = (object) $this->data['wombat'];

		//get the customer ID via their email
		$id = $this->getBCID($client,$request_data);
		$path = "customers/$id/addresses";
		$options = array(
			'headers'=>array('Content-Type'=>'application/json'),
			);

		if(!empty($wombat_obj->billing_address)) {
			$billing_address = (object) array(
				
			  "first_name"	=> $wombat_obj->firstname,
			  "last_name"		=> $wombat_obj->lastname,
			  "company"			=> '',
			  "street_1"		=> $wombat_obj->billing_address['address1'],
			  "street_2"		=> $wombat_obj->billing_address['address2'],
			  "city"				=> $wombat_obj->billing_address['city'],
			  "state"				=> $wombat_obj->billing_address['state'],
			  "zip"					=> $wombat_obj->billing_address['zipcode'],
			  "country"			=> $this->getCountryName($wombat_obj->billing_address['country']),
			  "phone"				=> $wombat_obj->billing_address['phone'],

				);

			$options['body'] = (string)json_encode($billing_address);
			//echo print_r($options['body'],true).PHP_EOL;
			try {
				if($action == 'create') {
					$client->post($path,$options);
				} else if($action == 'update') {
					$address_id = $wombat_obj->billing_address['bigcommerce_id'];
					$path = "customers/$id/addresses/$address_id";

					$client->put($path,$options);
				}
			}
			catch(\Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while ".($action=='create'?'creating':'updating')." customer address: ".$e->getResponse(),500);
			}
		}


		if(!empty($wombat_obj->shipping_address)) {
			$shipping_address = (object) array(
				
			  "first_name"	=> $wombat_obj->firstname,
			  "last_name"		=> $wombat_obj->lastname,
			  "company"			=> '',
			  "street_1"		=> $wombat_obj->shipping_address['address1'],
			  "street_2"		=> $wombat_obj->shipping_address['address2'],
			  "city"				=> $wombat_obj->shipping_address['city'],
			  "state"				=> $wombat_obj->shipping_address['state'],
			  "zip"					=> $wombat_obj->shipping_address['zipcode'],
			  "country"			=> $this->getCountryName($wombat_obj->shipping_address['country']),
			  "phone"				=> $wombat_obj->shipping_address['phone'],

				);

			$options['body'] = (string)json_encode($shipping_address);
			//echo print_r($options['body'],true).PHP_EOL;
			try {
				if($action == 'create') {
					$client->post($path,$options);
				} else if($action == 'update') {
					$address_id = $wombat_obj->shipping_address['bigcommerce_id'];
					$path = "customers/$id/addresses/$address_id";
					
					$client->put($path,$options);
				}
			}
			catch(\Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while ".($action=='create'?'creating':'updating')." customer address: ".$e->getResponse(),500);
			}
		}
		
	}

	/**
	 * Convert a country iso code to a full name
	 */
	private function getCountryName($iso) {
		$countries = array(
			'AF' => 'Afghanistan',
			'AX' => 'Aland Islands',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua And Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BA' => 'Bosnia And Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei Darussalam',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Congo, Democratic Republic',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'CI' => 'Cote D\'Ivoire',
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands (Malvinas)',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern Territories',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard Island & Mcdonald Islands',
			'VA' => 'Holy See (Vatican City State)',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran, Islamic Republic Of',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle Of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KR' => 'Korea',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Lao People\'s Democratic Republic',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libyan Arab Jamahiriya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macao',
			'MK' => 'Macedonia',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia, Federated States Of',
			'MD' => 'Moldova',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'AN' => 'Netherlands Antilles',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestinian Territory, Occupied',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion',
			'RO' => 'Romania',
			'RU' => 'Russian Federation',
			'RW' => 'Rwanda',
			'BL' => 'Saint Barthelemy',
			'SH' => 'Saint Helena',
			'KN' => 'Saint Kitts And Nevis',
			'LC' => 'Saint Lucia',
			'MF' => 'Saint Martin',
			'PM' => 'Saint Pierre And Miquelon',
			'VC' => 'Saint Vincent And Grenadines',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome And Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia And Sandwich Isl.',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard And Jan Mayen',
			'SZ' => 'Swaziland',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syrian Arab Republic',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TH' => 'Thailand',
			'TL' => 'Timor-Leste',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad And Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks And Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UM' => 'United States Outlying Islands',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VE' => 'Venezuela',
			'VN' => 'Viet Nam',
			'VG' => 'Virgin Islands, British',
			'VI' => 'Virgin Islands, U.S.',
			'WF' => 'Wallis And Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);

		$iso = strtoupper($iso);
		$output = '';
		if(array_key_exists($iso, $countries)) {
			$output = $countries[$iso];
		}
		return $output;
	}
	public function getHashId($id) {
		$hash = $this->request_data['hash'];
		
		return $hash.'_'.$id;
	}
}