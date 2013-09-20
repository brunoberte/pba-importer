<?php

require_once 'includes/Entity/Transport.php' ;
require_once 'includes/Entity/Error.php' ;
require_once 'includes/constants.php' ;
require_once 'includes/general_functions.php' ;
require_once 'includes/hspc_functions.php' ;

class HspcApi
{

	private $url;
	private $login;
	private $password;

	function HspcApi($url = '', $login = '', $password = '') {
		
		$this->url = $url;
		$this->login = $login;
		$this->password = $password;
		
	}
	
	function get_tlds() {
		
		$dm_plan_id = 1;

		$ret = $this->load_domain_package();
		$tlds = $_SESSION['domain_package'][$dm_plan_id]['tlds_for_registration'];

		return array_values($tlds);
		
	}
	
	function load_domain_package($series_key = '') {
		if(!$series_key) {
			$series_key = 'default';
		}
		## do not load DM HP, if already loaded
		if(isset($_SESSION['domain_package'][$series_key]) && is_array($_SESSION['domain_package'][$series_key])) {
			return true;
		}
		unset($_SESSION['domain_package'][$series_key]);
		$promo_id = NULL;
/*
		if(is_array($_SESSION['campaign']) && is_array($_SESSION['campaign']['promotion'])) {
			$promo_id = $_SESSION['campaign']['promotion']['promo_id'];
		}
		if(is_array($_SESSION['shopping_cart']) && isset($_SESSION['shopping_cart']['promo_id'])) {
			$promo_id = $_SESSION['shopping_cart']['promo_id'];
		}
*/

		if($result = call('get_extended_plan_info',
			array(
				'hp_sid' => $series_key == 'default' ? '' : $series_key,
				'promo_id' => $promo_id,	## TODO: really needed and works??
				'period' => is_array($_SESSION['package']) ? $_SESSION['package']['fee_list'][$_SESSION['package_period_id']]['period'] : NULL, ## TODO: really needed and works??
				'is_domain_hp' => 1
			),
			'HSPC/API/HP')
		) {
			$_SESSION['domain_package'][$series_key] = $result;
			if($series_key == 'default') {
				## make a reference to real default DM plan series key,
				## to make possible refer either by series key or by 'default' keyword
				$_SESSION['domain_package'][ $_SESSION['domain_package'][$series_key]['series_key'] ] = &$_SESSION['domain_package'][$series_key];
			}
			$_SESSION['domain_package'][$series_key]['assigned_dm_plan'] = $_SESSION['domain_package'][$series_key]['series_key'];
			$_SESSION['domain_package'][$series_key]['dm_actions'] = array();
			$_SESSION['domain_package'][$series_key]['tlds_for_registration'] = array();

			foreach($_SESSION['domain_package'][$series_key]['tld_list'] AS $key => $value) {
				$_SESSION['domain_package'][$series_key]['tld_list'][$value['tld']] = $value;
				if(is_array($value['fee_list']) && !empty($value['fee_list'])) {
					$_SESSION['domain_package'][$series_key]['tlds_for_registration'][] = $value['tld'];
				}
				if(!in_array('register_new',$_SESSION['domain_package'][$series_key]['dm_actions'])) {
					$_SESSION['domain_package'][$series_key]['dm_actions'][] = 'register_new';
				}
				if(array_key_exists('transfer_fee', $value)) {
					$_SESSION['domain_package'][$series_key]['tlds_for_transfer'][] = $value['tld'];
					if(!in_array('reg_transfer',$_SESSION['domain_package'][$series_key]['dm_actions'])) {
						$_SESSION['domain_package'][$series_key]['dm_actions'][] = 'reg_transfer';
					}
				}
			}

			$_SESSION['domain_package'][$series_key]['dm_actions'][] = 'domain_pointer';
			$_SESSION['domain_package'][$series_key]['tlds_for_lookup'] = get_lookup_extra_tld_list($_SESSION['domain_package'][$series_key]['tlds_for_registration']);

			foreach(array('tlds_for_registration', 'tlds_for_transfer', 'tlds_for_lookup') as $value) {
				if(is_array( $_SESSION['domain_package'][$series_key][$value] ) ) {
					sort($_SESSION['domain_package'][$series_key][$value], SORT_STRING);
				}
			}
			return true;
		} else {
			## no need in API fault displayed on page for this case
/*
			if($this->error->has(MC_INTERR)) {
				$this->error->get(MC_INTERR, true);
			}
*/
		}
		return false;
	}
	
	function get_planos() {
		
		$_SESSION['hspc_sid'] = null;
		$_SESSION['hspc_server_name'] = null;
		
		$ret = get_sellable_plan_list();

		$planos = array();

/*
		echo '<pre>';
		print_r($ret);
		exit;
*/
		$periodos = array(
			'1' 		=> 1, 	//one time 
			'31104000' 	=> 12,	//1 ano
			'62208000' 	=> 24,	//2 anos
			'93312000' 	=> 36,	//3 anos
		);

		foreach($ret['plan_list']['categories'] as $cat) {

			foreach($cat['hps'] as $v) {
				
				//obter preco do maior periodo
				$preco_anual = 0;
				$preco_mensal = 0;
				$period = '';
				$max = 0;
				foreach($v['fee_list'] as $fee) {
					
					if( isset($periodos[$fee['period']])) {
					
						if($max < $fee['period']) {
							$max = $fee['period'];
							
							$div = intval($periodos[$fee['period']]);
							
							$period = $fee['period'];
							
							if($div > 1) {
								$preco_anual = floatval($fee['setup_fee']['price']) + floatval($fee['subscr_fee']['price']) / $div * 12;
								$preco_mensal = $preco_anual / 12;
							} else {
								$preco_anual = floatval($fee['setup_fee']['price']) + floatval($fee['subscr_fee']['price']);
								$preco_mensal = $preco_anual;
							}
						}
					
/*
						$a = array(
							'period' => $fee['period'],
							'setup_price' => $fee['setup_fee']['price'],
							'subscr_price' => $fee['subscr_fee']['price'],
						);
						$precos[] = $a;
*/
					}
				}
				
				$a = array(
					'grupo' => $cat['name'],
					'name' => $v['name'],
					'series_key' => $v['series_key'],
					'period' => $period,
					'description' => strip_tags($v['description'], '<strong>'),
					'summary' => strip_tags($v['summary']),
					'preco_anual' => $preco_anual,
					'preco_mensal' => $preco_mensal,
				);
				
				$planos[] = $a;
			}
						
		}
		
/*
		echo '<pre>';		
		print_r($planos);
		echo '</pre>';		
*/
		
		return $planos;		
	}

	
}
