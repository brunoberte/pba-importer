<?php
//////////
// HSPc related common functions
//
// $Id: hspc_functions.php 888348 2013-06-19 15:00:06Z dkolvakh $
//////////


$fault_callbacks = array();


////
// Check domain names list for availability
function check_domains ($domain_names, $series_key, $dm_action = 'register_new', $check_error_info = false, $obj) {
	$domain_names = array_values(array_unique($domain_names));
	$domain_list = call('check_domain_list',
						array(
							'hp_sid' => $series_key,
							'action' => $dm_action,
							'account_id' => $_SESSION['account']['account_id'],
							'domain_list' => $domain_names
						),
						'HSPC/API/Domain');
	if(is_array($domain_list)) {
		if($check_error_info) {
			unset($_SESSION['domain_error_info']);
			if(!isset($domain_list['domain_error_info']) || !is_array($domain_list['domain_error_info'])) {
				## be strict
				$domain_list['domain_error_info'] = array();
			}
			foreach($domain_list['domain_error_info'] AS $domain_name => $error) {
				$_SESSION['domain_error_info'][$domain_name] = $error;
				if($error != 'FOREIGN_SUBDOMAIN') {
					get_error_handler()->add(MC_ERROR, null, sprintf($obj->string($error), $domain_name));
				}
			}
		}
		return $domain_list;//['available_domain_list'];
	}
	return array();
}


####
## get list of domains already owned by authorized user
## and vendor's domains available for subdomain creation
function get_domain_list() {
	unset($_SESSION['account_domains']);
	unset($_SESSION['assigned_account_domains']);
	unset($_SESSION['provider_domains']);
	unset($_SESSION['all_account_domains']);

	if($_SESSION['is_authorized']) {
		$result = call('get_domain_list', array('account_id' => $_SESSION['account']['account_id']), 'HSPC/API/Domain');
		$account_domains = array();
		$assigned_account_domains = array();
		if(is_array($result['subscr_domain_list'])) {
			foreach($result['subscr_domain_list'] as $domain) {
				## do not allow to reassign domain, already provisioned to some subscription, to new subscription
				## Now we need this allow for POA
				$all_account_domains[] = $domain['domain_name'];
				if($domain['plan_type']) {
					$assigned_account_domains[] = $domain['domain_name'];
					continue;
				} 
				$account_domains[] = $domain['domain_name'];
			}
		}
		if(count($all_account_domains)) {
			sort($all_account_domains);
			$_SESSION['all_account_domains'] = $all_account_domains;
		}
		if(count($account_domains)) {
			sort($account_domains);
			$_SESSION['account_domains'] = $account_domains;
		}
		if(count($assigned_account_domains)) {
			sort($assigned_account_domains);
			$_SESSION['assigned_account_domains'] = $assigned_account_domains;
		}
	}
	
	$result = call('get_domain_list', array('for_trial' => $_SESSION['shopping_cart']['period'] === 'trial' ? 1 : 0), 'HSPC/API/Domain');
	$provider_domains = $result['domain_list'];
	if(count($provider_domains)) {
		sort($provider_domains);
		$_SESSION['provider_domains'] = $provider_domains;
	}
}


function load_domain_contacts() {
	$result = call('get_domain_contact_list',
		array('account_id' => $_SESSION['account']['account_id']),
		'HSPC/API/Account');
	$_SESSION['account_domain_contacts'] = $result['contact_list'];
}


