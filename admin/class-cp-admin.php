<?php

/**
 * Handles admin side interactions of the plugin with WordPress.
 *
 * @package Wallet_Login
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MetaMask_Connect_Wallet extends Wallet_Login_Importer
{
    public function __construct()
    {
        // Calling parent class' constructor.
        parent::__construct();

        // Setup the meta box.
        add_action('admin_init', array($this, 'wallet_register_meta_box'));
        // add menu page to the menu
        add_action('admin_menu', array($this, 'cp_add_admin_menu'));
        add_action('admin_init', array($this, 'cp_settings_init'));

        // Enqueue custom JS.
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));

        // Add an ajax hack to save the html content.
        add_action('wp_ajax_gs_sim_description_hack', array($this, 'description_hack'));

        // Hijack the ajax_add_menu_item function in order to save Shortcode menu item properly.
        add_action('wp_ajax_add-menu-item', array($this, 'ajax_add_menu_item'));
        add_filter('wp_setup_nav_menu_item', array($this, 'metamask_nav_menu_type_label'));
    }

    public function cp_add_admin_menu()
    {
        add_options_page(__('Wallet Login Settings', 'wallet-login'), __('Wallet Login Settings', 'wallet-login'), 'manage_options', 'wallet-login-settings', array($this, 'wallet_login_settings'));
    }

    public function wallet_login_settings()
    {
        ?>
        <form action="options.php" method="post">
        <?php
settings_fields('cp_wallet_settings_section_group');
        do_settings_sections('cp_wallet_settings_page');?>
            <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save');?>" />
        </form>
<?php
}

    public function cp_settings_init()
    {
        register_setting('cp_wallet_settings_section_group', 'cp_wallet_settings_section_group', array($this, 'cp_wallet_settings_section_group_validate'));
        add_settings_section('wallet_settings', __('Wallet Login Settings', 'wallet-login'), array($this, 'cp_wallet_settings_section_callback'), 'cp_wallet_settings_page');
        add_settings_field('cp_wallet_modal_title', __('Modal Title', 'wallet-login'), array($this, 'cp_wallet_modal_title'), 'cp_wallet_settings_page', 'wallet_settings');
        add_settings_field('cp_wallet_terms_field', __('Modal Bottom Text', 'wallet-login'), array($this, 'cp_wallet_terms_field'), 'cp_wallet_settings_page', 'wallet_settings');
    }

    public function cp_wallet_settings_section_group_validate($input)
    {
        $newinput['cp_wallet_modal_title'] = trim($input['cp_wallet_modal_title']);
        $newinput['cp_wallet_terms_field'] = trim($input['cp_wallet_terms_field']);
        return $newinput;
    }

    public function cp_textarea_intro_render()
    {
        $options = get_option('cp_settings', array());
        ?>
        <textarea cols='40' rows='5' name='cp_settings[cp_textarea_intro]'><?php echo isset($options['cp_textarea_intro']) ? $options['cp_textarea_intro'] : false; ?></textarea>
<?php
}

    public function cp_wallet_settings_section_callback()
    {

    }

    public function cp_wallet_modal_title()
    {
        $options = get_option('cp_wallet_settings_section_group');
        echo "<input id='cp_wallet_modal_title' name='cp_wallet_settings_section_group[cp_wallet_modal_title]' type='text' value='" . esc_attr($options['cp_wallet_modal_title']) . "' />";
    }

    public function cp_wallet_terms_field()
    {
        $options = get_option('cp_wallet_settings_section_group', array());
        $content = isset($options['cp_wallet_terms_field']) ? $options['cp_wallet_terms_field'] : false;
        wp_editor($content, 'cp_wallet_terms_field', array(
            'textarea_name' => 'cp_wallet_settings_section_group[cp_wallet_terms_field]',
            'media_buttons' => false,
        ));
    }

    public function metamask_nav_menu_type_label($menu_item)
    {
        if (isset($menu_item->object) && 'gs_sim' == $menu_item->object) {
            $menu_item->type_label = __('MetaMask Button', 'wallet-login');
            $menu_item->description = '[cp_wallet_login]';
        }

        return $menu_item;
    }
    public function description_hack()
    {
        // Verify the nonce.
        $nonce = filter_input(INPUT_POST, 'description-nonce', FILTER_SANITIZE_STRING);
        if (!wp_verify_nonce($nonce, 'gs-sim-description-nonce')) {
            wp_die();
        }

        // Get the menu item. We need this unfiltered, so using FILTER_UNSAFE_RAW.
        $item = filter_input(INPUT_POST, 'menu-item', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);

        // Save the description in a transient. This is what we'll use in setup_item().
        set_transient('gs_sim_description_hack_' . $item['menu-item-object-id'], $item['menu-item-description']);

        // Increment the object id, so it can be used by JS.
        $object_id = $this->new_object_id($item['menu-item-object-id']);
        echo esc_js($object_id);
        wp_die();
    }

    /**
     * Enqueue our custom JS.
     *
     * @since 2.0
     * @access public
     *
     * @param string $hook The current screen.
     *
     * @return void
     */
    public function enqueue($hook)
    {
        // Don't enqueue if it isn't the menu editor.
        if ('nav-menus.php' !== $hook) {
            return;
        }

        wp_enqueue_script('gs-sim-admin', CP_URL . 'admin/assets/js/cp-in-menu.js', array('nav-menu'), 1.0, true);
    }

    /**
     * Gets a new object ID, given the current one
     *
     * @since 2.0
     * @access public
     *
     * @param int $last_object_id The current/last object id.
     *
     * @return int Returns new object ID.
     */
    public function new_object_id($last_object_id)
    {
        // make sure it's an integer.
        $object_id = (int) $last_object_id;

        // increment it.
        $object_id++;

        // if object_id was 0 to start off with, make it 1.
        $object_id = ($object_id < 1) ? 1 : $object_id;

        // save into the options table.
        update_option('gs_sim_last_object_id', $object_id);

        return $object_id;
    }

    /**
     * Ajax handler for add menu item request.
     *
     * This method is hijacked from WordPress default ajax_add_menu_item
     * so need to be updated accordingly.
     *
     * @since 2.0
     *
     * @return void
     */
    public function ajax_add_menu_item()
    {
        check_ajax_referer('add-menu_item', 'menu-settings-column-nonce');

        if (!current_user_can('edit_theme_options')) {
            wp_die(-1);
        }

        require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

        // For performance reasons, we omit some object properties from the checklist.
        // The following is a hacky way to restore them when adding non-custom items.
        $menu_items_data = array();
        // Get the menu item. We need this unfiltered, so using FILTER_UNSAFE_RAW.
        $menu_item = filter_input(INPUT_POST, 'menu-item', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);

        foreach ($menu_item as $menu_item_data) {
            if (
                !empty($menu_item_data['menu-item-type']) &&
                'custom' !== $menu_item_data['menu-item-type'] &&
                'gs_sim' !== $menu_item_data['menu-item-type'] &&
                !empty($menu_item_data['menu-item-object-id'])
            ) {
                switch ($menu_item_data['menu-item-type']) {
                    case 'post_type':
                        $_object = get_post($menu_item_data['menu-item-object-id']);
                        break;

                    case 'taxonomy':
                        $_object = get_term($menu_item_data['menu-item-object-id'], $menu_item_data['menu-item-object']);
                        break;
                }

                $_menu_items = array_map('wp_setup_nav_menu_item', array($_object));
                $_menu_item = reset($_menu_items);

                // Restore the missing menu item properties.
                $menu_item_data['menu-item-description'] = $_menu_item->description;
            }

            $menu_items_data[] = $menu_item_data;
        }

        $item_ids = wp_save_nav_menu_items(0, $menu_items_data);

        if (is_wp_error($item_ids)) {
            wp_die(0);
        }

        $menu_items = array();

        foreach ((array) $item_ids as $menu_item_id) {
            $menu_obj = get_post($menu_item_id);

            if (!empty($menu_obj->ID)) {
                $menu_obj = wp_setup_nav_menu_item($menu_obj);
                $menu_obj->label = $menu_obj->title; // don't show "(pending)" in ajax-added items.
                $menu_items[] = $menu_obj;
            }
        }

        $menu = filter_input(INPUT_POST, 'menu', FILTER_SANITIZE_NUMBER_INT);
        /** This filter is documented in wp-admin/includes/nav-menu.php */
        $walker_class_name = apply_filters('wp_edit_nav_menu_walker', 'Walker_Nav_Menu_Edit', $menu);

        if (!class_exists($walker_class_name)) {
            wp_die(0);
        }

        if (!empty($menu_items)) {
            $args = array(
                'after' => '',
                'before' => '',
                'link_after' => '',
                'link_before' => '',
                'walker' => new $walker_class_name(),
            );
            echo walk_nav_menu_tree($menu_items, 0, (object) $args);
        }
        wp_die();
    }

    /**
     * Method to allow saving of shortcodes in custom_link URL.
     *
     * @since 1.0
     *
     * @param string $url The processed URL for displaying/saving.
     * @param string $orig_url The URL that was submitted, retreived.
     * @param string $context Whether saving or displaying.
     *
     * @return string String containing the shortcode.
     */
    public function save_shortcode($url, $orig_url, $context)
    {
        if ('db' === $context && $this->has_shortcode($orig_url)) {
            return $orig_url;
        }
        return $url;
    }

    public function wallet_register_meta_box()
    {
        add_meta_box('wallet_metamask_button', __('MetaMask Button Shortcode', 'wallet-login'), array($this, 'wallet_meta_box_callback'), 'nav-menus', 'side', 'high');
    }

    public function wallet_meta_box_callback()
    {
        global $_nav_menu_placeholder, $nav_menu_selected_id;
        $nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;

        $last_object_id = get_option('gs_sim_last_object_id', 0);
        $object_id = $this->new_object_id($last_object_id);

        ?>

        <div class="gs-sim-div" id="gs-sim-div">
            <input type="hidden" class="menu-item-db-id" name="menu-item[<?php echo esc_attr($nav_menu_placeholder); ?>][menu-item-db-id]" value="0" />
            <input type="hidden" class="menu-item-object-id" name="menu-item[<?php echo esc_attr($nav_menu_placeholder); ?>][menu-item-object-id]" value="<?php echo esc_attr($object_id); ?>" />
            <input type="hidden" class="menu-item-object" name="menu-item[<?php echo esc_attr($nav_menu_placeholder); ?>][menu-item-object]" value="gs_sim" />
            <input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr($nav_menu_placeholder); ?>][menu-item-type]" value="gs_sim" />
            <input type="hidden" id="gs-sim-description-nonce" value="<?php echo esc_attr(wp_create_nonce('gs-sim-description-nonce')); ?>" />
            <p id="menu-item-title-wrap" style="display:inline;">
                <label for="gs-sim-title" style="display:none;"><?php esc_html_e('Title', 'wallet-login');?></label>
                <input id="gs-sim-title"  name="menu-item[<?php echo esc_attr($nav_menu_placeholder); ?>][menu-item-title]" type="hidden" class="regular-text menu-item-textbox" value="Metamask Connect Button"/>
            </p>

            <button class="btn-login light-mode connectWallet" style="display:flex;">
                <i><img src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/avatar-icon.svg'; ?>" alt="metamaskIcon" /></i>
                <span class="text-desktop" style="margin-top:10px;"><?php echo __('MetaMask Connect', 'wallet-login'); ?></span>
            </button>

            <p id="menu-item-html-wrap">
                <input type="hidden" name="menu-item[<?php echo esc_attr($nav_menu_placeholder); ?>][menu-item-description]" class="code menu-item-textbox" value="[cp_wallet_menu_button]">
            </p>

            <p class="button-controls">
                <span class="add-to-menu">
                    <input type="submit" <?php wp_nav_menu_disabled_check($nav_menu_selected_id);?> class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e('Add to Menu', 'wallet-login');?>" name="add-gs-sim-menu-item" id="submit-gs-sim" />
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
      <?php
    }
}

$admin_class = new MetaMask_Connect_Wallet();