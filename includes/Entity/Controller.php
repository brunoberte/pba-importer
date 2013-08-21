<?php
//////////
// Store controller. All requests handlers are collected here  
//
// $Id: Controller.php 888857 2013-06-20 14:50:50Z dkolvakh $
//////////

namespace Entity;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Entity\Main;
use Entity\Domains;
use Entity\Account;
use Entity\Translate;

use ArrayAccess;

class StoreController {

	public $request;
	public $router;
	public $error;

	private $view;

	public function init($request, $router) {
		$this->request = $request;
		$this->router = $router;
		$this->view = new ControllerHelper($request, $router);
		$this->error = get_error_handler();
		$this->error->setTranslator($this->view);
	}

	public function getRequest () {
		return $this->request;
	}

	public function redirect( $url, $status = 302 ) {
		sw_log_debug ("Redirect to " . $url);
		return new RedirectResponse( $url, $status );
	}
	
	public function get() {
		return $this->view;
	}

	public function render($path, $params = array()) {
		$str = $this->view->render( $path, $params );
		$response = new Response($str);
		return $response;
	}
	
	public function renderView($path, $params = array()) {
		return $this->view->render( $path, $params );
	}
	
	public function generateUrl ( $route,  $parameters = array(),  $absolute = false) {
		return $this->router->generate ($route, $parameters, $absolute);
	}

	public function redirect2DomainsTabAction() {
		## handle redirect from CP, "Register New Domain" link
		$_SESSION['reload_step'] = 'domains_tab';
		return $this->redirect($this->generateUrl('homepage'), 301);
	}

	public function redirect2PlansTabAction() {
		## handle redirect from anywhere, '/plans.php<?type_id=X>|<?series_key=X>|<?sb_node=X&sb_sid=X>' links
		$_SESSION['CP_GET'] = $_GET;
		$_SESSION['CP_GET']['action'] = 'view_all_products';				
		return $this->redirect($this->generateUrl('homepage'), 301);
	}

	public function indexResetAction() {
// 		if(
// 			!isset($_SESSION['reload_step'])
// 			||
// 			(isset($_SESSION['reload_step']) && in_array($_SESSION['reload_step'], array('checkout', 'confirmation')))
// 		) {
			$main = new Main($this->get('translator'));
			$main->cancel_order();
// 		}
		return $this->redirect($this->generateUrl('homepage'), 301);
	}

	public function indexBackAction() {
		unset($_SESSION['reload_step']);
		return $this->redirect($this->generateUrl('homepage'), 301);
	}

	public function indexAction() {
		if($this->getRequest()->getLocale() != $_SESSION['current_language']) {
			open_backend_session($this->getRequest()->getLocale());
		}
		$main = new Main($this->get('translator'));
		$insertion = null;
		$custom_content = null;
		$custom_js = null;
		$results = array();
		$selected_group_item_id = null;
		$adopt_wrapper_padding = null;
		$hide_navbar = null;
		if(isset($_SESSION['CP_GET'])) {
			## handle redirect from CP, "Buy SSL certificate" link, /plans.php links
			if(isset($_SESSION['CP_GET']['action']) && $_SESSION['CP_GET']['action'] == 'view_all_products') {
				$main->cancel_order();
				$_SESSION['reload_step'] = 'view_all_products';
			}
		}
		## handle different "target pages"
		## in future, hierarchy can be implemented with stack-like array in session 		
		if((array_key_exists('shopping_cart', $_SESSION) && isset($_SESSION['shopping_cart']['plan_id'])) || isset($_SESSION['reload_step'])) {
			$results = $main->get_initial_data(false);
			$plan_id = $_SESSION['shopping_cart']['plan_id'];
			$group_id = $_SESSION['shopping_cart']['group_id'];
			$selected_item_id = '';
			$selected_item = array();
			$step_html = null;
			if($plan_id === 'domains') {
				## when domains was checked before page reload, we should show index page,
				## and select corresponding tab
				## handler attached to domains tab activation will decide to show or not
				## configuration, sign-in and shopping cart blocks
				$custom_js[] = '$("li#custom-tabs-domains").click();';
				$selected_group_item_id = 'domains';
			} else if(isset($_SESSION['reload_step'])) {
				if($_SESSION['reload_step'] == 'domains_tab') {
					## if "Register New Domain" link acted in CP, show domains tab
					$custom_js[] = '$("li#custom-tabs-domains").click();';
					$selected_group_item_id = 'domains';
					unset($_SESSION['CP_GET']);
					unset($_SESSION['reload_step']);
				} else {
					## handle redirect from CP, "Buy SSL certificate" link and
					## redirects from anywhere, '/plans.php<?type_id=X>|<?series_key=X>|<?sb_node=X&sb_sid=X>' links:
					##  type_id set:					
					##   - try to find first tab, where desired HP type exists
					##   - highlight first HP of given type
					##  series_key set:
					##   - try to find tab, where desired HP id exists
					##   - choose found plan
					##  sb_node and sb_sid set:
					##   - try to load sellable plans with those parameters, if something found - use it further
					##   - if nothing found - use previous set of plans
					## handle reload page in the middle of purchase, plan_id is in shopping cart
					$type_id = isset($_SESSION['CP_GET']['type_id']) ? htmlspecialchars(strip_tags($_SESSION['CP_GET']['type_id'])) : null;
					$plan_id = isset($_SESSION['CP_GET']['series_key']) ? htmlspecialchars(strip_tags($_SESSION['CP_GET']['series_key'])) : $_SESSION['shopping_cart']['plan_id'];
					$_SESSION['sb_sid'] = isset($_SESSION['CP_GET']['sb_sid']) ? htmlspecialchars(strip_tags($_SESSION['CP_GET']['sb_sid'])) : null;
					$_SESSION['sb_node'] = isset($_SESSION['CP_GET']['sb_node']) ? htmlspecialchars(strip_tags($_SESSION['CP_GET']['sb_node'])) : null;
					if($_SESSION['sb_sid'] && $_SESSION['sb_node']) {
						$results_with_sb = $main->get_initial_data(false);
						if(!is_array($results_with_sb) || !count($results_with_sb)) {
							sw_log_warn('No compatible HPs found for sb_node="'.$_SESSION['sb_node'].'", sb_sid="'.$_SESSION['sb_sid'].'"');
							unset($_SESSION['sb_sid'], $_SESSION['sb_node']);
						} else {
							$results = $results_with_sb;
						}
					}
					if($type_id || $plan_id || $_SESSION['sb_sid']) {
						foreach($results as $group_item_id => $group_item) {
							foreach($group_item['items'] as $item_id => $item) {
								foreach($item['plans'] as $plan) {
									if(
										($type_id && $plan['type']['id'] == $type_id) ||
										($plan_id && $plan['series_key'] == $plan_id) ||
										$_SESSION['sb_sid']
									) {
										$selected_group_item_id = $group_item_id;
										$selected_item_id = $item_id;
										$selected_item = $item;
										break 3;
									}
								}
							}
						}
					}
					## if type_id or sb_sid set, choose tab
					if($type_id || $_SESSION['sb_sid']) {
						$custom_js[] = '$("li#custom-tabs-'.$selected_group_item_id.'").click();';
						$step_html = '&nbsp;';
					} else if($plan_id) {
						## nothing to do, will be handled with reload action
					}
					unset($_SESSION['CP_GET']);
					if($_SESSION['reload_step'] == 'view_all_products') {
						unset($_SESSION['reload_step']);
					}
				}
			} else {
				foreach($results AS $group_item_id => $group_item) {
					foreach($group_item['items'] as $item_id => $item) {
						## chosen plan can be added to more than one group, try to find correct one
						if($item_id != $group_id) {
							continue;
						}
						foreach($item['plans'] as $plan) {
							if($plan['series_key'] == $plan_id) {
								$selected_group_item_id = $group_item_id;
								$selected_item_id = $item_id;
								$selected_item = $item;
								break 3;
							}
						}
					}
				}
			}
			if(isset($_SESSION['reload_step'])) {
				sw_log_debug('reload_step => '.$_SESSION['reload_step']);
				switch($_SESSION['reload_step']) {
					case 'checkout':
						$custom_js = array();
						$custom_js[] = 'showBlock("#main")';
						$custom_js[] = 'checkout_submit_form();';
						$step_html = $this->showCheckoutAction(true)->getContent();
					break;
					case 'payment':
						$custom_js = array();
						$custom_js[] = 'showBlock("#main");';
						$custom_js[] = 'payment_submit_form();';
						$step_html = $this->showPaymentAction()->getContent();
						## hide navigation
						$adopt_wrapper_padding = true;
						$hide_navbar = true;
					break;
					case 'confirmation':
						## to correctly handle situation with failed payment transaction
						$res = $main->confirmation();
						if($res['back_to_payment'] && $res['back_to_payment'] != '3dsecure') {
							$custom_js = array();
							$custom_js[] = 'showBlock("#main");';
							$custom_js[] = 'payment_submit_form();';
							$_SESSION['reload_step'] = 'payment';
							$step_html = $this->showPaymentAction()->getContent();
							## hide navigation
							$adopt_wrapper_padding = true;
							$hide_navbar = true;
						} else {
							$custom_js = array();
							$custom_js[] = 'showBlock("#main");';
							$custom_js[] = 'confirmation_handle();';
							$custom_js[] = 'show_accounting_data();';
							$step_html = $this->showConfirmationAction($res)->getContent();
						}
					break;
				}
			}
			$custom_content = $this->renderView('refresh.html.php', 
				array(
					'item_id' => $selected_item_id,
					'plan_id' => $plan_id,
					'item' => $selected_item,
					'step_html' => $step_html,
					'results' => $results,
					'selected_group_item_id' => $selected_group_item_id
				)
			);
		} else {
			$results = $main->get_initial_data(true);
			$insertion = is_array($results) && count($results) ? NULL : "&nbsp;";
		}
		// status message will be rendered if missing $results (e.g. store is closed)
		return $this->render('general_home.html.php',
			array(
				'results' => $results,
				'cp_href' => $main->cp_href(),
				'insertion' => $insertion,
				'custom_content' => $custom_content,
				'custom_js' => $custom_js,
				'selected_group_item_id' => $selected_group_item_id,
				'adopt_wrapper_padding' => $adopt_wrapper_padding,
				'hide_navbar' => $hide_navbar,
			)
		);
	}

