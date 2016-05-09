<?php
/*
//sample usage:
$mp3file = new MP3File("npr_304314290.mp3");//http://www.npr.org/rss/podcast.php?id=510282
$duration1 = $mp3file->getDurationEstimate();//(faster) for CBR only
$duration2 = $mp3file->getDuration();//(slower) for VBR (or CBR)
echo "duration: $duration1 seconds"."\n";
echo "estimate: $duration2 seconds"."\n";
echo MP3File::formatTime($duration2)."\n";
**/

namespace OSPN;

class MP3File
{
	protected $filename;

	public function __construct($filename)
	{
		$this->filename = $filename;
	}

	public static function formatTime($duration) //as hh:mm:ss
	{
		//return sprintf("%d:%02d", $duration/60, $duration%60);
		$hours = floor($duration / 3600);
		$minutes = floor( ($duration - ($hours * 3600)) / 60);
		$seconds = $duration - ($hours * 3600) - ($minutes * 60);
		return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
	}

	//Read first mp3 frame only...  use for CBR constant bit rate MP3s
	public function getDurationEstimate()
	{
		return $this->getDuration($use_cbr_estimate=true);
	}

	//Read entire file, frame by frame... ie: Variable Bit Rate (VBR)
	public function getDuration($use_cbr_estimate=false)
	{
		$fd = fopen($this->filename, "rb");

		$duration=0;
		$block = fread($fd, 100);
		$offset = $this->skipID3v2Tag($block);
		fseek($fd, 0, SEEK_SET);
		$this->parseID3v2Tag($fd);
		fseek($fd, $offset, SEEK_SET);
		while (!feof($fd))
		{
			$block = fread($fd, 10);
			if (strlen($block)<10) { break; }
			//looking for 1111 1111 111 (frame synchronization bits)
			else if ($block[0]=="\xff" && (ord($block[1])&0xe0) )
			{
				$info = self::parseFrameHeader(substr($block, 0, 4));
				fseek($fd, $info['Framesize']-10, SEEK_CUR);
				$duration += ( $info['Samples'] / $info['Sampling Rate'] );
			}
			else if (substr($block, 0, 3)=='TAG')
			{
				fseek($fd, 128-10, SEEK_CUR);//skip over id3v1 tag size
			}
			else
			{
				fseek($fd, -9, SEEK_CUR);
			}
			if ($use_cbr_estimate && !empty($info))
			{ 
				return $this->estimateDuration($info['Bitrate'],$offset); 
			}
		}
		return round($duration);
	}

	private function estimateDuration($bitrate,$offset)
	{
		$kbps = ($bitrate*1000)/8;
		$datasize = filesize($this->filename) - $offset;
		return round($datasize / $kbps);
	}

	private function skipID3v2Tag(&$block)
	{
		if (substr($block, 0,3)=="ID3")
		{
			$id3v2_major_version = ord($block[3]);
			$id3v2_minor_version = ord($block[4]);
			$id3v2_flags = ord($block[5]);
			$flag_unsynchronisation  = $id3v2_flags & 0x80 ? 1 : 0;
			$flag_extended_header    = $id3v2_flags & 0x40 ? 1 : 0;
			$flag_experimental_ind   = $id3v2_flags & 0x20 ? 1 : 0;
			$flag_footer_present     = $id3v2_flags & 0x10 ? 1 : 0;
			$z0 = ord($block[6]);
			$z1 = ord($block[7]);
			$z2 = ord($block[8]);
			$z3 = ord($block[9]);
			if ( (($z0&0x80)==0) && (($z1&0x80)==0) && (($z2&0x80)==0) && (($z3&0x80)==0) )
			{
				$header_size = 10;
				$tag_size = (($z0&0x7f) * 2097152) + (($z1&0x7f) * 16384) + (($z2&0x7f) * 128) + ($z3&0x7f);
				$footer_size = $flag_footer_present ? 10 : 0;
				return $header_size + $tag_size + $footer_size;//bytes to skip
			}
		}
		return 0;
	}

