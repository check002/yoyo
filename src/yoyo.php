<?php

/**
 * this file provides the access to yoyo using the command line
 * it expects to read its configuration from a yoyo.configuration.php file in the current working directory
 */

error_reporting( E_ALL );

require_once( __DIR__.DIRECTORY_SEPARATOR."migrator.php" );

// load the configration from the caller directory
require_once( getcwd().DIRECTORY_SEPARATOR."yoyo.configuration.php" );

$arguments = $argv;
// we do not need the first argument since it is only the executable
array_shift( $arguments );

$command = "help";
if( ! empty( $arguments ) )
{
    $command = $arguments[ 0 ];
    array_shift( $arguments );
}

$migrator = new Migrator( $configuration );
try 
{
    switch( $command )
    {
        case "show":
            $migrator->show();
            // show the current migration / last applied migration
            break;

        case "log":
            $migrator->log();
            // show all applied migratons
            break;

        case "up":
            $migrator->up();
            // apply the next migration
            break;

        case "down":
            $migrator->down();
            // unapply the last migration
            break;

        case "drop":
            $migrator->drop();
            // undo all applied migrations
            break;

        case "raise":
            $migrator->raise();
            // apply all unapplied migrations
            break;

        case "check":
            $migrator->check();
            // show last migration and next migration
            break;

        case "catalogue":
            $migrator->catalogue();
            // show all applied and unapplied migrations in chronological order
            break;

        case "generate":
            if( empty( $arguments ) ) 
            {
                throw new Exception("Missing name for migration");
            }
            $migrator->generate( $arguments );
            // create a new migration skeleton
            break;

        default:
            // show the help
            $migrator->help();
            break;
    }
} catch( Exception $e)
{
    echo "An error occured".PHP_EOL;
    echo "\t".$e->getMessage().PHP_EOL;
}


?>