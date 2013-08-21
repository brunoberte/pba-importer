<?php
//////////
// Domains related methods
//
// $Id: Domains.php 888368 2013-06-19 15:32:41Z dkolvakh $
//////////

namespace Entity;

use Entity\Base;

class Domains extends Base {

	public function __construct($translator = NULL) {
		parent::__construct($translator);

		$this->load_domain_package();
	}

	function generate_domains_suggestions($dm_plan_id, $domains_to_check = array()) {
		$vowels = explode(' ', 'a e i y u o');
		unset($_SESSION['check_domains_cache'][$dm_plan_id]['suggestions']);
		if(is_array($_SESSION['check_domains_cache'][$dm_plan_id]['register_new']) && $GLOBALS['StoreConf']['SUGGESTIONS_ENABLED']) {
			$prefixes = preg_split('/[,\s]+/', $GLOBALS['StoreConf']['SUGGESTIONS_PREFIX']);
			$suffixes = preg_split('/[,\s]+/', $GLOBALS['StoreConf']['SUGGESTIONS_SUFFIX']);
			$suggestions = array();
			for($i = 0; $i < $GLOBALS['StoreConf']['DOMAINS_TO_SUGGEST']; $i++) {
				$prefix = $prefixes[rand(0, (count($prefixes)-1))];
				$suffix = $suffixes[rand(0, (count($suffixes)-1))];

				$domains = array_keys($_SESSION['check_domains_cache'][$dm_plan_id]['register_new']);
				$domain = $domains[rand(0, (count($domains)-1))];
				if(rand(0,1) && $suffix) {
					$new_domain = (in_array($domain{(strlen($domain)-1)}, $vowels) XOR in_array($suffix{0}, $vowels)) ?
						$domain.$suffix : $domain.'-'.$suffix;
					if(
						strpos($domain, $suffix) === false &&
						!in_array($new_domain, array_keys($_SESSION['check_domains_cache'][$dm_plan_id]['register_new']))
					) {
						$suggestions[] = $new_domain;
						foreach($_SESSION['check_domains_cache'][$dm_plan_id]['register_new'][$domain] as $tld => $status) {
							if($status != 'notchecked' && in_array($domain.'.'.$tld, array_values($domains_to_check))) {
								$suggestions_to_check[] = $new_domain.'.'.$tld;
							}
						}
					}
				} else if(!rand(0,1) && $prefix) {
					$new_domain = (in_array($prefix{(strlen($domain)-1)}, $vowels) XOR in_array($domain{0}, $vowels)) ?
						$prefix.$domain : $prefix.'-'.$domain;
					if(
						strpos($domain, $prefix) === false &&
						!in_array($new_domain, array_keys($_SESSION['check_domains_cache'][$dm_plan_id]['register_new']))
					) {
						$suggestions[] = $new_domain;
						foreach($_SESSION['check_domains_cache'][$dm_plan_id]['register_new'][$domain] as $tld => $status) {
							if($status != 'notchecked' && in_array($domain.'.'.$tld, array_values($domains_to_check))) {
								$suggestions_to_check[] = $new_domain.'.'.$tld;
							}
						}
					}

				}
			}

			if(!is_array($suggestions_to_check)) {
				$suggestions_to_check = array();
			}
			$available_suggestions = check_domains(
				$suggestions_to_check,
				$dm_plan_id,
				'register_new',
				false,
				$this
			);

			foreach($suggestions AS $domain) {
				foreach($_SESSION['domain_package'][$dm_plan_id]['tlds_for_registration'] AS $tld) {
					if(is_array($available_suggestions) && in_array($domain.'.'.$tld, $available_suggestions)) {
						$_SESSION['check_domains_cache'][$dm_plan_id]['suggestions'][$domain][$tld] = 'available';
					}
				}
			}
		}
	}

	function process_step() {
		$action = $this->translator->getParamVal('action', '_POST');
		$series_key = $this->translator->getParamVal('series_key', '_POST');
		sw_log_debug('action => '.$action.'; series_key => '.$series_key);
		switch($action) {
			case 'check_domains':
				return $this->_check_domains($series_key);
			break;

			case 'order_selected_domains':
				return $this->_order_domains($series_key);
			break;
		}
	}

