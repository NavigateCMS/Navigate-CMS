<?php
require_once(NAVIGATE_PATH.'/lib/core/core.php');
require_once(NAVIGATE_PATH.'/lib/core/misc.php');
require_once(NAVIGATE_PATH.'/lib/core/database.class.php');
require_once(NAVIGATE_PATH.'/lib/core/user.class.php');
require_once(NAVIGATE_PATH.'/lib/core/language.class.php');
require_once(NAVIGATE_PATH.'/lib/core/events.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/websites/website.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary_history.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/comments/comment.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webuser_votes/webuser_vote.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block.class.php');

require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/extensions/extension.class.php');

require_once(NAVIGATE_PATH.'/lib/external/idna_convert/idna_convert.class.php');

require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.phpmailer.php');
require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.smtp.php');

require_once(NAVIGATE_PATH.'/lib/external/firephp/FirePHP.class.php');
require_once(NAVIGATE_PATH.'/lib/external/firephp/navigatecms_firephp.class.php');

require_once(NAVIGATE_PATH.'/web/nvweb_routes.php');
require_once(NAVIGATE_PATH.'/web/nvweb_templates.php');
require_once(NAVIGATE_PATH.'/web/nvweb_objects.php');
require_once(NAVIGATE_PATH.'/web/nvweb_plugins.php');

disable_magic_quotes();


?>