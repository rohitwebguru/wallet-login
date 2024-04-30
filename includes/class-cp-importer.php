<?php
/**
 * @since      0.1.0
 * @package    WD
 * @subpackage WD/includes
 * @author     Rohit Sharma
 */
class Wallet_Login_Importer
{
    protected $loader, $plugin_name, $version;
    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    0.1.0
     */
    public function __construct()
    {
        $this->plugin_name = 'WD';
        $this->version = '0.1.0';
        $this->load_dependencies();

        // filter the menu item output on frontend.
        add_filter('walker_nav_menu_start_el', array($this, 'cp_render_metamask'), 20, 2);

        // Making it work with Max Mega Menu Plugin.
        add_filter('megamenu_walker_nav_menu_start_el', array($this, 'cp_render_metamask'), 20, 2);

        // filter the menu item before display in admin and in frontend.
        add_filter('wp_setup_nav_menu_item', array($this, 'cp_setup_item'), 10, 1);
    }

    /**
     * @description  Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @author   Rohit Sharma
     */
    private function load_dependencies()
    {
        // Load Admin File
        require_once CP_PATH . 'admin/class-cp-admin.php';

        // Load Frontend File
        require_once CP_PATH . 'public/class-cp-frontend.php';
    }

    /**
     * @description The name of the plugin used to uniquely identify it within the context of
     *                  WordPress and to define internationalization functionality.
     *
     * @since     0.1.0
     * @return    string    The name of the plugin.
     * @author    Rohit Sharma
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }
    /**
     * @description Retrieve the version number of the plugin.
     *
     * @since     0.1.0
     * @return    string    The version number of the plugin.
     * @author    Rohit Sharma
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Modifies the menu item display on frontend.
     *
     * @since 0.1.0
     *
     * @param string $item_output The original html.
     * @param object $item  The menu item being displayed.
     *
     * @return string Modified menu item to display.
     */
    public function cp_render_metamask($item_output, $item)
    {
        // Rare case when $item is not an object, usually with custom themes.
        if (!is_object($item) || !isset($item->object)) {
            return $item_output;
        }

        // if it isn't our custom object.
        if ('gs_sim' !== $item->object) {

            // check the legacy hack.
            if (isset($item->post_title) && 'FULL HTML OUTPUT' === $item->post_title) {

                // then just process as we used to.
                $item_output = do_shortcode($item->url);
            } else {
                $item_output = do_shortcode($item_output);
            }
        } elseif (isset($item->description)) {
            // just process it.
            $item_output = do_shortcode('[cp_wallet_menu_button]');
        }

        return $item_output;
    }

    /**
     * Modify the menu item before display on Menu editor and in frontend.
     *
     * @since 0.1.0
     *
     * @param object $item The menu item.
     *
     * @return object Modified menu item object.
     */

    public function cp_setup_item($item)
    {
        if (!is_object($item)) {
            return $item;
        }

        if ('gs_sim' === $item->object) {
            // setup our label.
            $item->type_label = __('MetaMask Button', 'wallet-login');
            $item->title = (!empty($item->title)) ? $item->title : __('MetaMask Connect Button', 'wallet-login');

            if (!empty($item->post_content)) {
                $item->description = $item->post_content;
            } else {
                // set up the description from the transient.
                $item->description = get_transient('gs_sim_description_hack_' . $item->object_id);

                // discard the transient.
                delete_transient('gs_sim_description_hack_' . $item->object_id);
            }
        }

        return $item;
    }
}

//  Initiate Importer Object
$plugin = new Wallet_Login_Importer();