	function _check_domains($series_key) {
		$dm_action = $this->translator->getParamVal('dm_action', '_POST');
		$dm_plan_id = $_SESSION['domain_package'][$series_key]['series_key'];
		sw_log_debug('dm_action => '.$dm_action.', dm_plan_id => '.$dm_plan_id);

		$prev_checked = $_SESSION['check_domains_cache'][$dm_plan_id]['register_new'];
		## to prevent php sending warnings in log
		if(!isset($_SESSION['check_domains_cache']) || !isset($_SESSION['check_domains_cache'][$dm_plan_id])) {
			$_SESSION['check_domains_cache'][$dm_plan_id] = array();
			$_SESSION['check_domains_cache'][$dm_plan_id]['register_new'] = $_SESSION['check_domains_cache'][$dm_plan_id]['reg_transfer'] =
				$_SESSION['check_domains_cache'][$dm_plan_id]['domain_pointer'] = array();
		}

		## clear last check results, leave only ordered domains
		foreach($_SESSION['check_domains_cache'][$dm_plan_id] as $key => $cache) {
			if(!in_array($key, array('register_new', 'reg_transfer', 'domain_pointer'))) {
				continue;
			}
			foreach($cache as $name => $tlds) {
				if(is_array($tlds)) {
					foreach($tlds as $tld => $status) {
						if(!in_array($status, array('ordered'))) {
							unset($_SESSION['check_domains_cache'][$dm_plan_id][$key][$name][$tld]);
						}
					}
					if(count($_SESSION['check_domains_cache'][$dm_plan_id][$key][$name]) == 0) {
						unset($_SESSION['check_domains_cache'][$dm_plan_id][$key][$name]);
					}
				} else {
					if(!in_array($tlds, array('ordered'))) {
						unset($_SESSION['check_domains_cache'][$dm_plan_id][$key][$name]);
					}
				}
			}
		}

		$_SESSION['check_domains_cache'][$dm_plan_id]['display_dm_action'] = $dm_action;
		$_SESSION['check_domains_cache'][$dm_plan_id]['domain_selection_type'] = $this->translator->getParamVal('domain_selection_type', '_POST', 'single');
		$domain_selection_type = $_SESSION['check_domains_cache'][$dm_plan_id]['domain_selection_type'];
		$post_domain_names = lc(trim($this->translator->getParamVal('domain_names', '_POST')));
		$post_domain_name = lc(trim($this->translator->getParamVal('domain_name', '_POST')));

		$entered_domains = array();
		$domains_to_check = array();
		$domains_to_show = array();
		$not_supported_tlds = array();

		$first_lookup = isset($_SESSION['domains'][$dm_plan_id]) && count($_SESSION['domains'][$dm_plan_id]) ? 0 : 1;

		if($dm_action == 'register_new') {
			$names_list = array();
			$period = $this->translator->getParamVal('period', '_POST', 0);
			$entered_tlds = $this->translator->getParamVal('tld', '_POST');
			if(isset($entered_tlds) && !is_array($entered_tlds)) {
				$entered_tlds = Array($entered_tlds);
			} else {
				## if group of checkboxes used, no need in additional tlds for lookup
				$_SESSION['domain_package'][$series_key]['tlds_for_lookup'] = array();
			}
			$_SESSION['check_domains_cache'][$dm_plan_id]['entered_tlds'] = $entered_tlds;
			if($domain_selection_type == 'multi' && $post_domain_names) {
				$entered_domains = preg_split("/[\,\s\;\n]+/", $post_domain_names);
			} else if($domain_selection_type == 'single' && $post_domain_name) {
				if(is_array($entered_tlds)) {
					foreach($entered_tlds as $tld) {
						$entered_domains[] = $post_domain_name.'.'.$tld;
					}
				} else {
					$entered_domains[] = $post_domain_name;
				}
			}
			foreach($entered_domains AS $domain) {
				if(!xss_safe($domain)) {
					## XSS defence
					$this->error->add(MC_ERROR, null, sprintf($this->string('DOMAIN_NAME_INVALID'), htmlspecialchars($domain)));
					return $this->rollback_step();
				}
				if($domain != '') {
					if(preg_match('/^(.+?)\.(('.implode(')|(', $_SESSION['domain_package'][$series_key]['tlds_for_registration']).'))?$/', $domain, $matches)) {
						$name = $matches[1];
						$tld  = $matches[2];
					} else {
						## tld not supported
						if(preg_match('/^([^\.]*)\.(.*)$/', $domain, $matches)) {
							## push into array, to show message later
							$not_supported_tlds[] = '.'.$matches[2];
							continue;
						} else {
							$name = $domain;
							$tld  = '';
						}
					}
					if(!in_array($name, $names_list)) {
						$names_list[] = $name;
					}

					if(!in_array($tld, $_SESSION['domain_package'][$series_key]['tlds_for_lookup'])) {
						if($tld) {
							## valid tld for registration
							$domains_to_check[] = $name.'.'.$tld;
						} else {
							$domains_to_check[] = $name;
						}
					}
					## additional tlds for lookup
					## valid only for drop-down with tld list
					## for group of checkboxes have no sense
					foreach($_SESSION['domain_package'][$series_key]['tlds_for_lookup'] AS $tld) {
						$domains_to_check[] = $name.'.'.$tld;
					}
				}
			}
			if(count($not_supported_tlds)) {
				$this->error->add(MC_ERROR, null, $this->string('WE_DONT_REGISTER_DOMAINS_OF_FOLLOWING_TLDS').': '.htmlspecialchars(join(', ', $not_supported_tlds)));
			}

			$available_domains = check_domains(
				$domains_to_check,
				$dm_plan_id,
				$dm_action,
				true,
				$this
			);

			foreach($names_list AS $name) {
				foreach($_SESSION['domain_package'][$series_key]['tlds_for_registration'] AS $tld_id => $tld) {
					if($_SESSION['domain_error_info'][$name.'.'.$tld]) {
						continue;
					}
					$domain_in_cart = $this->_domain_in_cart($dm_plan_id, $name.'.'.$tld, $dm_action);
					if(!in_array($name.'.'.$tld, $entered_domains) && !in_array($tld, $_SESSION['domain_package'][$series_key]['tlds_for_lookup'])
						&& !$domain_in_cart) {
						continue;
					}
					## guess period
					$period = $this->translator->getParamVal(escape_dm('period___'.$name.'.'.$tld.'_register_new'), '_POST');
					if(!$domain_in_cart && !isset($period)) {
						$_SESSION['check_domains_cache'][$dm_plan_id]['periods'][$name.'.'.$tld] = $this->_get_period_id($period, $tld_id, $series_key);
					} elseif(isset($period)) {
						$_SESSION['check_domains_cache'][$dm_plan_id]['periods'][$name.'.'.$tld] = $period;
					}
					## determine status
					if($lookup_status = $this->_get_lookup_status($name.'.'.$tld, $domain_in_cart, $domains_to_check, $available_domains)) {
						$domains_to_show[$name][$tld] = $lookup_status;
					}
				}
			}

			foreach($domains_to_show as $name => $value) {
				if(!array_key_exists($name, $_SESSION['check_domains_cache'][$dm_plan_id][$dm_action])) {
					$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] = $value;
				} else {
					foreach($value as $tld => $status) {
						if($status == 'ordered' || $status == 'unavailable' ||
							($status == 'available' && $_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name][$tld] != 'ordered')
						) {
							$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name][$tld] = $status;
						}
						if($status == 'unavailable') {
							## remove from cart, if was ordered earlier
							$this->_remove_from_cart($dm_plan_id, $name.'.'.$tld, $dm_action);
						}
					}
				}
			}

			// add entered domains to shopping cart if first time check and domain is available
			if($first_lookup) {
				foreach($domains_to_show as $name => $value) {
					foreach($value as $tld => $status) {
						if($status != 'unavailable' && in_array($name.'.'.$tld, $entered_domains)) {
							$_SESSION['domains'][$dm_plan_id][$name.'.'.$tld]['dm_action'] = $dm_action;
							$_SESSION['domains'][$dm_plan_id][$name.'.'.$tld]['tld'] = $tld;
							$_SESSION['domains'][$dm_plan_id][$name.'.'.$tld]['period_id'] = $_SESSION['check_domains_cache'][$dm_plan_id]['periods'][$name.'.'.$tld] ?
								$_SESSION['check_domains_cache'][$dm_plan_id]['periods'][$name.'.'.$tld] : 0;
							$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name][$tld] = 'ordered';
						}
					}
				}
			}

			// Generate suggestions
			$this->generate_domains_suggestions($dm_plan_id, $domains_to_check);
		}

		if($dm_action == 'reg_transfer') {
			$invalid_domains = array();

			if($domain_selection_type == 'multi' && $post_domain_names) {
				$entered_domains = preg_split("/[\,\s\;\n]+/", $post_domain_names);
			} else if($domain_selection_type == 'single' && $post_domain_name) {
				$entered_domains[] = $post_domain_name;
			}

			foreach($entered_domains AS $domain) {
				if(!xss_safe($domain)) {
					## XSS defence
					$this->error->add(MC_ERROR, null, sprintf($this->string('DOMAIN_NAME_INVALID'), htmlspecialchars($domain)));
					return $this->rollback_step();
				}
				if($domain != '') {
					if(preg_match('/^(.+?)\.(('.implode(')|(', $_SESSION['domain_package'][$series_key]['tlds_for_transfer']).'))?$/', $domain, $matches)) {
						$domains_to_check[] = $domain;
					} else {
						## tld not supported
						if(preg_match('/^([^\.]*)\.(.*)$/', $domain, $matches)) {
							## push into array, to show message later
							$not_supported_tlds[] = '.'.$matches[2];
						} else {
							$invalid_domains[] = $domain;
						}
					}
				}
			}
			if(count($not_supported_tlds)) {
				$this->error->add(MC_ERROR, null, $this->string('WE_DONT_TRANSFER_DOMAINS_OF_FOLLOWING_TLDS').': '.htmlspecialchars(join(', ', $not_supported_tlds)));
			}

			$entered_domains = $domains_to_check;

			$available_domains = check_domains(
				array_merge($domains_to_check, $invalid_domains),
				$dm_plan_id,
				$dm_action,
				true,
				$this
			);

			foreach($entered_domains AS $name) {
				if($_SESSION['domain_error_info'][$name]) {
					continue;
				}
				$domain_in_cart = $this->_domain_in_cart($dm_plan_id, $name, $dm_action);
				## determine status
				if($lookup_status = $this->_get_lookup_status($name, $domain_in_cart, $domains_to_check, $available_domains)) {
					$domains_to_show[$name] = $lookup_status;
				}
			}

			foreach($domains_to_show as $name => $value) {
				if(!array_key_exists($name, $_SESSION['check_domains_cache'][$dm_plan_id][$dm_action])) {
					$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] = $value;
				} else {
					if($value == 'ordered' || $value == 'unavailable' ||
						($value == 'available' && $_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] != 'ordered')
					) {
						$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] = $value;
					}
					if($value == 'unavailable') {
						## remove from cart, if was ordered earlier
						$this->_remove_from_cart($dm_plan_id, $name, $dm_action);
					}
				}
			}

			// add entered domains to shopping cart if first time check and domain is available
			if($first_lookup) {
				foreach($domains_to_show as $name => $status) {
					if($status != 'unavailable' && in_array($name, $entered_domains)) {
						foreach($_SESSION['domain_package'][$dm_plan_id]['tlds_for_transfer'] AS $tld) {
							if(preg_match('/\.'.$tld.'$/', $name)) {
								## guess tld
								if(strlen($_SESSION['domains'][$dm_plan_id][$name]['tld']) < strlen($tld)) {
									$_SESSION['domains'][$dm_plan_id][$name]['tld'] = $tld;
								}
							}
						}
						$_SESSION['domains'][$dm_plan_id][$name]['dm_action'] = $dm_action;
						$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] = 'ordered';
					}
				}
			}
		}

		if($dm_action == 'domain_pointer') {
			if($domain_selection_type == 'multi' && $post_domain_names) {
				$entered_domains = preg_split("/[\,\s\;\n]+/", $post_domain_names);
			} else if($domain_selection_type == 'single' && $post_domain_name) {
				$entered_domains[] = $post_domain_name;
			}

			foreach($entered_domains AS $domain) {
				if(!xss_safe($domain)) {
					## XSS defence
					$this->error->add(MC_ERROR, null, sprintf($this->string('DOMAIN_NAME_INVALID'), htmlspecialchars($domain)));
					return $this->rollback_step();
				}
				if($domain != '')  {
					$domains_to_check[] = $domain;
				}
			}

			$available_domains = check_domains(
				$domains_to_check,
				$dm_plan_id,
				'domain_pointer',
				true,
				$this
			);

			foreach($entered_domains AS $name) {
				if($_SESSION['domain_error_info'][$name]) {
					continue;
				}
				$domain_in_cart = $this->_domain_in_cart($dm_plan_id, $name, $dm_action);
				## determine status
				if($lookup_status = $this->_get_lookup_status($name, $domain_in_cart, $domains_to_check, $available_domains)) {
					$domains_to_show[$name] = $lookup_status;
				}
			}

			foreach($domains_to_show as $name => $value) {
				if(!array_key_exists($name, $_SESSION['check_domains_cache'][$dm_plan_id][$dm_action])) {
					$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] = $value;
				} else {
					if($value == 'ordered' || $value == 'unavailable' ||
						($value == 'available' && $_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] != 'ordered')
					) {
						$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] = $value;
					}
					if($value == 'unavailable') {
						## remove from cart, if was ordered earlier
						$this->_remove_from_cart($dm_plan_id, $name, $dm_action);
					}
				}
			}

			// add entered domains to shopping cart if first time check and domain is available
			if($first_lookup) {
				foreach($domains_to_show as $name => $status) {
					if($status != 'unavailable' && in_array($name, $entered_domains)) {
						$_SESSION['domains'][$dm_plan_id][$name]['dm_action'] = $dm_action;
						$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$name] = 'ordered';
					}
				}
			}
		}

		unset($_SESSION['ps']['domains']);
		unset($_SESSION['ps']['action']);
		if(count($entered_domains)) {
			return $this->teaser_step();
		} else {
			$this->error->add(MC_ERROR, $domain_selection_type == 'multi' ? PLEASE_SPECIFY_DOMAIN_NAMES : PLEASE_SPECIFY_DOMAIN_NAME);
			return $this->rollback_step();
		}
	}

	function _order_domains($series_key) {
		$dm_plan_id = $_SESSION['domain_package'][$series_key]['series_key'];

		## store form layout options
		$_SESSION['check_domains_cache'][$dm_plan_id]['display_dm_action'] = $this->translator->getParamVal('dm_action', '_POST');
		$_SESSION['check_domains_cache'][$dm_plan_id]['domain_selection_type'] = $this->translator->getParamVal('domain_selection_type', '_POST', 'single');
		$entered_tlds = $this->translator->getParamVal('tld', '_POST');
		if(isset($entered_tlds) && !is_array($entered_tlds)) {
			$entered_tlds = Array($entered_tlds);
		}
		$_SESSION['check_domains_cache'][$dm_plan_id]['entered_tlds'] = $entered_tlds;

		## Actions for domain list manipulation only
		if(is_array($_SESSION['check_domains_cache'][$dm_plan_id]['register_new'])) {
			## if suggestions is available, and checked, add to main array
			if(isset($_SESSION['check_domains_cache'][$dm_plan_id]['suggestions'])) {
				foreach($_SESSION['check_domains_cache'][$dm_plan_id]['suggestions'] AS $domain_name => $v) {
					foreach($_SESSION['domain_package'][$series_key]['tlds_for_registration'] AS $tld) {
						if($this->translator->getParamVal(escape_dm($domain_name.'.'.$tld.'_register_new'), '_POST') == 'on'
							&& !$_SESSION['check_domains_cache'][$dm_plan_id]['register_new'][$domain_name]) {
							$_SESSION['check_domains_cache'][$dm_plan_id]['register_new'][$domain_name] = $_SESSION['check_domains_cache'][$dm_plan_id]['suggestions'][$domain_name];
						}
					}
				}
			}

			## walk trough main array, virtually add or remove domains according to checkbox status and button pressed
			foreach($_SESSION['check_domains_cache'][$dm_plan_id]['register_new'] AS $domain_name => $v) {
				foreach($_SESSION['domain_package'][$series_key]['tlds_for_registration'] AS $tld) {
					if($v[$tld] == 'ordered' && $this->translator->getParamVal(escape_dm($domain_name.'.'.$tld.'_register_new'), '_POST') != 'on') {
						$_SESSION['check_domains_cache'][$dm_plan_id]['register_new'][$domain_name][$tld] = 'available';
						## remove from cart
						if($v[$tld] == 'ordered') {
							unset($_SESSION['domains'][$dm_plan_id][$domain_name.'.'.$tld]);
							unset($_SESSION['contact_error'][$dm_plan_id][$domain_name.'.'.$tld]);
							unset($_SESSION['contact_error'][$dm_plan_id]['extdata'][$domain_name.'.'.$tld]);
						}
					}
					if(($v[$tld] == 'available' || $v[$tld] == 'ordered') && $this->translator->getParamVal(escape_dm($domain_name.'.'.$tld.'_register_new'), '_POST') == 'on') {
						if($v[$tld] == 'available' && $this->translator->getParamVal(escape_dm($domain_name.'.'.$tld.'_register_new'), '_POST') == 'on') {
							## clean cart and check_domains_cache for the same domain but other dm_actions
							$this->_clean_cart_cache($dm_plan_id, 'register_new', $domain_name, $tld);
						}
						$_SESSION['check_domains_cache'][$dm_plan_id]['register_new'][$domain_name][$tld] = 'ordered';
						$_SESSION['check_domains_cache'][$dm_plan_id]['periods'][$domain_name.'.'.$tld] = $this->translator->getParamVal(escape_dm('period___'.$domain_name.'.'.$tld.'_register_new'), '_POST');
						## add to cart
						$_SESSION['domains'][$dm_plan_id][$domain_name.'.'.$tld]['dm_action'] = 'register_new';
						$_SESSION['domains'][$dm_plan_id][$domain_name.'.'.$tld]['tld'] = $tld;
						$_SESSION['domains'][$dm_plan_id][$domain_name.'.'.$tld]['period_id'] = $_SESSION['check_domains_cache'][$dm_plan_id]['periods'][$domain_name.'.'.$tld] ?
							$_SESSION['check_domains_cache'][$dm_plan_id]['periods'][$domain_name.'.'.$tld] : 0;
					}
				}
			}
		}

		if(is_array($_SESSION['check_domains_cache'][$dm_plan_id]['reg_transfer'])) {
			## walk trough main array, virtually add or remove domains according to checkbox status and button pressed
			foreach($_SESSION['check_domains_cache'][$dm_plan_id]['reg_transfer'] AS $domain_name => $v) {
				if($v == 'ordered' && $this->translator->getParamVal(escape_dm($domain_name.'_reg_transfer'), '_POST') != 'on') {
					$_SESSION['check_domains_cache'][$dm_plan_id]['reg_transfer'][$domain_name] = 'available';
					## remove from cart
					if($v == 'ordered') {
						unset($_SESSION['domains'][$dm_plan_id][$domain_name]);
						unset($_SESSION['contact_error'][$dm_plan_id][$domain_name]);
						unset($_SESSION['contact_error'][$dm_plan_id]['extdata'][$domain_name]);
					}
				}
				if($v == 'available' && $this->translator->getParamVal(escape_dm($domain_name.'_reg_transfer'), '_POST') == 'on') {
					## clean cart and check_domains_cache for the same domain but other dm_actions
					$this->_clean_cart_cache($dm_plan_id, 'reg_transfer', $domain_name);
					$_SESSION['check_domains_cache'][$dm_plan_id]['reg_transfer'][$domain_name] = 'ordered';
					## add to cart
					$_SESSION['domains'][$dm_plan_id][$domain_name]['dm_action'] = 'reg_transfer';
					foreach($_SESSION['domain_package'][$series_key]['tlds_for_transfer'] AS $tld) {
						if(preg_match('/\.'.$tld.'$/', $domain_name)) {
							if(strlen($_SESSION['domains'][$dm_plan_id][$domain_name]['tld']) < strlen($tld)) {
								$_SESSION['domains'][$dm_plan_id][$domain_name]['tld'] = $tld;
							}
						}
					}
				}
			}
		}

		if(is_array($_SESSION['check_domains_cache'][$dm_plan_id]['domain_pointer'])) {
			## walk trough main array, virtually add or remove domains according to checkbox status and button pressed
			foreach($_SESSION['check_domains_cache'][$dm_plan_id]['domain_pointer'] AS $domain_name => $v) {
				if($v == 'ordered' && $this->translator->getParamVal(escape_dm($domain_name.'_domain_pointer'), '_POST') != 'on') {
					$_SESSION['check_domains_cache'][$dm_plan_id]['domain_pointer'][$domain_name] = 'available';
					## remove from cart
					if($v == 'ordered') {
						unset($_SESSION['domains'][$dm_plan_id][$domain_name]);
						unset($_SESSION['contact_error'][$dm_plan_id][$domain_name]);
						unset($_SESSION['contact_error'][$dm_plan_id]['extdata'][$domain_name]);
					}
				}
				if($v == 'available' && $this->translator->getParamVal(escape_dm($domain_name.'_domain_pointer'), '_POST') == 'on') {
					## clean cart and check_domains_cache for the same domain but other dm_actions
					$this->_clean_cart_cache($dm_plan_id, 'domain_pointer', $domain_name);
					$_SESSION['check_domains_cache'][$dm_plan_id]['domain_pointer'][$domain_name] = 'ordered';
					## add to cart
					$_SESSION['domains'][$dm_plan_id][$domain_name]['dm_action'] = 'domain_pointer';
				}
			}
		}

		## If no domains exists in cart
		if(!count($_SESSION['domains'][$dm_plan_id])) {
			unset($_SESSION['domains'][$dm_plan_id]);
		}

		return $this->teaser_step();
	}

	function teaser_step() {
		if(!isset($_SESSION['domain_package']) || !is_array($_SESSION['domain_package'])) {
			$this->error->add(MC_WARN, 'DOMAIN_MANAGER_NOT_CONFIGURED');	## possibly will be dubbed in __construct
		}
		return 1;
	}

	## get internal id for period in years for some tld
	function _get_period_id($period = null, $tld = null, $series_key = null) {
		if(!isset($tld) || !isset($period)) {
			return 0;
		}
		if(!isset($_SESSION['domain_package'][$series_key]) || !isset($_SESSION['domain_package'][$series_key]['tld_list'][$tld])) {
			return 0;
		}
		foreach($_SESSION['domain_package'][$series_key]['tld_list'][$tld]['fee_list'] as $key => $value) {
			if($value['period'] == $period) {
				return $key;
			}
		}
		return 0;
	}

	function _count_ordered_domains($dm_plan_id) {
		if(is_array($_SESSION['domains'][$dm_plan_id]) && count($_SESSION['domains'][$dm_plan_id])) {
			return 1;
		} else {
			return 0;
		}
	}

	function _domain_in_cart($dm_plan_id, $name, $dm_action) {
		return
			is_array($_SESSION['domains'][$dm_plan_id]) && in_array($name, array_keys($_SESSION['domains'][$dm_plan_id])) &&
				$_SESSION['domains'][$dm_plan_id][$name]['dm_action'] == $dm_action;
	}

	function _remove_from_cart($dm_plan_id, $name, $dm_action) {
		$domain_in_cart = $this->_domain_in_cart($dm_plan_id, $name, $dm_action);
		if($domain_in_cart) {
			unset($_SESSION['domains'][$dm_plan_id][$name]);
			unset($_SESSION['contact_error'][$dm_plan_id][$name]);
			unset($_SESSION['contact_error'][$dm_plan_id]['extdata'][$name]);
			## and clean up
			if(!count($_SESSION['domains'][$dm_plan_id])) {
				unset($_SESSION['domains'][$dm_plan_id]);
			}
			if(!count($_SESSION['contact_error'][$dm_plan_id]['extdata'])) {
				unset($_SESSION['contact_error'][$dm_plan_id]['extdata']);
			}
			if(!count($_SESSION['contact_error'][$dm_plan_id])) {
				unset($_SESSION['contact_error'][$dm_plan_id]);
			}
		}
	}

	function _clean_cart_cache($dm_plan_id, $current_dm_action, $domain_name, $tld = null) {
		if(!isset($tld)) {
			## try to guess TLD for transfer and domain pointer operations
			$tld = '';
			foreach($_SESSION['domain_package'][$dm_plan_id]['tlds_for_registration'] AS $guess_tld) {
				if(preg_match('/\.'.$guess_tld.'$/', $domain_name)) {
					## find longest one
					if(strlen($tld) < strlen($guess_tld)) {
						$tld = $guess_tld;
					}
				}
			}
			foreach($_SESSION['domain_package'][$dm_plan_id]['tlds_for_transfer'] AS $guess_tld) {
				if(preg_match('/\.'.$guess_tld.'$/', $domain_name)) {
					## find longest one
					if(strlen($tld) < strlen($guess_tld)) {
						$tld = $guess_tld;
					}
				}
			}
			if(!strlen($tld)) {
				## nothing found, try to extract from domain name
				if(preg_match('/^([^\.]*)\.(.*)$/', $domain_name, $matches)) {
					$tld = $matches[2];
				}
			}
			$domain_name = preg_replace('/(.*)\.'.$tld.'/', '$1', $domain_name);
		}

		$domain = $domain_name.'.'.$tld;
		$_SESSION['domains'][$dm_plan_id][$domain] = array();
		foreach(array('register_new', 'reg_transfer', 'domain_pointer') as $dm_action) {
			if($dm_action == $current_dm_action) {
				## skip current action
				continue;
			}
			if($dm_action != 'register_new' && isset($_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$domain])) {
				## transfer, pointer
				if($_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$domain] == 'ordered') {
					$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$domain] = 'available';
				}
				$this->translator->setParamVal(escape_dm($domain.'_'.$dm_action), '_POST', 'off');
			}
			if($dm_action == 'register_new' && isset($_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$domain_name][$tld])) {
				## register
				if($_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$domain_name][$tld] == 'ordered') {
					$_SESSION['check_domains_cache'][$dm_plan_id][$dm_action][$domain_name][$tld] = 'available';
				}
				$this->translator->setParamVal(escape_dm($domain.'_'.$dm_action), '_POST', 'off');
			}
		}
	}

	function _get_lookup_status($name, $domain_in_cart, $domains_to_check, $available_domains) {
		if($domain_in_cart) {
			## is in cart already
			if(in_array($name, $domains_to_check)) {
				## and was checked again
				if(in_array($name, $available_domains)) {
					## and is available
					return 'ordered';
				} else {
					## and not available
					return 'unavailable';
				}
			} else {
				## do nothing, was not checked
				return null;
			}
		} else {
			## is not in cart already
			if(in_array($name, $domains_to_check)) {
				## and was checked
				if(in_array($name, $available_domains)) {
					## and is available
					return 'available';
				} else {
					## and not available
					return 'unavailable';
				}
			} else {
				## do nothing, was not checked
				return null;
			}
		}
		sw_log_error('unpredictable lookup status, parameters: name => '.$name.', domain_in_cart => '.$domain_in_cart.
			', domains_to_check => '.dumper($domains_to_check).', available_domains => '.dumper($available_domains));
		return 'unavailable';
	}