////
// Place the Order
function place_order ($calculate_only = false, $plan_id = null, $os_tmpl = null) {
	sw_log('calculate_only => '.$calculate_only.'; plan_id => '.$plan_id.'; os_tmpl => '.$os_tmpl);

	$domains = array();
	$package = is_array($_SESSION['plans'][$plan_id]) ? $_SESSION['plans'][$plan_id] : $_SESSION['domain_package']['default'];
	$_SESSION['package'] = $package;
	$period = is_array($_SESSION['plans'][$plan_id]) ? 
		$_SESSION['shopping_cart']['period'] : 
		$_SESSION['domain_package']['default']['fee_list'][$_SESSION['domain_package']['default']['period_id']]['period'];
	$is_trial = $period === 'trial' ? 1 : 0;
	if($is_trial) {
		$period = $_SESSION['plans'][$plan_id]['trial_period'];
	}

	$dm_plan_id = $package['assigned_dm_plan'];
	if(in_array($_SESSION['plans'][$plan_id]['type']['id'], array(HP_TYPE_VPS, HP_TYPE_PLESK_VIRTUAL_NODE, HP_TYPE_PLESK_DOMAIN, HP_TYPE_PLESK_CLIENT, HP_TYPE_PSVM, HP_TYPE_POA))) {
		if($_SESSION['configuration']['hostname_type'] == 'use_subdomain') {
			$domains[$_SESSION['configuration']['subdomain'].'.'.
				$_SESSION['configuration']['subdomain_hostname']] =
					array(
						'dm_action'				=> 'dns_hosting',
						'period'				=> NULL,
						'whois_privacy'			=> NULL,
						'dns_hosting'			=> NULL,
						'contact_hash'			=> NULL,
						'create_site'			=> 1,
						'hosting_destination'	=> 0,
						'is_default'			=> 1
					);
		}

		if($_SESSION['configuration']['hostname_type'] == 'use_domain' && 
			(
				(!is_array($_SESSION['domains']) || !is_array($_SESSION['domains'][$dm_plan_id])) 
				|| 
				(is_array($_SESSION['domains']) && is_array($_SESSION['domains'][$dm_plan_id]) && !in_array($_SESSION['configuration']['domain_hostname'], array_keys($_SESSION['domains'][$dm_plan_id])))
			)
		) {
				$domains[$_SESSION['configuration']['domain_hostname']] =
					array(
						'dm_action'				=> 'use_existing',
						'period'				=> NULL,
						'whois_privacy'			=> NULL,
						'dns_hosting'			=> NULL,
						'contact_hash'			=> NULL,
						'create_site'			=> 1,
						'hosting_destination'	=> 0,
						'is_default'			=> 1
					);
		}
	}

	unset($_SESSION['domains'][$dm_plan_id]['']);
	if(is_array($_SESSION['domains'][$dm_plan_id])) {
		foreach($_SESSION['domains'][$dm_plan_id] AS $key => $value) {
			$is_default = ($_SESSION['configuration']['hostname_type'] == 'use_domain' && $_SESSION['configuration']['domain_hostname'] == $key) ? 1 : 0;
			$domains[$key] =
				array(
					'dm_action'				=> $value['dm_action'],
					'period'				=> $_SESSION['domain_package'][$dm_plan_id]['tld_list'][$value['tld']]['fee_list'][$value['period_id']]['period'],
					'whois_privacy'			=> $value['whois_privacy'],
					'dns_hosting'			=> $value['dns_hosting'],
					'contact_hash'			=> $value['contacts'],
					'create_site'			=> $value['hosting'] ? $value['hosting'] : $is_default,
					'hosting_destination'	=> $value['hosting_destination'],
					'is_default'			=> $is_default,
					'ns_list'				=> ($value['dns_hosting']) ? NULL : $value['ns']
//					'extdata'				=> $value['extdata']
				);
		}
	}

	$domains1 = array();
	$i = 1;
	foreach($domains as $domain_name => $value) {
		$domains1['domain'.$i++] = array_merge(array('domain_name' => $domain_name), $value);
	}
	$domains = $domains1;

	if(count($domains) && $_SESSION['domains_extdata'][$dm_plan_id]) {
		$domains['ext_data'] = $_SESSION['domains_extdata'][$dm_plan_id];
	}

	$panel = $_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$os_tmpl] ?
		$_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$os_tmpl] :
		array('id' => 'none');
	
	$applications = array();
	if(
		is_array($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel['id']]) ||
		is_array($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl]['none'])
	) {
		if($panel['id'] != 'none') {
			$applications = array_merge(
				is_array($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel['id']]) ? 
					(array)array_keys($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel['id']]) : array(),
				is_array($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl]['none']) ? 
					(array)array_keys($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl]['none']) : array()
			);
		} else {
			$applications = 
				is_array($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel['id']]) ?
				array_keys($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel['id']]) : array();
		}
	}
	if($panel['id'] != 'none') {
		array_unshift($applications, $panel['id']);
	}
	if(!count($applications)) {
		$applications = NULL;
	}
	
	$custom_attributes = is_array($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes']) ?
		array_keys($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes']) :
		NULL;

	$sitebuilder = 
		isset($_SESSION['shopping_cart'][$plan_id]['addons']['sitebuilder']) && 
		$_SESSION['shopping_cart'][$plan_id]['addons']['sitebuilder']['value'] ?
			array(
				'site_id' => $_SESSION['shopping_cart'][$plan_id]['addons']['sitebuilder']['sb_sid'] ?
					$_SESSION['shopping_cart'][$plan_id]['addons']['sitebuilder']['sb_sid'] : 'new',
				'node_id' => $_SESSION['sb_node']
			) : NULL;

	$licenses = null;
	if(!$is_trial && is_array($_SESSION['shopping_cart'][$plan_id]['addons']['license_list'])) {
		$licenses = array();
		foreach($_SESSION['shopping_cart'][$plan_id]['addons']['license_list'] AS $license) {
			$licenses['plugin_'.$license['plugin_id']][$license['product_id']]['feature_list'] = _extract_license_data($license);

			if($license['addon_list'] && count($license['addon_list'])) {
				foreach($license['addon_list'] as $addon) {
					$licenses['plugin_'.$license['plugin_id']][$license['product_id']]['addon_list'][$addon['product_id']]['feature_list'] = _extract_license_data($addon);
				}
			}
		}
	} 

	if(!$_SESSION['credentials']['password'] || ($_SESSION['plans'][$plan_id]['type']['id'] == HP_TYPE_VPS && !$_SESSION['plans'][$plan_id]['is_root_access'])) {
		include_once('generate_password.php');
		$password_generator = new GeneratePassword();
		$_SESSION['credentials']['password'] = $password_generator->Generate();
	}

	if(in_array($_SESSION['plans'][$plan_id]['type']['id'], array(HP_TYPE_PLESK_CLIENT, HP_TYPE_PLESK_DOMAIN))) {
		$credentials = array($_SESSION['credentials']['password'],
			NULL,
			$_SESSION['configuration']['forward_url']);
	} elseif(in_array($_SESSION['plans'][$plan_id]['type']['id'], array(HP_TYPE_VPS, HP_TYPE_PSVM))) {
		$credentials = array($_SESSION['credentials']['password'], NULL, NULL);
	}

	$email = $_SESSION['person']['email'];
	$ip_address = $_SERVER['REMOTE_ADDR'];

	## Resources (QoS)
	if(is_array($_SESSION['plans'][$plan_id]['qos_list']) && is_array($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'])) {
		$qos_list = array();
		foreach($_SESSION['plans'][$plan_id]['qos_list'] as $qos) {
			if(
				array_key_exists($qos['id'], $_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list']) &&
				($qos['platform_id'] == $_SESSION['shopping_cart']['platform'] || $_SESSION['shopping_cart']['platform'] == 0)
			) {
				$qos_list['res_id_'.$qos['id']] =
						array(
							'res_id' => $qos['id'],
							'value' => $_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$qos['id']]['value'],
							'multiplier' => $qos['multiplier']
						);
			}
		}
		if(!count($qos_list)) {
			unset($qos_list);
		}
	}

	$ssl = NULL;
	if(in_array($_SESSION['plans'][$plan_id]['type']['id'], array(HP_TYPE_SSL_SINGLE)) && isset($_SESSION['ssl'][$plan_id])) {
		$ssl = $_SESSION['ssl'][$plan_id];
	}

	## isolate fraud data
	$fraud_data = NULL;
	foreach($_POST as $key => $value) {
		if(preg_match('/^AFM_/', $key)) {
			$fraud_data[$key] = $_POST[$key];
		}
	}

	## Questionnaire
	$answer_list = NULL;
	if(isset($package['question_list']) && is_array($package['question_list']) && count($package['question_list'])) {
		$answer_list = $_SESSION['configuration']['answers'];
	}

	unset($domains['']);
	$order_data = array (
		'account_id'	=> $_SESSION['account']['account_id'],
		'hp_sid'		=> $package['series_key'],
		'period'		=> $period,
		'for_trial'		=> $is_trial,
		'domain_hash'	=> count($domains) ? $domains : NULL,
		'ssl_hash'		=> $ssl,
		'app_list'		=> $applications,
		'attribute_list'=> $custom_attributes,
		'qos_list'		=> $qos_list,
		'sb_plan'		=> $sitebuilder,
		'license_list'	=> $licenses,
		'login'			=> $credentials,
		'initiator_email'=> $email,
		'initiator_ip'	=> $ip_address,
		'promo_id'		=> $_SESSION['shopping_cart']['promo_id'],
		'answer_list'	=> $answer_list,
		'referral'		=> $_POST['referral'],
		'campaign'		=> $_SESSION['digest'],
		'fraud_query'	=> $fraud_data,
		'os_tmpl_id'	=> $_SESSION['shopping_cart']['os_tmpl'],
		'tech_data'		=> TECH_DATA
	);

	if($calculate_only) {
		//sw_log(var_dump($order_data));
		## remove not needed data
		unset($order_data['initiator_email']);
		unset($order_data['initiator_ip']);
		unset($order_data['answer_list']);
		unset($order_data['referral']);
		unset($order_data['campaign']);
		unset($order_data['fraud_query']);
		unset($order_data['tech_data']);
		return call('calculate_order', $order_data, 'HSPC/API/Billing');
	} else {
		return call('place_order', $order_data, 'HSPC/API/Billing');
	}
}


////
// Unset shopping cart after order is placed
function unset_shopping_cart() {
	$_SESSION['archive']['package'] = $_SESSION['package'];
	$_SESSION['archive']['package_period_id'] = $_SESSION['package_period_id'];
	$_SESSION['archive']['domains'] = $_SESSION['domains'];
	unset($_SESSION['package']);
	unset($_SESSION['package_period_id']);
	unset($_SESSION['addons']);
	unset($_SESSION['sb_sid']);
	unset($_SESSION['sb_node']);
	unset($_SESSION['domains']);
	unset($_SESSION['ssl']);
	unset($_SESSION['check_domains_cache']);
	unset($_SESSION['promotions']);
	unset($_SESSION['campaign']);
	unset($_SESSION['configuration']);
	unset($_SESSION['digest']);
	unset($_SESSION['contact_error']);
	unset($_SESSION['domain_data_full']);
	unset($_SESSION['domain_contacts']);
}


function get_domain_tld($domain_name) {
	$parts = explode('.', $domain_name, 2);
	$tld = array_pop($parts);
	return $tld;
}


// not suitable for subdomains!
function get_domain_name($domain_name) {
	$parts = explode('.', $domain_name, 2);
	$name = array_shift($parts);
	return $name;
}


function get_invalid_contact($dm_plan_id, $desired_domain = null) {
	if(!is_array($_SESSION['contact_error'][$dm_plan_id])) {
		sw_log('contact_error is empty, none invalid contacts exist');
		return null;
	}
	$domain_list = array_keys($_SESSION['contact_error'][$dm_plan_id]);
	$domain = null;
	## try to find invalid contact for given domain, to fix contacts in chain mode in series per-domain
	foreach($domain_list as $key => $value) {
		if($desired_domain && $value == $desired_domain) {
			$domain = $value;
			break;
		}
	}
	if(!$domain && is_array($domain_list) && count($domain_list)) {
		## if domain not found, possibly there are no invalid contacts in given domain already, take next domain
		$domain = $domain_list[0];
	}

	if(!$domain) {
		sw_log_error('domain must be not empty');
		die;
	}

	$contact_type = null;
	$contact = array();
	if(is_array($_SESSION['domains'][$dm_plan_id][$domain]['contact_types'])) {
		foreach($_SESSION['domains'][$dm_plan_id][$domain]['contact_types'] as $c) {
			if($_SESSION['contact_error'][$dm_plan_id][$domain][$c['type']]) {
				$contact_type = $c['type'];
				break;
			}
		}

		$contact_id = $_SESSION['domains'][$dm_plan_id][$domain]['contacts'][$contact_type];

		$contact['domain'] = $domain;
		$contact['contact_type'] = $contact_type;
		$contact['contact_id'] = $contact_id;
	}
	return $contact;
}


function validate_domain_data($dm_plan_id) {
	////
	// Validate domain data (contacts and ext data)
	$domain_data_hash = array();
	$i = 1;
	if(is_array($_SESSION['domains'][$dm_plan_id])) {
		foreach($_SESSION['domains'][$dm_plan_id] AS $key => $value) {
			if($value['dm_action'] == 'register_new' || $value['dm_action'] == 'reg_transfer') {
				$domain_data_hash['domain'. $i++] = array(
					'domain_name' => $key,
					'action' => $value['dm_action'],
					'contact_hash' => $value['contacts']
				);
			}
		}
	}

	if($i > 1) {
		unset($_SESSION['contact_error'][$dm_plan_id]);
		if($result = call('validate_domain_data',
			array('hp_sid'		=> $dm_plan_id,
				'domain_data_hash' => $domain_data_hash,
				'account_id'	=> $_SESSION['account']['account_id'],
				'form_data'		=> $_POST
			),
			'HSPC/API/Domain')
		) {
			return true;
		} else {
			return false;
		}
	}
	return true;
}


////
// Calculate cart total
function calculate_total($plan_id = null) {
	$total = 0;
	$os_tmpl = $_SESSION['shopping_cart']['os_tmpl'];
	$period = $_SESSION['shopping_cart']['period'];
	$platform = $_SESSION['shopping_cart']['platform'];
	$is_trial = $period === 'trial' ? 1 : 0;

	if($_SESSION['plans'][$plan_id]) {
		//Package
		if($is_trial) {
			$period = $_SESSION['plans'][$plan_id]['trial_period'];
		}
		if(!$is_trial) {
			foreach($_SESSION['plans'][$plan_id]['fee_list'] as $fee) {
				if($fee['period'] == $period) {
					$total += $fee['setup_fee']['price'] + $fee['subscr_fee']['price'];
				}
			}
		}

		//Applications
		$panel = $_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$os_tmpl] ? 
			$_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$os_tmpl] :
			array( 'id' => 'none' );
		if($panel['id'] != 'none') {
			## Panel fee
			$total += $panel['setup_fee']['price'] + $panel['subscr_fee']['price'] * ($period/BILL_PERIOD);
			## Panel addons fee
			if(is_array($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel['id']])) {
				foreach($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel['id']] AS $application) {
					if($application['is_included']) {
						continue;
					}
					$total += $application['setup_fee']['price'] + $application['subscr_fee']['price'] * ($period/BILL_PERIOD);
				}
			}
		}
		## Standalone apps fee
		if(is_array($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl]['none'])) {
			foreach($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl]['none'] AS $application) {
				if($application['is_included']) {
					continue;
				}
				$total += $application['setup_fee']['price'] + $application['subscr_fee']['price'] * ($period/BILL_PERIOD);
			}
		}

		//Custom Attributes
		if(is_array($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'])) {
			foreach($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'] as $attribute) {
				if($attribute['is_included']) {
					continue;
				}
				$total += $attribute['setup_fee']['price'] + $attribute['subscr_fee']['price'] * ($period/BILL_PERIOD);
			}
		}

		//Sitebuidler
		if($_SESSION['shopping_cart'][$plan_id]['addons']['sitebuilder'] && !$_SESSION['plans'][$plan_id]['sb_plan']['included_value']) {
			$total += $_SESSION['plans'][$plan_id]['sb_plan']['setup_fee']['price'] + $_SESSION['plans'][$plan_id]['sb_plan']['subscr_fee']['price'] * ($period/BILL_PERIOD);
		}

		//Licenses
		if(!$is_trial && is_array($_SESSION['shopping_cart'][$plan_id]['addons']['license_list'])) {
			foreach($_SESSION['shopping_cart'][$plan_id]['addons']['license_list'] AS $license) {
				$total += _calculate_license_price($license, $period);
				if(array_key_exists('addon_list', $license) && is_array($license['addon_list'])) {
					foreach($license['addon_list'] as $addon) {
						$total += _calculate_license_price($addon, $period);
					}
				}
			}
		}

		## Resources
		if(is_array($_SESSION['plans'][$plan_id]['qos_list']) && is_array($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'])) {
			foreach($_SESSION['plans'][$plan_id]['qos_list'] as $qos) {
				if(
					array_key_exists($qos['id'], $_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list']) &&
					($qos['platform_id'] == $platform || $platform == 0)
				) {
					$total += $qos['overuse_rate']['price'] * 
						($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$qos['id']]['value'] - $qos['incl_amount']) * 
						($period/BILL_PERIOD);
				}
			}
		}
	}

	$series_key = $plan_id != 'domains' ? $_SESSION['plans'][$plan_id]['assigned_dm_plan'] : 'default';
	$dm_plan_id = $_SESSION['domain_package'][$series_key]['assigned_dm_plan'];
	if(is_array($_SESSION['domains'][$dm_plan_id])) {
		$dns_hosting_count = 0;
		foreach($_SESSION['domains'][$dm_plan_id] AS $key => $value) {
			$tld = $_SESSION['domain_package'][$dm_plan_id]['tld_list'][$value['tld']];
			switch ($value['dm_action']) {
				case 'register_new':
					$total += $tld['fee_list'][$value['period_id']]['registration_fee'];
				break;
				case 'reg_transfer':
					$total += $tld['transfer_fee'];
				break;
			}
			if($value['whois_privacy']) {
				$total += $tld['protect_fee'] * $tld['fee_list'][$value['period_id']]['period'];
			}
		}
	}

	return $total;
}


