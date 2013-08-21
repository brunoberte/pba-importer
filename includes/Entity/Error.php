<?php
//////////
// On-screen messages handling methods with logging
//
// $Id: Error.php 880019 2013-05-28 14:29:12Z dkolvakh $
//////////

namespace Entity;

class Error {
	private $translator;
	private $classes;
	private $messages;

	## define log methods for corresponding message class
	private $logger = Array(
		MC_ERROR => 'sw_log_error',
		MC_WARN => 'sw_log_warn',
		MC_SUCCESS => 'sw_log',
		MC_HINT => 'sw_log',
		MC_INTERR => 'sw_log_error',
	);

	####
	## Gather messages from session and initialize classes
	function __construct() {
		$this->classes = array_keys($this->logger);
		foreach($this->classes as $class) {
			$this->messages[$class] = isset($_SESSION[$class.'_message']) ? $_SESSION[$class.'_message'] : Array();
		}
	}

	####
	## Store messages to session
	function __destruct() {
		foreach($this->classes as $class) {
			$_SESSION[$class.'_message'] = $this->messages[$class];
		}
	}

	####
	## Set translator, needed to correctly handle non-localized messages
	function setTranslator($translator) {
		if(!isset($translator)) {
			sw_log_error('translator is not defined');
			return false;
		}
		$this->translator = $translator;
		return true;
	}

	####
	## Get messages of given class and flush stack
	## >> class = error|warning|success|hint
	## > flush = 1|0, default=1
	## << string, joined with MC_DIVIDER|false on error
	public function get($class, $flush = 1) {
		if(!in_array($class, $this->classes)) {
			sw_log_error('class "'.$class.'" not defined');
			return false;
		}
		$message = nl2br(join(MC_DIVIDER, $this->messages[$class]));
		if($flush) {
			$this->messages[$class] = Array();
		}
		return $message;
	}

	####
	## Add message of given class to stack
	## >> class = error|warning|success|hint
	## > msg_id - string id, will be localized internally
	## > msg - rendered string (f.e. with substitutions made)
	## << true|false on error
	public function add($class, $msg_id = '', $msg = '') {
		if(!in_array($class, $this->classes)) {
			sw_log_error('class "'.$class.'" not defined');
			return false;
		}
		if($msg_id) {
			$msg = $this->translator->trans($msg_id);
		}
		$this->logger[$class]('called from "'.get_backtrace(1).'", class="'.$class.'", message="'.($msg_id ? $msg_id : $msg).'"');
		array_push($this->messages[$class], $msg);
		return true;
	}

	####
	## Is messages of given class set?
	## > class = any|error|warning|success|hint, default='' (any of class)
	## << true|false
	public function has($class = 'any') {
		if($class != 'any' && !in_array($class, $this->classes)) {
			sw_log_error('class "'.$class.'" not defined');
			return false;
		}
		if($class == 'any') {
			## was any class set?
			foreach($this->classes as $class) {
				if(count($this->messages[$class])) {
					return true;
				}
			}
			return false;
		} else {
			return count($this->messages[$class]) ? true : false;
		}
	}

	public function trans($str) {
		return $this->translator->trans($str);
	}

}
