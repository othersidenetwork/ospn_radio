<?php

namespace OSPN;

class LiquidSoapClient {

	private $socket;
	private $connected;


	public function __construct($host, $port) {
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$this->connected = socket_connect($this->socket, $host, $port);
	}

	public function write($msg) {
		$msg = $msg . "\n";
		socket_write($this->socket, $msg, strlen($msg));
		return $this;
	}

	public function read(&$data) {
		$data = trim(socket_read($this->socket, 4094, PHP_NORMAL_READ)); socket_read($this->socket, 1, PHP_NORMAL_READ);
		return $this;
	}

	public function end() {
		$this->read($end);
		return $this;
	}
}
