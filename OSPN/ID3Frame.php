<?php 

namespace OSPN;

class ID3Frame {

	public function __construct($tag, $data) {
		$this->tag = $tag;
		$this->data = $data;
	}

	public function getData() {
		$data = $this->tag;

		// Frame tag
		$size = strlen($this->data);

		// Frame size
		$z3 = $size & 0x7F;
		$size >>= 7;
		$z2 = $size & 0x7F;
		$size >>= 7;
		$z1 = $size & 0x7F;
		$size >>= 7;
		$z0 = $size & 0x7F;
		$data .= chr($z0);
		$data .= chr($z1);
		$data .= chr($z2);
		$data .= chr($z3);

		// Frame flags
		$data .= chr(0);
		$data .= chr(0);
		$data .= $this->data;

		return $data;		
	}
}