////
// Load available payment plugins
function load_payment_options() {
	$payment_options = call('get_plugin_list',
		array(
			'amount'     => $_SESSION['order']['doc_balance'],
			'account_id' => $_SESSION['account']['account_id']
		),
		'HSPC/API/PP'
	);
	if(is_array($payment_options)) {
		$payment_options_new = array();
		foreach($payment_options AS $key => $value) {
			if($value['has_form']) {
				$payment_options[$key]['form_layout'] = call('get_layout_hash',
						array(
							'plugin_id'		=> $value['plugin_id'],
							'account_id'	=> $_SESSION['account']['account_id']
						),
						'HSPC/API/PP'
				);
				$payment_options[$key]['warning_layout'] = call('get_warning',
					array(
						'order_id'		=> $_SESSION['order']['id'],
						'warning_type'	=> 'paymethod'
					),
					'HSPC/API/Fraud'
				);
			}

			while (preg_match('@([\'\"])((?:https?://.+?)?/cp/(?!login.cgi\?sid).*?)\1@', $payment_options[$key]['form_layout']['form'], $matches)) {
				$payment_options[$key]['form_layout']['form'] = str_replace($matches[2], '/cp/login.cgi?sid='.$_SESSION['sid'].'&amp;ret_url='.str_replace(array('+', '/'), array('-', '_'), base64_encode($matches[2])), $payment_options[$key]['form_layout']['form']);
			}

			if(!$value['is_redirect']) {
				$payment_options[$key]['saved_paymethods'] = call('get_saved_paymethod_list',
					array(
						'plugin_id'		=> $value['plugin_id'],
						'account_id'	=> $_SESSION['account']['account_id']
					),
					'HSPC/API/PP'
				);
			}

			$payment_options_new[$value['plugin_id']] = $payment_options[$key];
		}

		$_SESSION['payment_options'] = $payment_options_new;
		return true;
	}
	return false;
}


