<?php 

class BufferFormatter
{
    private $indent;
    private $firstCall;

    public function __construct($indent = "")
    {
        $this->indent = $indent;
        $this->firstCall = true;
    }

    public function start()
    {
        ob_start( $this );
        ob_implicit_flush( true );
    }

    public function stop()
    {
        ob_end_flush();
        echo PHP_EOL;    
    }

    public function __invoke($part, $end_flag_0x04)
    {
        $formatted = preg_replace( '/\r\n|\r|\n/', PHP_EOL.$this->indent, $part);
        if( $this->firstCall ) 
        {
            $formatted = $this->indent.$formatted;
            $this->firstCall = false;
        }
        return $formatted;
    }
}

?>