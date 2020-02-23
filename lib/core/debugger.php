<?php
class debugger
{
    public static $timers;
    public static $starting_microtime;

    static function init()
    {
        global $events;

        self::$starting_microtime = microtime(true);

        error_reporting(E_ERROR | E_WARNING | E_PARSE);

        if(!file_exists(NAVIGATE_PRIVATE . '/tmp'))
        {
            @mkdir(NAVIGATE_PRIVATE . '/tmp');
        }

        $events->trigger('debugger', 'init');
    }

    static function dispatch()
    {
        global $events;
        $events->trigger('debugger', 'dispatch');
    }

    static function dump($variable)
    {
        global $events;
        return $events->trigger('debugger', 'dump', array('var' => $variable));
    }

    static function bar_dump($message, $title="", $options=array())
    {
        global $events;
        return $events->trigger(
            'debugger',
            'bardump',
            array(
                'message' => $message,
                'title' => $title,
                'options' => $options
            )
        );
    }

    /*
     * priority [info, debug, warning, error, exception, critical]
     */
    static function log($message, $priority = 'info')
    {
        global $events;
        return $events->trigger(
            'debugger',
            'log',
            array(
                'message' => $message,
                'priority' => $priority
            )
        );
    }

    static function console($message, $title="")
    {
        global $events;

        if(is_array($message) || is_object($message))
        {
            $message = print_r($message, true);
        }

        if(!empty($title))
        {
            $message = $title .":\n".$message;
        }

        return $events->trigger(
            'debugger',
            'console',
            array(
                'message' => $message
            )
        );
    }

    // TODO: maybe create an internal timer without the need of a plugin
    static function timer($name="")
    {
        global $events;
        return $events->trigger(
            'debugger',
            'timer',
            array(
                'name' => $name
            )
        );
    }

    static function stop_timer($name)
    {
        // code based on Nette Tracy Timers
        $time_elapsed = (int)self::timer($name);
        self::$timers[][$name] = $time_elapsed * 1000;
    }

    static function get_timers($format='array')
    {
        if($format=='list')
        {
            $list = "";
            for($i=0; $i < count(self::$timers); $i++)
            {
                $key = array_keys(self::$timers[$i]);
                $key = $key[0];
                $val = self::$timers[$i][$key];

                $list .= $key.': '.$val;
                $list .= "\n";
            }

            return $list;

        }
        else
        {
            return self::$timers;
        }
    }
}

// workaround to make older FirePHP calls work with new debugger integration (will be deprecated in Navigate CMS 3.0)
class firephp_nv
{
    static function log($message, $title="")
    {
        debugger::console("DEPECRATED firephp_nv::log(), please use debugger::console()");
        return debugger::console($message, $title);
    }
}

?>