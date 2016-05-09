# OSPN Radio : PHP scripts to operate the OSPN web radio.

## Database configuration
You will need to create `OSPN/Config.php` to hold the database configuration. For security reasons, this file is not stored in this repository.
```php
<?php
namespace OSPN;
class Config {
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