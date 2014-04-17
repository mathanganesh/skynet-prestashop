<?php

// Avoid direct access to the file
if (!defined('_PS_VERSION_'))
	exit;

//
class skynet extends CarrierModule
{

	public  $id_carrier;
	private $_html = '';
	private $_postErrors = array();
	private $_moduleName = 'skynet';


	/*
	** Construct Method
	**
	*/

	public function __construct()
	{
		$this->name = 'skynet';
		$this->tab = 'shipping_logistics';
		$this->version = '1.0';
		$this->author = 'Cybrain Solutions';
		$this->limited_countries = array('fr', 'us');

		parent::__construct ();

		$this->displayName = $this->l('SKYNET SHIPPING');
		$this->description = $this->l('Online shopping & Shipping services');

		if (self::isInstalled($this->name))
		{
			// Getting carrier list
			global $cookie;
			$carriers = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

			// Saving id carrier list
			$id_carrier_list = array();
			foreach($carriers as $carrier)
				$id_carrier_list[] .= $carrier['id_carrier'];

			// Testing if Carrier Id exists
			
		}
	}


	/*
	** Install / Uninstall Methods
	**
	*/

	public function install()
	{
		$carrierConfig = array(
			0 => array('name' => 'Skynet Wordldwide express',
				'id_tax_rules_group' => 0,
				'active' => true,
				'deleted' => 0,
				'shipping_handling' => false,
				'range_behavior' => 0,
				'delay' => array('fr' => 'Skynet Wordldwide express', 'en' => 'Skynet Wordldwide express', Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')) => 'Reach in time'),
				'id_zone' => 1,
				'is_module' => true,
				'shipping_external' => true,
				'external_module_name' => 'skynet',
				'need_range' => true
			)
			
		);

		$id_carrier1 = $this->installExternalCarrier($carrierConfig[0]);
		Configuration::updateValue('SKYNET_CARRIER_ID', (int)$id_carrier1);
		
		if (!parent::install() ||
			!$this->registerHook('extraCarrier'))
			return false;
		return true;
	}
	
	public function uninstall()
	{
		// Uninstall
		if (!parent::uninstall() ||
			!$this->unregisterHook('extraCarrier'))
			return false;
		
		// Delete External Carrier
		$Carrier1 = new Carrier((int)(Configuration::get('SKYNET_CARRIER_ID')));
		//$Carrier2 = new Carrier((int)(Configuration::get('YRS_STANDARD_URG_CARRIER_ID')));

		// If external carrier is default set other one as default
		if (Configuration::get('PS_CARRIER_DEFAULT') == (int)($Carrier1->id) || Configuration::get('PS_CARRIER_DEFAULT') == (int)($Carrier2->id))
		{
			global $cookie;
			$carriersD = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
			foreach($carriersD as $carrierD)
				if ($carrierD['active'] AND !$carrierD['deleted'] AND ($carrierD['name'] != $this->_config['name']))
					Configuration::updateValue('PS_CARRIER_DEFAULT', $carrierD['id_carrier']);
		}

		// Then delete Carrier
		$Carrier1->deleted = 1;
		//$Carrier2->deleted = 1;
		//if (!$Carrier1->update() || !$Carrier2->update())
		if (!$Carrier1->update())
			return false;
		

		return true;
	}

	public static function installExternalCarrier($config)
	{
		$carrier = new Carrier();
		$carrier->name = $config['name'];
		$carrier->id_tax_rules_group = $config['id_tax_rules_group'];
		$carrier->id_zone = $config['id_zone'];
		$carrier->active = $config['active'];
		$carrier->deleted = $config['deleted'];
		$carrier->delay = $config['delay'];
		$carrier->shipping_handling = $config['shipping_handling'];
		$carrier->range_behavior = $config['range_behavior'];
		$carrier->is_module = $config['is_module'];
		$carrier->shipping_external = $config['shipping_external'];
		$carrier->external_module_name = $config['external_module_name'];
		$carrier->need_range = $config['need_range'];

		$languages = Language::getLanguages(true);
		foreach ($languages as $language)
		{
			if ($language['iso_code'] == 'fr')
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
			if ($language['iso_code'] == 'en')
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
			if ($language['iso_code'] == Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')))
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
		}

		if ($carrier->add())
		{
			$groups = Group::getGroups(true);
			foreach ($groups as $group)
				Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_group', array('id_carrier' => (int)($carrier->id), 'id_group' => (int)($group['id_group'])), 'INSERT');

			$rangePrice = new RangePrice();
			$rangePrice->id_carrier = $carrier->id;
			$rangePrice->delimiter1 = '0';
			$rangePrice->delimiter2 = '10000';
			$rangePrice->add();

			$rangeWeight = new RangeWeight();
			$rangeWeight->id_carrier = $carrier->id;
			$rangeWeight->delimiter1 = '0';
			$rangeWeight->delimiter2 = '10000';
			$rangeWeight->add();

			$zones = Zone::getZones(true);
			foreach ($zones as $zone)
			{
				Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_zone', array('id_carrier' => (int)($carrier->id), 'id_zone' => (int)($zone['id_zone'])), 'INSERT');
				Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => (int)($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), 'INSERT');
				Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int)($rangeWeight->id), 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), 'INSERT');
			}

