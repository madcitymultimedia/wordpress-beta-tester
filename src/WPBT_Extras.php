<?php
/**
 * WordPress Beta Tester
 *
 * @package WordPress_Beta_Tester
 * @author Andy Fragen, original author Peter Westwood.
 * @license GPLv2+
 * @copyright 2009-2016 Peter Westwood (email : peter.westwood@ftwr.co.uk)
 */

class WPBT_Extras {

	/**
	 * Placeholder for saved options.
	 *
	 * @var $options
	 */
	protected static $options;

	/**
	 * Constructor.
	 *
	 * @param WP_Beta_Tester $wp_beta_tester
	 * @param array $options
	 * @return void
	 */
	public function __construct( WP_Beta_Tester $wp_beta_tester, $options ) {
		self::$options        = $options;
		$this->wp_beta_tester = $wp_beta_tester;
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'wp_beta_tester_add_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'wp_beta_tester_add_settings', array( $this, 'add_settings' ) );
		add_action( 'wp_beta_tester_add_admin_page', array( $this, 'add_admin_page' ), 10, 2 );
		add_action( 'wp_beta_tester_update_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Add class settings tab.
	 *
	 * @param array $tabs
	 * @return void
	 */
	public function add_settings_tab( $tabs ) {
		return array_merge( $tabs, array( 'wp_beta_tester_extras' => esc_html__( 'Extra Settings', 'wordpress-beta-tester' ) ) );
	}

	/**
	 * Setup Settings API.
	 *
	 * @return void
	 */
	public function add_settings() {
		register_setting(
			'wp_beta_tester',
			'wp_beta_tester_extras',
			array( 'WPBT_Settings', 'sanitize' )
		);

		add_settings_section(
			'wp_beta_tester_extras',
			esc_html__( 'Extra Settings', 'wordpress-beta-tester' ),
			array( $this, 'print_extra_settings_top' ),
			'wp_beta_tester_extras'
		);

		// Example with WSOD.
		add_settings_field(
			'trac44458',
			null,
			array( 'WPBT_Settings', 'checkbox_setting' ),
			'wp_beta_tester_extras',
			'wp_beta_tester_extras',
			array(
				'id'    => 'trac44458',
				'title' => esc_html__( 'Help test Servehappy\'s WSOD, Trac #44458', 'wordpress-beta-tester' ),
			)
		);
	}

	/**
	 * Save settings.
	 *
	 * @param mixed $post_data
	 * @return void
	 */
	public function save_settings( $post_data ) {
		if ( isset( $post_data['option_page'] ) &&
			'wp_beta_tester_extras' === $post_data['option_page']
		) {
			$options = isset( $post_data['wp-beta-tester'] )
				? $post_data['wp-beta-tester']
				: array();
			$options = WPBT_Settings::sanitize( $options );
			$this->update_constants( self::$options, $options );
			$filtered_options = array_filter( self::$options, array( $this, 'filter_save_settings' ) );
			$options          = array_merge( $filtered_options, $options );
			update_site_option( 'wp_beta_tester', (array) $options );
			add_filter( 'wp_beta_tester_save_redirect', array( $this, 'save_redirect_page' ) );
		}
	}

	/**
	 * Filter saved setting to remove unchecked checkboxes.
	 * // TODO: convert to anonymous function.
	 *
	 * @param array $checked
	 * @return void
	 */
	private function filter_save_settings( $checked ) {
		return '1' !== $checked;
	}

	/**
	 * Run on activation hook.
	 *
	 * @return void
	 */
	public function activate() {
		$add = array_filter( self::$options, array( $this, 'get_checked_options' ) );
		if ( ! empty( $add ) ) {
			$this->add_constants( $add );
		}
	}

	/**
	 * Run on deactivation hook.
	 *
	 * @return void
	 */
	public function deactivate() {
		$remove = array_filter( self::$options, array( $this, 'get_checked_options' ) );
		if ( ! empty( $remove ) ) {
			$this->remove_constants( $remove );
		}
	}

	/**
	 * Filter saved settings to get checked options.
	 * // TODO: convert to anonymous function.
	 *
	 * @param mixed $checked
	 * @return void
	 */
	private function get_checked_options( $checked ) {
		return '1' === $checked;
	}

	/**
	 * Update Feature Flag constants in wp-config.php.
	 *
	 * @param array $old Current value of self::$options.
	 * @param array $new New value of $options.
	 * @return void
	 */
	private function update_constants( $old, $new ) {
		$remove = array_diff_assoc( $old, $new );
		$add    = array_diff_assoc( $new, $old );

		if ( ! empty( $add ) ) {
			$this->add_constants( $add );
		}
		if ( ! empty( $remove ) ) {
			$this->remove_constants( $remove );
		}
	}

	/**
	 * Add constants to wp-config.php file.
	 *
	 * @uses https://github.com/wp-cli/wp-config-transformer
	 *
	 * @param array $add
	 * @return void
	 */
	private function add_constants( $add ) {
		$config_transformer = new WPBT_WPConfigTransformer( ABSPATH . 'wp-config.php' );
		foreach ( array_keys( $add ) as $constant ) {
			$feature_flag = strtoupper( 'wp_beta_tester_' . $constant );
			$config_transformer->update(
				'constant',
				$feature_flag,
				'true',
				array(
					'raw'       => true,
					'normalize' => true,
				)
			);
		}
	}

	/**
	 * Remove constants from wp-config.php file.
	 *
	 * @uses https://github.com/wp-cli/wp-config-transformer
	 *
	 * @param array $remove
	 * @return void
	 */
	private function remove_constants( $remove ) {
		$config_transformer = new WPBT_WPConfigTransformer( ABSPATH . 'wp-config.php' );
		foreach ( array_keys( $remove ) as $constant ) {
			$feature_flag = strtoupper( 'wp_beta_tester_' . $constant );
			$config_transformer->remove( 'constant', $feature_flag );
		}
	}

	/**
	 * Redirect page/tab after saving options.
	 *
	 * @param mixed $option_page
	 * @return void
	 */
	public function save_redirect_page( $option_page ) {
		return array_merge( $option_page, array( 'wp_beta_tester_extras' ) );
	}

	/**
	 * Print settings section information.
	 *
	 * @return void
	 */
	public function print_extra_settings_top() {
		esc_html_e( 'This area is for extra special beta testing. If nothing is present there are no additional features that need testing.', 'wordpress-beta-tester' );
	}

	/**
	 * Create core settings page.
	 *
	 * @param array $tab
	 * @param string $action
	 * @return void
	 */
	public function add_admin_page( $tab, $action ) {
		?>
		<div>
			<?php if ( 'wp_beta_tester_extras' === $tab ) : ?>
			<form method="post" action="<?php esc_attr_e( $action ); ?>">
				<?php settings_fields( 'wp_beta_tester_extras' ); ?>
				<?php do_settings_sections( 'wp_beta_tester_extras' ); ?>
				<?php submit_button(); ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