	public function logoffAction() {
		$main = new Main($this->get('translator'));
		$result = $main->logoff();
		return $this->redirect($this->generateUrl('homepage'), 301);
	}

	public function domainsAction() {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$domains = new Domains($this->get('translator'));
			$res = $domains->process_step();
			$series_key = $request->getParamVal('series_key', '_POST');
			$plan_id = $request->getParamVal('plan_id', '_POST');
			$result = Array(
				'result' => $res ? 'success' : 'error',
				'html' => '',
				'messages' => '',
			);
			$result['messages'] = $this->renderView('status_message_fit.html.php', array(id => 'plan_domains_'.$plan_id));
			$result['html'] = $this->renderView('domains_content.html.php', array('series_key' => $series_key ? $series_key : 'default', 'plan_id' => $plan_id));
			$response = new Response(json_encode($result));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function signinAction() {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$result = Array(
				'result' => 'success',
				'html' => '',
			);
			$success = false;
			$account = new Account($this->get('translator'));
			if($request->getParamVal('action', '_POST') == 'register_new') {
				$success = $account->register_new();
			} else {
				$success = $account->sign_in();
			}
			if($success) {
				$main = new Main($this->get('translator'));
				$cp_href = $main->cp_href();
				$result['html'] = $this->renderView('sign_in_result_ok.html.php', array(cp_href => $cp_href, 'plan_id' => $request->getParamVal('plan_id', '_POST')));
			} else {
				$result['result'] = 'error';
				$result['html'] = $this->renderView('sign_in_result_error.html.php', array('plan_id' => $request->getParamVal('plan_id', '_POST'), 'email' => $request->getParamVal('email', '_POST')));
			}
			$response = new Response(json_encode($result));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function configureAction( $plan_id ) {
		$request = $this->getRequest();
		$main = new Main($this->get('translator'));
		$domains = new Domains($this->get('translator'));
		sw_log('plan_id => '.$plan_id.'; action => '.$request->getParamVal('action', '_POST'));
		if($request->isXmlHttpRequest()) {
			if($_SESSION['shopping_cart']['plan_id'] == 'domains') {
				$dm_plan_id = $_SESSION['domain_package']['default']['series_key'];
			} else {
				$dm_plan_id = $_SESSION['plans'][$_SESSION['shopping_cart']['plan_id']]['assigned_dm_plan'];
			}
			if($request->getParamVal('action', '_POST') == 'contact_edit') {
				$domain = $request->getParamVal('contact_domain', '_POST');
				$contact_type = $request->getParamVal('contact_type', '_POST');
				$contact_id = $request->getParamVal('contact_id', '_POST');

				$domains->save_domain_data($dm_plan_id);
				install_error_handler('UserDomainDataError', "handle_domain_data_error");
				validate_domain_data($dm_plan_id);

				$results = $domains->teaser_domain_contacts($domain, $contact_type, $contact_id, $contact = NULL, $dm_plan_id);
				$extra_buttons = $results['extra_buttons'];
				$results['extra_buttons'] = $this->renderView('domain_contacts_buttons.html.php', array('extra_buttons' => $extra_buttons, 'store_apply' => null));
				$fields = $domains->get_domain_contact_errors($results['current_domain'], $results['current_ctype'], $dm_plan_id);

				$res = array();
				$res['result'] = 'success';
				$res['content'] = $this->renderView('domain_contacts.html.php', array('results' => $results));
				$res['fields'] = $fields;

				$response = new Response(json_encode($res));
				$response->headers->set('Content-Type', 'application/json');
				return $response;
			} else {
				$res = array(
					'result' => 'success',
					'fields' => array(),
					'valid' => array(),
				);
				$calc_res = array();
				$result = $main->configure($plan_id, $domains);
				sw_log_debug('configure result: '.dumper($result));
				if($result['result'] == 'success') {
					## try to calculate order
					$calc_res = $main->calculate_order();
					sw_log_debug('calculate_order result: '.dumper($calc_res));
					if($calc_res['result'] == 'error') {
						$result['result'] = 'error';
					}
				}
				if($result['result'] == 'success') {
					$response = new Response(json_encode($res));
					$response->headers->set('Content-Type', 'application/json');
					return $response;
				} else {
					## error occurred
					$res['result'] = 'error';
					$res['id'] = $result['id'];
					$res['fields'] = $result['fields'];
					$res['valid'] = $result['valid'];
					if(isset($_SESSION['contact_error'][$dm_plan_id])) {
						$res['domain_contact_error'] = 1;
						$res['contact_error_names'] = $domains->get_domain_contact_error_names($dm_plan_id);
						$res['id'] = 'domain_contacts';
					} else if(isset($_SESSION['ssl_error'][$plan_id])) {
						## define form field names
						foreach($_SESSION['ssl_error'][$plan_id] as $field => $msg) {
							$field = preg_replace('/\[/','\\[' , $field);
							$field = preg_replace('/\]/','\\]' , $field);
							$res['fields'][] = $field;
							if(preg_match("/csr_hide/", $field)) {
								$uncheck_contact = $field;
							}
						}
						if ($uncheck_contact) {
							$res['jsact'][] = array(
								'name' => $uncheck_contact,
								'type' => 'checkbox',
								'action' => 'uncheck'
							);
						}
					} else if(isset($calc_res['id'])) {
						$res['id'] = $calc_res['id'];
					} else if(
						in_array($_SESSION['package']['type']['id'], array(HP_TYPE_DOMAIN_REGISTRATION)) &&
						(!is_array($_SESSION['domains'][$dm_plan_id]) || !count($_SESSION['domains'][$dm_plan_id]))
					) {
						$res['id'] = 'plan_domains_domains';
					}
					if(isset($calc_res['fields'])) {
						$res['fields'] = array_merge($res['fields'], $calc_res['fields']);
					}
					$res['error_message'] = $this->renderView('status_message.html.php', array(id => $res['id']));
					$response = new Response(json_encode($res));
					$response->headers->set('Content-Type', 'application/json');
					return $response;
				}
			}
		}
	}

	public function showCheckoutAction($as_isXml = false) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest() || $as_isXml) {
			$main = new Main($this->get('translator'));
			$results = $main->checkout();
			$_SESSION['checkout_results'] = serialize($results);
			return new Response(
				$this->renderView('checkout.html.php', array('results' => $results))
			);
		}
	}

