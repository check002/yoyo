<?php

abstract class Migration
{
    public function initialize()
    {
        
    }

    public function beforeUp()
    {

    }

    public abstract function up();

    public function afterUp($success)
    {

    }

    public function beforeDown()
    {

    }

    public abstract function down();

    public function afterDown($success)
    {

    }
}

?>