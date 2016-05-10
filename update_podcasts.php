<?php

require( 'vendor/autoload.php' );

use OSPN\Config;

use PicoFeed\Reader\Reader;

$reader = new Reader();

$database = Config::getDatabaseConnection();

$podcasts = $database->select("podcasts", ["id", "upid", "url", 'item_id']);
foreach ($podcasts as $podcast) {
	$id = $podcast['id'];
	$upid = $podcast['upid'];
	$url = $podcast['url'];
	$item_id = $podcast['item_id'];

	if ($upid == null || $upid == '') {
		$upid = uniqid("podcast", true);
		$database->update("podcasts", ["upid" => $upid], ["id" => $id]);
	}
	$podcast_dir = Config::BASE_DIR . "/podcasts/" . $upid;
	if (!file_exists($podcast_dir)) {
		mkdir($podcast_dir);
	}

	$resource = $reader->download($url);

	$parser = $reader->getParser(
	    $resource->getUrl(),
	    $resource->getContent(),
	    $resource->getEncoding()
	);

	$feed = $parser->execute();

	// Only look at the last item
	$item = $feed->items[0];
	if ($item->id != $item_id) {
		
	}

	echo($item);	
}

