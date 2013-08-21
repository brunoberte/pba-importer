<?php
//////////
// Parent class for all Entity classes 
//
// $Id: Base.php 873372 2013-05-07 14:05:20Z dkolvakh $
//////////

namespace Entity;

class Base {

	public $translator;
	public $error;

	public function __construct($translator) {
		$this->translator = $translator;
		$this->error = get_error_handler();
	}

	public function string($string) {
		return $this->translator->trans($string);
	}

	public function generateUrl($route, $parameters = array(), $absolute = false) {
		return $this->translator->generateUrl($route, $parameters, $absolute);
	}

	public function rollback_step() {
		$this->teaser_step();
		return;
	}

}