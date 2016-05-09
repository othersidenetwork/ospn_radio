<?php

require( 'vendor/autoload.php' );

use OSPN\Retag;
use OSPN\Config;

$retag = new Retag();

$database = Config::getDatabaseConnection();

$datas = $database->select( 'cchits', [ 'id', 'ufid', 'artist_name', 'track_name' ], [ 'ORDER' => [ 'id DESC' ] ] );

$retag = new Retag();

foreach ( $datas as $data ) {
	$source = '/home/yannick/radio/workdir/' . $data['id'] . '.mp3';
	$ufid   = $data['ufid'];
	if ( $ufid == null ) {
		$ufid = uniqid( "", true );
	}
	$target = '/home/yannick/radio/music/' . $ufid . '.mp3';
	echo "Re-tagging {$source} to {$target}\n";
	/*
	$retag->retag( $source, $target, $ufid, $data['artist_name'], $data['track_name'] );
	$database->update( 'cchits', [ 'ufid' => $ufid ], [ 'id' => $data['id'] ] );
	*/
}
