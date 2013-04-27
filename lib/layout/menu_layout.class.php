<?php
require_once(NAVIGATE_PATH.'/lib/packages/profiles/profile.class.php');
class menu_layout
{	
	public $menus;
	
	public function load()
	{
		global $DB;
		global $user;

		$profile = new profile();
		$profile->load($user->profile);
		
		$profile_menus = implode(",", $profile->menus);

		if($DB->query('SELECT * 
						 FROM nv_menus
						WHERE id IN ('.$profile_menus.')
						  AND enabled = 1'))
		
		{
			$data = $DB->result();
			$menu_pos = $DB->result('id');
			
			for($pm=0; $pm < count($profile->menus); $pm++)
			{
				$p = array_search($profile->menus[$pm], $menu_pos);
				if($p===false) continue;
				$this->menus[] = $data[$p];
				$this->menus[count($this->menus)-1]->items = $this->load_items($data[$p]->id, json_decode($data[$p]->functions));
			}
		}

	}
	
	public function load_items($menu_id, $functions)
	{
		global $DB;

		$menu_functions = implode(",", $functions);
		$out = array();
		
		if(!empty($menu_functions))
		{
			if($DB->query('SELECT * 
							 FROM nv_functions
							WHERE id IN ('.$menu_functions.')
							  AND enabled = 1'))
			
			{
				$data = $DB->result();
				$menu_pos = $DB->result('id');
				
				for($pm=0; $pm < count($functions); $pm++)
				{
					$p = array_search($functions[$pm], $menu_pos);
					if($p===false) continue;
					$out[] = $data[$p];	
				}
			}
		}
		
		return $out;
		
	}

    public function function_is_displayed($id)
    {
        $found = false;

        for($m=0; $m < count($this->menus); $m++)
        {
            for($mi=0; $mi < count($this->menus[$m]->items); $mi++)
            {
                $found = ($this->menus[$m]->items[$mi]->id == $id);
                if($found)
                    break;
            }

            if($found)
                break;
        }

        return $found;
    }
	
	public function generate_html()
	{
		global $layout;
		
		$out = array();
		
		$out[] = '<ul>';
		$mindex = 0;
		foreach($this->menus as $menu)
		{
			$mindex++;
			$out[] = '<li><a href="#navigate-menu-'.$mindex.'"><img src="'.$menu->icon.'" width="16" height="16" align="absmiddle" /> '.t($menu->lid, ucfirst($menu->codename)).'</a></li>';
		}		
		$out[] = '</ul>';		

		if(!isset($_REQUEST['fid']))
            $_REQUEST['fid'] = 'dashboard';

		for($m = 1; $m <= $mindex; $m++)
		{
			$out[] = '<div id="navigate-menu-'.$m.'" class="ui-corner-tr">';
			for($i = 0; $i < count($this->menus[$m-1]->items); $i++)
			{
				if( $_REQUEST['fid']==$this->menus[$m-1]->items[$i]->id  ||
                    $_REQUEST['fid']==$this->menus[$m-1]->items[$i]->codename )
				{
					$layout->add_script(" var navigate_menu_current_tab = ".($m-1)."; ");
					$out[] = '<a class="navigate-content-actions-selected" style="text-shadow: #aaf 0px 0px 8px;" href="?fid='.$this->menus[$m-1]->items[$i]->codename.'"><img src="'.$this->menus[$m-1]->items[$i]->icon.'" width="16" height="16" align="absmiddle" /> '.t($this->menus[$m-1]->items[$i]->lid, ucfirst($this->menus[$m-1]->items[$i]->codename)).'</a>';
				}
				else
				{
					$out[] = '<a href="?fid='.$this->menus[$m-1]->items[$i]->codename.'"><img src="'.$this->menus[$m-1]->items[$i]->icon.'" width="16" height="16" align="absmiddle" /> '.t($this->menus[$m-1]->items[$i]->lid, ucfirst($this->menus[$m-1]->items[$i]->codename)).'</a>';
				}
			}
			$out[] = '</div>';
		}
		
		$layout->add_script('
			$("#navigate-menu").tabs({	event: "mouseover"	}); 
			$("#navigate-menu").css({ "border": "none", "background": "transparent" });
			$("#navigate-menu").children().eq(0).css({ "border-top": "none", "background": "transparent" });
		');
		
		return implode("", $out); // WebKit calculates wrong size if lines are separated with \r (why!?)
	}
}

?>