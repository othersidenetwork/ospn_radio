<?php

namespace OSPN;

class ErrorLogLogger extends BaseLogger {

	public function __construct($category) {
		parent::__construct($category);
	}

	public function log($msg) {
		if ($this->isEnabled()) {
			error_log($this->format($msg), 4);
		}
	}

}