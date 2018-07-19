<?php
/*
Plugin Name: SheetSync
Description: Sync your posts with private Google Spreadsheets
Author: Nathan Malachowski
Author URI: http://ntheory.design/
Version: 0.1
Text Domain: sheetsync
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Make sure this script is not directly accessed
defined('ABSPATH') OR die('Unauthorized Access');

/**
 * Set Constants
 */

// Path to plugin
define('SS_PATH', plugin_dir_path(__FILE__));
// URL to plugin
define('SS_URL',  plugin_dir_url(__FILE__));


/**
 * Load helpers
 */
require_once('library/helpers.php');

/**
 * Load the core library
 */
require_once('library/sheetsync.php');

/**
 * Composer Autoload
 */
require( SS_PATH . 'vendor/autoload.php' );

/**
 * Set plugin autoloader
 */
spl_autoload_register( '\Sheetsync\Sheetsync::autoload' );

/**
 * Run the Plugin
 */
add_action( 'init', array( '\\Sheetsync\\Sheetsync','init'), 0 );