	public function processCheckoutAction () {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
		$action = $request->getParamVal('action', '_POST', 'continue');
		$plan_id = $_SESSION['shopping_cart']['plan_id'];
		sw_log_debug('action => '.$action.'; series_key => '.$plan_id);
		$main = new Main($this->get('translator'));
		$result = Array(
			'result' => 'success',
			'html' => '',
			'custom_js' => null,
		);

		unset($_SESSION['payment_plugin_id']);
		unset($_SESSION['payment_type_action']);
		unset($_SESSION['payment']);
		unset($_SESSION['payment_status']);
		unset($_SESSION['fraud_denied_order']);
		switch ($action) {
			case 'continue':
				$promo_id = $request->getParamVal('promo_id', '_POST');
				if($promo_id) {
					sw_log_debug('try to apply promo_id: '.$promo_id);
					switch ($_SESSION['promotions'][$promo_id]['promo_type']) {
						case PROMOTION_DEFAULT :
							$_SESSION['shopping_cart']['promo_id'] = NULL;
						break;

						case PROMOTION_COUPON_CODE :
							$_SESSION['shopping_cart']['promo_id'] = NULL;
							$coupon_code = $request->getParamVal('coupon_code_promo_id', '_POST');
							foreach ($_SESSION['promotions'] as $promotion) {
								if($promotion['promo_type'] == PROMOTION_COUPON_CODE && $promotion['coupon_code'] == trim($coupon_code)) {
									$_SESSION['shopping_cart']['promo_id'] = $promotion['promo_id'];
									break;
								}
							}
							if($_SESSION['shopping_cart']['promo_id'] == NULL) {
								sw_log_debug('coupon code: "'.$coupon_code.'"');
								$this->error->add(MC_WARN, 'COUPON_CODE_INVALID');
								$result['custom_js'][] = 'checkout_submit_form();';
								$result['html'] = $this->showCheckoutAction(true)->getContent();
								break 2;
							}
						break;

						case PROMOTION_AGREEMENT :
							$_SESSION['shopping_cart']['promo_id'] = $promo_id;
						break;
					}
				} elseif(array_key_exists('promo_id', $_POST) && !$promo_id) {
					## do not use promotion option selected
					$_SESSION['shopping_cart']['promo_id'] = '';
				} else {
					$_SESSION['shopping_cart']['promo_id'] = NULL;
				}

				$res = $main->_can_place_order();
				if($res['result'] == 'error') {
					$result['fields'] = $res['fields'];
					$result['custom_js'][] = 'checkout_submit_form();';
					$result['html'] = $this->showCheckoutAction(true)->getContent();
					break;
				}

				// Place Order
				$plan_id = $_SESSION['shopping_cart']['plan_id'];
				$os_tmpl_id = $_SESSION['shopping_cart']['os_tmpl'];
				$order = place_order(false, $plan_id, $os_tmpl_id);
				$transport = get_api_transport();
				if($transport->fault) {
					if(strpos($transport->faultcode, 'soap:User') === 0) {
						## when no handler registered for FaultCode, error_message will contain FaultString already
						## just replace it with human-friendly message with the same meaning				
						$this->error->get(MC_ERROR, 1);
						$this->error->add(MC_ERROR, null, $this->translator->trans('CANNOT_CALCULATE_ORDER', array('%error%' => $transport->faultstring)));
						$result['custom_js'][] = 'checkout_submit_form();';
						$result['html'] = $this->showCheckoutAction(true)->getContent();
					} elseif(strpos($transport->faultcode, 'soap:AFMdenied') === 0) {
						$package_name = ($_SESSION['plans'][$plan_id]) ? $_SESSION['plans'][$plan_id]['name'] : $_SESSION['domain_package']['default']['name'];
						$fault_order = $transport->detail;
						unset($_SESSION['package']);
						unset($_SESSION['addons']);
						unset($_SESSION['domains']);
						unset($_SESSION['ssl']);
						unset($_SESSION['ssl_error']);
						unset($_SESSION['check_domains_cache']);
						unset($_SESSION['promotions']);
						unset($_SESSION['domain_contacts']);
						unset($_SESSION['payment_type']);
						$_SESSION['order'] = $fault_order['Array'][0];
						$_SESSION['order']['package_name'] = $package_name;
						$_SESSION['fraud_denied_order'] = true;
						$result['custom_js'][] = 'confirmation_handle();';
						$result['custom_js'][] = 'show_accounting_data();';
						$result['html'] = $this->showConfirmationAction()->getContent();
					} else {
						$this->error->get(MC_INTERR, true);
						$this->error->add(MC_ERROR, null, $transport->faultstring);
						$this->error->add(MC_ERROR, 'WE_CANNOT_ACCEPT_YOUR_ORDER');
						$result['custom_js'][] = 'checkout_submit_form();';
						$result['html'] = $this->showCheckoutAction(true)->getContent();
					}
				} else { // No getError()
					unset_shopping_cart();
					$_SESSION['order'] = $order;
					if($_SESSION['order']['doc_balance'] == 0) {
// 						unset($_SESSION['force_payment']);
						$_SESSION['reload_step'] = 'confirmation';
						$result['custom_js'][] = 'confirmation_handle();';
						$result['custom_js'][] = 'show_accounting_data();';
						$result['html'] = $this->showConfirmationAction()->getContent();
					} else {
// 						$_SESSION['force_payment'] = true;
						$_SESSION['reload_step'] = 'payment';
						$result['custom_js'][] = 'navbar_hide(true);';
						$result['custom_js'][] = 'payment_submit_form();';
						$result['html'] = $this->showPaymentAction()->getContent();
					}
				}
			break;

			case 'update_cart':
				$promo_id = $request->getParamVal('promo_id', '_POST');
				if($promo_id) {
					sw_log_debug('try to apply promo_id: '.$promo_id);
					## concrete promotion selected
					switch ($_SESSION['promotions'][$promo_id]['promo_type']) {
						case PROMOTION_DEFAULT :
							$_SESSION['shopping_cart']['promo_id'] = NULL;
							$this->error->add(MC_SUCCESS, null, sprintf($this->get('translator')->trans('PROMOTION_IS_APPLIED_TO_YOUR_ORDER'), $_SESSION['promotions'][$promo_id]['name']));
						break;

						case PROMOTION_COUPON_CODE :
							$_SESSION['shopping_cart']['promo_id'] = NULL;
							$coupon_code = $request->getParamVal('coupon_code_promo_id', '_POST');
							foreach ($_SESSION['promotions'] as $promotion) {
								if($promotion['promo_type'] == PROMOTION_COUPON_CODE && $promotion['coupon_code'] == trim($coupon_code)) {
									$_SESSION['shopping_cart']['promo_id'] = $promotion['promo_id'];
									$this->error->add(MC_SUCCESS, null, sprintf($this->get('translator')->trans('PROMOTION_IS_APPLIED_TO_YOUR_ORDER'), $promotion['name']));
									break;
								}
							}
							if($_SESSION['shopping_cart']['promo_id'] == NULL) {
								$this->error->add(MC_WARN, 'COUPON_CODE_INVALID');
							}
						break;

						case PROMOTION_AGREEMENT :
							$_SESSION['shopping_cart']['promo_id'] = $promo_id;
							$this->error->add(MC_SUCCESS, null, sprintf($this->get('translator')->trans('PROMOTION_IS_APPLIED_TO_YOUR_ORDER'), $_SESSION['promotions'][$promo_id]['name']));
						break;
					}
				} elseif(array_key_exists('promo_id', $_POST) && !$promo_id) {
					## do not use promotion option selected
					$_SESSION['shopping_cart']['promo_id'] = '';
				} else {
					$_SESSION['shopping_cart']['promo_id'] = NULL;
				}
				$result['custom_js'][] = 'checkout_submit_form();';
				$main->calculate_order();
				$result['html'] = $this->showCheckoutAction(true)->getContent();
			break;
		}
		$response = new Response(json_encode($result));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
		}
	}

	public function showPaymentAction($innerhtml = null) {
		$main = new Main($this->get('translator'));
		$main->payment();
		$response = $this->renderView('payment.html.php', array());
		return $innerhtml ? $response : new Response($response);
	}

	public function processPaymentAction($url) {
		$request = $this->getRequest();
		$status = $request->getParamVal('status', '_GET');
		sw_log("url => $url, status => $status");
		if(isset($url) && $status > 0) {
			$_SESSION['payment_status'] = $status;
			switch ($_SESSION['payment_status']) {
				case PP_PROC_DECLINED:
					$error = $request->getParamVal('message', '_GET');
					if($error) {
						$this->error->add(MC_ERROR, null, $error);
					}
					$_SESSION['reload_step'] = 'payment';
					return $this->redirect($this->generateUrl('homepage'));
				break;

				case PP_PROC_APPROVED:
// 					unset($_SESSION['force_payment']);
					$this->error->get(MC_HINT);
					$_SESSION['reload_step'] = 'confirmation';
					return $this->redirect($this->generateUrl('homepage'));
				break;

				case PP_PROC_POSTPONED:
				case PP_PROC_UNKNOWN:
					$_SESSION['payment_process_message'] = $request->getParamVal('message', '_GET');
// 					unset($_SESSION['force_payment']);
					$_SESSION['reload_step'] = 'confirmation';
					return $this->redirect($this->generateUrl('homepage'));
				break;

				default:
// 					unset($_SESSION['force_payment']);
					$_SESSION['reload_step'] = 'confirmation';
					return $this->redirect($this->generateUrl('homepage'));
				break;
			}
		}

		if($request->isXmlHttpRequest()) {
			$saved_paymethod_id = $request->getParamVal('saved_paymethod_id', '_POST');
			$action = $request->getParamVal('action', '_POST');
			$payment_type = $request->getParamVal('payment_type', '_POST');
			$plugin_id = $request->getParamVal('plugin_id', '_POST');
			sw_log('payment_type => '.$payment_type.', action => '.$action.', saved_paymethod_id => '.$saved_paymethod_id.', plugin_id => '.$plugin_id);
			$_SESSION['payment_type'] = $payment_type;
			$_SESSION['payment_type_action'] = $action;
			$_SESSION['payment_plugin_id'] = $plugin_id;
			$_SESSION['payment_saved_paymethod_id'] = $saved_paymethod_id;
			unset($_SESSION['payment']);
			unset($_SESSION['payment_status']);
			$main = new Main($this->get('translator'));
			$result = Array(
				'result' => 'success',
				'html' => '',
				'custom_js' => null,
				'animate' => 1,
			);

			if($action == 'pay') {
				if((int)$saved_paymethod_id > 0) {
					if(!($payment = $main->pay($plugin_id, $_SESSION['order']['id'], $request->getParamVal('saved_paymethod_id', '_POST')))) {
						if(!$this->error->has(MC_ERROR)) {
							$this->error->add(MC_ERROR, 'WE_CANNOT_PROCESS_YOUR_PAYMENT');
						}
						$_SESSION['reload_step'] = 'payment';
						$result['custom_js'][] = 'navbar_hide(true);';
						$result['custom_js'][] = 'payment_submit_form();';
						$result['html'] = $this->showPaymentAction()->getContent();
					}
				} elseif($saved_paymethod_id === 'new') {
					if(!($payment = $main->pay($plugin_id, $_SESSION['order']['id'], NULL))) {
						if(!$this->error->has(MC_ERROR)) {
							$this->error->add(MC_ERROR, 'WE_CANNOT_PROCESS_YOUR_PAYMENT');
						}
						$_SESSION['reload_step'] = 'payment';
						$result['custom_js'][] = 'navbar_hide(true);';
						$result['custom_js'][] = 'payment_submit_form();';
						$result['html'] = $this->showPaymentAction()->getContent();
					}
				} elseif($saved_paymethod_id === 'new_by_redirect') {
					$_SESSION['reload_step'] = 'payment';
					$result['custom_js'][] = 'navbar_hide(true);';
					$result['custom_js'][] = 'payment_submit_form();';
					$result['animate'] = 0;
					$result['html'] = $this->payRedirectAction($plugin_id, $this->getRequest()->getLocale())->getContent();
				}
				if(is_array($payment)) {
					$_SESSION['payment'] = $payment;
// 					unset($_SESSION['force_payment']);
					$_SESSION['reload_step'] = 'confirmation';
					sleep (5);
					$res = $main->get_payment_status();
					if($res['back_to_payment'] == '3dsecure' && $res['redirect_hash']) {
						$result['custom_js'][] = 'navbar_hide(true);';
						$result['custom_js'][] = 'payment_submit_form();';
						$result['animate'] = 0;
						$result['html'] = $this->renderView('3dsecure.html.php', array());
					} else if($res['back_to_payment']) {
						$result['custom_js'][] = 'navbar_hide(true);';
						$result['custom_js'][] = 'payment_submit_form();';
						$result['animate'] = 0;
						$result['html'] = $this->showPaymentAction()->getContent();
					} else {
						$result['custom_js'][] = 'navbar_hide(false);';
						$result['custom_js'][] = 'confirmation_handle();';
						$result['custom_js'][] = 'show_accounting_data();';
						$result['html'] = $this->showConfirmationAction()->getContent();
					}
				}
			} elseif($action == 'redirect') {
				$_SESSION['reload_step'] = 'payment';
				$result['custom_js'][] = 'navbar_hide(true);';
				$result['custom_js'][] = 'payment_submit_form();';
				$result['animate'] = 0;
				$result['html'] = $this->payRedirectAction($plugin_id, $this->getRequest()->getLocale())->getContent();
			} elseif($action == 'pay_offline') {
// 				unset($_SESSION['force_payment']);
				$_SESSION['reload_step'] = 'confirmation';
				$result['custom_js'][] = 'navbar_hide(false);';
				$result['custom_js'][] = 'confirmation_handle();';
				$result['custom_js'][] = 'show_accounting_data();';
				$result['html'] = $this->showConfirmationAction()->getContent();
			}
			$response = new Response(json_encode($result));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function payRedirectAction($plugin_id, $locale) {
		sw_log('plugin_id => '.$plugin_id.', locale => '.$locale);
		$main = new Main($this->get('translator'));
		
		if(!($redirect_hash = $main->get_redirect_hash($plugin_id, $locale, $this->generateUrl('process_payment', array(session_name() => session_id()), true)))) {
			$this->error->add(MC_ERROR, 'ERROR_LOADING_PLUGIN_DATA');
			return $this->redirect($this->generateUrl('payment'));
		}

		if(!$redirect_hash['url'] && !$redirect_hash['content']) {
			$this->error->add(MC_ERROR, 'ERROR_LOADING_PLUGIN_DATA');
			return $this->redirect($this->generateUrl('payment'));			
		}
		
		if(!$redirect_hash['method']) {
			$redirect_hash['method'] = 'get';
		}

		if($redirect_hash['iframe']) {
			$redirect_hash['timeout'] = 1;
			$_SESSION['redirect_hash'] = $redirect_hash;
			return $this->render('3dsecure.html.php', array());
		} else {
			return $this->render('pay_redirect.html.php', array("redirect_hash" => $redirect_hash));
		}
	}

	public function showConfirmationAction($results = null) {
		if(!isset($results) || !is_array($results)) {
			$main = new Main($this->get('translator'));
			$results = $main->confirmation();
		}
		if($results['back_to_payment'] && $results['back_to_payment'] != '3dsecure') {
			$_SESSION['reload_step'] = 'payment';
			return $this->showPaymentAction();
		} else {
			$this->error->add(MC_HINT, null, $results['confirmation_message']);
			if($_SESSION['order']['doc_balance'] == 0 && $_SESSION['order']['doc_total'] > 0) {
				$this->error->add(MC_HINT, 'YOUR_ORDER_HAS_BEEN_PAID');
			}
			if(in_array($_SESSION['payment_status'], array(PP_PROC_POSTPONED, PP_PROC_3DSECURE))) {
				$this->error->get(MC_HINT, true);
				$this->error->add(MC_WARN, 'PAYMENT_STATUS_UNKNOWN_PLEASE_WAIT');
			} else {
				$this->error->add(MC_WARN, 'WE_SUGGEST_THAT_YOU_PRINT');
			}
			unset($results['confirmation_message']);
			return new Response(
				$this->renderView('confirmation.html.php', array('results' => $results))
			);
		}
	}

	public function paymentStatusAction() {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$results = $main->get_payment_status();
			sw_log_debug('results: '.dumper($results));
			if($results['back_to_payment'] == '3dsecure' && $results['redirect_hash']) {
				$_SESSION['reload_step'] = 'payment';
				$results['content'] = $this->renderView('3dsecure.html.php', array());
			} else if($results['back_to_payment']) {
				$_SESSION['reload_step'] = 'payment';
				$results['content'] = $this->showPaymentAction(true);
			} else {
				if($_SESSION['payment_status'] != PP_PROC_POSTPONED) {
					$results['confirmation_message'] = $results['confirmation_message'];
					$results['confirmation_message_warn'] = $this->get('translator')->trans('WE_SUGGEST_THAT_YOU_PRINT');
					$results['stop_check'] = 1;
				}
			}
			$response = new Response(json_encode($results));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}
	
	public function getAccountingDataAction() {
		$request = $this->getRequest();
		return new Response(
			$this->renderView('accounting.html.php', array('action' => 'show_accounting_data'))
		);
	}

	public function getPlanGroupAction($id) {
sw_log("id => $id");
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			unset($_SESSION['reload_step']);
			$main = new Main($this->get('translator'));
			$plan_grouping = $main->get_initial_data(false);
			$result = Array(
				'result' => 'success',
				'html' => '',
				'messages' => '',
			);
			foreach($plan_grouping AS $group_item_id => $group_item) {
				foreach($group_item['items'] as $item_id => $item) {
					if($item_id != $id) {
						continue;
					} else {
						## determine selected plan
						$plan_selected = null;
						$first_plan = null;						
						foreach($item['plans'] as $plan) {
							if(!$first_plan) {
								$first_plan = $plan['series_key'];
							}
							if(
								(
									isset($_SESSION['shopping_cart']) && 
									isset($_SESSION['shopping_cart']['plan_id']) && 
									$_SESSION['shopping_cart']['plan_id'] == $plan['series_key']
								)
								||
								(
									!isset($_SESSION['shopping_cart']) ||
									!isset($_SESSION['shopping_cart']['plan_id'])
								)
							) {
								$plan_selected = $plan['series_key'];
								break;
							}
						}
						if(!$plan_selected) {
							## when something from another group was chosen before, set as default first plan from current group
							$plan_selected = $first_plan;
						}
						$result['html'] = $this->renderView('tabs_plans_choise.html.php', array('item_id' => $item_id, 'item' => $item, 'plan_selected' => $plan_selected, 'summary' => $group_item['summary']));
						break 2;
					}
				}
			}
			if(!$result['html']) {
				## package can be either not found or available for resellers only
				$result['result'] = 'error';
				$result['messages'] = array(array('error', nl2br($this->get('translator')->trans('PACKAGE_FOR_RESELLERS_OR_NOT_FOUND'))));
			}
			$response = new Response(json_encode($result));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function getDomainsTabAction() {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			unset($_SESSION['reload_step']);
			$result = Array(
				'result' => 'success',
				'html' => '',
				'update_cart' => 0
			);
			$domains = new Domains($this->get('translator'));
			$dm_plan_id = null;
			if(!isset($_SESSION['domain_package']['default'])) {
				$this->error->add(MC_WARN, 'DOMAIN_MANAGER_NOT_CONFIGURED');
			} else {
				$dm_plan_id = $_SESSION['domain_package']['default']['assigned_dm_plan'];
			}
			$result['html'] = $this->renderView('domains_tab.html.php', array());
			if($dm_plan_id && is_array($_SESSION['domains'][$dm_plan_id])) {
				$result['update_cart'] = 1;
			}
			$response = new Response(json_encode($result));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function getPeriodsAction($plan_id) {
		sw_log_debug('plan_id => '.$plan_id);
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			if(hp_for_reseller_only($_SESSION['plans'][$plan_id], true)) {
				return new Response();
			}
			## determine selected period
			$period_selected = $this->guessPeriod($_SESSION['plans'][$plan_id]);
			return $this->render('fee_list.html.php',
				array('plan' => $_SESSION['plans'][$plan_id], 'period_selected' => $period_selected)
			);
		}
	}

	public function guessPeriod($plan) {
		$period_selected = array();
		foreach($plan['fee_list'] as $fee) {
			## if nothing was selected yet, or trial period was selected, use first period
			## if concrete period was selected already, use it
			if(
				(is_array($_SESSION['shopping_cart']) && $_SESSION['shopping_cart']['period'] == $fee['period'])
				||
				(
					is_array($_SESSION['shopping_cart']) && 
					(
						$_SESSION['shopping_cart']['period'] == 'trial' ||
						$_SESSION['shopping_cart']['period'] == 'undefined'
					)
				)
				||
				(!is_array($_SESSION['shopping_cart']) || !isset($_SESSION['shopping_cart']['period']))
			) {
				$period_selected = $fee;
				break;
			}
		}
		## If none of periods has been matched, use first one
		if(!count($period_selected)) {
			$period_selected = $plan['fee_list'][0];
		}
		return $period_selected;
	}

	public function getContentAction($type, $id, $plan_id) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$plans = $main->get_content($type, $id, $plan_id);
			if(!isset($_SESSION['plans'][$plan_id])) {
				$this->error->add(MC_ERROR, 'PACKAGE_FOR_RESELLERS_OR_NOT_FOUND');
				return $this->render('status_message_fit.html.php', array(id => 'plan_not_found'));
			}
			$main->get_resources();
			$target_list = array();
			if($_SESSION['account']['account_id']) {
				install_error_handler("SubscrNotFound", "handle_hosting_target_list_error");
				$result = call('get_hosting_target_list', array('account_id' => $_SESSION['account']['account_id']), 'HSPC/API/Billing');
				$target_list = $result['hosting_target_list'];
				get_domain_list();
			}
			$period_selected = $this->guessPeriod($_SESSION['plans'][$plan_id]);
			return $this->render('group.html.php',
				array('plans' => $plans, 'target_list' => $target_list, 'plan_id' => $plan_id, 'period_selected' => $period_selected)
			);
		}
	}

	public function getResourcesAction() {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$resources = $main->get_resources(true);
			$response = new Response(json_encode($resources));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function getSignInForm($plan_id) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			if(array_key_exists('is_authorized', $_SESSION) && $_SESSION['is_authorized']) {
				$main = new Main($this->get('translator'));
				$cp_href = $main->cp_href();
				return $this->render('sign_in_result_ok.html.php', array(cp_href => $cp_href, 'plan_id' => $plan_id));
			} else {
				return $this->render('customer_sign_in.html.php', array('plan_id' => $plan_id));
			}
		}
	}

	public function updateResourceAction($plan_id, $short_name, $value) {
sw_log("plan_id => $plan_id, short_name => $short_name, value => $value");
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$main->update_resource($plan_id, $short_name, $value);
			$response = new Response();
			return $response;
		}
	}

	public function updateApplicationAction($plan_id, $panel_id, $app_id, $enable) {
sw_log("plan_id => $plan_id, panel_id => $panel_id, app_id => $app_id, enable => $enable");
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$main->update_application($plan_id, $panel_id, $app_id, $enable);
			return new Response();
		}
	}

	public function updatePanelAction($plan_id, $panel_id, $os_tmpl) {
sw_log("plan_id => $plan_id, panel_id => $panel_id, os_tmpl => $os_tmpl");
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$main->update_panel($plan_id, $panel_id, $os_tmpl);
			return new Response();
		}
	}	

	public function updateLicenseAction($plan_id) {
sw_log("plan_id => $plan_id");
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$main->update_license($plan_id);
			$response = new Response(json_encode(Array('result' => 'success')));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function updateAttributeAction($plan_id, $input_id, $enable, $value) {
sw_log("plan_id => $plan_id, input_id => $input_id, enable => $enable, value => $value");
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$main->update_attribute($plan_id, $input_id, $enable, $value);
			$response = new Response();
			return $response;
		}
	}

	public function updateConfigParamAction($plan_id, $param) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$plan_id = strip_tags($plan_id);
			$param = strip_tags($param);
			$value = $request->getParamVal('value', '_POST');
			$add_params = $request->getParamVal('add_params', '_POST');
			$main = new Main($this->get('translator'));
			$result = $main->set_config_param($plan_id, $param, $value, $add_params);
			$response = new Response(json_encode($result));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function updateConfigurationAction($plan_id) {
		sw_log_debug('plan_id => '.$plan_id);
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			if($plan_id == 'domains') {
				$plan = $_SESSION['domain_package']['default'];
				## replace series key with hardcoded value, when domains are ordered first 
				$plan['series_key'] = 'domains';
			} else {
				$plan = $_SESSION['plans'][$plan_id];
			}
			$target_list = array();
			if($_SESSION['account']['account_id']) {
				install_error_handler("SubscrNotFound", "handle_hosting_target_list_error");
				$result = call('get_hosting_target_list',
											 array('account_id' => $_SESSION['account']['account_id']), 'HSPC/API/Billing');
				$target_list = $result['hosting_target_list'];
			}
			if(hp_for_reseller_only($plan, true)) {
				$this->error->add(MC_ERROR, 'PACKAGE_FOR_RESELLERS_OR_NOT_FOUND');
				return $this->render('status_message_fit.html.php', array(id => 'configuration'));
			}
			if($plan['type']['id'] == HP_TYPE_SSL_SINGLE) {
				$main = new Main($this->get('translator'));
				$ssl_edit_form = '';
				if($result = $main->get_cert_form($plan_id)) {
					$ssl_edit_form = $result;
				}
			}
			return $this->render('plan_configuration.html.php',
				 array('plan' => $plan, 'target_list' => $target_list, 'ssl_edit_form' => $ssl_edit_form)
			);
		}
	}

	public function updateShoppingCartAction($plan_id, $group_id, $os_tmpl, $period, $platform) {
		sw_log("plan_id => $plan_id, group_id => $group_id, os_tmpl => $os_tmpl, period => $period, platform => $platform");
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$result = $main->update_shopping_cart($plan_id, $group_id, $os_tmpl, $period, $platform);
			return $this->render('shopping_cart.html.php', 
				$result ? array("plan_id" => $plan_id, "os_tmpl" => $os_tmpl, "period" => $period, "platform" => $platform) : array()
			);
		}
	}

	public function clearShoppingCartAction($plan_id) {
		unset($_SESSION['shopping_cart']);
		unset($_SESSION['domains']);
		unset($_SESSION['check_domains_cache']);
		$_SESSION['shopping_cart']['plan_id'] = $plan_id;
		return $this->redirect($this->generateUrl('homepage'), 301);
	}

	public function dsRedirectFormAction() {
		return $this->render('3dsredirectform.html.php',
			array("redirect_hash" => $_SESSION['redirect_hash'])
		);
	}

	public function showAboutUsAction () {
		$main = new Main($this->get('translator'));
		return $this->render('general_home.html.php', 
			array(
				'insertion' => $this->renderView('about_us.html.php', array()),
				'cp_href' => $main->cp_href(),
				'adopt_wrapper_padding' => true,
			)
		);
	}

	public function showContactsAction() {
		$request = $this->getRequest();
		$main = new Main($this->get('translator'));
		if($request->isXmlHttpRequest()) {
			if($request->getParamVal('action', '_POST') == 'send') {
				if($main->process_contacts()) {
					return $this->render('status_message_fit.html.php', array(id => 'contacts'));
				}
			}
		}
		return $this->render('general_home.html.php', 
			array(
				'insertion' => $this->renderView('contacts.html.php', array()),
				'cp_href' => $main->cp_href(),
				'adopt_wrapper_padding' => true,
			)
		);
	}

	public function showPartnersAction() {
		$request = $this->getRequest();
		## partner application form available only in provider store
		if($_SESSION['vendor_id'] != 1) {
			return $this->redirect($this->generateUrl('homepage'), 301);
		}

		$main = new Main($this->get('translator'));
		if($request->isXmlHttpRequest()) {
			$main->process_partners();
			return $this->render('partners_content.html.php', array(id => 'reseller_application'));
		} else {
			if(isset($_SESSION['reseller_id'])) {
				$this->error->add(MC_SUCCESS, 'YOUR_PARNER_APPLICATION_HAS_BEEN_SUBMITTED');
			}
		}

		$main->get_extended_attr_list();

		return $this->render('general_home.html.php', 
			array(
				'insertion' => $this->renderView('partners.html.php', array()),
				'cp_href' => $main->cp_href(),
				'adopt_wrapper_padding' => true,				
			)
		);
	}

	public function showInformationAction($source) {
		$agreements = array();
		if(!preg_match('/^(account_agreement_text|order_agreement_text|reseller_agreement_text)$/', $source)) {
			$source = 'account_agreement_text';
		}
		if($source) {
			$agreements = load_agreements(array($source => 1));
		}
		$main = new Main($this->get('translator'));
		return $this->render('general_home.html.php', 
			array(
				'insertion' => $this->renderView('information.html.php', array('title' => $agreements[$source]['title'], 'body' => $agreements[$source]['body'])),
				'cp_href' => $main->cp_href(),
				'adopt_wrapper_padding' => true,				
			)
		);
	}

	public function showWhoisAction($domain = NULL) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$res = array();
			$res['result'] = 'success';
			$res['content'] = '';
			if($domain) {
				## XSS defence
				$whois_domain = preg_replace('/\s+/', '', $domain);
				$whois_domain = escapeshellcmd($whois_domain);
				if($domain === $whois_domain) {
					$res['content'] = $this->renderView('whois.html.php', array('domain' => $domain));
				}
			}
			$response = new Response(json_encode($res));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function getPopupHeader($type, $attr = null) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$res = array();
			$res['result'] = 'success';
			$title = '';
			## currently popups are intended only for domain contacts and whois information,
			## so icon will be the same in both cases
			$class = 'pp-domain-information';
			switch($type) {
				case 'whois':
					## $attr == domain name in this case
					if($attr) {
						$domain = $attr;
						## XSS defence
						$whois_domain = preg_replace('/\s+/', '', $domain);
						$whois_domain = escapeshellcmd($whois_domain);
						if($domain === $whois_domain) {
							$title = sprintf($this->get('translator')->trans('DOMAIN_WHOIS_INFORMATION'), $domain);
						} else {
							$title = sprintf($this->get('translator')->trans('DOMAIN_NAME_INVALID'), '');
						}
					}
				break;

				case 'dm_contact':
					$title = $this->get('translator')->trans('DOMAINS_CONTACTS');
				break;

				default:
				break;
			}
			$res['header'] = $this->renderView('popup_header.html.php', array('class' => $class, 'title' => $title));
			$response = new Response(json_encode($res));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}

	public function domainContactsAction($domain, $contact_type, $contact_id, $action) {
		$request = $this->getRequest();
		$store_apply = $request->getParamVal('store_apply', '_POST');
		sw_log('domain => '.$domain.', contact_type => '.$contact_type.', contact_id => '.$contact_id.', action => '.$action.', store_apply => '.$store_apply);
		if($request->isXmlHttpRequest()) {
			$domains = new Domains($this->get('translator'));
			if($_SESSION['shopping_cart']['plan_id'] == 'domains') {
				$dm_plan_id = $_SESSION['domain_package']['default']['series_key'];
			} else {
				$dm_plan_id = $_SESSION['plans'][$_SESSION['shopping_cart']['plan_id']]['assigned_dm_plan'];
			}
			## save error contacts status before new validation
			$old_invalid_contacts = $domains->get_invalid_contact_list($dm_plan_id);
			$res = $domains->process_domain_contacts($domain, $contact_type, $contact_id, $dm_plan_id);
			$action = $action ? $action : $_SESSION['domain_contacts'][$dm_plan_id]['action'];
			if(is_array($res)) {
				$invalid_contact = $domains->next_step_domain_contacts($domain, $contact_type, $res['contact_id'], $contact_id, $action, $dm_plan_id, $store_apply);
				sw_log_debug('invalid_contact = '.dumper($invalid_contact));
				$tld = get_domain_tld($domain);
				if($invalid_contact) {
					## process next invalid contact in chain mode
					$results = $domains->teaser_domain_contacts($domain, $contact_type, $contact_id, $contact = $invalid_contact, $dm_plan_id);
					$res['result'] = 'success';
					$res['chain_edit'] = 1;
					$res['contact_title'] = $results['contact_title'];
					$res['form_layout'] = $results['form_layout'];
					$res['contact_type'] = $contact_type;
					$res['update_contact'] = $contact_id ? $contact_id : NULL;
					$res['contacts_prefilling_type'] = $_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'];
					$res['store_apply'] = $store_apply;
					$res['create_form_action'] = $results['create_form_action'];
					$res['update_form_action'] = $results['update_form_action'];
					if(isset($_SESSION['contact_error'][$dm_plan_id]) && $_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] == 'configure_manually') {
						$res['contact_error_names'] = $domains->get_domain_contact_error_names($dm_plan_id);
					}
					$res['tld'] = $tld;
					$res['processed_domain'] = $domain;
					$res['current_domain'] = $results['current_domain'];
					$res['invalid_contacts'] = $old_invalid_contacts;
					$res['id'] = 'domain_contact_edit';
					$res['fields'] = $domains->get_domain_contact_errors($results['current_domain'], $results['current_ctype'], $dm_plan_id);
					$res['error_message'] = $this->renderView('status_message.html.php', array(id => $res['id'], 'noscroll' => 1));
					$res['extra_buttons'] = $this->renderView('domain_contacts_buttons.html.php', array('extra_buttons' => $results['extra_buttons'], 'store_apply' => $store_apply));
					$res['invalid_domain_info'] = sprintf($this->get('translator')->trans('INVALID_DOMAINS'), implode(", ", $results['invalid_domain_list']));
					$res['contact_form_title'] = sprintf($this->get('translator')->trans('CREATE_NEW_OR_UPDATE_CONTACT_FOR'), $results['contact_title'], $results['current_domain']);
					$response = new Response(json_encode($res));
					$response->headers->set('Content-Type', 'application/json');
					return $response;
				} else {
					$res['result'] = 'success';
					$res['contact_type'] = $contact_type;
					$res['update_contact'] = $contact_id ? $contact_id : NULL;
					$res['contacts_prefilling_type'] = $_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'];
					$res['store_apply'] = $store_apply;
					if(isset($_SESSION['contact_error'][$dm_plan_id]) && $_SESSION['domain_contacts'][$dm_plan_id]['contacts_prefilling_type'] == 'configure_manually') {
						$res['contact_error_names'] = $domains->get_domain_contact_error_names($dm_plan_id);
					}
					$res['tld'] = $tld;
					$res['processed_domain'] = $domain;
					$res['invalid_contacts'] = $old_invalid_contacts;
					$response = new Response(json_encode($res));
					$response->headers->set('Content-Type', 'application/json');
					return $response;
				}
			} else {
				$res['result'] = 'error';
				$res['id'] = 'domain_contact_edit';
				$res['fields'] = $domains->get_domain_contact_errors($domain, $contact_type, $dm_plan_id);
				$res['error_message'] = $this->renderView('status_message.html.php', array(id => $res['id'], 'noscroll' => 1));
				$tld = get_domain_tld($domain);
				$type_info = $domains->get_domain_contact_info($domain, $contact_type, $dm_plan_id);
				$extra_buttons = $domains->get_radio_buttons($tld, $contact_type, $type_info, $dm_plan_id);
				$res['extra_buttons'] = $this->renderView('domain_contacts_buttons.html.php', array('extra_buttons' => $extra_buttons, 'store_apply' => $store_apply));
				$response = new Response(json_encode($res));
				$response->headers->set('Content-Type', 'application/json');
				return $response;
			}
		}
	}
	
	public function parsecsrAction($plan_id) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$ssl_csr_data = $main->get_parsed_csr_data($plan_id);
			if($ssl_csr_data['parse_error']) {
				$this->error->add(MC_ERROR, 'SSL_VALIDATE_ERROR_CSR');
				$ssl_csr_data['error_message'] = $this->renderView('status_message.html.php', array(id => 'configuration'));
				$ssl_csr_data['fields'] = array('ssl_csr_csr');
			} else {
				$ssl_csr_data['details'] = $this->renderView('csr_details.html.php', array('ssl_csr_data' => $ssl_csr_data));
			}
			$response = new Response(json_encode($ssl_csr_data));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}
	
	public function getApproverListAction($plan_id) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$res = $main->get_approver_email_list($plan_id);
			if($res['result'] == 'error') {
				$res['error_message'] = $this->renderView('status_message.html.php', array(id => 'configuration'));
			}
			$response = new Response(json_encode($res));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}
	
	public function getCertFormAction($plan_id) {
		$request = $this->getRequest();
		if($request->isXmlHttpRequest()) {
			$main = new Main($this->get('translator'));
			$res['result'] = 'error';
			if($result = $main->get_cert_form($plan_id)){
				$res['result'] = 'success';
				$res['response'] = $result;
			}
			$response = new Response(json_encode($res));
			$response->headers->set('Content-Type', 'application/json');
			return $response;
		}
	}
}

