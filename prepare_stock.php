<?php

require( 'vendor/autoload.php' );

use OSPN\Config;

$database = Config::getDatabaseConnection();

$tracks = $database->select('tracks', ['id', 'ufid', 'artist_name', 'track_name'], ['ORDER' => ['id DESC']]);

foreach($tracks as $track) {
	$ufid = $track['ufid'];
	if ($ufid == null || $ufid == '') {
		$ufid = uniqid('', true);
	}
	$source = '/home/yannick/radio/workdir/' . $track['id'] . '.mp3';
	$target = '/home/yannick/radio/music/' . $track['ufid'] . '.mp3';

	$cmd = '/usr/bin/soxi -t ' . $source;
	$file_type = exec($cmd);
	if ($file_type == 'mp3') {
		echo 'Copying ' . $source . ' to ' . $target . "\n";
		copy($source, $target);
	} else {
		echo 'Transcoding ' . $source . ' (' . $file_type . ') to ' . $target . "\n";
		$cmd = '/usr/bin/ffmpeg -y -v 0 -i ' . $source . ' -c:a mp3 -b:a 128k ' . $target;
		exec($cmd);
	}

	$cmd = '/usr/bin/eyeD3 --remove-all';
	$cmd .= ' --unique-file-id=yannick.mauray@otherside.network:' . $ufid;
	$cmd .= ' -a "' . $track['artist_name'] . '"';
	$cmd .= ' -t "' . $track['track_name'] . '"';
	$cmd .= ' --no-color';
	$cmd .= ' --no-zero-padding';
	$cmd .= ' --no-tagging-time-frame';
	$cmd .= ' ' . $target;
	$cmd .= ' 2>/dev/null 1>/dev/null';
	exec($cmd);

	$cmd = '/usr/bin/soxi -D ' . $target;
	$duration = intval(exec($cmd));
	$hours = floor($duration / 3600);
	$minutes = floor( ($duration - ($hours * 3600)) / 60);
	$seconds = $duration - ($hours * 3600) - ($minutes * 60);
	$duration_format = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

	$database->update('tracks', ['ufid' => $ufid, 'duration' => $duration, 'duration_format' => $duration_format], ['id' => $track['id']]);
}