	private function parseID3v2Tag($fd) {
		$identifier = fread($fd, 3);
		if ($identifier == "ID3") {
			$this->majorVersion = ord(fread($fd, 1));
			$this->minorVersion = ord(fread($fd, 1));
			echo "major: {$this->majorVersion}, minor: {$this->minorVersion}\n";
			$flags = ord(fread($fd, 1));
			$this->flag_unsynchronisation  = $flags & 0x80 ? 1 : 0;
			$this->flag_extended_header    = $flags & 0x40 ? 1 : 0;
			$this->flag_experimental_ind   = $flags & 0x20 ? 1 : 0;
			$this->flag_footer_present     = $flags & 0x10 ? 1 : 0;
			$z0 = ord(fread($fd, 1));
			$z1 = ord(fread($fd, 1));
			$z2 = ord(fread($fd, 1));
			$z3 = ord(fread($fd, 1));
			echo "z0: ${z0}, z1: ${z1}, z2: ${z2}, z3: ${z3}\n";
			$tag_size = (($z0&0x7f) * 2097152) + (($z1&0x7f) * 16384) + (($z2&0x7f) * 128) + ($z3&0x7f);
			echo "tag_size: ${tag_size}\n";
			$block = fread($fd, $tag_size);
			echo "size before : " . strlen($block) . "\n";
			if ($this->flag_unsynchronisation) {
				$block = $this->unsynchronize($block);
			}
			echo "size after : " . strlen($block) . "\n";
			$index = 0;
			if ($this->flag_extended_header) {
				throw new \Exception("Extended headers not implemented yet");
			}
			while (($index < $tag_size) && (ord($block[$index]) != 0)) {
				$frame_id = substr($block, $index, 4);
				$index += 4;
				
				$z0 = ord($block[$index++]);
				$z1 = ord($block[$index++]);
				$z2 = ord($block[$index++]);
				$z3 = ord($block[$index++]);
				if ($this->majorVersion == 3) {
					$frame_size = ($z0 * 16777216) + ($z1 * 65536) + ($z2 * 256) + $z3;	
				} else {
					$frame_size = (($z0&0x7f) * 2097152) + (($z1&0x7f) * 16384) + (($z2&0x7f) * 128) + ($z3&0x7f);	
				}
				
				$flag = ord($block[$index++]);
				$tag_alter_preservation = $flag & 0x40 ? 1 : 0;
				$file_alter_preservation = $flag & 0x20 ? 1 : 0;
				$read_only = $flag & 0x10 ? 1 : 0;

				$flag = ord($block[$index++]);
				$grouping_identity = $flag & 0x40 ? 1 : 0;
				$compression = $flag & 0x08 ? 1 : 0;
				$encryption = $flag & 0x04 ? 1 : 0;
				$unsynchronisation = $flag & 0x02 ? 1 : 0;
				$data_length_indicator = $flag & 0x01 ? 1 : 0;

				if ($compression) throw new \Exception("Compressed frames not supported yet", 1);
				if ($encryption) throw new \Exception("Encrypted frames not supported yet", 1);
				if ($unsynchronisation) throw new \Exception("Unsynchronized frames not supported yet", 1);

				//echo "frame_id = {$frame_id}\n";
				$frame_index = $index;
				if ($frame_id[0] == "T" && $frame_id != "TXXX") {
					$encoding = ord($block[$frame_index++]);
					if ($encoding == 0) {
						//throw new \Exception("ISO-8859-1 encoding is not supported", 1);
					} else if ($encoding == 1) {
						throw new \Exception("UTF-8 encoding is not supported", 1);
					}
					$text_index = $frame_index;
					while (ord($block[$text_index]) != 0 && $text_index < ($index + $frame_size)) {
						$text_index += 1;
					}
					$text = substr($block, $frame_index, $text_index - $frame_index);
					$frame_index = $text_index + 1;
					if ($encoding == 0) {
						$text = utf8_encode($text);
					}

					switch ($frame_id) {
						case "TALB":
							$this->album = $text;
							echo "Album: {$text}\n";
							break;
						case "TIT2":
							$this->title = $text;
							echo "Track: {$text}\n";
							break;
						case "TPE1":
							$this->artist = $text;
							echo "Artist: {$text}\n";
							break;
					}
				}

				$index += $frame_size;
			}
		}
	}

	private function unsynchronize($block) {
		$data = "";
		for ($i = 0; $i < strlen($block); $i++) {
			if (($i + 2) < strlen($block)) {
				$x = ord($block[$i]);
				$y = ord($block[$i + 1]);
				$z = ord($block[$i + 2]);
				if ($x == 0xFF && $y == 0x00 && ($z & 0xE0) == 0xE0) {
					$data .= chr($x);
					$data .= chr($z);
					$i += 2;
				} else if ($x == 0xFF && $y == 0x00 && $z == 0x00) {
					$data .= chr($x);
					$data .= chr($z);
					$i += 2;
				} else {
					$data .= chr($x);
				}
			} else {
				$data .= chr($x);
			}
		}
		return $data;
	}

