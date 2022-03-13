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

    static function timer($name="")
    {
        self::$timers[$name] = array(
            'start' => microtime(TRUE),
            'end' => null,
            'elapsed' => null
        );
    }

    static function stop_timer($name)
    {
        self::$timers[$name]['end'] = microtime(TRUE);
        self::$timers[$name]['elapsed'] = max(0, intval((self::$timers[$name]['end'] - self::$timers[$name]['start']) * 1000));
    }

    static function get_timers($format='array')
    {
        if($format=='list')
        {
            $list = "";

            foreach(self::$timers as $key => $data)
            {
                $list .= $key.': '.$data['elapsed'];
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