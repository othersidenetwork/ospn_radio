<?php

require( 'vendor/autoload.php' );

use OSPN\Retag;

$retag = new Retag();

$database = new medoo( [
	'database_type' => 'mysql',
	'database_name' => 'ospn_radio',
	'server'        => 'localhost',
	'username'      => 'ospn_radio',
	'password'      => 'ospn_radio',
	'charset'       => 'utf8'
] );

$datas = $database->select( 'cchits', [ '*' ], [ 'ORDER' => [ 'id DESC' ] ] );

$retag = new Retag();

foreach ( $datas as $data ) {
	$source = '/home/yannick/radio/workdir/' . $data['id'] . '.mp3';
	$ufid   = $data['ufid'];
	if ( $ufid == null ) {
		$ufid = uniqid( "", true );
	}
	$target = '/home/yannick/radio/music/' . $ufid . '.mp3';
	echo "Re-tagging {$source} to {$target}\n";
	$retag->retag( $source, $target, $ufid, $data['artist_name'], $data['track_name'] );
	$database->update( 'cchits', [ 'ufid' => $ufid ], [ 'id' => $data['id'] ] );
}
