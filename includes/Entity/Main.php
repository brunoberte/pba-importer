<?php
//////////
// Common methods
//
// $Id: Main.php 890524 2013-06-26 09:11:08Z dkolvakh $
//////////

namespace Entity;

use Entity\Domains;
use Entity\Account;
use Entity\Base;

class Main extends Base {

	function process_step($action) {
		switch($action) {
		case 'preselect':
## TODO: adopt for new store design
			## pass a set of pre-defined parameters to store
			$_SESSION['ps'] = array();
			$_SESSION['ps']['domains'] = array();
			$_SESSION['ps']['domains']['domain_name'] = param('ps_domain_name');
			$_SESSION['ps']['domains']['domain_selection_type'] = param('ps_domain_selection_type');
			$_SESSION['ps']['domains']['dm_action'] = param('ps_dm_action');
			$_SESSION['ps']['domains']['period'] = param('ps_dm_period');
			$_SESSION['ps']['domains']['tld'] = param('ps_tld');
			$_SESSION['ps']['domains']['domain_names'] = param('ps_domain_names');
			$_SESSION['ps']['domains']['skip_domains'] = param('ps_skip_domains');	## Obsoleted
			if($_SESSION['ps']['domains']['skip_domains']) {
				$_SESSION['ps']['action'] = 'skip_domains';
			} else if($_SESSION['ps']['domains']['domain_name'] || $_SESSION['ps']['domains']['domain_names']) {
				$_SESSION['ps']['action'] = 'check_domains';
			} else {
				unset($_SESSION['ps']['action']);
				unset($_SESSION['ps']['domains']);
			}
			$_SESSION['ps']['step'] = 'domains';
			$_SESSION['ps']['package']['series_key'] = param('ps_series_key');
			$_SESSION['ps']['package']['period'] = param('ps_period');
			$_SESSION['ps']['package']['for_trial'] = param('ps_for_trial');
			if(param('ps_lang_id')) {
				open_backend_session(param('ps_lang_id'));
			}
			if($_SESSION['ps']['package']['series_key']) {
				load_domain_package();
				if($_SESSION['ps']['package']['series_key'] == $_SESSION['domain_package']['series_key']) {
					$_SESSION['package'] = true;
				} else {
					load_package($_SESSION['ps']['package']['series_key'],
						$_SESSION['campaign']['promo_id'],
						$_SESSION['account']['account_id'],
						$_SESSION['ps']['package']['period'],
						$_SESSION['ps']['package']['for_trial']
					);
				}
				unset($_SESSION['ps']['package']);
			}
			if(param('ps_redirect_step') && in_array(param('ps_redirect_step'), Array('index', 'domains', 'plans'))) {
				$_SESSION['ps']['step'] = param('ps_redirect_step');
			}
			redirect($_SESSION['ps']['step'].'.php');
			break;

		case 'cancel_order':
			$this->cancel_order();
			$this->error->add(MC_SUCCESS, 'ORDER_HAS_BEEN_CANCELLED');
			teaser_step();
		break;

		case 'change_language':
			if(!xss_safe($_GET['lang_id'])) {
				## XSS defence
				redirect('index.php');
			}
			## old-style redirect, used on every page of store to change language and get back
			open_backend_session($lang_id = $_GET['lang_id']);
			redirect($_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'index.php');

		break;

		case 'view_all_products':
			if(!is_array($_SESSION['domains'])) {
				unset($_SESSION['domain_package']);
				unset($_SESSION['check_domains_cache']);
			}
			$redirect_params = array();
			if($_GET['type_id']) {
				$redirect_params[] = 'type_id='.preg_replace("/\D/", "", $_GET['type_id']);
			}
			if(array_key_exists('category_id', $_GET)) {
				$redirect_params[] = 'category_id='.preg_replace("/\D/", "", $_GET['category_id']);
			}
			unset($_SESSION['sb_node']);
			redirect('plans.php'.(count($redirect_params) ? '?' : '').implode('&', $redirect_params));
			break;

		}
	}

	public function cancel_order() {
		unset($_SESSION['shopping_cart']);
		unset($_SESSION['ps']);
		unset($_SESSION['package']);
		unset($_SESSION['package_period_id']);
		unset($_SESSION['addons']);
		unset($_SESSION['domains']);
		unset($_SESSION['ssl']);
		unset($_SESSION['ssl_error']);
		unset($_SESSION['check_domains_cache']);
		unset($_SESSION['promotions']);
		unset($_SESSION['configuration']);
		unset($_SESSION['contact_error']);
		unset($_SESSION['domain_contacts']);
		unset($_SESSION['reload_step']);
		unset($_SESSION['domain_data_full']);
		unset($_SESSION['accounting_data']);
		unset($_SESSION['order']);
		unset($_SESSION['sb_sid']);
		unset($_SESSION['sb_node']);
	}

	function get_initial_data($reset_cart = true) {
		if($reset_cart) {
			$this->cancel_order();
		}

		if(!$_SESSION['provider_config']['store']['is_opened']) {
			$this->error->add(MC_WARN, 'STORE_CLOSED');
		} else {
			$plans_to_show = get_sellable_plan_list(
				NULL,
				NULL,
				NULL	##$_SESSION['account']['account_id']	to speed up plan loading
			);

			## load domain packages
			$domains = new Domains($this->translator);

			if(!$_SESSION['domain_package']['default'] && !(is_array($plans_to_show) && count($plans_to_show))) {
				$this->error->add(MC_WARN, 'STORE_CLOSED');
			}
		}

		unset($_SESSION['_groups'], $_SESSION['_plans'], $_SESSION['plan_names']);
		$plan_grouping_by_cat = $plan_grouping_by_type = array();

		$plans_to_show = $plans_to_show['plan_list'];
		if(is_array($plans_to_show)) {
			$this->get_extended_attr_list();
			if($plans_to_show['hps'] || $plans_to_show['groups']) {
				array_push(
					$plans_to_show['categories'],
					array(
						id     => 'more_services',
						name   => $this->string('MORE_SERVICES'),
						description => $this->string('MORE_SERVICES_DESC'),
						summary => $this->string('MORE_SERVICES_SUMMARY'),
						groups => $plans_to_show['groups'],
						hps    => $plans_to_show['hps']
					)
				);
			}

			foreach($plans_to_show['categories'] as $category) {
				$plan_grouping_by_cat[$category['id']] = $category;
				foreach($category['hps'] as $plan) {
					if(hp_for_reseller_only($plan)) {
						continue;
					}
					$id = '_plans_' . $plan['series_key'];
					$plan_grouping_by_cat[$category['id']]['items'][$id] = $plan;
					$plan_grouping_by_cat[$category['id']]['items'][$id]['id'] = $plan['series_key'];
					$plan_grouping_by_cat[$category['id']]['items'][$id]['plans'][] = $plan;
					$_SESSION['_plans'][$plan['series_key']][$plan['series_key']] = $plan['series_key'];
					$_SESSION['plan_names'][$plan['series_key']]['group_name']['_plans_'.$plan['series_key']] = $plan['name'];
					$_SESSION['plan_names'][$plan['series_key']]['plan_name'] = $plan['name'];
				}
				foreach($category['groups'] as $group) {
					$id = '_groups_' . $group['id'];
					$plan_grouping_by_cat[$category['id']]['items'][$id] = $group;
					foreach($group['hps'] as $plan) {
						if(hp_for_reseller_only($plan)) {
							continue;
						}
						$plan_grouping_by_cat[$category['id']]['items'][$id]['type'] = $plan['type'];
						$plan_grouping_by_cat[$category['id']]['items'][$id]['plans'][] = $plan;
						$_SESSION['_groups'][$group['id']][$plan['series_key']] = $plan['series_key'];
						$_SESSION['plan_names'][$plan['series_key']]['plan_name'] = $plan['name'];
						$_SESSION['plan_names'][$plan['series_key']]['group_name']['_groups_'.$group['id']] = $group['name'];
					}
					if(!isset($plan_grouping_by_cat[$category['id']]['items'][$id]['plans']) || !count($plan_grouping_by_cat[$category['id']]['items'][$id]['plans'])) {
						unset($plan_grouping_by_cat[$category['id']]['items'][$id]);
					}
				}
				if(!isset($plan_grouping_by_cat[$category['id']]['items']) || !count($plan_grouping_by_cat[$category['id']]['items'])) {
					unset($plan_grouping_by_cat[$category['id']]);
				}
			}
		}

		return $plan_grouping_by_cat;
	}

