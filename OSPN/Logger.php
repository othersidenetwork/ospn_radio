<?php

namespace OSPN;

interface Logger {

	public function log($msg);
	public function disable();
	public function enable();

}