////
// Initiate paymnet processing using selected payment option
function pay($plugin_id, $order_id, $paymethod_id, $form_args, $fraud_query) {

	return call('pay',
		array(
			'plugin_id'		=> $plugin_id,
			'order_id'		=> $order_id,
			'paymethod_id'	=> $paymethod_id,
			'form_args'		=> $form_args,
			'fraud_query'	=> $fraud_query,
			'account_id'	=> $_SESSION['account']['account_id'],
			'initiator_email'=> $_SESSION['person']['email'],
			'initiator_ip'	=> $_SERVER['REMOTE_ADDR']
		),
		'HSPC/API/PP'
	);

}


////
// If customer / reseller is already authorized, check HP for allowance to buy
function hp_for_reseller_only($plan, $check_account = true) {
	if(
		(
			($check_account && $_SESSION['account'] && $_SESSION['account']['account_id'] && ($_SESSION['account']['type'] == ACCOUNT_TYPE_CUSTOMER || $_SESSION['account']['account_type'] == ACCOUNT_TYPE_CUSTOMER))
			||
			(!$check_account)
		)
		&&
		(
			in_array($plan['type']['id'], array(HP_TYPE_VIRTUOZZO_DEDICATED_NODE, HP_TYPE_PLESK_DEDICATED_NODE, HP_TYPE_PLESK_VIRTUAL_NODE))
			||
			$plan['for_reseller_only'] == 1
		)
	) {
		return true;
	} else {
		return false;
	}
}


