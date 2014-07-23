<?php
/*
Plugin Name: Easy Digital Downloads - Add To Cart Redirect
Plugin URI: http://sumobi.com/
Description: Redirects customer to another post/page/download after adding a download to the cart
Version: 1.0
Author: Andrew Munro, Sumobi
Author URI: http://sumobi.com/
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
*/


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Add_To_Cart_Redirect' ) ) {

	final class EDD_Add_To_Cart_Redirect {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of EDD_Add_To_Cart_Redirect exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 1.0
		 */
		private static $instance;

		/**
		 * Plugin Version
		 */
		private $version = '1.0';

		/**
		 * Plugin Title
		 */
		public $title = 'EDD Add To Cart Redirect';


		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0
		 *
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Add_To_Cart_Redirect ) ) {
				self::$instance = new EDD_Add_To_Cart_Redirect;
				self::$instance->hooks();
			}

			return self::$instance;
		}

		/**
		 * Constructor Function
		 *
		 * @since 1.0
		 * @access private
		 */
		private function __construct() {
			self::$instance = $this;
		}

		/**
		 * Reset the instance of the class
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function hooks() {
			add_action( 'admin_init', array( $this, 'activation' ) );
			add_action( 'after_setup_theme', array( $this, 'load_textdomain' ) );
			add_action( 'edd_post_add_to_cart', array( $this, 'edd_post_add_to_cart' ), 10, 2 );
			add_action( 'edd_meta_box_settings_fields', array( $this, 'add_metabox' ), 30 );
			add_action( 'edd_purchase_link_end', array( $this, 'add_hidden_field' ), 10, 2 );
			add_action( 'wp_footer', array( $this, 'footer_js' ) );
			add_action( 'wp_ajax_edd_redirect', array( $this, 'process_ajax' ) );
			add_action( 'wp_ajax_nopriv_edd_redirect', array( $this, 'process_ajax' ) );

			add_filter( 'shortcode_atts_purchase_link', array( $this, 'filter_purchase_link' ), 10, 3 );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_meta' ), 10, 2 );
			add_filter( 'edd_metabox_fields_save', array( $this, 'save_metabox' ) );
			add_filter( 'edd_metabox_save__edd_atcr_redirect_id', array( $this, 'validate_metabox' ) );

			do_action( 'edd_atcr_setup_actions' );
		}

		/**
		 * Activation function fires when the plugin is activated.
		 *
		 * This function is fired when the activation hook is called by WordPress,
		 * it flushes the rewrite rules and disables the plugin if EDD isn't active
		 * and throws an error.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @return void
		 */
		public function activation() {
			if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
				// is this plugin active?
				if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					// deactivate the plugin
			 		deactivate_plugins( plugin_basename( __FILE__ ) );
			 		// unset activation notice
			 		unset( $_GET[ 'activate' ] );
			 		// display notice
			 		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
				}

			}
			else {
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ), 10, 2 );
			}
		}

		/**
		 * Admin notices
		 *
		 * @since 1.0
		*/
		public function admin_notices() {
			$edd_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/easy-digital-downloads/easy-digital-downloads.php', false, false );

			if ( ! is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') ) {
				echo '<div class="error"><p>' . sprintf( __( 'You must install %sEasy Digital Downloads%s to use %s.', 'edd-atcr' ), '<a href="http://easydigitaldownloads.com" title="Easy Digital Downloads" target="_blank">', '</a>', $this->title ) . '</p></div>';
			}

			if ( $edd_plugin_data['Version'] < '1.9' ) {
				echo '<div class="error"><p>' . sprintf( __( '%s requires Easy Digital Downloads Version 1.9 or greater. Please update Easy Digital Downloads.', 'edd-atcr' ), $this->title ) . '</p></div>';
			}
		}

		/**
		 * Loads the plugin language files
		 *
		 * @access public
		 * @since 1.0
		 * @return void
		 */
		public function load_textdomain() {
			// Set filter for plugin's languages directory
			$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
			$lang_dir = apply_filters( 'edd_atcr_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale        = apply_filters( 'plugin_locale',  get_locale(), 'edd-atcr' );
			$mofile        = sprintf( '%1$s-%2$s.mo', 'edd-atcr', $locale );

			// Setup paths to current locale file
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/edd-atcr/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-atcr folder
				load_textdomain( 'edd-atcr', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-atcr/languages/ folder
				load_textdomain( 'edd-atcr', $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'edd-atcr', false, $lang_dir );
			}
		}

		/**
		 * Plugin settings link
		 *
		 * @since 1.0
		*/
		public function settings_link( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=extensions' ) . '">' . __( 'Settings', 'edd-atcr' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Modify plugin metalinks
		 *
		 * @access      public
		 * @since       1.0.0
		 * @param       array $links The current links array
		 * @param       string $file A specific plugin table entry
		 * @return      array $links The modified links array
		 */
		public function plugin_meta( $links, $file ) {
		    if ( $file == plugin_basename( __FILE__ ) ) {
		        $plugins_link = array(
		            '<a title="'. __( 'View more plugins for Easy Digital Downloads by Sumobi', 'edd-atcr' ) .'" href="https://easydigitaldownloads.com/blog/author/andrewmunro/?ref=166" target="_blank">' . __( 'Author\'s EDD plugins', 'edd-atcr' ) . '</a>'
		        );

		        $links = array_merge( $links, $plugins_link );
		    }

		    return $links;
		}


		/**
		 * Filter the [purchase_link] shortcode
		 *
		 * @since 1.0
		 * @todo  waiting on EDD support
		*/
		public function filter_purchase_link( $out, $pairs, $atts ) {
			$out['redirect'] = '';
		    return $out;
		}
		
		/**
		 * Redirect for when ajax is disabled
		 * @param  [type] $download_id [description]
		 * @param  [type] $options     [description]
		 * @return [type]              [description]
		 */
		public function edd_post_add_to_cart( $download_id, $options ) {
			// only run when ajax is not enabled
			if ( edd_is_ajax_enabled() )
				return;

			if ( $this->redirect_to_checkout( $download_id ) ) {
				$redirect = edd_get_checkout_uri();
			}
			else {
				$redirect = get_permalink( $this->get_redirect_id( $download_id ) );
			}

			if ( $redirect ) {
				wp_redirect( $redirect ); exit;
			}
			
		}
		
		/**
		 * Add metabox
		 *
		 * @since 1.0
		 * @param int $post_id Download (Post) ID
		 * @return void
		 */
		public function add_metabox( $post_id = 0 ) {
		    global $edd_options;

		    $redirect_to_checkout = get_post_meta( $post_id, '_edd_actr_redirect_to_checkout', true );
			$redirect_id          = get_post_meta( $post_id, '_edd_atcr_redirect_id', true );
		?>
			
			<p><strong><?php _e( 'Add To Cart Redirect:', 'edd-atcr' ); ?></strong></p>
			
			<p>
				<label for="edd-atcr-redirect-to-checkout">
					<input type="checkbox" name="_edd_actr_redirect_to_checkout" id="edd-atcr-redirect-to-checkout" value="1" <?php checked( 1, $redirect_to_checkout ); ?> />
					<?php _e( 'Redirect to checkout', 'edd-atcr' ); ?>
				</label>
			</p>

				<?php echo EDD()->html->text( array(
					'name'  => '_edd_atcr_redirect_id',
					'value' => $redirect_id,
					'class' => 'large-text'
				) ); ?>
			<label for="_edd_atcr_redirect_id">	
				<?php _e( 'Enter the ID of the post/page/download you would like to redirect to', 'edd-atcr' ); ?>
			</label>

		<?php
		}
		
		/**
		 * Save metabox field
		 * @param  [type] $fields [description]
		 * @return [type]         [description]
		 */
		public function save_metabox( $fields ) {
			$fields[] = '_edd_atcr_redirect_id';
			$fields[] = '_edd_actr_redirect_to_checkout';

		    return $fields;
		}

		/**
		 * Validate metabox to ensure only integers are allowed
		 * @param  [type] $field [description]
		 * @return [type]        [description]
		 */
		public function validate_metabox( $field ) {
			$field = absint( $field ) ? $field : '';
		    return $field;
		}


		/**
		 * Get redirect
		 * @param  integer $download_id [description]
		 * @return [type]               [description]
		 */
		public function get_redirect_id( $download_id = 0 ) {
			$redirect_id = get_post_meta( $download_id, '_edd_atcr_redirect_id', true );

			if ( $redirect_id ) {
				return $redirect_id;
			}

			return false;
		}

		/**
		 * Redirect to checkout
		 * @param  integer $download_id [description]
		 * @return [type]               [description]
		 */
		public function redirect_to_checkout( $download_id = 0 ) {
			$redirect_to_checkout = get_post_meta( $download_id, '_edd_actr_redirect_to_checkout', true );

			if ( $redirect_to_checkout ) {
				return true;
			}

			return false;
		}

		/**
		 * Insert hidden input field to tell ajax where to redirect to
		 * @param  [type] $download_id [description]
		 * @return [type]              [description]
		 */
		public function add_hidden_field( $download_id, $args ) {
			if ( ! edd_is_ajax_enabled() )
				return;

			// download should redirect to checkout
			if ( $this->redirect_to_checkout( $download_id ) ) {
				$download_id = 'redirect-to-checkout';
			}
			// passed in from the shortcode
			else if ( isset( $args['redirect'] ) ) {
				$download_id = (int) $args['redirect'];
			}
			else if ( $download_id ) {
				$download_id = $this->get_redirect_id( $download_id );
			}
			else {
				$download_id = '';
			}

			if ( $download_id ) {
				echo '<input type="text" name="edd_redirect" data-action="edd_redirect" class="edd-redirect" value="' . $download_id . '" />';
			}
			
		}
		
		/**
		 * Footer Javascript
		 * @return [type] [description]
		 */
		function footer_js() {
			// requires Ajax to be enabled
			if ( ! edd_is_ajax_enabled() )
				return;

			?>
			<script>
				jQuery(document).ready(function($) {
				
					$('body').on('click.eddAddToCart', '.edd-add-to-cart', function (e) {
						var form     = $(this).closest('form');
						var download = $(form).find('.edd-redirect').val();
						var action   = $(form).find('.edd-redirect').data('action');

				        var data = {
				            action: action,
				            download_id: download,
				            post_data: $(form).serialize()
				        };

				        $.ajax({
				            type: "POST",
				            data: data,
				            dataType: "json",
				            url: edd_scripts.ajaxurl,
				            success: function (response) {
				            	// redirect
				            	if ( response.redirect ) {
				            		window.location = response.redirect;
				            	}
					        }
				        }).fail(function (response) {
				            if ( window.console && window.console.log ) {
				                console.log( response );
				            }
				        }).done(function (response) {

				        });


					});
				});

				</script>
			<?php
		}
		
		/**
		 * Process Ajax
		 * @return [type] [description]
		 */
		function process_ajax() {
			if ( isset( $_POST['download_id'] ) ) {

				$return = array();

				if ( $_POST['download_id'] == 'redirect-to-checkout' ) {
					// redirect straight to cart
					$return['redirect'] = edd_get_checkout_uri();
				} else {
					// get permalink for post ID
					$return['redirect'] = get_permalink( $_POST['download_id'] );
				}

				echo json_encode( $return );
			}
			edd_die();
		}


	}
}

/**
 * Loads a single instance of EDD_Add_To_Cart_Redirect
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $edd_add_to_cart_redirect = edd_add_to_cart_redirect(); ?>
 *
 * @since 1.0
 *
 * @see EDD_Add_To_Cart_Redirect::get_instance()
 *
 * @return object Returns an instance of the EDD_Add_To_Cart_Redirect class
 */
function edd_add_to_cart_redirect() {
	return EDD_Add_To_Cart_Redirect::get_instance();
}

/**
 * Loads plugin after all the others have loaded and have registered their hooks and filters
 *
 * @since 1.0
*/
add_action( 'plugins_loaded', 'edd_add_to_cart_redirect', apply_filters( 'edd_add_to_cart_redirect_action_priority', 10 ) );