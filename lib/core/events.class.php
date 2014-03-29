<?php
class events
{
    public $events;

    public function __construct()
    {
        $this->events = array();
    }

    /**
     * Binds an event of a module to trigger a certain function of an extension
     *
     * @param string $module Navigate CMS application or module
     * @param string $event Codename of the event
     * @param string $extension Codename of the extension to run
     * @param string $function Function name of the extension to run
     */
    public function bind($module, $event, $extension, $function)
    {
        if(!is_array($this->events[$module]))
            $this->events[$module] = array();

        if(!is_array($this->events[$module][$event]))
            $this->events[$module][$event] = array();

        $this->events[$module][$event][] = array(
            'extension' => $extension,
            'function' => $function
        );
    }

    /**
     * Trigger a previously bound event
     *
     * @param string $module Navigate CMS application or module
     * @param string $event Codename of the event to fire
     * @param mixed $parameter One and only parameter to send to the event
     */
    public function trigger($module, $event, $parameter)
    {
        $messages = array();

        if(!is_array($this->events[$module][$event]))
            return $messages;

        foreach($this->events[$module][$event] as $trigger)
        {
            $messages[$trigger['extension']] = call_user_func($trigger['function'], $parameter);
        }

        return $messages;
    }

    public function add_actions($module, $parameters, $extra=array())
    {
        if(is_array($this->events[$module]['actions']))
        {
            foreach($this->events[$module]['actions'] as $trigger)
            {
                $result = call_user_func($trigger['function'], $parameters);
                if(!empty($result))
                    $extra[] = $result;
            }
        }

        if(!empty($extra))
        {
            array_unshift(
                $extra,
                '<a href="#" class="content-actions-submenu-trigger">
                    <img height="16" align="absmiddle" width="16" src="img/icons/silk/plugin.png"> '.t(521, "Extra").' &#9662;
                </a>'
            );
            $navibars = $parameters['navibars'];
            $navibars->add_actions(
                array($extra)
            );
        }
    }

    /**
     * Automatically binds extension events to Navigate CMS modules
     * It checks the "bindings" section of every extension definition
     */
    public function extension_backend_bindings()
    {
        // when running inside Navigate CMS, this binds all extension events
        $extensions = extension::list_installed();

        for($e=0; $e < count($extensions); $e++)
        {
            if(!empty($extensions[$e]['bindings']))
            {
                foreach($extensions[$e]['bindings'] as $binding)
                {
                    include_once(NAVIGATE_PATH.'/plugins/'.$extensions[$e]['code'].'/'.$extensions[$e]['code'].'.php');
                    $this->bind($binding->module, $binding->event, $extensions[$e]['code'], $binding->function);
                }
            }
        }
    }
}

?>