	function get_content($type, $id, $plan_id) {
		sw_log('type => '.$type.'; id => '.$id.'; plan_id => '.$plan_id);
		## reset stored plans, load only needed ones
		$for_trial = $_SESSION['shopping_cart']['period'] === 'trial' ? 1 : 0;
		$desired_plan_id = null;
		if($plan_id !== 'undefined') {
			$desired_plan_id = $plan_id;
		} else if(
			array_key_exists('shopping_cart', $_SESSION) && isset($_SESSION['shopping_cart']['plan_id']) &&
			$_SESSION['shopping_cart']['plan_id'] !== 'domains'
		) {
			## get plan from shopping cart, if domains wasn't checked before
			$desired_plan_id = $_SESSION['shopping_cart']['plan_id'];
		}
		if($desired_plan_id == null) {
			## when initial loading, forget about previously loaded plans
			$_SESSION['plans'] = array();
		}
		foreach($_SESSION[$type][$id] as $series_key) {
			if($desired_plan_id == null) {
				## get first from group
				$desired_plan_id = $series_key;
			}
		}
		$package_period_id = $_SESSION['package_period_id'];
		## this handler will reset trial period in shopping cart, if set, on API error
		install_error_handler('HPNoTrial', "handle_load_plan_error");
		load_package(
			$desired_plan_id,
			NULL,
			$_SESSION['account']['account_id'],
			$for_trial ? NULL : $_SESSION['shopping_cart']['period'],
			$for_trial
		);
		if(get_api_transport()->fault) {
			## try to load package for non-trial period
			load_package(
				$desired_plan_id,
				NULL,
				$_SESSION['account']['account_id'],
				NULL,
				0
			);
		}
		if(!$for_trial && $_SESSION['shopping_cart']['period']) {
			## check that plan has been requested for existing period for it
			## this is important while calculating promotions
			$period_found = 0;
			foreach($_SESSION['plans'][$desired_plan_id]['fee_list'] as $fee) {
				if($fee['period'] == $_SESSION['shopping_cart']['period']) {
					$period_found = 1;
					break;
				}
			}
			if(!$period_found) {
				## previously selected period from cart has not been found for HP,
				## reload plan for first available period for it
				load_package(
					$desired_plan_id,
					NULL,
					$_SESSION['account']['account_id'],
					NULL,
					0
				);
			}
		}

		if(is_array($_SESSION['plans'])) {
			$domains = new Domains($this->translator);
			foreach($_SESSION['plans'] as &$plan) {
				foreach($plan['fee_list'] as &$fee) {
					$fee['info'] = format_period($fee['period'], $this->translator);
				}
				$domains->load_domain_package(
					(isset($plan['assigned_dm_plan']) && $plan['assigned_dm_plan']) ?
						$plan['assigned_dm_plan'] : ''
				);
			}
			if($package_period_id !== $_SESSION['package_period_id']) {
				## package period changed, need to update shopping cart items with new prices
				$this->update_shopping_cart_prices($desired_plan_id);
			}
		} else {
			$_SESSION['plans'] = array();
		}

		if(count($_SESSION['plans'])) {
			return array($desired_plan_id => $_SESSION['plans'][$desired_plan_id]);
		} else {
			return array();
		}
	}

