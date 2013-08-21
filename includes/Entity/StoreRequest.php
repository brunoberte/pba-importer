<?php
//////////
// Custom Request class, inherited from Symfony\Component\HttpFoundation\Request class
// Implements incoming parameters safe fetch method against XSS injection
//
// $Id: StoreRequest.php 885824 2013-06-13 08:20:29Z dkolvakh $
//////////

namespace Entity;

use Symfony\Component\HttpFoundation\Request;

class StoreRequest extends Request {

	public function getParamVal($key, $from = null, $default = null, $deep = false) {
		$val = null;
		switch ($from) {
			case '_POST':
				$val = $this->request->get($key, $default, $deep);
			break;
			case '_GET':
				$val = $this->query->get($key, $default, $deep);
			break;
			default:
				$val = parent::get($key, $default, $deep);
			break;
		}
		if(is_array($val)) {
			foreach($val as $k => $v) {
				$val[$k] = htmlspecialchars(strip_tags($v));
			}
		} else {
			$val = htmlspecialchars(strip_tags($val));
		}
##sw_log_debug('$key: '.$key.' $from: '.$from.'; $val='.dumper($val));
		return $val;
	}

	public function setParamVal($key, $from, $val) {
##sw_log_debug('$key: '.$key.' $from: '.$from.'; $val='.$val);
		switch ($from) {
			case '_POST':
				$this->request->set($key, $val);
			break;
			case '_GET':
				$this->query->set($key, $val);
			break;
			default:
				sw_log_error('Unexpected parameter value: from="'.$from.'"');
			break;
		}
	}

}