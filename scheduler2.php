<?php

require( 'vendor/autoload.php' );

use OSPN\Config;
use Cron\CronExpression;
use OSPN\LiquidSoapClient;
use OSPN\ErrorLogLogger;

$database = Config::getDatabaseConnection();
$client = new LiquidSoapClient('127.0.0.1', 1234);
$logger = new ErrorLogLogger("scheduler");
$now = new \DateTime();

$client->write("songs.secondary_queue")->read($ids)->end();
if ($ids != "") {
    $ids = explode(" ", trim($ids));
    foreach ($ids as $id) {
   		$client->write("songs.remove " . $id)->read($ok)->end();
    }
}

$type = sizeof($argv) > 1 ? $argv[1] : null;
$ufid = sizeof($argv) > 2 ? $argv[2] : null;

$logger->log("type: " . $type);
$logger->log("ufid: " . $ufid);

// Reference time
$reference_time = new \DateTime();

// Default duration, in case we're starting.
$duration = 10;
if ($ufid != null) {
	$current_track = $database->select('tracks', ['id', 'duration'], ['ufid' => $ufid])[0];
	$duration = $current_track['duration'];
	$reference_time->setTimestamp($reference_time->getTimestamp() + $duration);
	$database->insert('history', ['track_id' => $current_track['id']]);
}

$logger->log('This song will end at ' . $reference_time->format("H:i:s") . '.');

// Reset all crons to the "tick" before reference time
$crons = $database->select('crontab', ['cron', 'expression'], ['disabled' => 0]);
foreach ($crons as $cron) {
	$cron_expression = CronExpression::factory($cron['expression']);
	$previous_datetime = $cron_expression->getPreviousRunDate($reference_time);
	$database->update('crontab', ['next' => $previous_datetime->getTimestamp(), 'next_format' => $previous_datetime->format("Y-m-d H:i:s")], ['cron' => $cron['cron']]);
	$logger->log("cron: " . $cron['cron'] . " set to " . $previous_datetime->format("Y-m-d H:i:s"));
}

// Advance crons that will be too far past reference time
$crons = $database->query('SELECT crontab.cron, crontab.expression FROM crontab WHERE crontab.next - ' . $reference_time->getTimestamp() . ' < -30')->fetchAll();
foreach ($crons as $cron) {
	$cron_expression = CronExpression::factory($cron['expression']);
	$previous_datetime = $cron_expression->getPreviousRunDate($reference_time);
	$next_datetime = $cron_expression->getNextRunDate($previous_datetime);
	$database->update('crontab', ['next' => $next_datetime->getTimestamp(), 'next_format' => $next_datetime->format("Y-m-d H:i:s")], ['cron' => $cron['cron']]);
	$logger->log("cron: " . $cron['cron'] . " will be far too late, forwarded to " . $next_datetime->format("Y-m-d H:i:s"));
}

// Will there be a cron to execute ? We look for a cron scheduled for up to 30 seconds past reference time.
$crons = $database->query('SELECT crontab.* FROM crontab WHERE next - ' . $reference_time->getTimestamp() . ' <= 30 AND disabled = 0 ORDER BY next ASC')->fetchAll();
if ($crons != null && sizeof($crons) != 0) {
	$cron = $crons[0];
	$logger->log('cron: ' . $cron['cron'] . ' is up next !');

	switch ($cron['cron']) {
		case 'jingles':
			$client->write('jingles.push ' . Config::BASE_DIR . '/jingles/outro_zoe_1.mp3');
			$reference_time->setTimestamp($reference_time->getTimestamp() + 10);
			break;
		default:
			break;
	}
}

// Compute time between reference time and next cron.
$datas = $database->select('crontab', ['next'], ['ORDER' => ['next ASC']]);
$data = $datas[0];
$interval = abs($data['next'] - $reference_time->getTimestamp());

// Find tracks that either finishes up to 5 seconds before cron time, or up to 20 seconds after
$interval_low = $interval - 5;
$interval_high = $interval + 20;
$sql = "SELECT * FROM tracks WHERE isNSFW = 0 AND disabled = 0 AND (date_selected is NULL OR date_selected < DATE_ADD(NOW(), INTERVAL -4 DAY)) AND duration BETWEEN {$interval_low} AND {$interval_high} ORDER BY abs(duration - {$interval}), rand()";
$tracks = $database->query($sql)->fetchAll();

// If there is no such track (we're too far away from cron time), then pick a track at random
if ($tracks == null || sizeof($tracks) == 0) {
	$tracks = $database->query("SELECT * FROM tracks WHERE isNSFW = 0 AND disabled = 0 AND (date_selected is NULL OR date_selected < DATE_ADD(NOW(), INTERVAL -4 DAY)) ORDER BY rand()")->fetchAll();
}

$track = $tracks[0];
$database->query("UPDATE tracks SET date_selected = NOW() WHERE id = " . $track['id']);

$end_time = new \DateTime();
$end_time->setTimestamp($reference_time->getTimestamp() + $track['duration']);
$logger->log('The selected song is ' . $track['duration'] . ' seconds long and will end at ' . $end_time->format('H:i:s') . '.');

$msg = 'songs.push annotate:type="track",ufid="' . $track['ufid'] . '":' . Config::BASE_DIR . '/music/' . $track['ufid'] . ".mp3";
$client->write($msg)->read($newid)->end();
$client->write("quit")->read($bye);
sleep(1);