	function update_shopping_cart_prices($plan_id) {
		sw_log_debug('plan_id => '.$plan_id);
		## shortcuts
		$plan = $_SESSION['plans'][$plan_id];
		$cart = $_SESSION['shopping_cart'][$plan_id];
		####
		## Resources
		if(isset($cart['configuration']['qos_list']) && count($cart['configuration']['qos_list'])) {
			foreach($plan['qos_list'] as $qos) {
				foreach($cart['configuration']['qos_list'] as $res_id => $res) {
					if($res['short_name'] == $qos['short_name'] && $res['platform_id'] == $qos['platform_id']) {
						$_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$res_id]['overuse_rate'] = $qos['overuse_rate'];
						break;
					}
				}
			}
		}
		####
		## Panel, applications, custom attributes
		if(isset($cart['addons'])) {
			if(isset($cart['addons']['panels'])) {
				foreach($cart['addons']['panels'] as $os_tmpl => $value) {
					foreach($plan['app_list'] as $app) {
						if($app['id'] == $cart['addons']['panels'][$os_tmpl]['id']) {
							$_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$os_tmpl]['setup_fee'] = $app['setup_fee'];
							$_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$os_tmpl]['subscr_fee'] = $app['subscr_fee'];
							break;
						}
					}
				}
			}
			if(isset($cart['addons']['app_list'])) {
				foreach($cart['addons']['app_list'] as $os_tmpl => $value) {
					if(isset($cart['addons']['app_list'][$os_tmpl]) && count($cart['addons']['app_list'][$os_tmpl])) {
						foreach($cart['addons']['app_list'][$os_tmpl] as $panel_id => $apps) {
							if(count($apps)) {
								foreach($plan['app_list'] as $app) {
									foreach($apps as $app_id => $value2) {
										if($app['id'] == $app_id) {
											$_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel_id][$app_id]['setup_fee'] = $app['setup_fee'];
											$_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel_id][$app_id]['subscr_fee'] = $app['subscr_fee'];
											break;
										}
									}
								}
							}
						}
					}
				}
			}
			if(isset($cart['addons']['custom_attributes']) && count($cart['addons']['custom_attributes'])) {
				foreach($plan['custom_attribute_list'] as $attr) {
					foreach($attr['option_list'] as $option) {
						if(array_key_exists($option['id'], $cart['addons']['custom_attributes'])) {
							$_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$option['id']]['setup_fee'] = $option['setup_fee'];
							$_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$option['id']]['subscr_fee'] = $option['subscr_fee'];
							$_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$option['id']]['upgrade_fee'] = $option['upgrade_fee'];
						}
					}
				}
			}
		}
	}

	function get_resources($use_cache = false) {
		if($use_cache && isset($_SESSION['resources']) && is_array($_SESSION['resources'])) {
			return $_SESSION['resources'];
		}
		$resources = array();
		$applications = array();
		$os_templates = array();
		$licenses = array();
		$attributes = array();
		unset($_SESSION['resources'],$_SESSION['applications'],$_SESSION['os_templates'],$_SESSION['licenses'],$_SESSION['custom_attributes']);
		foreach($_SESSION['plans'] as $plan) {
			// arrayify arrays
			if(!array_key_exists($plan['series_key'], $os_templates)) { $os_templates[$plan['series_key']] = array(); }
			if(!array_key_exists($plan['series_key'], $resources)) { $resources[$plan['series_key']] = array(); }
			if(!array_key_exists($plan['series_key'], $applications)) { $applications[$plan['series_key']] = array(); }
			if(!array_key_exists($plan['series_key'], $licenses)) { $licenses[$plan['series_key']] = array(); }
			if(!array_key_exists($plan['series_key'], $attributes)) { $attributes[$plan['series_key']] = array(); }

			if(is_array($plan['os_tmpls'])){
				foreach($plan['os_tmpls'] as $os_template) {
					if(!in_array($os_template, $os_templates[$plan['series_key']])) {
						$os_templates[$plan['series_key']][] = $os_template;
					}
				}
			}
			if(is_array($plan['qos_list'])) {
				foreach($plan['qos_list'] as $resource) {
					## filter out unlimited and non-changeable resources
					if(
						$resource['is_unlim'] ||
						$resource['incl_amount'] == $resource['max_amount']
					) {
						continue;
					}
					$short_name = $resource['short_name'];
					if(!array_key_exists($short_name, $resources[$plan['series_key']])) {
						$resources[$plan['series_key']][$short_name] = $resource;
						if(
							is_array($_SESSION['shopping_cart'][$plan['series_key']]['configuration']['qos_list']) &&
							isset($_SESSION['shopping_cart'][$plan['series_key']]['configuration']['qos_list'][$resource['id']])
						) {
							$resources[$plan['series_key']][$short_name]['value'] = $_SESSION['shopping_cart'][$plan['series_key']]['configuration']['qos_list'][$resource['id']]['value'];
						} else {
							$resources[$plan['series_key']][$short_name]['value'] = $resource['incl_amount'];
						}
					}
				}
			}
			if(is_array($plan['app_list'])) {
				$app_cp_map = array();
				foreach($plan['app_list'] as $application) {
					if(trim($application['category_name']) === 'Control Panels' && count($application['applications'])) {
						foreach($application['applications'] as $depend_app) {
							$app_cp_map[$depend_app['package']][] = $application['id'];
						}
					}
				}
				foreach($plan['app_list'] as $application) {
					if(!in_array($application, $applications[$plan['series_key']])) {
						$applications[$plan['series_key']][] = $application;
						if($application['is_included'] == 1) {
							if(trim($application['category_name']) === 'Control Panels') {
								 ## Put into cart only first included CP, per OS tmpl
								 ## Several included control panels per OS is some kind of misconfig, currently we do not support this
								 ## Possibly, in this case all the rest included CP's should became regular applications
								 if(!isset($_SESSION['shopping_cart'][$plan['series_key']]['addons']['panels'][$application['os_tmpl']])) {
									$_SESSION['shopping_cart'][$plan['series_key']]['addons']['panels'][$application['os_tmpl']] = $application;
								}
							} else {
								$panel_ids = array();
								if(array_key_exists($application['package'], $app_cp_map)) {
									$panel_ids = $app_cp_map[$application['package']];
								} else {
									$panel_ids[] = 'none';
								}
								foreach($panel_ids as $panel_id) {
									$os_tmpl = $application['os_tmpl'] ? $application['os_tmpl'] : 0;
									$_SESSION['shopping_cart'][$plan['series_key']]['addons']['app_list'][$os_tmpl][$panel_id][$application['id']] = $application;
								}
							}
						}
						## if Sitebuilder site alias passed, find and add WACP and Site to shopping cart on first page load
						if(
							isset($_SESSION['sb_sid']) && $_SESSION['sb_sid'] &&
							!isset($_SESSION['shopping_cart'][$plan['series_key']]['addons']['sitebuilder']) &&
							$application['id'] == APP_WACP
						) {
							sw_log_debug('Sitebuilder Site passed, WACP found in CT HP, add WACP and Site to cart');
							$_SESSION['shopping_cart'][$plan['series_key']]['addons']['sitebuilder']['sb_sid'] = $_SESSION['sb_sid'];
							$_SESSION['shopping_cart'][$plan['series_key']]['addons']['sitebuilder']['value'] = 1;
							if(trim($application['category_name']) === 'Control Panels') {
								$_SESSION['shopping_cart'][$plan['series_key']]['addons']['panels'][$application['os_tmpl']] = $application;
							} else {
								$panel_ids = array();
								if(array_key_exists($application['package'], $app_cp_map)) {
									$panel_ids = $app_cp_map[$application['package']];
								} else {
									$panel_ids[] = 'none';
								}
								foreach($panel_ids as $panel_id) {
									$os_tmpl = $application['os_tmpl'] ? $application['os_tmpl'] : 0;
									$_SESSION['shopping_cart'][$plan['series_key']]['addons']['app_list'][$os_tmpl][$panel_id][$application['id']] = $application;
								}
							}
						}
					}
				}
			}
			## if Sitebuilder site alias passed, and not a CT HP, add Site to shopping cart on first page load
			if(
				isset($_SESSION['sb_sid']) && $_SESSION['sb_sid'] &&
				$plan['type']['id'] != HP_TYPE_VPS &&
				!isset($_SESSION['shopping_cart'][$plan['series_key']]['addons']['sitebuilder'])
			) {
				sw_log_debug('Sitebuilder Site passed, not CT HP, add Site to cart');
				$_SESSION['shopping_cart'][$plan['series_key']]['addons']['sitebuilder']['sb_sid'] = $_SESSION['sb_sid'];
				$_SESSION['shopping_cart'][$plan['series_key']]['addons']['sitebuilder']['value'] = 1;
			}
			if(is_array($plan['license_list'])) {
				foreach($plan['license_list'] as $license) {
					if(!in_array($license, $licenses[$plan['series_key']])) {
						$licenses[$plan['series_key']][] = $license;
					}
				}
				$_SESSION['licenses'] = $licenses;
				if(!is_array($_SESSION['shopping_cart'][$plan['series_key']]['addons']['license_list'])) {
					$this->update_license($plan['series_key'], 1);
				}
			}
			if(is_array($plan['custom_attribute_list'])) {
				$is_set = is_array($_SESSION['shopping_cart'][$plan['series_key']]['addons']['custom_attributes']);
				foreach($plan['custom_attribute_list'] as $attribute) {
					if(!in_array($attribute, $attributes[$plan['series_key']])) {
						$attributes[$plan['series_key']][] = $attribute;
						if(!$is_set) {
							## make defaults selection
							foreach($attribute['option_list'] as $option) {
								if($option['is_included']) {
									$_SESSION['shopping_cart'][$plan['series_key']]['addons']['custom_attributes'][$option['id']] = $option;
								}
								if($option['is_default']) {
									$_SESSION['shopping_cart'][$plan['series_key']]['addons']['custom_attributes'][$option['id']] = $option;
								}
							}
						}
					}
				}
			}
		}
		$_SESSION['resources'] = $resources;
		$_SESSION['applications'] = $applications;
		$_SESSION['os_templates'] = $os_templates;
		$_SESSION['attributes'] = $attributes;
		return $resources;
	}

	function update_resource($plan_id, $short_name, $value) {
		if(is_array($_SESSION['resources'][$plan_id])){
			foreach($_SESSION['plans'][$plan_id]['qos_list'] as $resource) {
				if($resource['short_name'] === $short_name) {
					if($resource['incl_amount'] >= $value) {
						unset($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$resource['id']]);
					} else {
						$_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$resource['id']] = $resource;
						$_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$resource['id']]['value'] = $value;
					}
				}
			}
		}
	}

	function update_shopping_cart($plan_id, $group_id, $os_tmpl_id, $period, $platform) {
		if(hp_for_reseller_only($_SESSION['plans'][$plan_id], true)) {
			sw_log_warn('Attempting to order HP destined for resellers only #'.$plan_id.' under customer account');
			return 0;
		}
		$_SESSION['shopping_cart']['plan_id'] = $plan_id;
		$_SESSION['shopping_cart']['os_tmpl'] = $os_tmpl_id === 'undefined' ? 0 : $os_tmpl_id;
		$old_period = $_SESSION['shopping_cart']['period'];
		$_SESSION['shopping_cart']['period'] = $period;
		$_SESSION['shopping_cart']['platform'] = $platform === 'undefined' ? 0 : $platform;
		$_SESSION['shopping_cart']['group_id'] = $group_id === 'undefined' ? 0 : $group_id;
		$_SESSION['shopping_cart']['group_name'] = $_SESSION['plan_names'][$plan_id]['group_name'][$_SESSION['shopping_cart']['group_id']];
		$_SESSION['shopping_cart']['plan_name'] = $_SESSION['plan_names'][$plan_id]['plan_name'];
		if(($old_period == 'trial' || $period == 'trial') && $old_period != $period) {
			## when switch to/from trial period, reload domains list
			get_domain_list();
		}
		return 1;
	}

	function clear_shopping_cart($plan_id = NULL) {
		if($plan_id) {
			unset($_SESSION['shopping_cart'][$plan_id]);
		} else {
			unset($_SESSION['shopping_cart']);
		}
		unset($_SESSION['domains']);
		unset($_SESSION['check_domains_cache']);
		return;
	}

	function update_application($plan_id, $panel_id, $app_id, $enable) {
		foreach($_SESSION['applications'][$plan_id] as $application) {
			if($application['id'] == $app_id) {
				$os_tmpl = $application['os_tmpl'] ? $application['os_tmpl'] : 0;
				if($enable) {
					$_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel_id][$application['id']] = $application;
				} else {
					unset($_SESSION['shopping_cart'][$plan_id]['addons']['app_list'][$os_tmpl][$panel_id][$application['id']]);
				}
			}
		}
	}

	function update_panel($plan_id, $panel_id, $os_tmpl) {
		if($panel_id != 'none') {
			foreach($_SESSION['applications'][$plan_id] as $application) {
				if($application['id'] == $panel_id) {
					unset($_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$application['os_tmpl']]);
					$_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$application['os_tmpl']] = $application;
				}
			}
		} else {
			$_SESSION['shopping_cart'][$plan_id]['addons']['panels'][$os_tmpl] = array('id' => 'none');
		}
	}

	function update_license($plan_id, $included = 0) {
		foreach($_SESSION['licenses'][$plan_id] AS $license_id => $license) {
			$input_basename = escape_license_product('plan_licenses_' . $plan_id . '_base_license___'.$license['plugin_id'].'___'.$license['product_id']);
			$checked = false;
			if($included) {
				$checked = $license['is_included'];
			} else {
				$checked = $this->translator->getParamVal($input_basename, '_POST');
			}
			if(!$checked) {
				unset($_SESSION['shopping_cart'][$plan_id]['addons']['license_list'][$license_id]);
				continue;
			}
			$_SESSION['shopping_cart'][$plan_id]['addons']['license_list'][$license_id] = $license;
			## may be empty arrays there
			$_SESSION['shopping_cart'][$plan_id]['addons']['license_list'][$license_id]['feature_list'] = $this->get_license_data($license, $input_basename, $included);

			if($license['addon_list'] && count($license['addon_list'])) {
				foreach($license['addon_list'] as $addon_id => $addon) {
					$input_basename = escape_license_product('plan_licenses_' . $plan_id . '_base_license___'.$license['plugin_id'].'___'.$license['product_id'].'___'.$addon['product_id']);
					$checked = false;
					if($included) {
						$checked = $addon['is_included'];
					} else {
						$checked = $this->translator->getParamVal($input_basename, '_POST');
					}
					if(!$checked) {
						unset($_SESSION['shopping_cart'][$plan_id]['addons']['license_list'][$license_id]['addon_list'][$addon_id]);
						continue;
					}
					$_SESSION['shopping_cart'][$plan_id]['addons']['license_list'][$license_id]['addon_list'][$addon_id] = $addon;
					## may be empty arrays there
					$_SESSION['shopping_cart'][$plan_id]['addons']['license_list'][$license_id]['addon_list'][$addon_id]['feature_list'] = $this->get_license_data($addon, $input_basename, $included);
				}
			}
		}
	}

	function get_license_data($license, $input_basename, $included) {
		$set = array();
		if($license['feature_list']) {
			if($license['feature_list']['groups']) {
				$set['groups'] = array();
				foreach($license['feature_list']['groups'] as $group_id => $group) {
					$group_input_basename = escape_license_product($input_basename.'___groups___'.$group_id);
					$checked = false;
					if($included) {
						$checked = true;
					} else {
						$checked = $this->translator->getParamVal($group_input_basename, '_POST');
					}
					if($checked) {
						$set['groups'][$group_id] = $group;
						$set['groups'][$group_id]['options'] = array();
						foreach($group['options'] as $option_id => $option) {
							if($option['id'] == $this->translator->getParamVal($group_input_basename, '_POST') || ($included && $option['is_default'])) {
								$set['groups'][$group_id]['options'][$option_id] = $option;
							}
						}
						## add first option, if group is required but nothing chosen
						if($group['is_required'] && !count($set['groups'][$group_id]['options']) && count($group['options'])) {
							foreach($group['options'] as $option_id => $option) {
								$set['groups'][$group_id]['options'][$option_id] = $option;
								break;
							}
						}
					}
				}
			}
			if($license['feature_list']['standalone']) {
				$set['standalone'] = array();
				foreach($license['feature_list']['standalone'] as $standalone_id => $standalone) {
					$standalone_input_basename = escape_license_product($input_basename.'___standalone___'.$standalone['id']);
					$checked = false;
					if($included) {
						$checked = $standalone['is_included'];
					} else {
						$checked = $this->translator->getParamVal($standalone_input_basename, '_POST');
					}
					if($checked) {
						$set['standalone'][$standalone_id] = $standalone;
					}
				}
			}
		}
		return $set;
	}

	function update_attribute($plan_id, $input_id, $enable, $value) {
		foreach($_SESSION['attributes'][$plan_id] AS $custom_attribute) {
			if($value === 'none' && $custom_attribute['is_exclusive'] && $input_id === "plan_".$plan_id."_custom_attribute_".$custom_attribute['id']) {
				foreach($custom_attribute['option_list'] AS $inner_option) {
					unset($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$inner_option['id']]);
				}
			} else if($custom_attribute['is_exclusive']) {
				foreach($custom_attribute['option_list'] AS $option) {
					if($option['id'] == $value) {
						foreach($custom_attribute['option_list'] AS $inner_option) {
							unset($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$inner_option['id']]);
						}
						$_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$option['id']] = $option;
					}
				}
			} else {
				foreach($custom_attribute['option_list'] AS $option) {
					if($input_id === "plan_".$plan_id."_custom_attribute_option_".$option['id']) {
						if($enable) {
							$_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$option['id']] = $option;
						} else {
							unset($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$option['id']]);
						}
					}
				}
			}
		}
	}

	function configure($plan_id, $domains_obj) {
		sw_log_debug('plan_id => '.$plan_id);
		unset($_SESSION['checkout_results']);
		unset($_SESSION['is_order_payed']);
		unset($_SESSION['order_calc']);

		$res = array(
			'result' => 'error',
			'fields' => array(),
			'valid' => array(),
			'id' => 'configuration'
		);

		## put chosen plan into session
		## possibly just temporary workaround, m.b. chosen plan should be placed in session upon choose
		## or even change everywhere to use $plan_id instead of 'package' to get chosen plan
		if($plan_id == 'domains') {
			$_SESSION['package'] = $_SESSION['domain_package']['default'];
		} else {
			$_SESSION['package'] = $_SESSION['plans'][$plan_id];
		}
		$dm_plan_id = $_SESSION['package']['assigned_dm_plan'];

		if(
			in_array(
				$_SESSION['plans'][$plan_id]['type']['id'],
				array(
					HP_TYPE_VPS, HP_TYPE_PLESK_VIRTUAL_NODE,
					HP_TYPE_PLESK_DOMAIN, HP_TYPE_PLESK_CLIENT,
					HP_TYPE_PSVM
				)
			)
			||
			(
				$_SESSION['package']['type']['id'] == HP_TYPE_POA &&
				is_array($_SESSION['package']['dns_hosting'])
			)
		) {
			$_SESSION['configuration']['hostname_type'] = $this->translator->getParamVal('hostname_type', '_POST');
			$_SESSION['configuration']['domain_hostname'] = $this->translator->getParamVal('domain_hostname', '_POST');
			$_SESSION['configuration']['subdomain_hostname'] = $this->translator->getParamVal('subdomain_hostname', '_POST');
			$_SESSION['configuration']['subdomain'] = $this->translator->getParamVal('subdomain', '_POST');
			if(!xss_safe($_POST['subdomain'])) {
				## XSS defence
				$this->error->add(MC_ERROR, null, sprintf($this->string('DOMAIN_NAME_INVALID'), htmlspecialchars($_POST['subdomain'])));
				$res['fields'][] = 'subdomain';
				return $res;
			}

			if($_SESSION['package']['type']['id'] == HP_TYPE_PLESK_DOMAIN && in_array($_SESSION['package']['physical_hosting_type'], array(1, 2))) {
				$pdhostname=(
					$_SESSION['configuration']['hostname_type'] == 'use_subdomain' &&
					$_SESSION['configuration']['subdomain']
				) ?
				$_SESSION['configuration']['subdomain'].'.'.$_SESSION['configuration']['subdomain_hostname']
				:
				$_SESSION['configuration']['domain_hostname'];

				call('validate_plesk_login', array('forward_url' => $this->translator->getParamVal('forward_url', '_POST'), 'hostname' => $pdhostname), 'HSPC/API/HP');
				$transport = get_api_transport();
				if($transport->fault) {
					unset($_SESSION['configuration']['forward_url']);
					$res['fields'][] = 'forward_url';
					if(strpos($transport->faultcode, 'soap:UserPlesk') != 0) {
						$this->error->add(MC_ERROR, 'FORWARDING_URL_INCORRECT');
					}
					return $res;
				} else {
					$_SESSION['configuration']['forward_url'] = $this->translator->getParamVal('forward_url', '_POST');
				}
			}

		}

		$password_source = $this->translator->getParamVal('password_source', '_POST');
		if($password_source == 'enter_new') {
			if($_POST['password'] != $_POST['repassword']) {
				$this->error->add(MC_ERROR, 'PASSWORD_MISTMATCH_TO_RETYPED_PASSWORD');
				$res['fields'][] = 'password';
				$res['fields'][] = 'repassword';
				return $res;
			}
			if($_POST['password'] == '') {
				$this->error->add(MC_ERROR, 'EMPTY_PASSWORD');
				$res['fields'][] = 'password';
				$res['fields'][] = 'repassword';
				return $res;
			}
			## password may contain escapeable symbols
			$_SESSION['credentials']['password'] = $_POST['password'];
			$_SESSION['credentials']['password_source'] = $password_source;
		} elseif($password_source == 'use_account') {
			$_SESSION['credentials']['password'] = $_SESSION['credentials']['account_password'];
			$_SESSION['credentials']['password_source'] = $password_source;
		}


		// Check VPS password strength
		if(
			(
				$_SESSION['package']['is_root_access'] &&
				$_SESSION['package']['type']['id'] == HP_TYPE_VPS
			)
			||
			$_SESSION['package']['type']['id'] ==  HP_TYPE_PSVM
		) {
			call('validate_password', array('password' => $_SESSION['credentials']['password']), 'HSPC/API/Account');
			$transport = get_api_transport();
			if($transport->fault) {
				$res['fields'][] = 'password';
				$res['fields'][] = 'repassword';
				return $res;
			}
		}

		$result = $domains_obj->save_domain_data($dm_plan_id);
		if(!$result) {
			return $res;
		}

		// Check new config values

		// Check hostname
		if(
			in_array(
				$_SESSION['package']['type']['id'],
				array( HP_TYPE_VPS, HP_TYPE_PLESK_VIRTUAL_NODE,
					HP_TYPE_PLESK_DOMAIN, HP_TYPE_PLESK_CLIENT,
					HP_TYPE_PSVM
				)
			)
			||
			(
				$_SESSION['package']['type']['id'] == HP_TYPE_POA &&
				is_array($_SESSION['package']['dns_hosting'])
			)
		) {
			if(!(in_array($_SESSION['package']['type']['id'], array(HP_TYPE_PLESK_CLIENT, HP_TYPE_VPS, HP_TYPE_PLESK_VIRTUAL_NODE, HP_TYPE_PSVM)) && $_SESSION['configuration']['hostname_type'] == 'no_hostname')) {
				$default_hostname = ($_SESSION['configuration']['hostname_type'] == 'use_domain') ?
											$_SESSION['configuration']['domain_hostname'] :
												$_SESSION['configuration']['subdomain'] . '.' .
												$_SESSION['configuration']['subdomain_hostname'];

				if(!$default_hostname || $default_hostname == '.') {
					$this->error->add(MC_ERROR, null, sprintf($this->string('YOU_MUST_SELECT_DOMAIN_WITH_PACKAGE'), $_SESSION['package']['name']));
					return $res;
				}
				$result = call('check_domain_name_syntax', array('domain' => $default_hostname), 'HSPC/API/Domain');
				if(!$result['result']) {
					$res['fields'][] = $_SESSION['configuration']['hostname_type'] == 'use_domain' ? 'domain_hostname' : 'subdomain';
					$this->error->add(MC_ERROR, null, sprintf($this->string('DOMAIN_NAME_INVALID'), $default_hostname));
					return $res;
				}
				else {
					if($_SESSION['configuration']['hostname_type'] == 'use_subdomain') {
						$domains_to_check_sub = array();
						$domains_to_check_sub[] = $default_hostname;
						$available_domains_sub = check_domains(
							$domains_to_check_sub,
							$dm_plan_id,
							'dns_hosting',
							false,
							$this
						);
						if(!in_array($default_hostname, $available_domains_sub)) {
							$res['fields'][] = 'subdomain';
							$this->error->add(MC_ERROR, null, sprintf($this->string('CANT_USE_DOMAIN_AS_HOSTNAME'), $default_hostname));
							return $res;
						}
					}
				}
			}
		}

		## Resources (QoS)
		if(is_array($_SESSION['package']['qos_list']) && is_array($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'])) {
			$error_list = array();
			foreach($_SESSION['package']['qos_list'] as $qos) {
				## filter non-billable resources
				if(!$qos['is_rateable'])
					continue;
				## filter non-increasable resources
				if($qos['incl_amount'] == $qos['max_amount'])
					continue;

				$value = array_key_exists($qos['id'], $_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list']) ?
					$_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$qos['id']]['value'] : NULL;
				if($value == NULL) {
					continue;
				}

				## check value
				if(!preg_match('/^\d+$/', $value) || $value > $qos['max_amount'] || $value < $qos['incl_amount']) {
					if(!isset($error_list[$qos['short_name']])) {
						$res['fields'][] = 'resource_value_'.$qos['short_name'].$plan_id;
					}
					$error_list[$qos['short_name']] = sprintf($this->string('NOT_UNSIGNED_BETWEEN'), $qos['name'], $qos['incl_amount'], $qos['max_amount']);
					continue;
				}
				if($qos['min_upgrade_unit'] > 1 && (($qos['incl_amount'] - $value) % $qos['min_upgrade_unit'])) {
					if(!isset($error_list[$qos['short_name']])) {
						$res['fields'][] = 'resource_value_'.$qos['short_name'].$plan_id;
					}
					$error_list[$qos['short_name']] = sprintf($this->string('CAN_CHANGE_LIM_BY_DIVISIBLE_NUMBER'), $qos['name'], $qos['min_upgrade_unit']);
					continue;
				}

				if($value <= $qos['incl_amount']) {
					unset($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$qos['id']]);
				}
			}
			if(count($error_list)) {
				## for resources error message should be shown in corresponding block, and QoS input fields should be highlighted as well
				$this->error->add(MC_ERROR, null, join("\n", $error_list));
				$res['id'] = 'resources';
				return $res;
			}
			if(!count($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'])) {
				unset($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list']);
			}
		}

		## Custom attributes
		if(is_array($_SESSION['plans'][$plan_id]['custom_attribute_list'])) {
			foreach($_SESSION['plans'][$plan_id]['custom_attribute_list'] AS $custom_attribute) {
				if($custom_attribute['is_required']) {
					$required = true;
					$span_list = array();
					foreach($custom_attribute['option_list'] AS $option) {
						$span_list[] = 'span_plan_'.$plan_id.'_custom_attribute_'.$custom_attribute['id'].'_'.$option['id'];
						if(isset($_SESSION['shopping_cart'][$plan_id]['addons']['custom_attributes'][$option['id']])) {
							$required = false;
						}
					}
					if($required) {
						$this->error->add(MC_ERROR, null, $custom_attribute['is_exclusive'] ?
							sprintf($this->string('YOU_MUST_SELECT_AN_OPTION_OF_ATTRIBUTE'), $custom_attribute['name']) :
							sprintf($this->string('YOU_MUST_SELECT_AT_LEAST_ONE_OPTION_OF_ATTRIBUTE'), $custom_attribute['name'])
						);
						$res['result'] = 'error';
						$res['id'] = 'attributes';
						$res['fields'] = array_merge($res['fields'], $span_list);
					}
					$res['valid'] = array_merge($res['valid'], $span_list);
				}
			}
			if(count($res['fields'])) {
				return $res;
			}
		}

		## Question Answers
		if(is_array($_SESSION['package']['question_list'])) {
			foreach($_SESSION['package']['question_list'] AS $key => $question) {
				$_SESSION['configuration']['answers'][$key] = array($question['id'], $this->translator->getParamVal('answer_'.$question['id'], '_POST'));
			}
		}

		## Check for domain pointer availability
		if(is_array($_SESSION['domains'][$dm_plan_id]) && $_SESSION['account']['account_id']) {
			$domains_to_check = array();
			foreach($_SESSION['domains'][$dm_plan_id] as $key => $value) {
				if($value['dm_action'] != 'domain_pointer')
					continue;
				$domains_to_check[] = $key;
			}
			$series_key = $_SESSION['package']['assigned_dm_plan'] ? $_SESSION['package']['assigned_dm_plan'] : 'default';
			if(count($domains_to_check)) {
				$available_domains = check_domains(
					$domains_to_check,
					$dm_plan_id,
					'domain_pointer',
					false,
					$this
				);
				$unavailable_domains = array();
				foreach($domains_to_check AS $name) {
					if(!in_array($name, $available_domains)) {
						$unavailable_domains[] = $name;
						$_SESSION['check_domains_cache'][$dm_plan_id]['domain_pointer'][$name] = 'unavailable';
					}
				}
				if(count($unavailable_domains)) {
					$this->error->add(MC_ERROR, null, sprintf($this->string('CANT_USE_DOMAINS_AS_POINTER'), implode(', ', $unavailable_domains)));
					return $res;
				}
			}
		}

		if($_SESSION['package']['type']['id'] == HP_TYPE_SSL_SINGLE) {
			unset($_SESSION['ssl_error'][$plan_id]);

			$_SESSION['ssl'][$plan_id] = $_POST;

			## Call API to check data
			$result = call('validate_cert_form', array(
				'hp_sid' => $_SESSION['plans'][$plan_id]['series_key'],
				'form_data' => $_SESSION['ssl'][$plan_id],
				'account_id' => $_SESSION['account']['account_id']
			), 'HSPC/API/SSL');

			if($result) {
				$_SESSION['ssl_error'][$plan_id] = $result;
				$this->error->add(MC_ERROR, null, $this->string('CSR_FIELDS_INVALID'));
			}

			if($this->error->has(MC_ERROR)) {
				return $res;
			}
		}
		else {
			install_error_handler('UserDomainDataError', "handle_domain_data_error");
			if(!validate_domain_data($dm_plan_id)) {
				$domains_obj->get_domain_data_error($dm_plan_id);
				return $res;
			}
		}

		## do additional checks
		return $this->_can_place_order();
	}

	function checkout() {
		$plan_id = $_SESSION['shopping_cart']['plan_id'];

		if(!$this->error->has(MC_ERROR)) {
			$this->error->add(MC_HINT, 'CHECKOUT_HINT_MESSAGE');
		}
		$order_calc = array();
		if(isset($_SESSION['order_calc'])) {
			$order_calc = unserialize($_SESSION['order_calc']);
		}

		if($plan_id === 'domains') {
			load_promotions($_SESSION['domain_package']['default']['series_key'], $_SESSION['account']['account_id']);
		} else {
			load_promotions($plan_id, $_SESSION['account']['account_id']);
		}

		if($_SESSION['plans'][$plan_id]['type']['id'] == HP_TYPE_SSL_SINGLE) {
			## Call API to check data
			$ssl_csr_data = $this->get_parsed_csr_data($plan_id);
		}
		$_SESSION['reload_step'] = 'checkout';

		return array("order_calc" => $order_calc, "ssl_csr_data" => $ssl_csr_data, "plan_id" => $plan_id);
	}

	function get_parsed_csr_data($plan_id) {
		$form_data = count($_POST) && $_POST['ssl_csr_csr'] ? $_POST :
			(isset($_SESSION['ssl']) && isset($_SESSION['ssl'][$plan_id]) && count($_SESSION['ssl'][$plan_id]) ? $_SESSION['ssl'][$plan_id] : array());
		$ssl_csr_data = call('get_parsed_csr_data', array(
			'hp_sid' => $_SESSION['plans'][$plan_id]['series_key'],
			'form_data' => $form_data
		), 'HSPC/API/SSL');
		$transport = get_api_transport();
		if($transport->fault) {
			$ssl_csr_data['parse_error'] = 1;
		} else {
			$_SESSION['ssl'][$plan_id] = $form_data;
		}
		return $ssl_csr_data;
	}

	function get_approver_email_list($plan_id) {
		$domain = count($_POST) && isset($_POST['ssl_csr_data']) ? $_POST['ssl_csr_data']['common_name'] :
			(isset($_SESSION['ssl']) && isset($_SESSION['ssl'][$plan_id]) && count($_SESSION['ssl'][$plan_id]) ? $_SESSION['ssl'][$plan_id]['ssl_csr_data']['common_name'] : '');
		sw_log_debug('plan_id => '.$plan_id.', domain => '.$domain);
		$res = array('result' => 'success', 'approver_list' => array(), 'fields' => array());
		if(!xss_safe($domain)) {
			$this->error->add(MC_ERROR, null, sprintf($this->string('DOMAIN_NAME_INVALID'), htmlspecialchars($domain)));
			$res['result'] = 'error';
			$res['fields'] = array('ssl_csr_data\\[common_name\\]');
			unset($_SESSION['ssl'][$plan_id]['ssl_csr_data']['common_name']);
		} else {
			$approver_list = call('get_approver_email_list', array(
				'hp_sid' => $_SESSION['plans'][$plan_id]['series_key'],
				'domain' => $domain,
			), 'HSPC/API/SSL');
			$res['approver_list'] = $approver_list['approver_list'];
		}
		return $res;
	}

	function get_cert_form($plan_id) {
		$form_data = count($_POST) ? $_POST :
			(isset($_SESSION['ssl']) && isset($_SESSION['ssl'][$plan_id]) && count($_SESSION['ssl'][$plan_id]) ? $_SESSION['ssl'][$plan_id] : array());
		$_SESSION['ssl'][$plan_id] = $form_data;
		return call('get_cert_form', array(
			'hp_sid' => $_SESSION['plans'][$plan_id]['series_key'],
			'form_data' => $form_data,
			'account_id' => $_SESSION['account']['account_id']
		), 'HSPC/API/SSL');
	}

////
// Initiate payment processing using selected payment option
	function pay($plugin_id, $order_id, $paymethod_id) {
		return call('pay',
			array('plugin_id'	 => $plugin_id,
				'order_id'		 => $order_id,
				'paymethod_id'	 => $paymethod_id,
				'form_args'		 => $_POST,
				'fraud_query'	 => $_POST,
				'account_id'	 => $_SESSION['account']['account_id'],
				'initiator_email'=> $_SESSION['person']['email'],
				'initiator_ip'	 => $_SERVER['REMOTE_ADDR']
			),
			'HSPC/API/PP'
		);
	}

	function get_redirect_hash($plugin_id, $locale, $url) {
		return call('get_redirect_hash',
			array(
				'plugin_id' => $plugin_id,
				'order_id' => $_SESSION['order']['id'],
				'url_back' => $url,
			),
			'HSPC/API/PP'
		);
	}

	function payment() {
		$no_errors = load_payment_options();

		if($no_errors) {
			if(!$_SESSION['payment_options'] && !$_SESSION['provider_config']['store']['allow_offline']) {
				$this->error->add(MC_ERROR, 'NO_PAYMENT_OPTIONS_AVAILABLE');
			} else {
				$this->error->add(MC_HINT, 'PAYMENT_HINT_MESSAGE');
			}
		}

		if(is_array($_SESSION['payment_options'])) {
			$i = 0;
			## by default, select first payment plugin with "new" paymethod option
			$_SESSION['active_payment_plugin_id'] = $i;
			$_SESSION['active_paymethod_id'] = 'new';
			## when page loads, will try to activate existing payment method, if available
			## or, if some paymethod previously selected, choose it
			foreach($_SESSION['payment_options'] AS $key => $value) {
				if(isset($_SESSION['payment_plugin_id'])) {
					## if some paymethod was selected already
					if($value['plugin_id'] == $_SESSION['payment_plugin_id']) {
						$_SESSION['active_payment_plugin_id'] = $i;
						$_SESSION['active_paymethod_id'] = $_SESSION['payment_saved_paymethod_id'];
						break 1;
					}
				} else {
					## otherwise, try to find first already existing paymethod
					if(isset($value['saved_paymethods']) && is_array($value['saved_paymethods'])) {
						foreach($value['saved_paymethods'] AS $saved_paymethod) {
							$_SESSION['active_payment_plugin_id'] = $i;
							$_SESSION['active_paymethod_id'] = $saved_paymethod['id'];
							break 2;
						}
					}
				}
				$i++;
			}
		}

		if($_SESSION['order']['doc_balance'] < $_SESSION['order']['doc_total']) {
			$this->error->add(MC_HINT, null, sprintf($this->string('YOUR_ORDER_HAS_BEEN_PARTLY_PAID'), format_price($_SESSION['order']['doc_balance'])));
		}
		return true;
	}

	function confirmation() {
		$_SESSION['account'] = get_account_info($_SESSION['account']['account_id']);

		if($_SESSION['payment_type_action'] === 'pay_offline' && $_SESSION['order']['doc_balance']>0) {
			$_SESSION['payment_process_message'] = '';
			$_SESSION['order_process_message'] = $this->string('YOUR_ORDER_PROC_ONCE_PAYMENT_RECEIVED');
			$actions_title = $this->string('TO_MANAGE_SUBSCR_UNTIL_PROCESSED');
			$_SESSION['reload_step'] = 'confirmation';
		} elseif($_SESSION['shopping_cart']['period'] === 'trial') {
			$newpaymethod_warning_resume = call('get_resume_newpaymethod',
				array('order_id' => $_SESSION['order']['id']),
				'HSPC/API/Fraud');
			$_SESSION['payment_process_message'] = '';
			$_SESSION['order_process_message'] = $this->string('YOUR_ORDER_ACCEPTED_SUBSCR_ACTIVATED_SOON');
			$_SESSION['reload_step'] = 'confirmation';
		} elseif($_SESSION['order']['doc_balance'] == 0) {
			$_SESSION['payment_process_message'] = '';
			$_SESSION['order_process_message'] = $this->string('YOUR_ORDER_ACCEPTED_AND_SCHLD_4_PROCESSING');
			$_SESSION['reload_step'] = 'confirmation';
		} else {
			$newpaymethod_warning_resume = call('get_resume_newpaymethod',
				array('order_id' => $_SESSION['order']['id']),
				'HSPC/API/Fraud');
			$res = $this->get_payment_status();
			$actions_title = $res['actions_title'];
			$back_to_payment = $res['back_to_payment'];
			$confirmation_message = $res['confirmation_message'];
			if(isset($_SESSION['fraud_denied_order'])) {
				$this->error->get(MC_INTERR, true);
				$this->error->add(MC_ERROR, 'ORDER_PLACED_BUT_SUSPENDED_BY_FRAUD_SCREENING');
				unset($confirmation_message);
				unset($_SESSION['payment_process_message']);
				unset($_SESSION['payment_status']);
				unset($_SESSION['order_process_message']);
			}
		}

		$confirmation_message = $confirmation_message ? $confirmation_message :
			sprintf(
				$this->string('THANK_YOU_FOR_CHOOSING'),
				$_SESSION['person']['first_name'].' '.$_SESSION['person']['last_name'], $_SESSION['vendor']['name']
			).' '.$_SESSION['payment_process_message'].' '.$_SESSION['order_process_message'];

		$actions_title = $actions_title ? $actions_title : $this->string('TO_MANAGE_SUBSCR');
		$_SESSION['accounting_data'] = $this->get_accounting_data();

		return array(
			"actions_title" => $actions_title,
			"confirmation_message" => $confirmation_message,
			"newpaymethod_warning_resume" => $newpaymethod_warning_resume,
			"back_to_payment" => $back_to_payment
		);
	}

	function get_accounting_data(){
		return call('get_accounting_data',
			array('account_id' => $_SESSION['account']['account_id'], 'order_id' => $_SESSION['order']['id']),
			'HSPC/API/PP'
		);
	}

	function get_payment_status() {
		$_SESSION['payment_status'] = call('get_status', array('order_id' => $_SESSION['order']['id']), 'HSPC/API/PP');
		sw_log_debug('payment_status => '.$_SESSION['payment_status']);
		switch($_SESSION['payment_status']) {
			case PP_PROC_DECLINED:
				$message = call('get_safe_description', array('order_id' => $_SESSION['order']['id']), 'HSPC/API/Fraud');
				$this->error->add(MC_ERROR, null, $message ? $message : $this->string('ERROR_CHARGING_CARD'));
				$_SESSION['reload_step'] = 'payment';
				$back_to_payment = "yes";
			break;

			case PP_PROC_APPROVED:
				// Check if there's any domains for registration / transfer, to display appropriate message
				if(is_array($_SESSION['archive']['domains']) && count($_SESSION['archive']['domains'])) {
					foreach($_SESSION['archive']['domains'] AS $domain) {
						if($domain['dm_action'] == 'register_new' || $domain['dm_action'] == 'reg_transfer') {
							$domains = true;
						}
					}
				}
				$_SESSION['payment_process_message'] = sprintf($this->string('PAYMENT_METHOD_CHARGED_OK'), $_SESSION['payment_type']);
				$_SESSION['order_process_message'] = $this->string('YOUR_ORDER_ACCEPTED_AND_SCHLD_4_PROCESSING') .
					($domains ? $this->string('WE_WILL_NOTIFY_ONCE_DOMAINS_REGISTERED') : '');
				$_SESSION['reload_step'] = 'confirmation';
			break;

			case PP_PROC_POSTPONED:
				## TODO: use this variable to not miss 3dsecure procedure, also clean up bones when
//  				$_SESSION['force_payment'] = true;
				$_SESSION['payment_process_message'] = $this->string('PAYMENT_PENDING');
				$_SESSION['order_process_message'] = $this->string('ORDER_WILL_BE_PROCESSED_ONCE_PAYMENT_RECEIVED');
				$actions_title = $this->string('TO_MANAGE_SUBSCR_UNTIL_PROCESSED');
				$_SESSION['reload_step'] = 'confirmation';
			break;

			case PP_PROC_UNKNOWN:
				$message = call('get_safe_description',
								array('order_id' => $_SESSION['order']['id']),
								'HSPC/API/Fraud');
				$_SESSION['payment_process_message'] = $_SESSION['payment_process_message']
					? $_SESSION['payment_process_message']
					: ($message ? $message : $this->string('PAYMENT_UNKNOWN'));
				$_SESSION['order_process_message'] = $this->string('ORDER_WILL_BE_PROCESSED_ONCE_PAYMENT_RECEIVED');
				$actions_title = $this->string('TO_MANAGE_SUBSCR_UNTIL_PROCESSED');
				$_SESSION['reload_step'] = 'confirmation';
			break;

			case PP_PROC_3DSECURE:
//  				$_SESSION['force_payment'] = true;
				$redirect_hash = call('get_3dsecure_redirect',
					array(
						'doc_id' => $_SESSION['order']['id'],
						'url_back' => $this->generateUrl('process_payment', array(session_name() => session_id()), true),
					),
					'HSPC/API/PP'
				);
				if($redirect_hash) {
					$redirect_hash['timeout'] = 8000;
					$_SESSION['redirect_hash'] = $redirect_hash;
					$_SESSION['reload_step'] = 'payment';
					$back_to_payment = '3dsecure';
				} else {
					$_SESSION['payment_process_message'] = $this->string('PAYMENT_PENDING');
					$_SESSION['order_process_message'] = $this->string('ORDER_WILL_BE_PROCESSED_ONCE_PAYMENT_RECEIVED');
					$actions_title = $this->string('TO_MANAGE_SUBSCR_UNTIL_PROCESSED');
				}
			break;

			default:
				if(isset($_SESSION['payment']) && is_array($_SESSION['payment']) && $_SESSION['payment']['msg']) {
					$_SESSION['payment_process_message'] = $_SESSION['payment']['msg'];
					$_SESSION['order_process_message'] = '';
				} else {
					$_SESSION['payment_process_message'] = '';
					$_SESSION['order_process_message'] = $this->string('YOUR_ORDER_ACCEPTED_AND_SCHLD_4_PROCESSING');
				}
				$_SESSION['reload_step'] = 'confirmation';
			break;
		}

		$confirmation_message =
			sprintf(
				$this->string('THANK_YOU_FOR_CHOOSING'),
				$_SESSION['person']['first_name'].' '.$_SESSION['person']['last_name'], $_SESSION['vendor']['name']
			).' '.$_SESSION['payment_process_message'].' '.$_SESSION['order_process_message'];

		return array(
			'back_to_payment' => $back_to_payment,
			'redirect_hash' => $redirect_hash,
			'actions_title' => $actions_title,
			'confirmation_message' => $confirmation_message
		);
	}

	function _can_place_order() {
		sw_log_debug('_can_place_order');
		$res = array();
		$res['result'] = 'error';
		$res['fields'] = array();
		$res['valid'] = array();
		if(!$_SESSION['package'] && $_SESSION['shopping_cart']['plan_id'] != 'domains') {
			sw_log_error("Package not defined.");
			$this->error->add(MC_ERROR, 'PLEASE_SELECT_PLAN_TO_PROCEED');
			return $res;
		}

		$dm_plan_id = $_SESSION['package']['assigned_dm_plan'];
		$plan_id = $_SESSION['shopping_cart']['plan_id'];
		## for domain pointers customer should buy real HP, not only domain registration
		if(in_array($_SESSION['package']['type']['id'], array(HP_TYPE_DOMAIN_REGISTRATION))) {
			if(!is_array($_SESSION['domains'][$dm_plan_id]) || !count($_SESSION['domains'][$dm_plan_id])) {
				$this->error->add(MC_ERROR, null, sprintf($this->string('YOU_MUST_SELECT_DOMAIN_WITH_PACKAGE'), $_SESSION['package']['name']));
				return $res;
			}
			$register_new_domains = 1;
			foreach($_SESSION['domains'][$dm_plan_id] AS $key => $value) {
				if($value['dm_action'] == 'domain_pointer') {
					$register_new_domains = 0;
					break;
				}
			}
			if(!$register_new_domains) {
				$this->error->add(MC_ERROR, 'SHOULD_BUY_NOT_ONLY_DOMAIN_PACKAGE');
				return $res;
			}
		}

		if(
			is_array($_SESSION['package']) &&
			$_SESSION['package']['assigned_dm_plan'] &&
			(
				!$_SESSION['configuration']['hostname_type'] ||
				$_SESSION['configuration']['hostname_type'] == 'no_hostname' ||
				$_SESSION['configuration']['hostname_type'] == 'use_domain'
			)
			&&
			(
				(
					$_SESSION['package']['type']['id'] == HP_TYPE_PLESK_DOMAIN &&
					!is_array($_SESSION['domains'][$dm_plan_id]) &&
					!is_array($_SESSION['account_domains']) &&
					!is_array($_SESSION['provider_domains'])
				)
// 				||
// 				(
// 					in_array($_SESSION['package']['type']['id'], array(HP_TYPE_VPS, HP_TYPE_PLESK_VIRTUAL_NODE, HP_TYPE_PSVM)) &&
// 					!is_array($_SESSION['domains'][$dm_plan_id]) &&
// 					!is_array($_SESSION['account_domains']) &&
// 					!is_array($_SESSION['assigned_account_domains']) &&
// 					!is_array($_SESSION['provider_domains'])
// 				)
			)
		) {
			$this->error->add(MC_ERROR, null, sprintf($this->string('YOU_MUST_SELECT_DOMAIN_WITH_PACKAGE'), $_SESSION['package']['name']));
			return $res;
		}

		if(!$_SESSION['is_authorized']) {
			$this->error->add(MC_ERROR, 'PLEASE_SIGN_IN_OR_REGISTER_TO_PROCEED');
			return $res;
		}

		if(
			in_array($_SESSION['package']['type']['id'], array(HP_TYPE_VPS, HP_TYPE_PLESK_DOMAIN, HP_TYPE_PLESK_VIRTUAL_NODE, HP_TYPE_PSVM)) &&
				(!$_SESSION['configuration']['hostname_type']
				 ||
				 ($_SESSION['configuration']['hostname_type'] == 'use_subdomain' &&
				 (!$_SESSION['configuration']['subdomain_hostname'] || !$_SESSION['configuration']['subdomain']))
				 ||
				 ($_SESSION['configuration']['hostname_type'] == 'use_domain' &&
				 !$_SESSION['configuration']['domain_hostname'])
				)
		) {
			$this->error->add(MC_ERROR, null, sprintf($this->string('YOU_MUST_SELECT_HOSTNAME_WITH_PACKAGE'), $_SESSION['package']['name']));
			return $res;
		}

		## check for num dns_hosting
		if(is_array($_SESSION['domains'][$dm_plan_id]) &&
			count($_SESSION['domains'][$dm_plan_id]) &&
			is_array($_SESSION['package']['dns_hosting']) &&
			!$_SESSION['package']['dns_hosting']['is_unlim']
		) {
			$domains_with_dns = 0;
			foreach($_SESSION['domains'][$dm_plan_id] as $key => $value) {
				if($value['dm_action'] == 'domain_pointer' &&
					(!isset($value['dns_hosting']) || (isset($value['dns_hosting']) && $value['dns_hosting']))
				) {
					$domains_with_dns++;
					if(!isset($value['dns_hosting'])) {
						$_SESSION['domains'][$dm_plan_id][$key]['dns_hosting'] = true;
					}
				}
			}
			if($domains_with_dns > $_SESSION['package']['dns_hosting']['included_value'] && is_array($_SESSION['package']['qos_list'])) {
				$numdnshosting = '';
				if(is_array($_SESSION['package']['qos_list'])) {
					foreach($_SESSION['package']['qos_list'] as $qos) {
						if($qos['short_name'] == $_SESSION['package']['dns_hosting']['short_name']) {
							$numdnshosting = $qos;
							break;
						}
					}
				}
				if($numdnshosting) {
					if($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$numdnshosting['id']] < $domains_with_dns) {
						if($numdnshosting['min_upgrade_unit'] > 1) {
							do {
								$_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$numdnshosting['id']] += $numdnshosting['min_upgrade_unit'];
							} while($_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$numdnshosting['id']] < $domains_with_dns);
						} else {
							$_SESSION['shopping_cart'][$plan_id]['configuration']['qos_list'][$numdnshosting['id']] = $domains_with_dns;
						}
					}
				}
			}
		}
		$res['result'] = 'success';
		return $res;
	}

	function calculate_order() {
		sw_log('calculate_order');
		$plan_id = $_SESSION['shopping_cart']['plan_id'];
		$os_tmpl_id = $_SESSION['shopping_cart']['os_tmpl'];

		$domains = new Domains($this->translator);
		if(is_array($_SESSION['plans'][$plan_id]) && $_SESSION['plans'][$plan_id]['assigned_dm_plan']) {
			$domains->load_domain_package($_SESSION['plans'][$plan_id]['assigned_dm_plan']);
		} else {
			$domains->load_domain_package();
		}

		if($_SESSION['is_authorized']) {
			$_SESSION['warning_layout'] = call('get_warning',
				array(
					'account_id'   => $_SESSION['account']['account_id'],
					'warning_type' => 'order',
					'ext_data'   => array(
						'for_trial'    => ($_SESSION['shopping_cart']['period'] === 'trial') ? 1 : 0,
						'order_amount' => calculate_total($plan_id)
					)
				),
				'HSPC/API/Fraud'
			);
		}

		$res = array();
		$res['result'] = 'success';

		## try to calculate order
		$order_calc = place_order(true, $plan_id, $os_tmpl_id);
		$transport = get_api_transport();
		if($transport->fault) {
			## if order cannot be placed, try to catch known error codes
			## and show message in appropriate page block
			## by default mesage will be shown on top
			$res['result'] = 'error';
			$res['id'] = 'general_home';
			switch($transport->faultcode) {
				case 'soap:UserAppIncompat':
					$res['id'] = 'applications';
				break;

				case 'soap:DomainDataError':
					$res['id'] = 'domain_contacts';
				break;

				case 'soap:DMExistingUnavail':
					$res['id'] = 'configuration';
					$this->error->get(MC_INTERR, true);
					$this->error->add(MC_ERROR, null, $transport->faultstring);
				break;

				case 'soap:TLDNoSuchPeriod':
				case 'soap:HPNoDomainAvailable':
				case 'soap:HPNoTransferDomainAvailable':
				case 'soap:HPNoSecureWhois':
				case 'soap:HPNoDomainSubscrAllowed':
					$res['id'] = 'configuration';
					$this->error->get(MC_INTERR, true);
					$this->error->add(MC_ERROR, null, $transport->faultstring);
				break;

				case 'soap:UserNoNS':
					$res['id'] = 'configuration';
				break;

				case 'soap:UserSiteIDInvalid':
					$res['id'] = 'configuration';
					$res['fields'] = array('sb_sid');
				break;

				default:
				break;
			}
		} else {
			$_SESSION['order_calc'] = serialize($order_calc);
		}
		return $res;
	}

	function process_contacts() {
		$comments = $this->translator->getParamVal('comments', '_POST');
		$email = $this->translator->getParamVal('email', '_POST');
		$name = $this->translator->getParamVal('name', '_POST');
		if(!$comments || !$email || !$name) {
			$this->error->add(MC_ERROR, 'SOME_REQUIRED_FIELDS_ARE_EMPTY');
		} else {
			if($_SERVER['HTTP_REFERER'] && preg_match('#\:\/\/'.$_SERVER['SERVER_NAME'].'\/#', $_SERVER['HTTP_REFERER'])) {
				call(
					'send',
					array(
						'to_email' => $_SESSION['vendor']['admin_email'],
						'to_name' => $_SESSION['vendor']['name'],
						'subject' => $this->string('CONTACT_INFO'),
						'body' => $comments,
						'from_email' => $email,
						'from_name' => $name
					),
					'HSPC/API/Mailer'
				);
			} else {
				sw_log_error('Email has not been sent, HTTP_REFERER absent or invalid');
			}
			$this->error->add(MC_SUCCESS, 'YOUR_MESSAGE_HAS_BEEN_SENT');
			return true;
		}
		return false;
	}

	function process_partners() {
		$required_fields = array(
			'company_name', 'first_name', 'last_name',
			'email', 'address1', 'city', 'country', 'zip',
			'phone_country_code', 'phone_number'
		);
		$form_filled = 1;
		foreach($required_fields as $key) {
			if(!$_POST[$key]) {
				$form_filled = 0;
				break;
			}
		}
		if(!$form_filled) {
			$this->error->add(MC_ERROR, 'SOME_REQUIRED_FIELDS_ARE_EMPTY');
		} else {
			if($_SERVER['HTTP_REFERER'] && preg_match('#\:\/\/'.$_SERVER['SERVER_NAME'].'\/#', $_SERVER['HTTP_REFERER'])) {
				$account = new Account($this->translator);
				if($reseller_id = $account->create_reseller()) {
					$_SESSION['reseller_id'] = $reseller_id;
					$this->error->add(MC_SUCCESS, 'YOUR_PARNER_APPLICATION_HAS_BEEN_SUBMITTED');
					return true;
				} else if(!$this->error->has(MC_ERROR)) {
					$this->error->add(MC_ERROR, 'ERROR_OCCURED');
				}
			} else {
				sw_log_error('Partner application has not been submitted, HTTP_REFERER absent or invalid');
			}
		}
		return false;
	}

	function get_extended_attr_list() {
		$result = call('get_extended_attr_list', array(), 'HSPC/API/Account');
		$_SESSION['extended_attr_list'] = $result['extended_attr_list'];
	}

	function cp_href() {
		return 'http'.
			($_SESSION['provider_config']['is_use_ssl_cp'] ? 's' : '').
			'://'.
			$_SESSION['hspc_server_name'].
			'/cp'.
			((isset($_SESSION['is_authorized']) && isset($_SESSION['sid'])) ?
				'/login.cgi?sid='.
				$_SESSION['sid'].
				'&amp;ret_url='.
				str_replace(
					array('+', '/'),
					array('-', '_'),
					base64_encode('http'.($_SESSION['provider_config']['is_use_ssl_cp'] ? 's' : '').'://'.$_SESSION['hspc_server_name'].'/cp')
				) : '');
	}

	function logoff() {
// 		if($_SESSION['order']['id'] && $_SESSION['force_payment']) {
// 			$this->error->add(MC_ERROR, 'ORDER_PLACED_ALREADY_YOU_CANNOT_RETURN');
// 			return false;
// 		}
		unset($_SESSION['is_authorized']);
		unset($_SESSION['person']);
		unset($_SESSION['account']);
		unset($_SESSION['account_domain_contacts']);
		unset($_SESSION['domain_contacts']);
		unset($_SESSION['all_account_domains']);
		unset($_SESSION['account_domains']);
		unset($_SESSION['assigned_account_domains']);
		unset($_SESSION['reload_step']);
		unset($_SESSION['credentials']);
		unset($_SESSION['accounting_data']);
		unset($_SESSION['order']);
//  		unset($_SESSION['force_payment']);
		$this->clear_shopping_cart();
		return true;
	}

	####
	##  This function intended to keep particular configuration parameter in session, without validation and so on, so be careful
	function set_config_param($plan_id, $param, $value, $add_params) {
		$result = Array(
			'result' => 'success',
			'error_message' => ''
		);
		if($plan_id == 'domains') {
			$_SESSION['package'] = $_SESSION['domain_package']['default'];
		} else {
			$_SESSION['package'] = $_SESSION['plans'][$plan_id];
		}
		$dm_plan_id = $_SESSION['package']['assigned_dm_plan'];
		sw_log_debug("plan_id => $plan_id, param => '$param', value => '$value', add_params => '$add_params', dm_plan_id => $dm_plan_id");
		switch($param) {
			case 'contacts_prefilling_type':
				$_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] = $value;
				if($value == 'use_contact' && $add_params) {
					$_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_contact_id'] = $add_params;
				}
			break;

			case 'dns_hosting':
			case 'whois_privacy':
			case 'ns_hostname_1':
			case 'ns_ip_1':
			case 'ns_hostname_2':
			case 'ns_ip_2':
			case 'individual_contact':
			case 'hosting':
				if($add_params) {
					foreach($_SESSION['domains'][$dm_plan_id] AS $key => $domain) {
						if(escape_dm($key) != $add_params) {
							## not desired domain, look further
							continue;
						}
						if(preg_match('/^ns_.*/', $param)) {
							## if special keyword used, set as empty string. Just to not broke route
							$value = $value == '_set_empty' ? '' : $value;
							$index = $param == 'ns_hostname_1' || $param == 'ns_ip_1' ? 0 : 1;
							$subindex = preg_match('/ns_hostname.*/', $param) ? 0 : 1;
							$_SESSION['domains'][$dm_plan_id][$key]['ns'][$index][$subindex] = $value;
						} else if($param == 'individual_contact') {
							## value in this case will be 'contact_id,contact_type'
							$cnt = preg_split('/\,/', $value);
							$_SESSION['domains'][$dm_plan_id][$key]['contacts'][$cnt[1]] = $cnt[0];
						} else if($param == 'hosting') {
							## value in this case will be 'hosting,hosting_destination'
							$cnt = preg_split('/\,/', $value);
							$_SESSION['domains'][$dm_plan_id][$key]['hosting'] = $cnt[0];
							$_SESSION['domains'][$dm_plan_id][$key]['hosting_destination'] = $cnt[1];
						} else {
							$_SESSION['domains'][$dm_plan_id][$key][$param] = $value == 'on' ? 1 : 0;
						}
						## no need to iterate further
						break;
					}
				}
			break;

			case 'password_source':
				if($value == 'enter_new') {
					## value in this case will be 'password,typed_password_value-|/|+retyped_password_value'
					$add_params = preg_replace('/password\,/', '', $add_params);
					$cnt = preg_split('/\-\|\/\|\+/', $add_params);
					if(is_array($cnt) && count($cnt) == 2 && $cnt[0] && $cnt[0] === $cnt[1]) {
						$_SESSION['credentials']['password'] = $cnt[0];
					} else {
						$_SESSION['credentials']['password'] = null;
					}
				} elseif($value == 'use_account') {
					$_SESSION['credentials']['password'] = $_SESSION['credentials']['account_password'];
				}
				$_SESSION['credentials']['password_source'] = $value;
			break;

			case 'hostname_type':
				$_SESSION['configuration']['hostname_type'] = $value;
				if($value == 'use_domain') {
					$_SESSION['configuration']['domain_hostname'] = $add_params;
					unset($_SESSION['configuration']['subdomain']);
					unset($_SESSION['configuration']['subdomain_hostname']);
				} else if($value == 'use_subdomain') {
					## value in this case will be 'subdomain,subdomain_hotname'
					$cnt = preg_split('/\,/', $add_params);
					$_SESSION['configuration']['subdomain'] = $cnt[0];
					$_SESSION['configuration']['subdomain_hostname'] = $cnt[1];
					unset($_SESSION['configuration']['domain_hostname']);
				} else {
					## no_hostname
					unset($_SESSION['configuration']['domain_hostname']);
					unset($_SESSION['configuration']['subdomain']);
					unset($_SESSION['configuration']['subdomain_hostname']);
				}
			break;

			case 'forward_url':
				$_SESSION['configuration']['forward_url'] = $value;
			break;

			case 'question':
				$cnt = preg_split('/\,/', $add_params);
				$_SESSION['configuration']['answers'][$cnt[0]] = array($cnt[1], $value);
			break;

			case 'ssl_form':
				parse_str(htmlspecialchars_decode($value), $_SESSION['ssl'][$plan_id]);
			break;

			case 'sitebuilder':
				$_SESSION['shopping_cart'][$plan_id]['addons']['sitebuilder']['value'] = $value;
				$_SESSION['shopping_cart'][$plan_id]['addons']['sitebuilder']['sb_sid'] = $add_params;
			break;

			default:
				sw_log_warn('Parameter "'.$param.'" unknown, nothing to do...');
			break;
		}
		return $result;
	}

}