////
/*
// Get sellable hosting plans into session storage, grouped by plan type
function get_sellable_plan_list($type_id = NULL, $promo_id = NULL, $account_id = NULL) {
	if(is_null($promo_id) && is_array($_SESSION['campaign']) && is_array($_SESSION['campaign']['promotion'])) {
		$promo_id = $_SESSION['campaign']['promotion']['promo_id'];
	}
	return call('get_categorized_plan_list',
		array (
			'type_id' => $type_id,
			'promo_id' => $promo_id,
			'account_id' => $account_id,
			'sb_sid' => $_SESSION['sb_sid'],
			'sb_node' => $_SESSION['sb_node']
		),
		'HSPC/API/HP'
	);
}
*/

function get_sellable_plan_list($type_id = NULL, $promo_id = NULL, $account_id = NULL) {
	return call('get_categorized_plan_list',
		array (
			'type_id' => $type_id,
			'promo_id' => $promo_id,
			'account_id' => $account_id,
			'sb_sid' => '',
			'sb_node' => ''
		),
		'HSPC/API/HP'
	);
}


////
// Load selected hosting package
function load_package($series_key, $promo_id = NULL, $account_id = NULL, $period = NULL, $for_trial = NULL) {

	if(is_null($promo_id) && is_array($_SESSION['campaign']) && is_array($_SESSION['campaign']['promotion'])) {
		$promo_id = $_SESSION['campaign']['promotion']['promo_id'];
	}

	if($package = call('get_full_extended_plan_info',
		array ('hp_sid' => $series_key,
			'promo_id' => $promo_id,
			'account_id' => $account_id,
			'period' => $period,
			'for_trial' => $for_trial
		),
		'HSPC/API/HP')
	) {

		$_SESSION['package'] = $package;
		//$_SESSION['group'][$group_id][$series_key] = $package;
		$_SESSION['plans'][$series_key] = $package;

		if($period) {
			foreach($_SESSION['package']['fee_list'] AS $i => $fee) {
				if($period == $fee['period']) {
					$_SESSION['package_period_id'] = $i;
				}
			}
		}

		if(!$period || !$_SESSION['package_period_id']) {
			$_SESSION['package_period_id'] = 0;
		}

		$_SESSION['shopping_cart']['promo_id'] = $promo_id ? $promo_id : 0;

		if($for_trial) {
			$_SESSION['package_period_id'] = 'trial';
		}

		// Reload addons, if any
		// Applications
		if(isset($_SESSION['package']['app_list']) && is_array($_SESSION['package']['app_list'])) {
			foreach($_SESSION['package']['app_list'] AS $application) {
				if($_SESSION['addons']['app_list'][$application['id']]) {
					$_SESSION['addons']['app_list'][$application['id']] = $application;
				}
			}
		}
		// Custom attributes
		if(isset($_SESSION['package']['custom_attribute_list']) && is_array($_SESSION['package']['custom_attribute_list'])) {
			foreach($_SESSION['package']['custom_attribute_list'] AS $custom_attribute) {
					foreach($custom_attribute['option_list'] AS $option) {
						if($_SESSION['addons']['custom_attributes'][$option['id']]) {
							$_SESSION['addons']['custom_attributes'][$option['id']] = $option;
						}
					}
			}
		}

		$_SESSION['package']['os_tmpl'] = $os_tmpl; 

		return true;
	}
	return false;
}