class ControllerHelper implements ArrayAccess {
	private $request;
	private $translator;
	private $templates_path;
	private $public_customized; // 1 - customized, -1 not customized, 0 or other not defined yet
	private $template_customized; // 1 - customized, -1 not customized, 0 or other not defined yet
	private $vendor_id;
	private $web_dir;

	function __construct($request, $router) {
		$this->request = $request;
		$this['router'] = $router;

		$this->templates_path = $GLOBALS['StoreConf']['TEMPLATE_DIR'];
		$this->translator = new Translate($request->getLocale());
		$this['error'] = get_error_handler();

		if(strtoupper($request->getLocale()) != 'EN') {
			$this->translator_def = new Translate('en');
		}

		$this->vendor_id = $_SESSION['vendor_id'];
	}

	private function getWebDir () {
		if(null === $this->WebDir) {
			$script = $this->request->server->get('SCRIPT_FILENAME');
			preg_match ("/(.*)\/.*$/", $script, $matches);
			$this->WebDir = $matches[1];
		}
		return $this->WebDir;
	}

	public function getUrl($file_path) {
		$custom_url_prf = '/vendor/' . $this->vendor_id;
		if(null === $this->public_customized) {
			$cst_dir = sprintf($GLOBALS['StoreConf']['CUSTOM_STAT_DIR'], $this->vendor_id);

			if(is_dir($cst_dir)) {
				$this->public_customized = 1;
				if($cst_dir != readlink($this->getWebDir().$custom_url_prf)) {
					unlink($this->getWebDir().$custom_url_prf);
					symlink($cst_dir, $this->getWebDir().$custom_url_prf);
				}
			} else {
				$this->public_customized = -1;
			}
		}
		if($this->public_customized == 1) {
			if(
				!$GLOBALS['StoreConf']['CHECK_STATIC_BY_STORE'] ||
				file_exists($this->getWebDir().'/vendor/'.$this->vendor_id.$file_path)
			) {
				$file_path = '/vendor/'.$this->vendor_id.$file_path;
			}
		}

		return $this->request->getBasePath() . $file_path;
	}

