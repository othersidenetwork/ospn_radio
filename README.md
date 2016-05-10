# OSPN Radio : PHP scripts to operate the OSPN web radio.

## Configuration
You will need to create `OSPN/Config.php` to hold the configuration. For security reasons, this file is not stored in this repository.
```php
<?php
namespace OSPN;
class Config {

	const BASE_DIR = '/path/to/radio';

	public static function getDatabaseConnection() {
		return new \medoo( [
			'database_type' => 'mysql',
			'database_name' => 'mydbname',
			'server'        => 'mydbhost',
			'username'      => 'mydbusername',
			'password'      => 'mydbpassword',
			'charset'       => 'utf8'
		] );
	}
}
```

## Liquidsoap script

```
#! /home/yannick/radio/bin/liquidsoap -d

# Log file
set("log.file.path", "/home/yannick/radio/logs/radio.log")
set("log.file.append", false)
#set("log.stdout", true)
set("log.level", 2)

#def notify(m)
# Nothing to do
#end

def scheduler() =
	result = list.hd(get_process_lines('/usr/bin/php /home/yannick/radio/php/scheduler.php'))
	request.create(result)
end

safe = single('/home/yannick/radio/jingles/outro_zoe_1.mp3')
tracks = request.dynamic(scheduler)

radio = fallback([tracks, safe])
radio = skip_blank(threshold = -20.0, radio)
#radio = store_metadata(on_track(notify, radio))
radio = store_metadata(radio)
radio = normalize(radio)
radio = compress(radio)

output.pulseaudio(radio)
```