////
// Load promotions list by hosting plan
function load_promotions($series_key, $account_id) {
	unset($_SESSION['promotions']);
	if($promotions = call('get_plan_promotion_list', array('hp_sid' => $series_key, 'account_id' => $account_id), 'HSPC/API/HP')) {
		foreach($promotions AS $promotion) {
			$_SESSION['promotions'][$promotion['promo_id']] = $promotion;
		}
		if(is_array($_SESSION['campaign']) && is_array($_SESSION['campaign']['promotion'])) {
			$_SESSION['promotions'][$_SESSION['campaign']['promotion']['promo_id']] = $_SESSION['campaign']['promotion'];
		}
		return true;
	}
	return false;
}


////
// Get account information
function get_account_info($account_id = NULL) {
	return call('get_account_info', array('account_id' => $account_id), 'HSPC/API/Account');
}


////
// Domain conatacts: create new
function create_domain_contact($data) {
	// Cleanup $data
	$fields = array(
		'account_id', 'address1', 'address2', 'city', 'comment', 'country', 'email', 'fax', 'first_name',
		'gender', 'house_num', 'insertion', 'last_name', 'middle_name', 'company_name', 'phone', 'mobile',
		'prefix', 'state', 'suffix', 'zip'
	);
	$clean_data = array();

	if($data['country']!='US' && $data['country']!='CA') {
		$data['state'] = $data['state_alt'];
	}

	$data['phone'] = '+' . $data['phone_country_code'] .
		(($data['phone_area_code']) ? '(' . $data['phone_area_code'] . ')' : '') .
		$data['phone_number'] . (($data['phone_extension']) ? 'ext' . $data['phone_extension'] : '');

	$data['fax'] = '+' . $data['fax_country_code'] .
		(($data['fax_area_code']) ? '(' . $data['fax_area_code'] . ')' : '') .
		$data['fax_number'] . (($data['fax_extension']) ? 'ext' . $data['fax_extension'] : '');

	$data['is_corporate'] = ($data['company_name']) ? 1 : 0;

	foreach($data AS $key => $value) {
		if(in_array($key, $fields)) {
			$clean_data[$key] = $value;
		}
	}

	if($contact = call('create_domain_contact', $clean_data, 'HSPC/API/Account')) {
		return $contact['contact_id'];
	}

	return false;
}


////
// Load provider config (price format, regional settings, etc)
function load_provider_config() {
	if($provider_config = call('get_provider_config', array('no_agreement'=>1), 'HSPC/API/Config')) {
		$_SESSION['provider_config'] = $provider_config;
		if(!$_SESSION['current_language']) {
			$_SESSION['current_language'] = $_SESSION['provider_config']['default_lang'];
		}
		if(!$_SESSION['hspc_server_name']) {
			$_SESSION['hspc_server_name'] = ($_SESSION['provider_config'] && $_SESSION['provider_config']['server_name'] ?
				$_SESSION['provider_config']['server_name'] :
				$GLOBALS['StoreConf']['SERVER_NAME']
			);
		}
		return true;
	}
	return false;
}


////
// Load agreements
function load_agreements($data = array()) {
	if($agreements = call('get_agreement_hash', $data, 'HSPC/API/Config')) {
		return $agreements;
	}
	return false;
}


function get_contact_type_hash($reg_domain_list, $reg_domain_list_actions, $series_key) {
	$result = call('get_contact_type_hash',
		array(
			'domain_list' => $reg_domain_list,
			'domain_actions_list' => $reg_domain_list_actions,
			'hp_sid' => $series_key
		), 'HSPC/API/Domain'
	);
	return $result;
}


function get_domain_extdata_form($domain, $dm_action, $series_key) {
	$result = call ('get_domain_extdata_form',
		array(
			'domain' => $domain,
			'action' => $dm_action,
			'hp_sid' => $series_key,
			'form_data' => $_SESSION['domain_data_full']
		), 'HSPC/API/Domain'
	);
	return $result;
}