	public function trans($str, $params = array()) {
		$value = $this->translator->get($str, $params);
		if(!$value && $this->translator_def) {
			$value = $this->translator_def->get($str, $params);
		}

		if(!$value)
			$value = "Error: STRING ($str) NOT FOUND!";
		return $value;
	}

	public function generateUrl($route, $parameters = array(), $absolute = false) {
		return $this['router']->generate($route, $parameters, $absolute);
	}

	public function render($__path__, $params = array()) {
		sw_log_debug('Render template: '.$__path__);
		$view               = $this;
		$view['assets']     = $this;
		$view['translator'] = $this;
		$view['request']    = $this;

		$cst_dir = sprintf($GLOBALS['StoreConf']['CUSTOM_TMPL_DIR'], $this->vendor_id );

		if(null === $this->template_customized) {
			$this->template_customized =
			  is_dir($cst_dir) ? 1 : -1;
		}

		$cst_tmpl_file = $cst_dir.$__path__;
		$__content__ = null;
		if($this->template_customized == 1 && file_exists($cst_tmpl_file)) {
			$__content__ = file_get_contents($cst_tmpl_file);
		} else {
			$__content__ = file_get_contents($GLOBALS['StoreConf']['TEMPLATE_DIR'].$__path__);
		}

		if(!$__content__) {
			sw_log_error('Template '.$GLOBALS['StoreConf']['TEMPLATE_DIR'].$__path__.' not found');
			return 'Template '.$GLOBALS['StoreConf']['TEMPLATE_DIR'].$__path__.' not found';
		}

		extract($params, EXTR_SKIP);
		ob_start();

		eval('; ?>'.$__content__.'<?php ;');
		$str = ob_get_clean();
		return $str;
	}

	public function getParamVal($key, $from = null, $default = null, $deep = false) {
		return $this->request->getParamVal($key, $from, $default, $deep);
	}

	public function setParamVal($key, $from, $val) {
		return $this->request->setParamVal($key, $from, $val);
	}

	public function getLocale() {
		return $this->request->getLocale();
	}

	public function getClientIp() {
		return $this->request->getClientIp();
	}

	// ArrayAccess methods
	public function offsetExists($offset) {
		return isset($this->_container[$offset]);
	}

	public function offsetGet($offset) {
		return $this->offsetExists($offset) ? $this->_container[$offset] : null;
	}

	public function offsetSet($offset, $value) {
		if(is_null($offset)) {
			$this->_container[] = $value;
		} else {
			$this->_container[$offset] = $value;
		}
	}

	public function offsetUnset($offset) {
		unset($this->_container[$offset]);
	}
	// /ArrayAccess methods
}
