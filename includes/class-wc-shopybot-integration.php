<?php
/**
 * Integration Demo Integration.
 *
 * @package  WC_Chatbot_Integration
 * @category Integration
 * @author   WooThemes
 */
if(!defined('ABSPATH')) {
	exit;
}

if(!class_exists('WC_Shopybot_Integration')) :
	class WC_Shopybot_Integration extends WC_Integration {
		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			global $woocommerce;
			$this->shopybot_host = $this->shopybot_url();

			$this->id                  = 'shopybot-woocommerce';
			$this->method_title        = __('Shopybot', 'shopybot-woocommerce');
			$this->method_description  = __('Connect your WooCommerce shop with Facebook Messenger!', 'shopybot-woocommerce');
			$this->export_filename     = $this->id . '.xml';
			$this->auto_checkout_param = 'autocheckout-id';

			$this->shopybot_export = new WC_Shopybot_Export($this->id, array());

			$this->check_inbound_data();
			// Define user set variables.
			$this->shopybot_api_key      = get_option('shopybot_api_key');
			$this->shopybot_fb_page_id   = get_option('shopybot_fb_page_id');
			$this->shopybot_fb_page_name = get_option('shopybot_fb_page_name');

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Actions.
            // Admin options, look in parent abstract class
			add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
			add_action('wp_ajax_generate_export_url', array($this, 'generate_export_url'));
			// Checking whether we auto checkouting
			add_action('template_redirect',  array($this, 'wc_auto_checkout'),  10);
        }

		public function wc_auto_checkout() {
			if (isset($_GET[$this->auto_checkout_param])) {
				$product_id = $_GET[$this->auto_checkout_param];
				WC()->cart->add_to_cart($product_id);

				wp_redirect(wc_get_checkout_url());
			}
		}

		function generate_export_url() {
			global $wpdb; // this is how you get access to the database
			$WC_Shopybot_Export = new WC_Shopybot_Export();
			$WC_Shopybot_Export->export();
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		function delete_shopybot_wp_options(){
            delete_option('shopybot_api_key');
            delete_option('shopybot_shop_token');
            delete_option('shopybot_connect_fb_page_url');
            delete_option('shopybot_connect_shop_url');
            delete_option('shopybot_disconnect_shop_url');
            delete_option('shopybot_disconnect_fb_page_url');
            delete_option('shopybot_fb_page_id');
            delete_option('shopybot_fb_page_name');

            delete_option('shopybot-woocommerce_in_process');
            delete_option('shopybot-woocommerce_page');
            delete_option('shopybot-woocommerce_pages');
            delete_option('shopybot-woocommerce_lock');
            delete_option('shopybot-woocommerce_get_ids');
        }


		public function check_inbound_data() {
			if(isset($_GET["connect_data"]) && strlen($_GET["connect_data"]) > 0) {
				$data = json_decode(base64_decode($_GET["connect_data"]), true);
				if($data) {
					update_option('shopybot_api_key', $data["shopybot_api_key"], $autoload = false);
					update_option('shopybot_shop_token', $data["shopybot_shop_token"], $autoload = false);
					update_option('shopybot_connect_fb_page_url', $data["shopybot_connect_fb_page_url"], $autoload = false);
					update_option('shopybot_connect_shop_url', $data["shopybot_connect_shop_url"], $autoload = false);
					update_option('shopybot_disconnect_shop_url', $data["shopybot_disconnect_shop_url"], $autoload = false);
					update_option('shopybot_disconnect_fb_page_url', $data["shopybot_disconnect_fb_page_url"], $autoload = false);

					add_action('admin_notices', 'shopybot_shop_connect_success');
				} else {
					add_action('admin_notices', 'shopybot_shop_connect_error');
				}

			}

			if(isset($_GET["disconnect_data"]) && strlen($_GET["disconnect_data"]) > 0) {
				$data = json_decode(base64_decode($_GET["disconnect_data"]), true);

        if(isset($data['bot_delete_result'])) {
            $delete_result = $data['bot_delete_result'];

            if(isset($delete_result['error']) && $delete_result['error'] == 'wrong_api_key') {
                $this->delete_shopybot_wp_options();
                add_action('admin_notices', 'shopybot_shop_disconnect_error');
            }

            if(isset($delete_result['ok']) && $delete_result['ok'] == 'bot_deleted') {
                $this->delete_shopybot_wp_options();
                add_action('admin_notices', 'shopybot_shop_disconnect_success');
            } else {
                add_action('admin_notices', 'shopybot_shop_disconnect_error');
            }
        }
			}

			if(isset($_GET["connect_fb_data"]) && strlen($_GET["connect_fb_data"]) > 0) {
				$data = json_decode(base64_decode(urldecode($_GET["connect_fb_data"])), true);

				update_option('shopybot_fb_page_name', $data["shopybot_fb_page_name"], $autoload = false);
				update_option('shopybot_fb_page_id', $data["shopybot_fb_page_id"], $autoload = false);
				add_action('admin_notices', function() {
					?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Congratulations! You connected Facebook Page to your Bot', 'shopybot-woocommerce'); ?></p>
            </div>
					<?php
				});
			}

			if(isset($_GET["disconnect_fb_data"]) && strlen($_GET["disconnect_fb_data"]) > 0) {
				$data = json_decode(base64_decode($_GET["disconnect_fb_data"]), true);

				$api_key = get_option('shopybot_api_key');
				if($api_key == $data['api_key']) {
					update_option('shopybot_fb_page_name', null, $autoload = false);
					update_option('shopybot_fb_page_id', null, $autoload = false);
					add_action('admin_notices', function() {
						?>
              <div class="notice notice-success is-dismissible">
                  <p><?php _e('Congratulations! You disconnected Facebook Page from your Bot', 'shopybot-woocommerce'); ?></p>
              </div>
						<?php
					});
				} else {
					add_action('admin_notices', function() {
						?>
              <div class="notice notice-error is-dismissible">
                  <p><?php _e('Error! Cannot disconnect Facebook Page. Please contact support@shopybot.com', 'shopybot-woocommerce'); ?></p>
              </div>
						<?php
					});
				}
			}
		}

		public function init_form_facebook() {
            if(!$this->shopybot_fb_page_id) {
                $data_array = array(
                    'api_key'      => $this->shopybot_api_key,
                    'shop_token'   => get_option('shopybot_shop_token'),
                    'redirect_url' => get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=integration&section=shopybot-woocommerce',
                );

                $data = base64_encode(json_encode($data_array));

                $this->form_fields['connect_facebook_page'] = array(
                    'title'             => __('Connect to Facebook Page', 'shopybot-woocommerce'),
                    'type'              => 'button',
                    'custom_attributes' => array(
                        'onclick' => "javascript: shopybot_fb_connect('" . get_option('shopybot_connect_fb_page_url') . "?data=$data" . "');",
                    ),
                    'description'       => __('Click to connect your shop to shopybot.com<br><small class="shopybot-well">This will open a ShopyBot page where you connect your Facebook page.<br>If you do not have a Facebook Page - <a href="http://facebook.com/pages/create/?ref=shopybot" target="_blank">create it here</a></small>', 'shopybot-woocommerce'),
                    'desc_tip'          => false
                );

            } else {
                $this->form_fields['facebook_page_link'] = array(
                    'title'       => __('Facebook Page', 'shopybot-woocommerce'),
                    'type'        => 'title',
                    'description' => sprintf(
                        __('<span class="shopybot-fb-page-name"><img src="http://graph.facebook.com/%s/picture?type=square"/> <span>%s</span></span><div class="shopybot-fb-buttons"><a target="_blank" href="%s" class="button-secondary shopybot-open-facebook-page">Open Facebook Page</a><br/><a target="_blank" href="%s" class="button-secondary shopybot-open-bot">Open Bot</a></div>', 'shopybot-woocommerce'),
                        $this->shopybot_fb_page_id,
                        $this->shopybot_fb_page_name,
                        'https://www.facebook.com/' . $this->shopybot_fb_page_id,
                        'https://m.me/' . $this->shopybot_fb_page_id
                    ),
                    'id'          => 'export_url',
                );

                $data_array = array(
                    'api_key'      => $this->shopybot_api_key,
                    'shop_token'   => get_option('shopybot_shop_token'),
                    'redirect_url' => get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=integration&section=shopybot-woocommerce',
                );

                $data = base64_encode(json_encode($data_array));

                $this->form_fields['disconnect_facebook_page'] = array(
                    'title'             => __('Disconnect Facebook Page', 'shopybot-woocommerce'),
                    'type'              => 'button',
                    'custom_attributes' => array(
                        'onclick' => "javascript: shopybot_fb_disconnect('" . get_option('shopybot_disconnect_fb_page_url') . "?data=$data" . "');",
                    ),
                    'description'       => __('Click to disconnect your shop from shopybot.com.<br> This will opens a page on www.shopybot.com, disconnects from current facebook page and return you back here', 'shopybot-woocommerce'),
                    'desc_tip'          => false
                );
            }
        }

        public function init_form_export_file() {
            if($this->offers_ready()) {
                $this->form_fields['export_url']          = array(
                    'title'       => __('Export', 'shopybot-woocommerce'),
                    'type'        => 'title',
                    'description' => sprintf(
                        __('<div id="shopybot-num-products"><strong>%s</strong> products ready for export</div>ShopyBot will use this URL to import products: <a target="_blank" href="%s">%s</a>', 'shopybot-woocommerce'),
                        number_format($this->shopybot_export->numberOffers()),
                        $this->export_url(),
                        $this->export_url()
                    ),
                    'id'          => 'export_url',
                );
                $this->form_fields['generate_export_url'] = array(
                    'title'             => __('Re-generate Export File', 'shopybot-woocommerce'),
                    'type'              => 'button',
                    'custom_attributes' => array(
                        'onclick' => "return false",
                    ),

                    'description' =>
                        '<img class="shopybot-loading" src="' . plugins_url('/assets/img/loading.gif', dirname(__FILE__)) . '" />'.
												__('Click to re-generate Products for Export. This will take few minutes, depends on how many products you have', 'shopybot-woocommerce').
												'<br/>' .
                        '<div id="shopybot-generate-progress"> <strong>'.
												__('Do not close the browser window during generation process!', 'shopybot-woocommerce').
												'</strong></div>',
                    'desc_tip'    => false,
                );
            } else {
                $this->form_fields['export_url']          = array(
                    'title'       => __('Export', 'shopybot-woocommerce'),
                    'type'        => 'title',
                    'description' => __("You don't have export file. Please click button to generate it", 'shopybot-woocommerce'),
                    'id'          => 'export_url',
                );
                $this->form_fields['generate_export_url'] = array(
                    'title'             => __('Generate Export File', 'shopybot-woocommerce'),
                    'type'              => 'button',
                    'custom_attributes' => array(
                        'onclick' => "return false",
                    ),
                    'description'       => __('Click to generate Export File. This will take few minutes, depends on how many products you have.<br/> <div id="shopybot-generate-progress"><strong>Do not close the browser window during generation process!</strong><img class="shopybot-loading-gif" src="/wp-content/plugins/shopybot-woocommerce/assets/img/loading.gif" src-fallback="/wp-content/plugins/shopybot-woocommerce/assets/img/loading.gif" /></div>', 'shopybot-woocommerce'),
                    'desc_tip'          => false,
                );
            }
        }

        public function init_form_disconnect_from_shopybot() {
            $data_array = array(
                'api_key'      => $this->shopybot_api_key,
                'redirect_url' => get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=integration&section=shopybot-woocommerce',
            );

            $data = base64_encode(json_encode($data_array));

            $this->form_fields['disconnect'] = array(
                'title'             => __('Disconnect', 'shopybot-woocommerce'),
                'type'              => 'button',
                'custom_attributes' => array(
                    'onclick' => "javascript: shopybot_disconnect('{$this->shopybot_host}/ecommerce/disconnect-shop?data=$data')",
                ),
                'description'       =>
									__('Click to disconnect your store from shopybot.com', 'shopybot-woocommerce').
									'<br><small class="shopybot-well">'.
									__('This will delete your Bot with all the products and statistics information on shopybot.com.', 'shopybot-woocommerce').
									'<br><span style="color: red">'.
									__('WARNING! Cannot be undone! But you can connect your store one more time and import all your products as before.', 'shopybot-woocommerce').
									'</span> </small>',
                'desc_tip'          => false,
            );
        }

        public function init_form_connect_to_shopybot() {
            $store_name = $this->get_store_name();

            $data_array = array(
                'store_name'          => $store_name,
                'store_description'   => $this->get_store_description(),
                'products_export_url' => $this->export_url(),
                'store_crc'           => md5(get_site_url()),
                'redirect_url'        => get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=integration&section=shopybot-woocommerce',
            );

            $data = base64_encode(json_encode($data_array));

            $this->form_fields['connect'] = array(
                'title'             => __('Connect to ShopyBot!', 'shopybot-woocommerce'),
                'type'              => 'button',
                'custom_attributes' => array(
                    'onclick' => "javascript: shopybot_connect('{$this->shopybot_host}/ecommerce/connect-shop?data=$data')",
                ),
                'description'       => __('Click to connect your store to shopybot.com platform', 'shopybot-woocommerce'),
                'desc_tip'          => false,
            );
        }

		/**
		 * Initialize integration settings form fields.
		 *
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = array();

			if($this->shopybot_api_key) {
                $this->init_form_export_file();
			    $this->init_form_facebook();
			    $this->init_form_disconnect_from_shopybot();
			} else {
			    $this->init_form_connect_to_shopybot();
            }

			$this->form_fields['contact_us'] = array(
				'title'       => __('Contact us', 'shopybot-woocommerce'),
				'type'        => 'title',
				'description' => __('Please contact us at <a href="mailto:support@shopybot.com">support@shopybot.com</a> if you have questions or suggestions.', 'shopybot-woocommerce'),
				'id'          => 'export_url',
			);
		}

		/**
		 * Get sanitized store name
		 * @return mixed|string
		 */
		private function get_store_name() {
			$name = trim(str_replace("'", "\u{2019}", html_entity_decode(get_bloginfo('name'), ENT_QUOTES, 'UTF-8')));
			if($name) {
				return $name;
			}
			// Fallback to site url
			$url = get_site_url();
			if($url) {
				return parse_url($url, PHP_URL_HOST);
			}
			// If site url doesn't exist, fall back to http host.
			if($_SERVER['HTTP_HOST']) {
				return $_SERVER['HTTP_HOST'];
			}

			// If http host doesn't exist, fall back to local host name.
			$url = gethostname();

			return ($url) ? $url : 'Please enter information';
		}

		/**
		 * Get store description, sanitized
		 * @return mixed|string
		 */
		private function get_store_description() {
			$description = trim(str_replace("'", "\u{2019}", html_entity_decode(get_bloginfo('description'), ENT_QUOTES, 'UTF-8')));
			if($description) {
				return $description;
			}

			return $this->get_store_name();
		}


		/**
		 * Generate Button HTML.
		 */
		public function generate_button_html($key, $data) {
			$field    = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'class'             => 'button-secondary',
				'css'               => '',
				'custom_attributes' => array(),
				'desc_tip'          => false,
				'description'       => '',
				'title'             => '',
			);

			$data = wp_parse_args($data, $defaults);

			ob_start();
			?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
							<?php echo $this->get_tooltip_html($data); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <button class="<?php echo esc_attr($data['class']); ?>" type="button" name="<?php echo esc_attr($field); ?>"
                            id="<?php echo esc_attr($field); ?>"
                            style="<?php echo esc_attr($data['css']); ?>" <?php echo $this->get_custom_attribute_html($data); ?>><?php echo wp_kses_post($data['title']); ?></button>
									<?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
			<?php
			return ob_get_clean();
		}


		/**
		 * Display errors by overriding the display_errors() method
		 * @see display_errors()
		 */
		public function display_errors() {
			// loop through each error and display it
			foreach($this->errors as $key => $value) {
            ?>
              <div class="error">
                  <p><?php _e('Looks like you made a mistake with the ' . $value . ' field. Make sure it isn&apos;t longer than 20 characters', 'shopybot-woocommerce'); ?></p>
              </div>
            <?php
			}
		}

		private function export_url() {
			return get_site_url() . '/' . $this->export_filename;
		}

		private function shopybot_url() {
			if($_SERVER["SERVER_NAME"] == "shopybotshop.loc") {
				return "http://localhost:4001"; # dev settings
			} else {
				return 'https://www.shopybot.com';
			}
		}

		private function offers_ready() {
			return ($this->shopybot_export->numberOffers() > 0) && !$this->export_file_generating();
		}

		private function export_file_generating() {
			return $this->shopybot_export->isLock();
		}

		/**
		 * @return string
		 */
		private function get_export_filename() {
			return wp_upload_dir()['basedir'] . '/' . $this->export_filename;
		}

		/**
		 * @return string
		 */
		private function get_export_filename_tmp() {
			return wp_upload_dir()['basedir'] . '/' . $this->export_filename_tmp;
		}


	}

endif;
