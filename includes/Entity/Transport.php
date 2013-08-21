<?php
//////////
// Transport class, implements SOAP client methods
//
// $Id: Transport.php 888348 2013-06-19 15:00:06Z dkolvakh $
//////////

namespace Entity;

use SoapClient;
use SoapHeader;

class Transport {
	private $soapClient;

	public function getSoapClient() {
		return $this->soapClient;
	}
	
	public function setSoapClient($client) {
		$this->soapClient = $client;
	}

	public function openSession($lang_id) {
		ini_set("default_socket_timeout", 600);
		$this->soapClient = new SoapClient(
			NULL,
			array(
				"location" => $GLOBALS['StoreConf']['HSPCOMPLETE_SERVER'].'/hspc/xml-api',
				"uri"      => 'HSPC/API/'.CURRENT_API_VERSION,
				"style"    => SOAP_RPC,
				"use"      => SOAP_ENCODED,
				"connection_timeout" => 600
			)
		);

		$result = $this->call(
			'session_open',
			array(
				'server_name' => $GLOBALS['StoreConf']['SERVER_NAME'],
				'secure_key'  => isset($GLOBALS['StoreConf']['SECURE_KEY']) ? $GLOBALS['StoreConf']['SECURE_KEY'] : NULL,
				'email'       => isset($GLOBALS['StoreConf']['VENDOR_EMAIL']) ? $GLOBALS['StoreConf']['VENDOR_EMAIL'] : NULL,
				'password'    => isset($GLOBALS['StoreConf']['VENDOR_PASSWORD']) ? $GLOBALS['StoreConf']['VENDOR_PASSWORD'] : NULL,
				'lang_id'     => $lang_id
			),
			'HSPC/API/'
		);
		
		if($this->fault) {
			$result = $this->call(
				'session_open',
				array(
					'email'    => isset($GLOBALS['StoreConf']['VENDOR_EMAIL']) ? $GLOBALS['StoreConf']['VENDOR_EMAIL'] : NULL,
					'password' => isset($GLOBALS['StoreConf']['VENDOR_PASSWORD']) ? $GLOBALS['StoreConf']['VENDOR_PASSWORD'] : NULL,
					'lang_id'  => $lang_id
				),
				'HSPC/API/'
			);
		}
		
		if(!$this->fault) {
			$this->soapClient->__setSoapHeaders(new SoapHeader('ns', 'HSPC-SID', $result["session_id"]));
		}
		return $result;
	}

	private function objectToArray($d) {
		if(is_object($d)) {
			$d = get_object_vars($d);
		}

		if(is_array($d)) { 
			return array_map( array($this, __FUNCTION__), $d);
		} else {
			return $d;
		}
	}

	public function call($function, $params, $namespace) {
		$time_start = microtime(true);
		if(!preg_match('/\/\d+\.\d+$/', $namespace)) {
			$namespace .= '/'.CURRENT_API_VERSION;
		}
		sw_log_debug(compact_string($function.', '.dumper($params).', '.$namespace));
		$result = '';
		$this->fault = 0;
		$this->faultcode ='';
		$this->faultstring='';
		try {
			$result = $this->soapClient->__soapCall(
				$function,
				$params ? array($params) : array(),
				array( "uri" => $namespace )
			);
			$result = $this->objectToArray ($result);
		} catch (\Exception $e) {
			$this->fault = 1;
			$this->faultcode = $e->faultcode;
			$this->faultstring = $e->faultstring;
			$this->detail = $this->objectToArray ( $e->detail );
			
			$result["detail"] = $this->detail;
			$result["faultcode"] = $this->faultcode;
			$result["faultstring"] = $this->faultstring;

			sw_log_error("API error in call($function): " . $e->faultstring);
		}
		$time_end = microtime(true);
		sw_log_debug(compact_string(sprintf('%0.2fs', $time_end - $time_start).', Result: '.dumper($result)));
		return $result;
	}
}

?>