<?php

namespace OSPN;

class Retag {

	public function retag( $source, $target, $ufid, $artist, $title ) {
		if ( ! file_exists( $source ) ) {
			return ( "Source file {$source} does not exist." );
		}

		$source_size = filesize( $source );
		$source_fd   = fopen( $source, "rb" );
		fseek( $source_fd, -128, SEEK_END );
		$tag = fread( $source_fd, 3 );
		if ($tag == 'TAG') {
			$source_size -= 128;
		}
		fseek( $source_fd, 0, SEEK_SET );
		$offset = $this->getOffset( $source_fd );
		fseek( $source_fd, $offset, SEEK_SET );

		$target_fd = fopen( $target, "wb" );

		$tag = new ID3Tag();
		$tag->setUFID( $ufid );
		$tag->setArtist( $artist );
		$tag->setTitle( $title );
		$tag->write( $target_fd );
		stream_copy_to_stream( $source_fd, $target_fd, $source_size - $offset );
		fflush( $target_fd );
		fclose( $target_fd );
		fclose( $source_fd );

		return null;
	}

	private function getExistingUFID( $fd ) {
		// Not an ID3 tag ?
		$id3 = fread( $fd, 3 );
		if ( $id3 != 'ID3' ) {
			return null;
		}

		// Our tags have a major version of 4 and a minor version of 0.
		$majorVersion = ord( fread( $fd, 1 ) );
		$minorVersion = ord( fread( $fd, 1 ) );
		if ( $majorVersion != 4 ) {
			return null;
		}
		if ( $minorVersion != 0 ) {
			return null;
		}

		// Our tags are unsynchronized, don't have an extended header, are not experimental and have a footer.
		$flags = ord( fread( $fd, 1 ) );
		if ( $flags != 0x90 ) {
			return null;
		}

		$z0                      = ord( fread( $fd, 1 ) );
		$z1                      = ord( fread( $fd, 1 ) );
		$z2                      = ord( fread( $fd, 1 ) );
		$z3                      = ord( fread( $fd, 1 ) );
		$tag_unsynchronized_size = 2097152 * $z0 + 16384 * $z1 + 128 * $z2 + $z3;
		$tag_data                = $this->ununsynchronize( fread( $fd, $tag_unsynchronized_size ) );
		$tag_size                = strlen( $tag_data );
		$data_index              = 0;
		while ( $data_index < $tag_size ) {
			$frame_id = substr( $tag_data, $data_index, 4 );
			if ( ord( $frame_id[0] ) == 0x00 ) {
				break;
			}
			$data_index += 4;
			$z0         = ord( $tag_data[ $data_index ++ ] );
			$z1         = ord( $tag_data[ $data_index ++ ] );
			$z2         = ord( $tag_data[ $data_index ++ ] );
			$z3         = ord( $tag_data[ $data_index ++ ] );
			$frame_size = $z0 * 2097152 + $z1 * 16384 + $z2 * 128 + $z3;

			$frame_flag_0 = ord( $tag_data[ $data_index ++ ] );
			$frame_flag_1 = ord( $tag_data[ $data_index ++ ] );

			$frame_index = $data_index;
			$data_index += $frame_size;
			if ( $frame_id == "UFID" && $frame_size > 33 && substr( $tag_data, $frame_index, 32 ) == "yannick.mauray@otherside.network" && ord( $tag_data[ $frame_index + 32 ] ) == 0x00 ) {
				$frame_index += 33;
				$ufid = substr( $tag_data, $frame_index, $data_index - $frame_index );

				return $ufid;
			}
		}

		return null;
	}

	private function ununsynchronize( $unsynchronized_data ) {
		$data = "";
		for ( $i = 0; $i < strlen( $unsynchronized_data ); $i ++ ) {
			if ( ( $i + 2 ) < strlen( $unsynchronized_data ) ) {
				$x = ord( $unsynchronized_data[ $i ] );
				$y = ord( $unsynchronized_data[ $i + 1 ] );
				$z = ord( $unsynchronized_data[ $i + 2 ] );
				if ( $x == 0xFF && $y == 0x00 && ( ( $z & 0xE0 ) == 0xE0 || $z == 0x00 ) ) {
					$data .= chr( $x );
					$data .= chr( $z );
					$i += 2;
				} else {
					$data .= chr( $x );
				}
			} else {
				$data .= chr( $x );
			}
		}

		return $data;
	}

	private function getOffset( $fd ) {
		$offset = 0;

		$id3 = fread( $fd, 3 );
		if ( $id3[0] != 'I' || $id3[1] == 'D' || $id3[2] == '3' ) {
			return $offset;
		}

		$majorVersion = ord( fread( $fd, 1 ) );
		$minorVersion = ord( fread( $fd, 1 ) );
		$flags        = ord( fread( $fd, 1 ) );
		$z0           = ord( fread( $fd, 1 ) );
		$z1           = ord( fread( $fd, 1 ) );
		$z2           = ord( fread( $fd, 1 ) );
		$z3           = ord( fread( $fd, 1 ) );
		$tag_size     = 2097152 * $z0 + 16384 * $z1 + 128 * $z2 + $z3;
		$offset += 10;

		$tag_data = fread( $fd, $tag_size );
		$offset += $tag_size;

		if ( $majorVersion == 4 ) {
			$footer_present = ( $flags & 0x10 ) == 0x10;
			if ( $footer_present ) {
				$id3          = fread( $fd, 3 );
				$majorVersion = ord( fread( $fd, 1 ) );
				$minorVersion = ord( fread( $fd, 1 ) );
				$flags        = ord( fread( $fd, 1 ) );
				$z0           = ord( fread( $fd, 1 ) );
				$z1           = ord( fread( $fd, 1 ) );
				$z2           = ord( fread( $fd, 1 ) );
				$z3           = ord( fread( $fd, 1 ) );
				$offset += 10;
			}
		}
		$a = ord( fread( $fd, 1 ) );
		$b = ord( fread( $fd, 1 ) );
		while ( $a != 0xFF && $b != 0xFB ) {
			$a = $b;
			$b = ord( fread( $fd, 1 ) );
			$offset += 1;
		}

		return $offset;
	}

}