	public static function parseFrameHeader($fourbytes)
	{
		static $versions = array(
			0x0=>'2.5',0x1=>'x',0x2=>'2',0x3=>'1', // x=>'reserved'
		);
		static $layers = array(
			0x0=>'x',0x1=>'3',0x2=>'2',0x3=>'1', // x=>'reserved'
		);
		static $bitrates = array(
			'V1L1'=>array(0,32,64,96,128,160,192,224,256,288,320,352,384,416,448),
			'V1L2'=>array(0,32,48,56, 64, 80, 96,112,128,160,192,224,256,320,384),
			'V1L3'=>array(0,32,40,48, 56, 64, 80, 96,112,128,160,192,224,256,320),
			'V2L1'=>array(0,32,48,56, 64, 80, 96,112,128,144,160,176,192,224,256),
			'V2L2'=>array(0, 8,16,24, 32, 40, 48, 56, 64, 80, 96,112,128,144,160),
			'V2L3'=>array(0, 8,16,24, 32, 40, 48, 56, 64, 80, 96,112,128,144,160),
		);
		static $sample_rates = array(
			'1'   => array(44100,48000,32000),
			'2'   => array(22050,24000,16000),
			'2.5' => array(11025,12000, 8000),
		);
		static $samples = array(
			1 => array( 1 => 384, 2 =>1152, 3 =>1152, ), //MPEGv1,     Layers 1,2,3
			2 => array( 1 => 384, 2 =>1152, 3 => 576, ), //MPEGv2/2.5, Layers 1,2,3
		);
		//$b0=ord($fourbytes[0]);//will always be 0xff
		$b1=ord($fourbytes[1]);
		$b2=ord($fourbytes[2]);
		$b3=ord($fourbytes[3]);

		$version_bits = ($b1 & 0x18) >> 3;
		$version = $versions[$version_bits];
		$simple_version =  ($version=='2.5' ? 2 : $version);

		$layer_bits = ($b1 & 0x06) >> 1;
		$layer = $layers[$layer_bits];

		$protection_bit = ($b1 & 0x01);
		$bitrate_key = sprintf('V%dL%d', $simple_version , $layer);
		$bitrate_idx = ($b2 & 0xf0) >> 4;
		$bitrate = isset($bitrates[$bitrate_key][$bitrate_idx]) ? $bitrates[$bitrate_key][$bitrate_idx] : 0;

		$sample_rate_idx = ($b2 & 0x0c) >> 2;//0xc => b1100
		$sample_rate = isset($sample_rates[$version][$sample_rate_idx]) ? $sample_rates[$version][$sample_rate_idx] : 0;
		$padding_bit = ($b2 & 0x02) >> 1;
		$private_bit = ($b2 & 0x01);
		$channel_mode_bits = ($b3 & 0xc0) >> 6;
		$mode_extension_bits = ($b3 & 0x30) >> 4;
		$copyright_bit = ($b3 & 0x08) >> 3;
		$original_bit = ($b3 & 0x04) >> 2;
		$emphasis = ($b3 & 0x03);

		$info = array();
		$info['Version'] = $version;//MPEGVersion
		$info['Layer'] = $layer;
		//$info['Protection Bit'] = $protection_bit; //0=> protected by 2 byte CRC, 1=>not protected
		$info['Bitrate'] = $bitrate;
		$info['Sampling Rate'] = $sample_rate;
		//$info['Padding Bit'] = $padding_bit;
		//$info['Private Bit'] = $private_bit;
		//$info['Channel Mode'] = $channel_mode_bits;
		//$info['Mode Extension'] = $mode_extension_bits;
		//$info['Copyright'] = $copyright_bit;
		//$info['Original'] = $original_bit;
		//$info['Emphasis'] = $emphasis;
		$info['Framesize'] = self::framesize($layer, $bitrate, $sample_rate, $padding_bit);
		$info['Samples'] = $samples[$simple_version][$layer];
		return $info;
	}

	private static function framesize($layer, $bitrate,$sample_rate,$padding_bit)
	{
		if ($layer==1)
			return intval(((12 * $bitrate*1000 /$sample_rate) + $padding_bit) * 4);
		else //layer 2, 3
			return intval(((144 * $bitrate*1000)/$sample_rate) + $padding_bit);
	}

}


