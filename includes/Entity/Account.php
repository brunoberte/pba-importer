<?php
//////////
// Person/Account related methods
//
// $Id: Account.php 887409 2013-06-18 05:34:24Z dkolvakh $
//////////

namespace Entity;

use Entity\Base;

class Account extends Base {

	####
	## Authorize person by email/password
	## Load person/account details
	public function sign_in() {
		$login_to_cp = 1;
		$account_type = $this->translator->getParamVal('account_type', '_POST', ACCOUNT_TYPE_CUSTOMER);
		unset($_SESSION['account'], $_SESSION['person'], $_SESSION['is_authorized']);
		$result = call( 'auth_person',
						array ( 'email'			=> $this->translator->getParamVal('email', '_POST'),
								'password'		=> $_POST['password'],	## password may contain escapeable symbols
								'ip'			=> $this->translator->getClientIp(),
								'sid'			=> $this->translator->getParamVal('sid', '_POST'),
								'login_to_cp'	=> $login_to_cp), 'HSPC/API/Person');
		$transport = get_api_transport();
		if($transport->fault) {
			$this->handle_signin_error($transport);
		} else if(is_array($result)) {
			$_SESSION['person'] = $result['person'];

			if($login_to_cp && $result['sid']) {
				$_SESSION['sid'] = $result['sid'];
			}

			if(is_array($_SESSION['person']['account_list'])) {
				foreach($_SESSION['person']['account_list'] AS $key => $value) {
					if(preg_match('/'.$value['type'].'/', $account_type)) {
						$_SESSION['account'] = $value;
						break;
					}
				}
			}
		}

		if(isset($_SESSION['account']) && $_SESSION['account']) {
			$_SESSION['is_authorized'] = true;
			$_SESSION['credentials']['account_password'] = $_POST['password'];
			if(!isset($_SESSION['credentials']['password']) || !$_SESSION['credentials']['password']) {
				$_SESSION['credentials']['password'] = $_POST['password'];
				$_SESSION['credentials']['password_source'] = 'use_account';
			}
			get_domain_list();
			load_domain_contacts();
			$this->error->add(MC_SUCCESS, 'YOU_BEEN_LOGGED_IN');
			return true;
		} else {
			unset($_SESSION['person']);
			if(!$this->error->has(MC_ERROR)) {
				$this->error->add(MC_ERROR, 'LOGIN_INVALID');
			}
		}

		return false;
	}

	####
	## Register new account && authorize person
	public function register_new() {
		$fields = array(
			'address1', 'address2', 'city', 'comment', 'country', 'email', 'fax_src', 'first_name',
			'gender', 'insertion', 'company_name', 'lang', 'last_name', 'middle_name', 'mobile_src',
			'phone_src', 'prefix', 'state', 'suffix', 'zip', 'tax_ex_number', 'timezone'/* , 'house_num', 'house_suff' */
		);
		$clean_data = $this->_fill_account_data($fields);

		$clean_data['password'] = $_POST['password'];	## password may contain escapeable symbols
		$clean_data['fraud_check'] = 1;
		$clean_data['ip_address'] = $this->translator->getClientIp();

		install_error_handler(array('UserPerson', 'NewAccountsDenied', 'UserExtData', 'InvalidAccountStatus'), 'handle_register_new_account_error');
		$account_id = call('create_customer', $clean_data, 'HSPC/API/Account');
		if($account_id) {
			$this->error->add(MC_SUCCESS, 'YOU_BEEN_REGISTERED');
			return $this->sign_in();
		} else {
			return false;
		}
	}

	####
	## Create inactive reseller account (upon submitting partner application)
	public function create_reseller() {
		$fields = array(
			'address1', 'address2', 'city', 'comment', 'country', 'email', 'fax_src', 'first_name',
			'gender', 'insertion', 'company_name', 'url', 'lang', 'last_name', 'middle_name', 'mobile_src',
			'phone_src', 'prefix', 'state', 'suffix', 'zip', 'tax_ex_number'/* , 'house_num', 'house_suff' */
		);
		$clean_data = $this->_fill_account_data($fields);

		return call('create_reseller', $clean_data, 'HSPC/API/Account');
	}

	function _fill_account_data($fields) {
		$request_fields = array(
			'address1', 'address2', 'city', 'country', 'state', 'state_alt', 'zip', 'email',
			'phone_country_code', 'phone_area_code', 'phone_number', 'phone_extension',
			'mobile_country_code', 'mobile_area_code', 'mobile_number',
			'fax_country_code', 'fax_area_code', 'fax_number', 'fax_extension',
			'gender', 'prefix', 'suffix', 'first_name', 'insertion', 'last_name', 'middle_name', 'lang',
			'tax_ex_number', 'timezone', 'company_name', 'url', 'comment'/* ,'house_num', 'house_suff' */
		);
		$data = array();
		foreach($request_fields as $key) {
			$data[$key] = html_entity_decode($this->translator->getParamVal($key, '_POST'));
		}

		$clean_data = array();

		if(!in_array($data['country'], array('US', 'CA', 'BR'))) {
			$data['state'] = $data['state_alt'];
		}

		$data['phone_src'] = $data['phone_country_code'].'|'.$data['phone_area_code'].'|'.$data['phone_number'].'|'.$data['phone_extension'];
		$data['mobile_src'] = $data['mobile_country_code'].'|'.$data['mobile_area_code'].'|'.$data['mobile_number'].'|';
		$data['fax_src'] = $data['fax_country_code'].'|'.$data['fax_area_code'].'|'.$data['fax_number'].'|'.$data['fax_extension'];

		// Don't let phone prefilling spoil everything
		$data['phone_src'] = $data['phone_number'] ? $data['phone_src'] : '|||';
		$data['mobile_src'] = $data['mobile_number'] ? $data['mobile_src'] : '|||';
		$data['fax_src'] = $data['fax_number'] ? $data['fax_src'] : '|||';

		## Do not pass 'empty' values
		if($data['gender'] == '-') {
			unset($data['gender']);
		}
		if($data['prefix'] == '-') {
			unset($data['prefix']);
		}
		if($data['country'] == '-') {
			unset($data['country']);
		}

		foreach($data AS $key => $value) {
			if(in_array($key, $fields)) {
				$clean_data[$key] = $value;
			}
		}

		if(array_key_exists('extended_attr_list', $_SESSION) && count($_SESSION['extended_attr_list'])) {
			foreach($_SESSION['extended_attr_list'] AS $ext_attribute) {
				$clean_data['ext_data'][] = array($ext_attribute['view_name'], $this->translator->getParamVal($ext_attribute['view_name'], '_POST'));
			}
		}
		return $clean_data;
	}

	function handle_signin_error($transport) {
		## clean already existing messages
		$this->error->get(MC_INTERR, true);
		$this->error->get(MC_ERROR, true);

		switch($transport->faultcode) {
			case 'soap:UserAuthen':
			case 'soap:AuthzError':
			case 'soap:WrongParams':
			case 'soap:MissingPerson':
			case 'soap:PersonsDenied':
			case 'soap:LoginError':
				$this->error->add(MC_ERROR, 'LOGIN_INVALID');
			break;

			case 'soap:UserDelayed':
				$this->error->add(MC_ERROR, 'LOGIN_DELAYED');
			break;

			case 'soap:UserFraudError':
				$this->error->add(MC_ERROR, null, $transport->faultstring);
			break;

			default:
				$this->error->add(MC_ERROR, $transport->faultstring ? null : 'LOGIN_INVALID', $transport->faultstring ? $transport->faultstring : null);
			break;
		}
	}

}