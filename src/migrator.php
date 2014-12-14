<?php
    
require_once( __DIR__.DIRECTORY_SEPARATOR."buffer_formatter.php" );
require_once( __DIR__.DIRECTORY_SEPARATOR."migration.php" );

class Migrator
{
    private $configuration;
    private $initialized;
    private $appliedMigrations;
    private $pendingMigrations;
    private static $INJECTIONS = array();

    private static $TIMESTAMP_SIZE = 19;
    private static $TIMESTAMP_PATTERN = "[0123456789][0123456789][0123456789][0123456789]_[0123456789][0123456789]_[0123456789][0123456789]_[0123456789][0123456789]_[0123456789][0123456789]_[0123456789][0123456789]";
    private static $FILE_TYPE_PATTERN = "migration.php";

    public function __construct($configuration)
    {
        $this->configuration = $configuration;
        $this->initialized = false;
        $this->appliedMigrations = array();
        $this->pendingMigrations = array();
    }

    public static function inject($name, $object)
    {
        self::$INJECTIONS[ self::asInjectionName( $name ) ] = $object;
    }

    private function targetPath($file = null)
    {
        $file = ( is_null( $file ) ? "" : DIRECTORY_SEPARATOR.$file );
        return $this->configuration['migration_path'].$file;
    }

    private function readPendingMigrations()
    {
        $files = $this->targetPathContent( self::$TIMESTAMP_PATTERN.".*.".self::$FILE_TYPE_PATTERN );
        $targetPath = $this->targetPath("");
        $this->pendingMigrations = array();
        foreach( $files as $file )
        {
            $migration = str_replace( $targetPath, "", $file );
            if( ! in_array( $migration, $this->appliedMigrations ) )
            {
                $this->pendingMigrations[] = $migration;
            }
        }
        sort( $this->pendingMigrations );
    }

    private function targetPathContent($filter = "*")
    {
        return glob( $this->targetPath( $filter ) );
    }

    private function logFilePath()
    {
        return $this->targetPath( "migration.log.php" );
    }

    private function readLogfile()
    {
        $logFile = $this->logFilePath();
        if( file_exists( $logFile ) )
        {
            include( $logFile );
            $this->appliedMigrations = array();
            foreach( $migrations as $migration )
            {
                $this->appliedMigrations[] = $migration;
            }
            sort( $this->appliedMigrations );
        }
    }

    private function writePHPFile($filePath, $content)
    {
        $handle = fopen( $filePath, 'w' );
        $header = "<?php".PHP_EOL.PHP_EOL;
        $footer = PHP_EOL.PHP_EOL."?>".PHP_EOL;
        fwrite( $handle, $header.$content.$footer );
        fclose( $handle );
    }

    private function writeLogfile()
    {
        $logFile = $this->logFilePath();
        $content = "\$migrations = array(".PHP_EOL;
        foreach( $this->appliedMigrations as $migration )
        {
            $content .= "\t'".$migration."',".PHP_EOL;
        }
        $content .= ");";
        $this->writePHPFile( $logFile, $content );
    }

    private function initialize()
    {
        if( ! $this->initialized )
        {
            $targetPath = $this->targetPath();
            if ( ! file_exists( $targetPath ) ) 
            {
                mkdir($targetPath, 0777, true);
            }

            $this->readLogfile();
            $this->readPendingMigrations();
            $this->initialized = true;
        }
    }

    private function formatDescription( $description, $indent = "" )
    {
        $lines = preg_split('/\r\n|\r|\n/', $description );
        return $indent.implode( PHP_EOL.$indent, $lines ).PHP_EOL;
    }

    public function show()
    {
        $this->initialize();

        $migration = end( $this->appliedMigrations );
        reset( $this->appliedMigrations );

        if( $migration )
        {
            $filename = $this->targetPath( $migration );
            require_once($filename);
            $classname = self::getClassname( $migration );
            echo "\t".$classname::name().PHP_EOL;
            $description = $classname::description();
            if( ! empty( $description ) )
            {
                echo $this->formatDescription( $description, "\t" );
            }
        }
        else
        {
            echo "No migration applied yet".PHP_EOL;
        }
    }

    public function log()
    {
        $this->initialize();

        $this->printMigrations( $this->appliedMigrations );
    }

    private function nextPendingMigration()
    {
        if( ! empty( $this->pendingMigrations ) )
        {
            return $this->pendingMigrations[ 0 ];
        }
        return null;
    }

    private function lastAppliedMigration()
    {
        $migration = end( $this->appliedMigrations );
        reset( $this->appliedMigrations );
        return $migration === FALSE ? null : $migration;
    }

