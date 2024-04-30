<?php
/**
 * Plugin Name: Wallet Login
 * Plugin URI: https://rohitfullstackdeveloper.com/
 * Author: Rohit Sharma
 * Author URI: https://rohitfullstackdeveloper.com/
 * Description: Wallet Login MetaMask Connect
 * Version: 0.1.0
 * License: GPL2
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: wallet-login
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('CP_DIRNAME', basename(dirname(__FILE__)));
define('CP_RELPATH', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
define('CP_PATH', plugin_dir_path(__FILE__));
define('CP_URL', plugin_dir_url(__FILE__));
define('CP_PREFIX', 'cp');

/**
 * The core plugin class
 */
require CP_PATH . 'includes/class-cp-importer.php';