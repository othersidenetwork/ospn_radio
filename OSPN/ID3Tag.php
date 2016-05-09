<?php 

namespace OSPN;

class ID3Tag {

	private $ufid;
	private $artist;
	private $title;

	public function setUFID($ufid) {
		$this->ufid = $ufid;
	}

	public function setArtist($artist) {
		$this->artist = $artist;
	}

	public function setTitle($title) {
		$this->title = $title;
	}

	public function write($fd) {
		$frames = $this->unsynchronize($this->buildFrames());

		// ID3v2/file identifier
		fwrite($fd, "ID3");

		// Major/minor version
		fwrite($fd, chr(4));
		fwrite($fd, chr(0));

		// Flags
		fwrite($fd, chr(0x90));

		// Tag size
		$size = strlen($frames);
		$z3 = $size & 0x7F;
		$size >>= 7;
		$z2 = $size & 0x7F;
		$size >>= 7;
		$z1 = $size & 0x7F;
		$size >>= 7;
		$z0 = $size & 0x7F;
		fwrite($fd, chr($z0));
		fwrite($fd, chr($z1));
		fwrite($fd, chr($z2));
		fwrite($fd, chr($z3));

		fwrite($fd, $frames);

		// ID3v2/file identifier
		fwrite($fd, "3DI");

		// Major/minor version
		fwrite($fd, chr(4));
		fwrite($fd, chr(0));

		// Flags
		fwrite($fd, chr(0x90));

		// Tag size
		fwrite($fd, chr($z0));
		fwrite($fd, chr($z1));
		fwrite($fd, chr($z2));
		fwrite($fd, chr($z3));

		fflush($fd);
	}

	private function buildFrames() {
		
		$frames = "";

		// First frame is our UFID
		$data = "yannick.mauray@otherside.network" . chr(0) . $this->ufid;
		$ufid = new ID3Frame("UFID", $data);
		$frames .= $ufid->getData();

		// Next is the artist
		$data = chr(3) . $this->artist;
		$tpe1 = new ID3Frame("TPE1", $data);
		$frames .= $tpe1->getData();

		// Then the title
		$data = chr(3) . $this->title;
		$tit2 = new ID3Frame("TIT2", $data);
		$frames .= $tit2->getData();

		return $frames;
	}

	private function unsynchronize($data) {
		$unsynchronized = "";

		for ($i = 0; $i < strlen($data); $i++) {
			$c = ord($data[$i]);
			if ($c == 0xFF && ($i + 1) < strlen($data)) {
				$d = ord($data[$i + 1]);
				if ($d == 0x00 || ($d & 0xE0) == 0xE0) {
					$unsynchronized .= chr($c);
					$unsynchronized .= chr(0x00);
					$unsynchronized .= chr($d);
				} else {
					$unsynchronized .= chr($c);	
				}
			} else {
				$unsynchronized .= chr($c);
			}
		}

		return $unsynchronized;
	}

}
