<?php
class events
{
    public $events;
    public $extensions;

    public function __construct()
    {
        $this->events = array();
        $this->extensions = extension::list_installed();
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
        if(!isset($this->events[$module]) || !is_array($this->events[$module]))
        {
            $this->events[$module] = array();
        }

        if(!isset($this->events[$module][$event]) || !is_array($this->events[$module][$event]))
        {
            $this->events[$module][$event] = array();
        }

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
    public function trigger($module, $event, $parameter=NULL)
    {
        $messages = array();

        if(!isset($this->events[$module][$event]) || !is_array($this->events[$module][$event]))
        {
            return $messages;
        }

        foreach($this->events[$module][$event] as $trigger)
        {
            if(APP_DEBUG)
            {
                debugger::console($trigger, $module.'/'.$event);
            }

            $event_out = call_user_func($trigger['function'], $parameter);
            if(!empty($event_out))
            {
                $extension_info = array('title' => '?');
                foreach($this->extensions as $extension)
                {
                    if($extension['code'] == $trigger['extension'])
                    {
                        $extension_info = $extension;
                        break;
                    }
                }

                $messages[$trigger['extension']] = array(
                    'out' => $event_out,
                    'title' => @$extension_info['title']
                );
            }
        }

        return $messages;
    }

    public function add_actions($module, $parameters, $extra=array(), $extra_parent_action='')
    {
        if(is_array($this->events[$module]['actions']))
        {
            foreach($this->events[$module]['actions'] as $trigger)
            {
                $result = call_user_func($trigger['function'], $parameters);
                if(!empty($result))
                {
                    $extra[] = $result;
                }
            }
        }

        if(!empty($extra))
        {
            if(empty($extra_parent_action))
            {
                $extra_parent_action = '
                    <a href="#" class="content-actions-submenu-trigger">
                        <img height="16" align="absmiddle" width="16" src="img/icons/silk/plugin.png"> '.t(521, "Extra").' &#9662;
                    </a>
                ';
            }

            array_unshift(
                $extra,
                $extra_parent_action
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
     * except the preinit type extensions
     */
    public function extension_backend_bindings($ignore_permissions=true)
    {
        // when running inside Navigate CMS, this binds all extension events
        $extensions = extension::list_installed(null, $ignore_permissions);

        for($e=0; $e < count($extensions); $e++)
        {
            if(!isset($extensions[$e]['enabled']) || $extensions[$e]['enabled'] == '1')
            {
                if(!empty($extensions[$e]['bindings']))
                {
                    foreach($extensions[$e]['bindings'] as $binding)
                    {
                        extension::include_php($extensions[$e]['code']);
                        $this->bind($binding->module, $binding->event, $extensions[$e]['code'], $binding->function);
                    }
                }
            }
        }
    }

    /**
     * Automatically binds extension events to Navigate CMS modules
     * It checks the "bindings" section of every extension definition
     * but ONLY of the preinit types: [debugger]
     *
     * Note: this function is executed for EVERY WEBSITE managed under the SAME Navigate CMS instance
     * because, in this moment, we don't know which website is selected and we don't know which extensions are enabled or not
     */
    public function extension_preinit_bindings()
    {
        // when running inside Navigate CMS, this binds all extension events
        $extensions = extension::list_installed('debugger', false);

        for($e=0; $e < count($extensions); $e++)
        {
            if(!isset($extensions[$e]['enabled']) || $extensions[$e]['enabled'] == '1')
            {
                if(!empty($extensions[$e]['bindings']))
                {
                    foreach($extensions[$e]['bindings'] as $binding)
                    {
                        extension::include_php($extensions[$e]['code']);
                        $this->bind($binding->module, $binding->event, $extensions[$e]['code'], $binding->function);
                    }
                }
            }
        }
    }
}

?>