<?php
class Wallet_Login_Frontend
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'custom_register_endpoint'));
        add_action('rest_api_init', array($this, 'custom_logout_endpoint'));
        add_action('wp_enqueue_scripts', array($this, 'wallet_plugin_enqueue_scripts'));
        add_shortcode('cp_wallet_login', array($this, 'to_display_connect_wallet_button'));
        add_shortcode('cp_wallet_menu_button', array($this, 'admin_menu_wallet_button'));
        add_filter("script_loader_tag", array($this, "add_module_to_my_script"), 10, 3);
    }

    // Add a custom API endpoint to handle user registration
    public function custom_register_endpoint()
    {
        register_rest_route('custom/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'custom_register_user'),
        ));
    }

    // Callback function for user registration
    public function custom_register_user($request)
    {
        $params = $request->get_json_params();

        // Get the Ethereum wallet address from the JSON request
        $ethereum_wallet_address = $params['ethereum_wallet_address'];

        // Get the email address from the JSON request (if you included it in the payload)
        $ethereum_wallet_email = $params['ethereum_wallet_email'];

        // Check if the user already exists based on the wallet address
        $existing_user = get_user_by('login', $ethereum_wallet_address);

        if (empty($existing_user)) {
            $password = $ethereum_wallet_address;

            // Hash the password using wp_hash_password()
            $user_id = wp_create_user($ethereum_wallet_address, $password, $ethereum_wallet_email);

            // Save the Ethereum wallet address as user meta data for future reference
            update_user_meta($user_id, 'ethereum_wallet_address', $ethereum_wallet_address);

            // Update user email
            $userArgs = array(
                'ID' => $user_id,
                'user_email' => esc_attr($ethereum_wallet_email),
            );
            wp_update_user($userArgs);
        }

        $user = get_user_by('login', $ethereum_wallet_address);

        if ($user) {
            $user_id = $user->ID;
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
        }

        // Return a success response
        return array(
            'status' => 'success',
            'message' => 'User registered successfully!',
        );
    }

    // Add a custom API endpoint to handle user logout
    public function custom_logout_endpoint()
    {
        register_rest_route('custom/v1', '/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'custom_logout_user'),
        ));
    }

    // Callback function for user logout
    public function custom_logout_user($request)
    {
        // Log out the current user
        wp_logout();

        // Return a success response
        return array(
            'status' => 'success',
            'message' => 'User logged out successfully!',
        );
    }

    public function add_module_to_my_script($tag, $handle, $src)
    {
        if ("copied-bundle" === $handle || "walletconnect-min-script" === $handle || "variables-script" === $handle) {
            $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        }

        return $tag;
    }

    // Enqueue styles and scripts on the public-facing side
    public function wallet_plugin_enqueue_scripts()
    {
        wp_enqueue_style('custom-style', plugin_dir_url(__FILE__) . 'assets/css/custom-style.css');

        wp_enqueue_script('wallet-connect-cdn-script', 'https://cdnjs.cloudflare.com/ajax/libs/web3/1.5.2/web3.min.js', array('jquery'), '1.0', true);

        wp_enqueue_script('ether-web3-provider-script', 'https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.1/ethers.umd.min.js', array('jquery'), '1.0', true);

        wp_enqueue_script('web3-Modal-script', plugin_dir_url(__FILE__) . 'assets/js/resources/js/web3/web3ModalDistIndex.js', array('jquery'), '1.0', true);

        wp_script_add_data('web3-Modal-script', 'html5', array('id' => 'mo_web3_web3ModalDistIndex-js'));

        wp_enqueue_script('web3-login-script', plugin_dir_url(__FILE__) . 'assets/js/resources/js/web3_login.min.js', array('jquery'), '1.0', true);

        wp_script_add_data('web3-login-script', 'html5', array('id' => 'mo_web3_web3_login-js'));

        wp_enqueue_script('web3-Modal-min-script', plugin_dir_url(__FILE__) . 'assets/js/resources/js/web3_modal.min.js', array('jquery'), '1.0', true);

        wp_script_add_data('web3-Modal-min-script', 'html5', array('id' => 'mo_web3_web3_modal-js'));

        wp_enqueue_script('variables-script', plugin_dir_url(__FILE__) . 'assets/js/dist/variables.js', array('jquery'), '1.0', true);

        wp_register_script('walletconnect-min-script', plugins_url('assets/js/resources/js/walletconnect_modal.min.js', __FILE__), array('jquery'), '1.0', true);

        // Get the dynamic website URL
        $website_url = site_url();

        // Localize the data to be used in your JavaScript file
        wp_localize_script('walletconnect-min-script', 'walletConnectData', array(
            'websiteURL' => $website_url,
        ));

        // Localize the data to be used in your JavaScript file
        wp_localize_script('walletconnect-min-script', 'customData', array(
            'websiteURL' => $website_url,
        ));

        wp_enqueue_script('walletconnect-min-script');

        wp_register_script('custom-script-wallet', plugins_url('assets/js/custom-script.js', __FILE__), array('jquery'), '1.1', true);

        // Get the dynamic website URL
        $website_url = site_url();

        // Localize the data to be used in your JavaScript file
        wp_localize_script('custom-script-wallet', 'customData', array(
            'websiteURL' => $website_url,
            'base_path' => plugin_dir_url(__FILE__),
        ));

        wp_enqueue_script('custom-script-wallet');
    }

    public function to_display_connect_wallet_button($atts)
    {
        ob_start(); // Start output buffering

        // Get the current page URL
        $current_url = get_permalink();

        $wallet_atts = shortcode_atts(array(
            'button-text' => __('Connect Wallet', 'wallet-login'),
            'logged-in-menu' => "0",
            'colour-mode' => "light",
            'logged-in-icon' => '',
            'logged-out-icon' => '',
            'login-redirect' => '',
            // 'logout-redirect' => '',
        ), $atts);

        if (empty($wallet_atts['logout-redirect'])) {
            $logout_url_field = '<input type="hidden" id="logout-url" value="' . $current_url . '"/>';
        } else {
            $logout_url_field = '<input type="hidden" id="logout-url" value="' . esc_html($wallet_atts['logout-redirect']) . '"/>';
        }

        if (empty($wallet_atts['login-redirect'])) {
            $login_url_field = '<input type="hidden" id="login-url" value=""/>';
        } else {
            $login_url_field = '<input type="hidden" id="login-url" value="' . esc_html($wallet_atts['login-redirect']) . '"/>';
        }

        //  Set Avatar Icon
        $avatar_icon = CP_URL . 'public/assets/images/avatar-icon.svg';

        if (!empty($wallet_atts['logged-out-icon'])) {
            $logged_out_icon = $wallet_atts['logged-out-icon'];
        } else {
            $logged_out_icon = $avatar_icon;
        }

        if (!empty($wallet_atts['logged-in-icon'])) {
            $logged_in_icon = $wallet_atts['logged-in-icon'];
        } else {
            $logged_in_icon = $avatar_icon;
        }

        $wallet_options = get_option('cp_wallet_settings_section_group');

        // Check if the option exists and is not empty
        if (!empty($wallet_options)) {
            // Access individual option values
            $cp_wallet_modal_title = isset($wallet_options['cp_wallet_modal_title']) ? $wallet_options['cp_wallet_modal_title'] : '';
            $cp_wallet_terms_field = isset($wallet_options['cp_wallet_terms_field']) ? $wallet_options['cp_wallet_terms_field'] : '';
        }else{
            $cp_wallet_modal_title = '';
            $cp_wallet_terms_field = '';
        }
?>

        <section class="section">
            <div class="container">
                <?php echo $logout_url_field; ?>
                <?php echo $login_url_field; ?>
                <div style="display: flex; align-items: center; gap: 50px; flex-wrap: wrap">
                    <button
                        class="btn-login <?php echo esc_html($wallet_atts['colour-mode']) . '-mode'; ?>">
                        <i><img src="<?php echo esc_html($logged_out_icon); ?>" alt="" /></i>
                        <span class="text-desktop"><?php echo esc_html($wallet_atts['button-text']); ?></span>
                        <span class="text-mobile"><?php echo esc_html($wallet_atts['button-text']); ?></span>
                    </button>

                    <div class="dropdown">
                        <button class="btn-login dropbtn <?php echo esc_html($wallet_atts['colour-mode']) . '-mode'; ?>">
                            <i><img src="<?php echo esc_html($logged_in_icon); ?>" alt="" /></i>
                            <span></span>
                        </button>
                        <ul class="dropdown-content">
<?php
// For logged-in Menu
        if (is_user_logged_in() && ($wallet_atts['logged-in-menu'] !== "0")) {

            // Get Login Menu
            $login_menu_id = $wallet_atts['logged-in-menu'];

            // Fetch the menu items for the logged-in menu
            $menu_items = wp_get_nav_menu_items($login_menu_id);

            // Looping through each menu item
            if ($menu_items) {
                foreach ($menu_items as $item) {
                    echo '<li>';
                    echo '<a href="' . $item->url . '">' . esc_html($item->title) . '</a>';
                    echo '</li>';
                }
            }
            echo '<li class="divider"></li>';
        }
        ?>
                            <li>
                                <a href="#"><?php echo esc_html(__('Disconnect', 'wallet-login')); ?>
                                    <i><img src="<?php echo plugin_dir_url(__FILE__) . 'assets' . '/images/logout-icon.svg'; ?>"
                                            alt="" /></i></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <script>
            jQuery(document).ready(function(){
                var plugin_base_path =  '<?php echo plugin_dir_url(__FILE__); ?>';
                var modal_color_mode = '<?php echo esc_html('modal-' . $wallet_atts['colour-mode']); ?>';
                var dynamic_modal_title = '<?php echo $cp_wallet_modal_title; ?>';
                var dynamic_terms = '<?php echo $cp_wallet_terms_field; ?>';
                var  modalContent = '<div class="modal '+modal_color_mode+' hidden"><button class="close-modal"></button><h4>'+dynamic_modal_title+'</h4>';
                modalContent+= '<div class="divider"></div><a href="#" class="modal-wallet-select-btn metamask_click">';
                modalContent+= '<img src="'+plugin_base_path+'assets/images/metamask_fox_modal.svg" alt="">Metamask</a>';
                modalContent+= '<a href="#" class="modal-wallet-select-btn wallet_connect_click" id="wallet_connect_click"><img src="'+plugin_base_path+'assets/images/wallet-connect-modal.svg" alt="">Wallet Connect</a>';
                modalContent+= '<a href="#" class="modal-wallet-select-btn coinbase_click"><img src="'+plugin_base_path+'assets/images/coinbase-v2-modal.svg" alt="">CoinBase</a>';
                modalContent+= '<p class="modal-terms">'+dynamic_terms+'</p></div><div class="overlay hidden"></div>`';
                jQuery( 'body' ).append( modalContent );
            });
        </script>

<?php
//

        return ob_get_clean();
    }

    public function admin_menu_wallet_button()
    {
        global $wp;
        ob_start(); // Start output buffering
        $current_url = home_url($wp->request);
        ?>

        <button class="menu-btn-login">
            <i><img src="<?php echo plugin_dir_url(__FILE__) . 'assets/' . 'images/avatar-icon.svg'; ?>"
        alt="" /></i>
            <span class="text-desktop"><?php echo __('Connect Wallet', 'wallet-login'); ?></span>
            <span class="text-mobile"><?php echo __('Connect Wallet', 'wallet-login'); ?></span>
        </button>

        <div class="dropdown">
            <button class="menu-btn-login dropbtn">
                <i> <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/' . 'images/avatar-icon.svg'; ?>" alt=""></i>
                <span></span>
            </button>

            <ul class="dropdown-content">
                <li><a href="#"><?php echo __('Disconnect', 'wallet-login'); ?><i><img src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/logout-icon.svg'; ?>" alt=""></i></a></li>
            </ul>
            <input type="hidden" id="logout-url" value="<?php echo $current_url; ?>"/>
        </div>
<?php
return ob_get_clean(); // Return the buffered output
    }
}

$frontend = new Wallet_Login_Frontend();