			// Copy Logo
			if (!copy(dirname(__FILE__).'/carrier.jpg', _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg'))
				return false;

			// Return ID Carrier
			return (int)($carrier->id);
		}

		return false;
	}
	
	
	
	
	private function xmlRequest($dest,$weight)
	{
	
	$price=0;
$soapClient = new SoapClient("http://b2b.skynetworldwide.net/service.asmx?WSDL",array( "trace" => 1 ));

if($_SESSION['seesion_id']=="")
{
	$array=array(
		"aStationId"=> Configuration::get('LOGIN_ORIGIN'),
		"aUserName"=>Configuration::get('LOGIN_USERID'),
		"aPassword"=> Configuration::get('LOGIN_PASSWORD')
	);
	$login = $soapClient->__call("Login2", array($array));
	$_SESSION['seesion_id']=$login->Login2Result;
}
$session=$_SESSION['seesion_id'];
if($session!="")
{

$service_param = array (
  "strOrigin" => Configuration::get('LOGIN_ORIGIN'),
  "strDest" => $dest,
  "strAccount"=>Configuration::get('LOGIN_USERID'),
  "strSession"=>$session
);

$info = $soapClient->__call("GetTariff", array($service_param));

$xml=str_replace('&','',$info->GetTariffResult);

$sxml = simplexml_load_string($xml);
$final_rate=json_decode(json_encode($sxml));


	foreach($final_rate->Tariff as $vals)
	{
	
	  foreach($vals as $v)
	  {
		
		$w1=$v->WeightFrom;
		$w2=$v->WeightTo;
		
		if($weight>=$w1 && $weight<=$w2)
		{
			$amount=$v->Amount;
		}
	
	  }
		
	}
	
	
	
			if($amount>0)
			{
					
					if($final_rate->OtherCharges->Charge->Amount!="")
					{
						$price=$amount+$final_rate->OtherCharges->Charge->Amount;
					}
					else
					{
						$price=$amount;
					}
			}
	}
return $price;
	
   }
	
	


	
	public function getContent()
	{
		$this->_html .= '<h2>' . $this->l('Skynet Shipping Details').'</h2>';
		if (!empty($_POST) AND Tools::isSubmit('submitSave'))
		{
			
			if (!sizeof($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors AS $err)
					$this->_html .= '<div class="alert error"><img src="'._PS_IMG_.'admin/forbbiden.gif" alt="nok" />&nbsp;'.$err.'</div>';
		}
		$this->_displayForm();
		return $this->_html;
	}

	private function _displayForm()
	{
		$this->_html .= '<fieldset>
		<legend><img src="'.$this->_path.'logo.gif" alt="" /> '.$this->l('My Carrier Module Status').'</legend>';

		

		$this->_html .= '</fieldset><div class="clear">&nbsp;</div>
			<style>
				#tabList { clear: left; }
				.tabItem { display: block; background: #FFFFF0; border: 1px solid #CCCCCC; padding: 10px; padding-top: 20px; }
			</style>
			<div id="tabList">
				<div class="tabItem">
					<form action="index.php?tab='.Tools::getValue('tab').'&configure='.Tools::getValue('configure').'&token='.Tools::getValue('token').'&tab_module='.Tools::getValue('tab_module').'&module_name='.Tools::getValue('module_name').'&id_tab=1&section=general" method="post" class="form" id="configForm">

					<fieldset style="border: 0px;">
						<h4>'.$this->l('General configuration').' :</h4>
						
						<label>'.$this->l('LOGIN USERID').' : </label>
						<div class="margin-form">
						<input type="text" size="20" name="LOGIN_USERID" value="'.Tools::getValue('LOGIN_USERID', Configuration::get('LOGIN_USERID')).'" />
						</div>
 <label>'.$this->l('LOGIN_PASSWORD').' : </label>
						<div class="margin-form">
						<input type="text" size="20" name="LOGIN_PASSWORD" value="'.Tools::getValue('LOGIN_PASSWORD', Configuration::get('LOGIN_PASSWORD')).'" />
						</div>
						
						<label>'.$this->l('ORIGIN').' : </label>
						<div class="margin-form">
						<input type="text" size="20" name="LOGIN_ORIGIN" value="'.Tools::getValue('LOGIN_ORIGIN', Configuration::get('LOGIN_ORIGIN')).'" />
						</div>';
						 
 
						
				$this->_html .='</div>
					<br /><br />
				</fieldset>				
				<div class="margin-form"><input class="button" name="submitSave" type="submit"></div>
			</form>
		</div></div>';
	}

	

	private function _postProcess()
	{
	
			foreach($_POST as $keys=>$values)
			{
				
				Configuration::updateValue($keys, $values);
				
			}
	

	}


	



	/*
	** Front Methods
	**
	** If you set need_range at true when you created your carrier (in install method), the method called by the cart will be getOrderShippingCost
	** If not, the method called will be getOrderShippingCostExternal
	**
	** $params var contains the cart, the customer, the address
	** $shipping_cost var contains the price calculated by the range in carrier tab
	**
	*/
	
	public function getOrderShippingCost($params, $shipping_cost)
	{
	 
	    $address = new Address($this->context->cart->id_address_delivery);
		$id_zone = Address::getZoneById((int)($address->id));
		
		$id_zone = Address::getZoneById((int)($address->id));
		
		
		$id_state=$address->id_state;
		if(isset($_POST['id_state']))
		{
		$id_state=$_POST['id_state'];			
		}
		
		$sql="SELECT iso_code
		FROM `"._DB_PREFIX_."state`
		WHERE `id_state` ='".$id_state."'";
				
		$statcode = Db::getInstance()->getRow($sql);
		
		
		
		
		$cart = Context::getContext()->cart;		 
		
				
		$weight=$cart->getTotalWeight($cart->getProducts());
	
		//echo 'we'.$weight;
		//echo $statcode['iso_code'];
		
		$price=$this->xmlRequest($statcode['iso_code'],$weight);
			
			
			
			if($price>0)
			{
			return $price;	
			}
			else
			{
				return false;
			}
			
	}
	
	public function getOrderShippingCostExternal($params)
	{
		
		if ($this->id_carrier == (int)(Configuration::get('SKYNET_CARRIER_ID')))
			$price=$this->xmlRequest($yrcInfo);
		
		//if($price>0)		
			return $price;
			
		

		//return false;
	}
	
	
	
	/**
	 * Get a carrier list liable to the module
	 *
	 * @return array
	 */
	public function getYrcCarriers()
	{

		
		
		
		$query = 'SELECT c.id_carrier, c.range_behavior, cl.delay FROM `'._DB_PREFIX_.'carrier` c LEFT JOIN `'._DB_PREFIX_.'carrier_lang` cl ON c.`id_carrier` = cl.`id_carrier` WHERE  c.`deleted` = 0	AND cl.`id_shop` = 1 AND cl.id_lang = '.$this->context->language->id .' AND c.`active` = 1	AND c.id_carrier IN ('.Configuration::get('YRS_STANDARD_URG_CARRIER_ID').','.Configuration::get('SKYNET_CARRIER_ID').')';


		$carriers = Db::getInstance()->executeS($query);

		if (!is_array($carriers))
			$carriers = array();
		return $carriers;
	}

	
	
	public function hookExtraCarrier($params)
	{
	   
	   return $this->display(__FILE__, 'help.tpl');
	  
	}
	
	
	
	
}