////
// Load domain registration hosting plan by hp series key
// Loads default plan unless $series_key is specified
// 	(see Commerce Director > Store manager > Configure Store > Plan Listing tab)
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
		if(is_array($_SESSION['campaign']) && is_array($_SESSION['campaign']['promotion'])) {
			$promo_id = $_SESSION['campaign']['promotion']['promo_id'];
		}
		if(is_array($_SESSION['shopping_cart']) && isset($_SESSION['shopping_cart']['promo_id'])) {
			$promo_id = $_SESSION['shopping_cart']['promo_id'];
		}

		if($result = call( 'get_extended_plan_info',
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
			if($this->error->has(MC_INTERR)) {
				$this->error->get(MC_INTERR, true);
			}
		}
		return false;
	}

	function get_domain_contact_errors($domain, $ctype, $dm_plan_id) {
		$fields = array();
		if(
			$_SESSION['contact_error'] && $_SESSION['contact_error'][$dm_plan_id] &&
			$_SESSION['contact_error'][$dm_plan_id][$domain] && $_SESSION['contact_error'][$dm_plan_id][$domain][$ctype]
		) {
			foreach($_SESSION['contact_error'][$dm_plan_id][$domain][$ctype] as $error) {
				$this->error->add(MC_ERROR, null, $error['message']);
				if($error['field'] == 'phone' || $error['field'] == 'fax') {
					$fields[] = 'item_' . str_replace(array('.','-'),'_',$domain) . '_' . $ctype . '_'.$error['field'] . '_number';
					$fields[] = 'item_' . str_replace(array('.','-'),'_',$domain) . '_' . $ctype . '_'.$error['field'] . '_country_code';
				} else {
					$fields[] = 'item_' . str_replace(array('.','-'),'_',$domain) . '_' . $ctype . '_'.$error['field'];
				}
			}
		}
		return $fields;
	}

	function get_domain_contact_error_names($dm_plan_id) {
		$names = array();
		if($_SESSION['contact_error'] && $_SESSION['contact_error'][$dm_plan_id] && is_array($_SESSION['contact_error'][$dm_plan_id])) {
			foreach($_SESSION['contact_error'][$dm_plan_id] as $domain => $err_hash) {
				if($domain == 'extdata') {
					continue;
				}
				foreach($err_hash as $ctype => $value) {
					$names[] = $ctype."_ctname_".escape_dm($domain);
				}
			}
		}
		return $names;
	}

	function get_invalid_contact_list($dm_plan_id) {
		$names = array();
		if(is_array($_SESSION['contact_error']) && is_array($_SESSION['contact_error'][$dm_plan_id])) {
			foreach($_SESSION['contact_error'][$dm_plan_id] as $domain => $err_hash) {
				if($domain == 'extdata') {
					continue;
				}
				foreach($err_hash as $ctype => $value) {
					$names[] = $ctype."_contact_".escape_dm($domain);
				}
			}
		}
		return $names;
	}

	function process_domain_contacts($domain, $contact_type, $contact_id, $dm_plan_id) {
		sw_log_debug('domain => '.$domain.', contact_type => '.$contact_type.', contact_id => '.$contact_id.', dm_plan_id => '.$dm_plan_id);
		install_error_handler('UserDomainContactError', "handle_domain_contact_error");
		if($result = call('validate_contact', array(
			'hp_sid'	   => $dm_plan_id,
			'domain' 	   => $domain,
			'action'	   => $_SESSION['domains'][$dm_plan_id][$domain]['dm_action'],
			'contact_type' => $contact_type,
			'account_id'   => $_SESSION['account']['account_id'],
			'form_data'    => $_POST,
			'domain_form_data' => $_SESSION['domains_extdata'][$dm_plan_id],
			'contact_hash' => $_SESSION['domains'][$dm_plan_id][$domain]['contacts']
			), 'HSPC/API/Domain')
		) {
			if($result = call('save_contact', array(
				'hp_sid'	   => $dm_plan_id,
				'domain' 	   => $domain,
				'action'	   => $_SESSION['domains'][$dm_plan_id][$domain]['dm_action'],
				'contact_type' => $contact_type,
				'account_id'   => $_SESSION['account']['account_id'],
				'contact_id'   => $contact_id,
				'form_data'    => $_POST
				), 'HSPC/API/Domain')
			) {
				$fname = 'item_' . str_replace(array('.','-'),'_',$domain) . '_' . $contact_type . '_fname';
				$lname = 'item_' . str_replace(array('.','-'),'_',$domain) . '_' . $contact_type . '_lname';
				$cnt_name = html_entity_decode($this->translator->getParamVal($fname, '_POST').' '.$this->translator->getParamVal($lname, '_POST'));
				if(strlen($cnt_name) > $GLOBALS['StoreConf']['DOMAIN_CONTACT_DISPLAY_LENGHT']) {
					$cnt_name = substr($cnt_name, 0, $GLOBALS['StoreConf']['DOMAIN_CONTACT_DISPLAY_LENGHT']).'...';
				}
				$result['contact_name'] = $cnt_name;
				load_domain_contacts();
				return $result;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function teaser_domain_contacts($domain, $contact_type = NULL, $contact_id, $contact, $dm_plan_id) {
		$action = $this->translator->getParamVal('action', '_POST');
		$chain_mode = $action == 'chainedit' || $_SESSION['domain_contacts'][$dm_plan_id]['action'] == 'chainedit' ? true : false;
		sw_log_debug('domain => '.$domain.', contact_type => '.$contact_type.', contact_id => '.$contact_id.', dm_plan_id => '.$dm_plan_id.
			', chain_mode => '.$chain_mode.', action => '.$action.', contact => '.dumper($contact));

		$form_data = null;
		if(!$contact) {
			$form_data = $action == 'contact_edit' ? NULL : $_POST;
			if(!$contact_type) {
				$contact = get_invalid_contact($dm_plan_id, $domain);
			} else {
				$contact = array(
					'domain'       => $domain,
					'contact_type' => $contact_type,
					'contact_id'   => $contact_id,
				);
			}
		}

		if(!is_array($contact)) {
			## no invalid contacts exist, find first editable contact for first ordered domain
			$contact = array(
				'contact_id' => $contact_id,
			);
			foreach($_SESSION['domains'][$dm_plan_id] as $key => $domain) {
				if(!isset($domain['contact_types'])) {
					continue;
				}
				foreach($domain['contact_types'] AS $contact_type) {
					if(!$contact_type['force_default']) {
						$contact['domain'] = $key;
						$contact['contact_type'] = $contact_type['type'];
						break 2;
					} 
				}
			}
		}

		$result = call('get_contact_form', array(
			'hp_sid' => $dm_plan_id,
			'domain' => $contact['domain'],
			'action' => $_SESSION['domains'][$dm_plan_id][$contact['domain']]['dm_action'],
			'contact_type' => $contact['contact_type'],
			'contact_id' => $contact['contact_id'],
			'account_id' => $_SESSION['account']['account_id'],
			'form_data' => $form_data
			), 'HSPC/API/Domain'
		);
		$form_layout = $result['form_layout'];
		$current_domain = $contact['domain'];
		$current_tld = get_domain_tld($current_domain);
		$current_ctype = $contact['contact_type'];
		$current_cinfo = $this->get_domain_contact_info($current_domain, $current_ctype, $dm_plan_id);
		$readonly = $result['readonly'] || $contact['contact_id'] == 0;
		$current_cid = $readonly ? null : $contact['contact_id'];
		$create_form_url_param = array('domain' => $current_domain, 'contact_type' => $current_ctype);
		if($chain_mode) {
			$create_form_url_param['action'] = 'chainedit';
		}
		$create_form_action = $this->generateUrl('domaincontacts', $create_form_url_param);
		$update_form_url_param = $create_form_url_param;
		if($current_cid) {
			$update_form_url_param['contact_id'] = $current_cid;
		}
		$update_form_action = $this->generateUrl('domaincontacts', $update_form_url_param);
		$invalid_domain_list = array();
		if($chain_mode) {
			foreach($_SESSION['domains'][$dm_plan_id] as $domain_name => $domain_info) {
				if($_SESSION['contact_error'][$dm_plan_id][$domain_name]) {
					$invalid_domain_list[] = $domain_name;
				}
			}
		}
		$extra_buttons = $chain_mode ? $this->get_radio_buttons($current_tld, $current_ctype, $current_cinfo, $dm_plan_id) : array();
		$contact_title = $this->get_dmcontact_title_by_type($current_domain, $current_ctype, $dm_plan_id);

		return array(
			'create_form_action' => $create_form_action,
			'chain_mode' => $chain_mode,
			'invalid_domain_list' => $invalid_domain_list,
			'current_domain' => $current_domain,
			'current_ctype' => $current_ctype,
			'update_form_action' => $update_form_action,
			'form_layout' => $form_layout,
			'current_tld' => $current_tld,
			'extra_buttons' => $extra_buttons,
			'readonly' => $readonly,
			'contact_title' => $contact_title,
			'domain_contact_error' => $_SESSION['contact_error'][$dm_plan_id][$current_domain][$current_ctype],
			'contacts_prefilling_type' => $_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type']
		);
	}

	function next_step_domain_contacts($domain, $contact_type, $contact_id, $update, $action, $dm_plan_id, $store_apply) {
		sw_log_debug('domain => '.$domain.', contact_type => '.$contact_type.', contact_id => '.$contact_id.', update => '.$update.', action => '.$action.', store_apply => '.$store_apply.', dm_plan_id => '.$dm_plan_id);
		if($_SESSION['domains'][$dm_plan_id][$domain]) {
			$_SESSION['domains'][$dm_plan_id][$domain]['contacts'][$contact_type] = $contact_id;
		}

		## if 'chainedit' occurred, some contacts may be updated, some - created
		## turn configure_manually option to prevent override by default values on configure step
		## ???
		if($action == 'chainedit') {
			$_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] = 'configure_manually';
		} else {
			## store created/updated contact id to correctly preselect it on configure step
			if($_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] == 'use_contact') {
				$_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_contact_id'] = $contact_id;
			}
		}

		// If it was an update, and this contact is used in other
		// domain contacts configuration.
		$used_somewhere = ($store_apply && $store_apply != 'none') ? 1 : 0;
		if($update && !$used_somewhere) {
			foreach($_SESSION['domains'][$dm_plan_id] as $cdomain => $domain_info) {
				foreach($domain_info['contacts'] as $type => $cid) {
					if($cid == $contact_id) {
						$used_somewhere = 1;
						break 2;
					}
				}
			}
		}

		if($store_apply && $store_apply != 'none') {
			if($store_apply == 'tld_c') {
				$this->refill_domain_contacts($contact_id, $dm_plan_id, get_domain_tld($domain), $contact_type);
			} else if($store_apply == 'type_all') {
				$this->refill_domain_contacts($contact_id, $dm_plan_id, null, $contact_type);
			} else if($store_apply == 'tld_all') {
				$this->refill_domain_contacts($contact_id, $dm_plan_id, get_domain_tld($domain));
			} else if($store_apply == 'all') {
				$this->refill_domain_contacts($contact_id, $dm_plan_id);
			}
		}

		if($used_somewhere) {
			install_error_handler('UserDomainDataError', "update_domain_data_error");
			validate_domain_data($dm_plan_id);
		}

		if($action == 'chainedit') {
			if($_SESSION['contact_error'][$dm_plan_id][$domain][$contact_type]) {
				unset($_SESSION['contact_error'][$dm_plan_id][$domain][$contact_type]);
			}
			if(1 > count($_SESSION['contact_error'][$dm_plan_id][$domain])) {
				unset($_SESSION['contact_error'][$dm_plan_id][$domain]);
			}
			if(1 > count($_SESSION['contact_error'][$dm_plan_id])) {
				unset($_SESSION['contact_error'][$dm_plan_id]);
				return;
			}
			$my_contact = get_invalid_contact($dm_plan_id, $domain);
			return $my_contact;
		} else {
			if($_SESSION['contact_error'][$dm_plan_id][$domain][$contact_type]) {
				unset($_SESSION['contact_error'][$dm_plan_id][$domain][$contact_type]);
			}
			if(1 > count($_SESSION['contact_error'][$dm_plan_id][$domain])) {
				unset($_SESSION['contact_error'][$dm_plan_id][$domain]);
			}
			if(1 > count($_SESSION['contact_error'][$dm_plan_id])) {
				unset($_SESSION['contact_error'][$dm_plan_id]);
			}
			return;
		}
	}

	function save_domain_data($dm_plan_id) {
		if(!is_array($_SESSION['domains'][$dm_plan_id])) {
			return 1;
		}

		## save predefine domain contacts user selection
		$_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] = $this->translator->getParamVal('contacts_prefilling_type', '_POST');
		$_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_contact_id'] = $this->translator->getParamVal('contacts_prefilling_contact_id', '_POST');
		$ns_list = array();

		foreach($_SESSION['domains'][$dm_plan_id] AS $key => $value) {
			$domain = &$_SESSION['domains'][$dm_plan_id][$key];
			$domain['whois_privacy'] = $this->translator->getParamVal('whois_privacy_'.escape_dm($key), '_POST') == 'on' ? 1 : 0;
			$domain['dns_hosting'] 	= $this->translator->getParamVal('dns_hosting_'.escape_dm($key), '_POST') == 'on' ? 1 : 0;
			$domain['hosting'] 		= $this->translator->getParamVal('hosting_'.escape_dm($key), '_POST') == 'on' ? 1 : 0;
			$domain['hosting_destination'] = $domain['hosting'] ? 
				$this->translator->getParamVal('hosting_destination_'.escape_dm($key), '_POST', 0) : 0;

			if($domain['extdata_form']) {
				$domain['extdata'] = $_POST;
			}
			$_SESSION['domains_extdata'][$dm_plan_id] = $_POST;

			if(is_array($domain['contact_types'])) {
				foreach($domain['contact_types'] AS $type) {
					if($type['force_default'] && $type['default_contact']) {
						$domain['contacts'][$type['type']] = $type['default_contact'];
					} else {
						$domain['contacts'][$type['type']] = $this->translator->getParamVal($type['type'].'_contact_'.escape_dm($key), '_POST', 0);
					}
				}
			}

			unset($domain['ns']);
			if(!$domain['dns_hosting'] && $domain['dm_action'] != 'domain_pointer') {
				$domain['ns'][0][0] = $this->translator->getParamVal('ns_hostname_1_'.escape_dm($key), '_POST');
				$domain['ns'][0][1] = $this->translator->getParamVal('ns_ip_1_'.escape_dm($key), '_POST');
				$domain['ns'][1][0] = $this->translator->getParamVal('ns_hostname_2_'.escape_dm($key), '_POST');
				$domain['ns'][1][1] = $this->translator->getParamVal('ns_ip_2_'.escape_dm($key), '_POST');
				for($i = 0; $i <= 1; $i++) {
					if(strlen($domain['ns'][$i][0]) || strlen($domain['ns'][$i][1])) {
						$ns_list[] = $domain['ns'][$i];
					}
				}
			}
		}

		if(count($ns_list)) {
			call('validate_ns_list', array('ns_list' => $ns_list), 'HSPC/API/Domain');
			$transport = get_api_transport();
			if($transport->fault) {
				if(strpos($transport->faultcode, 'soap:User') === 0) {
					return $this->rollback_step();
				} else {
					$this->error->add(MC_ERROR, 'NS_SERVERS_INVALID');
					return $this->rollback_step();
				}
			}
		}

		$_SESSION['domain_data_full'] = $_POST;
		return 1;
	}

	function get_domain_data_error($dm_plan_id) {
		if(isset($_SESSION['contact_error']) && isset($_SESSION['contact_error'][$dm_plan_id]) && is_array($_SESSION['contact_error'][$dm_plan_id])) {
			$has_invalid_extdata = 0;
			$invalid_contacts = 0;
			foreach($_SESSION['contact_error'][$dm_plan_id] as $domain => $err_hash) {
				if($domain == 'extdata') {
					$has_invalid_extdata = 1;
				}	else {
					$invalid_contacts += count($err_hash);
				}
			}
			if($has_invalid_extdata) {
				foreach($_SESSION['contact_error'][$dm_plan_id]['extdata'] as $domain => $error_info) {
					$this->error->add(MC_ERROR, null, sprintf($this->string('EXT_DATA_ERROR_DESC'), $domain));
					foreach($error_info as $error) {
						$this->error->add(MC_ERROR, null, $error['message']);
					}
				}
			}
			if($invalid_contacts) {
				$this->error->add(MC_ERROR, 'CONTACT_ERROR_DESC');
			}
		}
	}

	// Returns array of which radiobuttons to show for customizing contact usage.
	function get_radio_buttons($tld, $type, $type_info, $dm_plan_id) {
		$buttons = array();
		// Count errors by TLD
		// This is needed in order to determine if we need to show
		// "Apply to TLD" and "Apply to All" buttons.
		$tld_error = array();
		$ctype_error = array();
		if(is_array($_SESSION['contact_error']) && is_array($_SESSION['contact_error'][$dm_plan_id])) {
			foreach($_SESSION['contact_error'][$dm_plan_id] as $domain => $error_info) {
				$c_tld = get_domain_tld($domain);
				if(!$tld_error[$c_tld]) {
					$tld_error[$c_tld] = array();
				}
				foreach($error_info as $c_type => $error) {
					if(!$ctype_error[$c_type]) {
						$ctype_error[$c_type] = array();
					}
					if(!$tld_error[$c_tld][$c_type]) {
						$tld_error[$c_tld][$c_type] = 0;
					}
					if(!$ctype_error[$c_type][$c_tld]) {
						$ctype_error[$c_type][$c_tld] = 0;
					}
					$tld_error[$c_tld][$c_type] += 1;
					$ctype_error[$c_type][$c_tld] += 1;
				}
			}
		}

		//	1. If there are more then one invalid type in TLD,
		//	show "Apply to all contact types for TLD domains".
		if($tld_error[$tld] && count(array_keys($tld_error[$tld])) > 1) {
			$buttons['tld_all'] = sprintf($this->string('APPLY_TO_ALL_IN_TLD'), '.'.$tld);
		}

		//	2. If there is more than one invalid contact of current type, in current tld,
		//	show "Apply to all TLD domains, where TYPE contact type is invalid"
		if($tld_error[$tld] && $tld_error[$tld][$type] && $tld_error[$tld][$type] > 1) {
			$buttons['tld_c'] = sprintf($this->string('APPLY_TO_ALL_DOMAINS_IN_TLD'), '.'.$tld, "'".$type_info['title']."'");
		}

		//	3. If there are more than one TLD, where this domain type is invalid,
		//	show "Apply to all TYPE contact types in all domains where it is invalid" radio.
		if($ctype_error[$type] && count(array_keys($ctype_error[$type])) > 1) {
			$buttons['type_all'] = sprintf($this->string('APPLY_TO_ALL_IN_TYPE'), "'".$type_info['title']."'");
		}

		//	4. If there is another TLD with another contact type invalid,
		//	show "Apply to all invalid contacts" button
		if(
			$tld_error[$tld] && count(array_keys($tld_error)) > 1 &&
			$ctype_error[$type] && count(array_keys($ctype_error)) > 1
		) {
			$buttons['all'] = $this->string('APPLY_TO_EVERYTHING');
		}

		##	5. Apply to current contact only
		##	show "Apply to current contact only"
		if(count(array_keys($buttons))) {
			$buttons['none'] = $this->string('APPLY_TO_CURRENT_ONLY');
		}

		return $buttons;
	}

	public function rollback_step() {
		return 0;
	}

	function refill_domain_contacts($new_id, $dm_plan_id, $tld = null, $contact_type = null) {
		foreach($_SESSION['contact_error'][$dm_plan_id] as $domain => $error_info) {
			if(!$_SESSION['domains'][$dm_plan_id][$domain]) {
				continue;
			}
			if(null == $tld || preg_match("/".$tld."$/", $domain)) {
				foreach($_SESSION['contact_error'][$dm_plan_id][$domain] as $ctype => $error) {
					if(null == $contact_type || $ctype == $contact_type) {
						$_SESSION['domains'][$dm_plan_id][$domain]['contacts'][$ctype] = $new_id;
					}
				}
			}
		}
	}

	function get_dmcontact_title_by_type($domain, $ctype, $dm_plan_id) {
		$title = null;
		if(is_array($_SESSION['domains'][$dm_plan_id]) && in_array($domain, array_keys($_SESSION['domains'][$dm_plan_id]))) {
			foreach($_SESSION['domains'][$dm_plan_id][$domain]['contact_types'] as $c) {
				if($c['type'] == $ctype) {
					$title = $c['title'];
					break;
				}
			}
		}
		return $title;
	}

	function get_domain_contact_info($domain, $type, $dm_plan_id) {
		foreach($_SESSION['domains'][$dm_plan_id][$domain]['contact_types'] as $c_type) {
			if($c_type['type'] == $type) {
				return $c_type;
			}
		}
	}

}