function _extract_license_data($license) {
	$set = array();
	if($license['feature_list']) {	
		if($license['feature_list']['groups']) {
			foreach($license['feature_list']['groups'] as $group_id => $group) {
				foreach($group['options'] as $option) {
					$set[] = $option['id'];
				}
			}
		}
		if($license['feature_list']['standalone']) {
			foreach($license['feature_list']['standalone'] as $standalone) {
				$set[] = $standalone['id'];
			}
		}
	}
	return $set;
}


function _calculate_license_price($license, $period = 0) {
	$price = 0;

	if(!$license['is_included']) {
		$price += $license['setup_fee']['price'] + $license['subscr_fee']['price'] * ($period/BILL_PERIOD);
	}
	if(is_array($license['feature_list']['groups'])) {
		foreach($license['feature_list']['groups'] as $group) {
			foreach($group['options'] as $option) {
				$price += $option['setup_fee']['price'] + $option['subscr_fee']['price'] * ($period/BILL_PERIOD);
			}
		}
	}
	if(is_array($license['feature_list']['standalone'])) {
		foreach($license['feature_list']['standalone'] as $standalone) {
			if(!$standalone['is_included']) {
				$price += $standalone['setup_fee']['price'] + $standalone['subscr_fee']['price'] * ($period/BILL_PERIOD);
			}
		}
	}
	return $price;
}


function get_lookup_extra_tld_list($tlds = array()) {
	$lookup_extra_tlds = explode(' ', $GLOBALS['StoreConf']['LOOKUP_EXTRA_TLDS']);
	$tlds_for_lookup = array();

	if($GLOBALS['StoreConf']['LOOKUP_EXTRA_TLDS'] == 'ALL') {
		$tlds_for_lookup = $tlds;
	} else if(!$GLOBALS['StoreConf']['LOOKUP_EXTRA_TLDS']) {

	} else if(is_array($lookup_extra_tlds)) {
		foreach($lookup_extra_tlds as $value) {
			if(in_array($value, $tlds)) {
				$tlds_for_lookup[] = $value;
			}
		}
	}
	return $tlds_for_lookup;
}


function get_lookup_tlds_list($dm_plan_id) {
	$lookup_tlds = array();
	if(
		isset($_SESSION['check_domains_cache'][$dm_plan_id]) && isset($_SESSION['check_domains_cache'][$dm_plan_id]['entered_tlds']) &&
		is_array($_SESSION['check_domains_cache'][$dm_plan_id]['entered_tlds'])
	) {
		$lookup_tlds = $_SESSION['check_domains_cache'][$dm_plan_id]['entered_tlds'];
	} else {
		$lookup_tlds = get_lookup_extra_tld_list($_SESSION['domain_package'][$dm_plan_id]['tlds_for_registration']);
	}
	return $lookup_tlds;
}


////
// Soap related functions
function call($function, $params = NULL, $namespace = 'HSPC/API', $repeat = 1) {
	$trans = get_api_transport();
	$result = $trans->call( $function, $params, $namespace );

	if($trans->fault ) {
		if('soap:AuthenRequired' == $trans->faultcode && $repeat) {
			open_backend_session();
			return call($function, $params, $namespace, $repeat - 1);
		}
		return handle_soap_error($result, $trans->faultstring );
		//TODO: make handle_error for all cases, handle_hspc_error for hspc errors, define difference
	}
	return $result;
}


function get_error_handler() {
	global $error_handler;
	if(!$error_handler) {
		$error_handler = new Entity\Error;
	}
	return $error_handler;
}


function get_api_transport() {
	global $pbas_transport;

	if(!$pbas_transport) {
		$pbas_transport = new Entity\Transport;
		if($_SESSION['hspc_sid']) {
			$pbas_transport->setSoapClient(unserialize($_SESSION['HSPclient']));
		} else {
			open_backend_session();
		}
	}
	return $pbas_transport;
}


function open_backend_session($lang_id = NULL) {
	sw_log_debug ('open_backend_session ' . $lang_id);
	$transport = get_api_transport();
	$result = $transport->openSession($lang_id);
	if($transport->fault) {
		get_error_handler()->add(MC_INTERR, null, $transport->faultstring);
		sw_log_error( 'Connection error: ' . $transport->faultstring );
		if($_SERVER["SERVER_NAME"]) {
			// not for command line, for http environment only
			error();
		}
	} else {
		sw_log('API Session has been opened, sid = '.$result['session_id']);
		// Put backend sid and client object to the session storage
		$_SESSION['HSPclient'] = serialize($transport->getSoapClient());
		$_SESSION['hspc_sid'] = $result['session_id'];
		$_SESSION['vendor_id'] = $result['account_id'];
		$_SESSION['SERVER_NAME'] = $GLOBALS['StoreConf']['SERVER_NAME'];
		$_SESSION['current_language'] = $lang_id;
		load_provider_config();
	}
}


function install_error_handler($fault, $handler) {
	global $fault_callbacks;
	if(!is_array($fault)) {
		$fault = array($fault);
	}
	foreach($fault as $faultcode) {
		$fault_callbacks['soap:'.$faultcode] = $handler;
	}
}


function remove_error_handler($fault) {
	global $fault_callbacks;
	unset($fault_callbacks['soap:' . $fault]);
}


