<?php

class debugger
{
    public static $timers;

    static function init()
    {
        /* prepare Tracy debugger
            note: if you don't want your users to see Tracy fatal errors, set "PRODUCTION" instead of "DEVELOPMENT")
        */
        error_reporting(E_ALL | E_WARNING | E_PARSE);

        if(!file_exists(NAVIGATE_PRIVATE . '/tmp'))
            @mkdir(NAVIGATE_PRIVATE . '/tmp');

        Tracy\Debugger::enable(
            Tracy\Debugger::DEVELOPMENT,
            NAVIGATE_PRIVATE . '/tmp'
        );

        Tracy\Debugger::$maxDepth = PHP_INT_MAX; // default: 3
        Tracy\Debugger::$maxLength = PHP_INT_MAX; // default: 150
    }

    static function dispatch()
    {
        Tracy\Debugger::dispatch();

        if(!APP_DEBUG)
        {
            Tracy\Debugger::$showBar = false;
            Tracy\Debugger::$logSeverity = E_ERROR | E_WARNING | E_PARSE;
        }
    }

    static function dump($variable)
    {
        return Tracy\Debugger::dump($variable);
    }

    static function bar_dump($message, $title="", $options=array())
    {
        return Tracy\Debugger::barDump($message, $title, $options);
    }

    /*
     * priority [info, debug, warning, error, exception, critical]
     */
    static function log($message, $priority = 'info')
    {
        return Tracy\Debugger::log($message, $priority);
    }

    static function console($message, $title="")
    {
        if(is_array($message) || is_object($message))
            $message = print_r($message, true);

        if(!empty($title))
            $message = $title .":\n".$message;

        return Tracy\Debugger::fireLog($message);
    }

    static function timer($name="")
    {
        return Tracy\Debugger::timer($name);
    }

    static function stop_timer($name)
    {
        self::$timers[][$name] = (int)(self::timer($name)*1000);
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
            return self::$timers;
    }
}

// workaround to make older FirePHP calls work with new debugger integration (will be deprecated in Navigate CMS 3.0)
class firephp_nv
{
    static function log($message, $title="")
    {
        debugger::console("DEPECRATED call firephp_nv::log(), please use debugger::console()");
        return debugger::console($message, $title);
    }
}

?>