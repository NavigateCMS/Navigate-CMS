<?php
require_once(NAVIGATE_PATH.'/lib/core/core.php');
require_once(NAVIGATE_PATH.'/lib/core/misc.php');
require_once(NAVIGATE_PATH.'/lib/core/database.class.php');
require_once(NAVIGATE_PATH.'/lib/core/language.class.php');
require_once(NAVIGATE_PATH.'/lib/core/user.class.php');
require_once(NAVIGATE_PATH.'/lib/core/events.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/layout.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navibars.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/naviforms.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navitable.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/naviorderedtable.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navitree.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navibrowse.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/navigrid.class.php');
require_once(NAVIGATE_PATH.'/lib/layout/menu_layout.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/grid_notes/grid_notes.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/websites/website.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');
require_once(NAVIGATE_PATH.'/web/nvweb_routes.php');
require_once(NAVIGATE_PATH.'/lib/packages/update/update.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/extensions/extension.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/users_log/users_log.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/permissions/permission.class.php');

require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.phpmailer.php');
require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.smtp.php');

// IDNA converter to allow unicode characters in URLs
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/UnicodeTranscoderInterface.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/UnicodeTranscoder.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/NamePrepDataInterface.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/NamePrepData.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/PunycodeInterface.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/Punycode.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/EncodingHelper.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/IdnaConvert.php');

require_once(NAVIGATE_PATH.'/lib/external/misc/cssmin.php');

require_once(NAVIGATE_PATH.'/lib/core/debugger.php');
require_once(NAVIGATE_PATH.'/lib/external/tracy/src/tracy.php');

require_once(NAVIGATE_PATH.'/lib/external/ref/ref.php');

require_once(NAVIGATE_PATH.'/lib/vendor/autoload.php');


/* prepare PHP to run Navigate CMS */

disable_magic_quotes();
@ini_set('default_charset', 'utf-8');

$max_upload = (int)(ini_get('upload_max_filesize'));
$max_post = (int)(ini_get('post_max_size'));
$memory_limit = (int)(ini_get('memory_limit'));
define('NAVIGATE_UPLOAD_MAX_SIZE', min($max_upload, $max_post, $memory_limit));

// Suppress DateTime warnings
$nv_default_timezone = @date_default_timezone_get();
if(empty($nv_default_timezone))
{
    $nv_default_timezone = 'UTC';
}

date_default_timezone_set($nv_default_timezone);

if(!defined("APP_UNIQUE"))
{
    define("APP_UNIQUE", "nv_default");
}

debugger::init();
include_once(NAVIGATE_PATH.'/cfg/session.php');
debugger::dispatch();


?>