function handle_soap_error($result, $error) {
	// Log errors
	sw_log('FaultCode => <' . $result['faultcode'] . '>');
	sw_log('FaultString => ' . $result['faultstring']);
	global $fault_callbacks;
	if(!$result) {
		get_error_handler()->add(MC_INTERR, null, $error);
	} else if($fault_callbacks[$result['faultcode']]) {
		sw_log("Calling callback: " . $fault_callbacks[$result['faultcode']]);
		return call_user_func($fault_callbacks[$result['faultcode']], $result);
	} else if($result['faultcode'] == 'HTTP') {
		## connection problems
		sw_log("No callback found!");
		if(!get_error_handler()->has(MC_ERROR)) {
			get_error_handler()->add(MC_ERROR, 'STORE_CLOSED');
		}
	} else {
		sw_log("No callback found!");
		if(strpos($result['faultcode'], 'soap:User') === 0) {
			get_error_handler()->add(MC_ERROR, null, $result['faultstring']);
		} else {
			// Show invisible message
			get_error_handler()->add(MC_INTERR, null, $result['faultstring']);
		}
	}
	return false;
}


function handle_hosting_target_list_error($result) {
	if($result['faultstring'] != 'Subscription not found') {
		get_error_handler()->add(MC_INTERR, null, $result['faultstring']);
	}
	return false;
}


function handle_register_new_account_error($result) {
	get_error_handler()->add(MC_ERROR, null, $result['faultstring']);
	return false;
}


function handle_load_plan_error($result) {
	if($_SESSION['shopping_cart']['period'] === 'trial') {
		unset($_SESSION['shopping_cart']['period']);
	}
	return false;
}


function handle_domain_contact_error($result) {
	if($_SESSION['shopping_cart']['plan_id'] == 'domains') {
		$dm_plan_id = $_SESSION['domain_package']['default']['series_key'];
	} else {
		$dm_plan_id = $_SESSION['plans'][$_SESSION['shopping_cart']['plan_id']]['assigned_dm_plan'];
	}

	if(!$_SESSION['contact_error'][$dm_plan_id]) {
		$_SESSION['contact_error'][$dm_plan_id] = array();
	}
	unset($_SESSION['domain_contacts'][$dm_plan_id]['action']);
	
	$first = 1;
	foreach($result['detail']['Array'] as $error) {
		if(! $_SESSION['contact_error'][$dm_plan_id][$error['domain']]) {
			$_SESSION['contact_error'][$dm_plan_id][$error['domain']] = array();
		}

		if($first || ! $_SESSION['contact_error'][$dm_plan_id][$error['domain']][$error['contact_type']]) {
			$first = 0;
			unset($_SESSION['contact_error'][$dm_plan_id][$error['domain']][$error['contact_type']]);
			$_SESSION['contact_error'][$dm_plan_id][$error['domain']][$error['contact_type']] = array();
		}

		$_SESSION['contact_error'][$dm_plan_id][$error['domain']][$error['contact_type']][] = $error;
	}
	if(!$_SESSION['contact_error'][$dm_plan_id]['extdata'] && $_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] == 'configure_manually') {
		$_SESSION['domain_contacts'][$dm_plan_id]['action'] = 'chainedit';
	}

	return false;
}


function update_domain_data_error($result) {
	if($_SESSION['shopping_cart']['plan_id'] == 'domains') {
		$dm_plan_id = $_SESSION['domain_package']['default']['series_key'];
	} else {
		$dm_plan_id = $_SESSION['plans'][$_SESSION['shopping_cart']['plan_id']]['assigned_dm_plan'];
	}
	$_SESSION['contact_error'][$dm_plan_id] = array();
	foreach($result['detail']['Array'] as $error) {
		if('contact' == $error['form']) {
			$index = $error['domain'];
			$subindex = $error['contact_type'];
		} else if('domain_extdata' == $error['form']) {
			$index = 'extdata';
			$subindex = $error['domain'];
		}
		if(! $_SESSION['contact_error'][$dm_plan_id][$index]) {
			$_SESSION['contact_error'][$dm_plan_id][$index] = array();
		}
		if(! $_SESSION['contact_error'][$dm_plan_id][$index][$subindex]) {
			$_SESSION['contact_error'][$dm_plan_id][$index][$subindex] = array();
		}
		$_SESSION['contact_error'][$dm_plan_id][$index][$subindex][] = $error;
	}

	return false;
}


function handle_domain_data_error($result) {
	if($_SESSION['shopping_cart']['plan_id'] == 'domains') {
		$dm_plan_id = $_SESSION['domain_package']['default']['series_key'];
	} else {
		$dm_plan_id = $_SESSION['plans'][$_SESSION['shopping_cart']['plan_id']]['assigned_dm_plan'];
	}

	$_SESSION['contact_error'][$dm_plan_id] = array();
	foreach($result['detail']['Array'] as $error) {
		if('contact' == $error['form']) {
			$index = $error['domain'];
			$subindex = $error['contact_type'];
		} else if('domain_extdata' == $error['form']) {
			$index = 'extdata';
			$subindex = $error['domain'];
		}

		if(!$_SESSION['contact_error'][$dm_plan_id][$index]) {
			$_SESSION['contact_error'][$dm_plan_id][$index] = array();
		}

		if(!$_SESSION['contact_error'][$dm_plan_id][$index][$subindex]) {
			$_SESSION['contact_error'][$dm_plan_id][$index][$subindex] = array();
		} 

		$_SESSION['contact_error'][$dm_plan_id][$index][$subindex][] = $error;
	}

	if($_SESSION['contact_error'][$dm_plan_id]['extdata']) {
		// Stay on this page if there are any extdata errors.
	} else {
		$_SESSION['domain_contacts'][$dm_plan_id]['action'] = 'chainedit';
		$_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] = 'configure_manually';
	}
	return false;
}


