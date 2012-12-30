<?php
class firephp_nv
{
    /**
     * Log object with label to firebug console
     *
     * @see FirePHP::LOG
     * @param mixes $Object
     * @param string $Label
     * @return true
     * @throws Exception
     */
    public static function log($Object, $Label = null, $Options = array())
    {
        $firephp = FirePHP::getInstance(true);
        return $firephp->log($Object, $Label, $Options);
    }

    /**
     * Log object with label to firebug console
     *
     * @see FirePHP::INFO
     * @param mixes $Object
     * @param string $Label
     * @return true
     * @throws Exception
     */
    public static function info($Object, $Label = null, $Options = array())
    {
        $firephp = FirePHP::getInstance(true);
        return $firephp->info($Object, $Label, $Options);
    }

    /**
     * Log object with label to firebug console
     *
     * @see FirePHP::WARN
     * @param mixes $Object
     * @param string $Label
     * @return true
     * @throws Exception
     */
    public static function warn($Object, $Label = null, $Options = array())
    {
        $firephp = FirePHP::getInstance(true);
        return $firephp->warn($Object, $Label, $Options);
    }

    /**
     * Log object with label to firebug console
     *
     * @see FirePHP::ERROR
     * @param mixes $Object
     * @param string $Label
     * @return true
     * @throws Exception
     */
    public static function error($Object, $Label = null, $Options = array())
    {
        $firephp = FirePHP::getInstance(true);
        return $firephp->error($Object, $Label, $Options);
    }

    /**
     * Dumps key and variable to firebug server panel
     *
     * @see FirePHP::DUMP
     * @param string $Key
     * @param mixed $Variable
     * @return true
     * @throws Exception
     */
    public static function dump($Key, $Variable, $Options = array())
    {
        $firephp = FirePHP::getInstance(true);
        return $firephp->dump($Key, $Variable, $Options);
    }

    /**
     * Log a table in the firebug console
     *
     * @see FirePHP::TABLE
     * @param string $Label
     * @param string $Table
     * @return true
     * @throws Exception
     */
    public static function table($Label, $Table, $Options = array())
    {
        $firephp = FirePHP::getInstance(true);
        return $firephp->table($Label, $Table, $Options);
    }
}
?>