<?php
require_once(NAVIGATE_PATH.'/lib/core/core.php');
require_once(NAVIGATE_PATH.'/lib/core/misc.php');
require_once(NAVIGATE_PATH.'/lib/core/database.class.php');
require_once(NAVIGATE_PATH.'/lib/core/language.class.php');
require_once(NAVIGATE_PATH.'/lib/core/user.class.php');
require_once(NAVIGATE_PATH.'/lib/core/events.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/layout.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navibars.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/naviforms.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navitable.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/naviorderedtable.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navitree.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navibrowse.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navigrid.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/menu_layout.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/grid_notes.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/websites/website.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');
require_once(NAVIGATE_PATH.'/web/nvweb_routes.php');
require_once(NAVIGATE_PATH.'/lib/packages/update/update.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/extensions/extension.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/users_log/users_log.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/permissions/permission.class.php');

require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.phpmailer.php');
require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.smtp.php');

require_once(NAVIGATE_PATH.'/lib/external/idna_convert/idna_convert.class.php');

require_once(NAVIGATE_PATH.'/lib/external/misc/cssmin.php');
require_once(NAVIGATE_PATH.'/lib/external/firephp/FirePHP.class.php');
require_once(NAVIGATE_PATH.'/lib/external/firephp/navigatecms_firephp.class.php');

disable_magic_quotes();

$max_upload = (int)(ini_get('upload_max_filesize'));
$max_post = (int)(ini_get('post_max_size'));
$memory_limit = (int)(ini_get('memory_limit'));
define('NAVIGATE_UPLOAD_MAX_SIZE', min($max_upload, $max_post, $memory_limit));

include_once(NAVIGATE_PATH.'/cfg/session.php');

?>