    private function execute($migration, $before, $main, $after)
    {
        $this->loadMigration( $migration );
        $exception = null;
        $class = self::getClassname( $migration );

        $object = new $class( self::$INJECTIONS );
        $object->initialize();
        try
        {
            call_user_func(array($object, $before));
        }
        catch (Exception $e)
        {
            return new Exception("Exception during before callback occured", 0, $e);
        }
        try
        {
            call_user_func(array($object, $main));
        }
        catch (Exception $e)
        {
            $exception = new Exception("Exception during execution occured", 0, $e);
        }
        try
        {
            call_user_func_array(array($object, $after), array( is_null( $exception ) ));
        }
        catch (Exception $e)
        {
            return new Exception("Exception during after callback occured", 0, $e);
        }
        return $exception;
    }

    private function printException(Exception $e)
    {
        do 
        {
            echo $e->getMessage().PHP_EOL;
            $formatter = new BufferFormatter("  ");
            $formatter->start();
            echo $e->getTraceAsString();
            $formatter->stop();
        }
        while ( ($e = $e->getPrevious()) != null );
    }

    public function up()
    {
        $this->initialize();

        $migration = $this->nextPendingMigration();
        if( ! is_null( $migration ) )
        {
            echo "Applying migration \"".$this->getMigrationName( $migration )."\"".PHP_EOL;
            $description = $this->getMigrationDescription( $migration ) ;
            if( ! empty( $description ) )
            {
                echo $this->formatDescription( $description, "\t" );
            }
            $formatter = new BufferFormatter("\t  ");
            $formatter->start();

            $exception = $this->execute( $migration, "beforeUp", "up", "afterUp" );
            
            $formatter->stop();

            if( ! is_null( $exception ) )
            {
                $this->printException( $exception ).PHP_EOL;
            }
            else
            {
                $this->registerMigration( $migration );
            }
        }
        else
        {
            echo "No migration to apply".PHP_EOL;
        }
    }

    private function registerMigration($migration)
    {
        if(($key = array_search($migration, $this->pendingMigrations)) !== false) {
            unset( $this->pendingMigrations[$key] );
            sort( $this->pendingMigrations );
        }
        $this->appliedMigrations[] = $migration;
        sort( $this->appliedMigrations );
        $this->writeLogfile();
    }

    private function unregisterMigration( $migration )
    {
        if(($key = array_search($migration, $this->appliedMigrations)) !== false) {
            unset( $this->appliedMigrations[$key] );
            sort( $this->appliedMigrations );
        }
        $this->pendingMigrations[] = $migration;
        sort( $this->pendingMigrations );
        $this->writeLogfile();
    }

    private function loadMigration( $migration ) 
    {
        $filename = $this->targetPath( $migration );
        require_once($filename);
    }

    public function down()
    {
        $this->initialize();

        $migration = $this->lastAppliedMigration();
        if( ! is_null( $migration ) )
        {
            echo "Reverting migration \"".$this->getMigrationName( $migration )."\"".PHP_EOL;
            $description = $this->getMigrationDescription( $migration );
            if( ! empty( $description ) )
            {
                echo PHP_EOL.$this->formatDescription( $description, "\t" );
            }
            $formatter = new BufferFormatter("\t  ");
            $formatter->start();

            $exception = $this->execute( $migration, "beforeDown", "down", "afterDown" );
            
            $formatter->stop();

            if( ! is_null( $exception ) )
            {
                $this->printException( $exception).PHP_EOL;
            }
            else
            {
                $this->unregisterMigration( $migration );
            }
        }
        else
        {
            echo "No migration to revert".PHP_EOL;
        }
    }

    public function drop()
    {
        $this->initialize();

        while( ! is_null( $this->lastAppliedMigration() ) )
        {
            $this->down();
        }
    }

    public function raise()
    {
        $this->initialize();

        while( ! is_null( $this->nextPendingMigration() ) )
        {
            $this->up();
        }
    }

    private static function getClassname( $migration )
    {
        $name = substr( $migration, self::$TIMESTAMP_SIZE + 1, strlen( $migration ) - 2 - self::$TIMESTAMP_SIZE - strlen( self::$FILE_TYPE_PATTERN ) );
        return self::asClassname( $name );
    }

    private function getMigrationName( $migration )
    {
        $this->loadMigration( $migration );
        $classname = self::getClassname( $migration );
        return $classname::name();
    }

    private function getMigrationDescription( $migration )
    {
        $this->loadMigration( $migration );
        $classname = self::getClassname( $migration );
        return $classname::description();
    }

    private function printMigrations( $migrations, $prefixApplied = false)
    {
        foreach( $migrations as $migration )
        {
            $prefix = "  ";
            if( $prefixApplied && in_array( $migration, $this->appliedMigrations ) )
            {
                $prefix = "* ";
            }
            
            $date = DateTime::createFromFormat("Y_m_d_H_i_s", substr( $migration, 0, self::$TIMESTAMP_SIZE ) );
            echo "\t".$prefix.$date->format("Y-m-d H:i:s")."\t".$this->getMigrationName( $migration ).PHP_EOL;
        }
    }

