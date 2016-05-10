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
$crons = $database->query('SELECT * FROM crontab WHERE next - UNIX_TIMESTAMP() <= 10 ORDER BY next ASC')->fetchAll();
foreach ($crons as $cron) {
	$then = new DateTime();
	if ($then->getTimestamp() < intval($cron['next'])) {
		$then->setTimestamp(intval($cron['next']));
	}
	$cron_expression = CronExpression::factory($cron['expression']);
	$next_datetime = $cron_expression->getNextRunDate($then);
	$next = $next_datetime->getTimestamp();
	$next_format = $next_datetime->format("Y-m-d H:i:s");
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
die("annotate:type=\"track\",ufid=\"" . $track['ufid'] . "\":/home/yannick/radio/music/" . $track['ufid'] . ".mp3");