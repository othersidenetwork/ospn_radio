<?php

namespace OSPN;

abstract class BaseLogger implements Logger {

	private $category;
	private $enabled;

	protected function __construct($category) {
		$this->category = $category;
		$this->enabled = true;
	}

	public function enable() {
		$this->enabled = true;
	}

	public function disable() {
		$this->enabled = false;
	}

	protected function isEnabled() {
		return $this->enabled;
	}

	protected function format($msg) {
		$now = new \DateTime();
		return $now->format("Y/m/d H:i:s") . " [" . $this->category . "] " . $msg;
	}
}
