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
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webuser_votes/webuser_vote.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block.class.php');

require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/extensions/extension.class.php');

// IDNA converter to allow unicode characters in URLs
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/UnicodeTranscoderInterface.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/UnicodeTranscoder.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/NamePrepDataInterface.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/NamePrepData.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/PunycodeInterface.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/Punycode.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/EncodingHelper.php');
require_once(NAVIGATE_PATH.'/lib/external/idna_convert/src/IdnaConvert.php');

require_once(NAVIGATE_PATH.'/lib/packages/permissions/permission.class.php');
require_once(NAVIGATE_PATH.'/lib/external/php-ixr/IXR_Library.php');

require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.phpmailer.php');
require_once(NAVIGATE_PATH.'/lib/external/phpmailer/class.smtp.php');

require_once(NAVIGATE_PATH.'/lib/external/htmlpurifier-lite/library/HTMLPurifier.auto.php');

require_once(NAVIGATE_PATH.'/lib/core/debugger.php');

require_once(NAVIGATE_PATH.'/web/nvweb_routes.php');
require_once(NAVIGATE_PATH.'/web/nvweb_templates.php');
require_once(NAVIGATE_PATH.'/web/nvweb_objects.php');
require_once(NAVIGATE_PATH.'/web/nvweb_plugins.php');
require_once(NAVIGATE_PATH.'/web/nvweb_xmlrpc.php');

require_once(NAVIGATE_PATH.'/lib/vendor/autoload.php');

// preload widely used webgets
require_once(NAVIGATE_PATH.'/lib/webgets/list.php');

disable_magic_quotes();
@ini_set('default_charset', 'utf-8');

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null);
$purifier = new HTMLPurifier($purifier_config);

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

include_once(NAVIGATE_PATH.'/cfg/session.php');

if(!defined('APP_UNIQUE'))
{
    define('APP_UNIQUE', 'nv_default');
}

?>