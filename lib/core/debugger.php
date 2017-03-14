<?php

class debugger
{
    static function init()
    {
        /* prepare Tracy debugger
            note: if you don't want your users to see Tracy fatal errors, set "PRODUCTION" instead of "DEVELOPMENT")
        */
        Tracy\Debugger::enable(
            Tracy\Debugger::DEVELOPMENT,
            NAVIGATE_PRIVATE . '/tmp'
        );
    }

    static function dispatch()
    {
        Tracy\Debugger::dispatch();

        if(!APP_DEBUG)
        {
            Tracy\Debugger::$showBar = false;
            Tracy\Debugger::$logSeverity = E_ERROR | E_WARNING;
        }
    }

    static function dump($variable)
    {
        return Tracy\Debugger::dump($variable);
    }

    static function barDump($message, $title="", $options=array())
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
        if(!empty($title))
            $message = $title .': '.$message;

        return Tracy\Debugger::fireLog($message);
    }

    static function timer($name="")
    {
        return Tracy\Debugger::timer($name);
    }
}

// workaround to make older FirePHP calls work with new debugger integration
class firephp_nv
{
    static function log($message, $title="")
    {
        return debugger::console($message, $title);
    }
}


?>