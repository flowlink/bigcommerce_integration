<?php

namespace Sprout\Wombat\Entity;

class Customer {

	/**
	 * @var array $data Hold the JSON object data retrieved from the source
	 */
	protected $data;

	/**
	 * @var array $_attached_resources Field names for data not contained in the main object that will need to be retrieved
	 */
	private $_attached_resources = array('addresses');
	
	/**
	 * @var array $client Http client object to perform additional requests
	 */
	private $client;

	/**
	 * @var array $request_data Data about the request that we've been sent
	 */
	private $request_data;

	public function __construct($data, $type='bc',$client,$request_data) {
		$this->data[$type] = $data;
		$this->client = $client;
		$this->request_data = $request_data;
	}

	/**
	 * Push this data to BigCommerce (BC)
	 */
	public function push() {
		$client = $this->client;
		$request_data = $this->request_data;
		
		$id = $this->getBCID();

		//format our data for BC	
		$bc_data = $this->getBigCommerceObject();
		$options = array(
			'body' => (string)json_encode($bc_data),
			//'debug'=>fopen('debug.txt', 'w')
			);

		//if there's an existing BC ID, then update
		if($id) {
			try {
				$response = $client->put("customers/$id",$options);
			} catch (RequestException $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce:::::".$e->getResponse()->getBody(),500);
			}

			$this->pushAttachedResources($id);
		} else {

			//no ID found, so create a new customer
			try {
				$response = $client->post("customers",$options);
			} catch (RequestException $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce:::::".$e->getResponse()->getBody(),500);
			}

			$this->pushAttachedResources($id);
		}

		$result = "The customer $bc_data->first_name $bc_data->last_name was ".($id ? 'updated' : 'created')." in BigCommerce";
		return $result;

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
		

			if(count($bc_obj->_addresses) > 1) {
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
		}

		$this->data['wombat'] = $wombat_obj;
		return $wombat_obj;
	}

	/**
	 * Get a BigCommerce-formatted set of data from a Wombat one.
	 */
	public function getBigCommerceObject() {
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
		if(!empty($wombat_obj->billing_address['phone'])) {
			$bc_obj->phone = $wombat_obj->billing_address['phone'];
		}
		
		$this->data['bc'] = $bc_obj;
		return $bc_obj;
	}

