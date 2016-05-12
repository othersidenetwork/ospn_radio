<?php

require( 'vendor/autoload.php' );

use OSPN\Config;
use Cron\CronExpression;

$database = Config::getDatabaseConnection();

// Initialize new crons
$crons = $database->query('SELECT * FROM crontab WHERE next IS NULL AND disabled = 0')->fetchAll();
foreach($crons as $cron) {
	$cron_expression = CronExpression::factory($cron['expression']);
	$next_datetime = $cron_expression->getNextRunDate();
	$next = $next_datetime->getTimestamp();
	$next_format = $next_datetime->format("Y-m-d H:i:s");
	$database->update("crontab", ["next" => $next, "next_format" => $next_format], ["cron" => $cron['cron']]);
}

// Look for scheduled jobs
$crons = $database->query('SELECT crontab.*, crontab.next - UNIX_TIMESTAMP() as delta FROM crontab WHERE next - UNIX_TIMESTAMP() <= 10 ORDER BY next ASC')->fetchAll();
foreach ($crons as $cron) {
	$now = new DateTime();
	$then = new DateTime();
	error_log($now->format("H:i:s") . " delta : " . $cron['delta'], 4);
	if ($then->getTimestamp() < intval($cron['next'])) {
		$then->setTimestamp(intval($cron['next']));
	}
	error_log($now->format("H:i:s") . " Effective time : " . $then->format("Y-m-d H:i:s"), 4);
	$cron_expression = CronExpression::factory($cron['expression']);
	$next_datetime = $cron_expression->getNextRunDate($then);
	$next = $next_datetime->getTimestamp();
	$next_format = $next_datetime->format("Y-m-d H:i:s");
	error_log($now->format("H:i:s") . " Next time : " . $next_format, 4);
	$database->update("crontab", ["next" => $next, "next_format" => $next_format], ["cron" => $cron['cron']]);
	switch ($cron['cron']) {
		case 'jingles':
			die("annotate:type=\"jingle\",title=\"OSPN Outro\",artist=\"Zoé\":/home/yannick/radio/jingles/outro_zoe_1.mp3");
			break;
		default:
			break;
	}
}

// Compute time between now and next jingle.
$datas = $database->select("crontab", ["next"], ["cron" => "jingles"]);
$data = $datas[0];
$now = new \DateTime();
$interval = abs($data['next'] - $now->getTimestamp());

// Find tracks that either finish up to 5 seconds before jingle time, or up to 20 seconds after
$interval_low = $interval - 5;
$interval_high = $interval + 20;
$sql = "SELECT * FROM tracks WHERE isNSFW = 0 AND disabled = 0 AND (date_selected is NULL OR date_selected < DATE_ADD(NOW(), INTERVAL -4 DAY)) AND duration BETWEEN {$interval_low} AND {$interval_high} ORDER BY abs(duration - {$interval}), rand()";
$tracks = $database->query($sql)->fetchAll();

// If there is no such track (we're too far away from jingle time), then pick a track at random
if ($tracks == null || sizeof($tracks) == 0) {
	$tracks = $database->query("SELECT * FROM tracks WHERE isNSFW = 0 AND disabled = 0 AND (date_selected is NULL OR date_selected < DATE_ADD(NOW(), INTERVAL -4 DAY)) ORDER BY rand()")->fetchAll();
}
$track = $tracks[0];
$database->query("UPDATE tracks SET date_selected = NOW() WHERE id = " . $track['id']);
$database->insert("history", ["track_id" => $track['id']]);
$now = new DateTime();
$then = new DateTime();
$then->setTimestamp($now->getTimestamp() + intval($track['duration']));
error_log($now->format("H:i:s") . " : After this " . ((intval(intval($track['duration']) / 6)) / 10) . " minutes long track (" . $track['track_name'] . " by " . $track['artist_name'] . "), the time will be " . $then->format("H:i:s"), 4);
die("annotate:type=\"track\",ufid=\"" . $track['ufid'] . "\":/home/yannick/radio/music/" . $track['ufid'] . ".mp3");