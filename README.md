yoyo
====

yoyo is a small migration tool for and using php. It provides the most simple migration management to enable you to implement the migrations the way you like. It is inspired by https://github.com/nickinuse/php-migrations but removes all database related stuff.

yoyo can be used as standalone application or embedded into an existing application i.e. using phake. To use it as standalone application you need to have a *yoyo.configuration.php* in the current working directory:

```php
$configuration = array(
    "migration_path" => "/path_to_directory/where/migrations_are_placed",
    "log_file_name" => "migration.log.php"
);
```

The rest is only a matter of typing *yoyo.php* and the help will be printed:
```
	help        show this help and exit
	show        shows the last applied migration
	log         show all applied migrations
	catalogue   show all migrations
	up          applies the next migration
	drop        unapplies all appield migrations
	raise       applies all unapplied migrations
	down        reverts the last migration
	check       show the summary of the last and the next migration
	generate    creates a skeleton for a new migration
```

To use yoyo as part of your application, you need to create a *Migrator* object with the configuration array (the same as in yoyo.configuration.php) and call the intended method.

```php
$migrator = new Migrator(array(
        "migration_path" => "/path_to_migration/where/migrations_are_placed",
        "log_file_name" => "migration.log.php"
    ));
$migrator->generate("migrationname");
```

To create a migration, use the *generate* command and pass the name (you can use spaces - the migration will be camel cased). It will be placed in the configured migration directory and when applied using the *up* or *raise* registered in the configured migration-log file. 

```php
class ExampleMigration extends Migration
{
  public static function name()
  {
    // plain text name given on command line
    return "Example migration";
  }

  public static function description()
  {
    // description for your migration to inform the user what will be done
    return "Optional description";
  }

  public function initialize()
  {
		// here you can do some initialization work
  }

  public function beforeUp()
  {
    // start transaction and preparation
  }

  public function up()
  {
		// do the migration stuff
  }
	
  public function afterUp($success)
  {
    // finish transaction and handle errors
  }
  
  public function beforeDown()
  {
    // start transaction and preparation
  }

  public function down()
  {
		// do the migration stuff
  }
	
  public function afterDown($success)
  {
    // finish transaction and handle errors
  }
}
```

Note that yoyo is only tested in the environment I needed it and there are currently only basic tests.

[![Build Status](https://travis-ci.org/check002/yoyo.svg?branch=master)](https://travis-ci.org/check002/yoyo)