    public function catalogue()
    {
        $this->initialize();

        $migrations = array_merge( $this->appliedMigrations, $this->pendingMigrations );
        sort($migrations);

        $this->printMigrations( $migrations, true );
    }

    public function check()
    {
        $this->initialize();

        $migration = $this->lastAppliedMigration();
        if( ! is_null( $migration ) )
        {
            $this->loadMigration( $migration );

            echo "Last applied migration: \"".$this->getMigrationName( $migration )."\"".PHP_EOL;
            $description = $this->getMigrationDescription( $migration );
            if( ! empty( $description ) )
            {
                echo PHP_EOL.$this->formatDescription( $description, "\t" );
            }
        }
        $migration = $this->lastAppliedMigration();
        if( ! is_null( $migration ) )
        {
            $this->loadMigration( $migration );

            echo "Next pending migration: \"".$this->getMigrationName( $migration )."\"".PHP_EOL;
            $description = $this->getMigrationDescription( $migration );
            if( ! empty( $description ) )
            {
                echo PHP_EOL.$this->formatDescription( $description, "\t" );
            }
        }
    }

    private static function asFilename( $name )
    {
        $name = preg_replace( '@([A-Z])@', '_$1', $name );
        $name = preg_replace( '@[^a-z0-9_]@i', '_', $name );
        $name = preg_replace( '@(_+)@', '_', trim( $name, "_" ) );
        $name = strtolower( $name );
        return $name;
    }

    private static function asInjectionName( $name )
    {
        $name = self::asClassname( $name );
        return lcfirst($name);
    }

    private static function asClassname( $name )
    {
        $name = self::asFilename( $name );
        $name = ucwords( str_replace( '_', ' ', $name ) );
        return str_replace( ' ', '', $name );
    }

    public function generate( $splittedName )
    {
        $this->initialize();

        if( ! is_string( $splittedName ) )
        {
            $name = implode(" ", $splittedName );
        }
        else
        {
            $name = $splittedName;
        }


        $filename = self::asFilename( $name ).".".self::$FILE_TYPE_PATTERN;
        $matchingFiles = $this->targetPathContent( self::$TIMESTAMP_PATTERN.".".$filename );
        if( sizeof( $matchingFiles ) > 0 )
        {
            throw new Exception("Name clash - migration with same name already exists (".implode(", ", $matchingFiles ).")");
        }

        $content  = "class ".self::asClassname( $name )." extends Migration".PHP_EOL;
        $content .= "{".PHP_EOL;
        $content .= "\tpublic static function name()" .PHP_EOL;
        $content .= "\t{".PHP_EOL;
        $content .= "\t\treturn \"".$name."\";".PHP_EOL;
        $content .= "\t}".PHP_EOL;
        $content .= PHP_EOL;
        $content .= "\tpublic static function description()" .PHP_EOL;
        $content .= "\t{".PHP_EOL;
        $content .= "\t\treturn \"\";".PHP_EOL;
        $content .= "\t}".PHP_EOL;
        $content .= PHP_EOL;
        $content .= "\tpublic function up()".PHP_EOL;
        $content .= "\t{".PHP_EOL;
        $content .= "\t\t".PHP_EOL;
        $content .= "\t}".PHP_EOL;
        $content .= PHP_EOL;
        $content .= "\tpublic function down()".PHP_EOL;
        $content .= "\t{".PHP_EOL;
        $content .= "\t\t".PHP_EOL;
        $content .= "\t}".PHP_EOL;
        $content .= "}".PHP_EOL;

        $date = new DateTime();
        $this->writePHPFile( $this->targetPath( $date->format("Y_m_d_H_i_s").".".$filename ), $content );
    }

    public function help()
    {
        echo "\nyoyo (c) 2014 check002, http://github.com/check002/yoyo.git".PHP_EOL.PHP_EOL;
        echo "\thelp        show this help and exit".PHP_EOL;
        echo "\tshow        shows the last applied migration".PHP_EOL;
        echo "\tlog         show all applied migrations".PHP_EOL;
        echo "\tcatalogue   show all migrations".PHP_EOL;
        echo "\tup          applies the next migration".PHP_EOL;
        echo "\tdrop        unapplies all appield migrations".PHP_EOL;
        echo "\traise       applies all unapplied migrations".PHP_EOL;
        echo "\tdown        reverts the last migration".PHP_EOL;
        echo "\tcheck       show the summary of the last and the next migration".PHP_EOL;
        echo "\tgenerate    creates a skeleton for a new migration from the given name".PHP_EOL;

    }
}

?>