	/**
	 * Get the BigCommerce ID for a customer by fetching customers filtered by email address
	 */
	public function getBCID() {
		
		$id = 0;

		if(!empty($this->data['wombat']['bigcommerce_id'])) {
			$id = $this->data['wombat']['bigcommerce_id'];
		}

		//if bigcommerce_id isn't available, check whether the Wombat ID is our hash_id format:
		if(!$id) {
			$hash = $this->request_data['hash'];
			$wombat_id = $this->data['wombat']['id'];

			if((stripos($id, $hash) !== false) && (strlen($id) >= strlen($hash))) {
				$id = str_replace($hash.'_', '', $wombat_id);
			
			}
		}

		// if neither of the above BCID stored, query BC for the email
		if(!$id) {
			$id = $this->getBCCustomerByEmail();
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
	public function pushAttachedResources($id) {
		$client = $this->client;
		$request_data = $this->request_data;
		$wombat_obj = (object) $this->data['wombat'];

		//if we haven't been passed an ID, the customer has just been created. Fetch their ID
		if(!$id) {
			$bc_id = $this->getBCCustomerByEmail();
		} else {

			//otherwise, we're doing an update on an existing customer ID
			$bc_id = $id;
		}
		
		//fetch any existing addresses
		$path = "customers/$id/addresses";
		$addresses = array();
		try {
			$response = $client->get($path);
		}
		catch(\Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching customer address:::::".$e->getResponse()->getBody(),500);
		}
		if($response->getStatusCode() != 204) {
			$addresses = $response->json(array('object'=>TRUE));
		}
		
		$options = array();

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
			
			try {
				if(!$id || (empty($wombat_obj->billing_address['bigcommerce_id']) && count($addresses) == 0)) {
					//this customer never existed, or the customer does but this address doesn't seem to
					$response = $client->post($path,$options);
				} else {

					//if we've been passed an existing address id, use that
					if(!empty($wombat_obj->billing_address['bigcommerce_id'])) {
						$address_id = $wombat_obj->billing_address['bigcommerce_id'];
					} else if(count($addresses) > 0) {
						//we've found an existing address, assume it's billing
						$address_id = $addresses[0]->id;
					}
					$path = "customers/$bc_id/addresses/$address_id";

					$response = $client->put($path,$options);
				}
			}
			catch(\Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while ".(!$id?'creating':'updating')." customer address:::::".$e->getResponse()->getBody(),500);
			}
			
			//Storing the returned address ID - won't currently be used, but maybe later...
			$bc_address = $response->json(array('object'=>TRUE));
			$wombat_obj->billing_address['bigcommerce_id'] = $bc_address->id;
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
			
			try {
				if(!$id || (empty($wombat_obj->shipping_address['bigcommerce_id']) && count($addresses) < 2)) {
					//this customer never existed, or the customer does but this address doesn't seem to
					$response = $client->post($path,$options);
				} else {
						
					//if we've been passed an existing address id, use that
					if(!empty($wombat_obj->shipping_address['bigcommerce_id'])) {
						$address_id = $wombat_obj->shipping_address['bigcommerce_id'];
					} else if(count($addresses) > 1) {
						//we've found an existing address, assume it's shipping
						$address_id = $addresses[1]->id;
					}
					$path = "customers/$id/addresses/$address_id";
					
					$response = $client->put($path,$options);
				}
			}
			catch(\Exception $e) {
				throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while ".(!$id?'creating':'updating')." customer address:::::".$e->getResponse()->getBody(),500);
			}

			$bc_address = $response->json(array('object'=>TRUE));
			$wombat_obj->shipping_address['bigcommerce_id'] = $bc_address->id;
		}
		
	}

	/**
	 * after creating an object in BigCommerce, return the IDs to Wombat
	 */
	public function pushBigCommerceIDs($client, $request_data) {
		$wombat_obj = (object) $this->data['wombat'];

		$customer_id = $this->getHashId($this->getBCID());

		$customer = (object) array(
			'id' 							=> $wombat_obj->id,
			'bigcommerce_id'	=> $customer_id,
			);

		if(!empty($wombat_obj->billing_address)) {
			$customer->billing_address = $wombat_obj->billing_address;
			
		}
		if(!empty($wombat_obj->shipping_address)) {
			$customer->billing_address = $wombat_obj->shipping_address;
		}

		$update_data = (object) array(
			'customers' => array($customer),
			);
		

		try{
			$client_options = array(
				'headers'=>array('Content-Type'=>'application/json'),
				'body' => (string)json_encode($update_data),
				//'debug' => fopen('debug.txt','w'),
				);
			$client->post('',$client_options);
		}
		catch (\Exception $e) {
			throw new \Exception($request_data['request_id'].":::::Error received from Wombat while pushing BigCommerce ID values for \"".$wombat_obj->id."\":::::".$e->getResponse()->getBody(),500);
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

	/**
	 * Add the store hash to the object ID
	 */
	public function getHashId($id) {
		$hash = $this->request_data['hash'];
		
		return $hash.'_'.$id;
	}

	/**
	 * Get an existing customer's ID from BigCommerce by email
	 */
	public function getBCCustomerByEmail() {
		$client = $this->client;
		$request_data = $this->request_data;

		$id = 0;
		$email = $this->data['wombat']['email'];
		
		try {
			$response = $client->get('customers',array('query'=>array('email'=>$email)));
			$data = $response->json(array('object'=>TRUE));
			
			//if results not empty
			if($response->getStatusCode() != 204) {
				$id = $data[0]->id;
			}
		} catch (\Exception $e) {
			throw new \Exception($request_data['request_id'].":::::Error received from BigCommerce while fetching BigCommerce Customer ID.:::::".$e->getMessage()->getBody(),500);
		}

		return $id;
	}
}