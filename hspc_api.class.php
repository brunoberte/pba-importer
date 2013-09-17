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
