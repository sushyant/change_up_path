<?php
/**
 * Plugin Name: Change Upload Directory
 * Plugin URI:  https://github.com/sushyant/change_up_path
 * Description: Allows administrators to change the WordPress upload directory without editing wp-config.php, migrating old media files (including thumbnails) to the new folder automatically.
 * Version:     1.3.0
 * Author:      Sushyant Zavarzadeh
 * Author URI:  https://sushyant.com
 * Text Domain: change_up_path
 * License:     GPL v3 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Change_Upload_Directory {
	
	const OPTION_NAME          = 'cud_upload_path';
	const PREVIOUS_OPTION_NAME = 'cud_previous_upload_path';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register admin settings and page
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Override WP's default upload directory
		add_filter( 'upload_dir', array( $this, 'override_upload_dir' ) );

		// Create folder on plugin activation
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );

		// Detect changes in the custom path and migrate if needed
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'maybe_migrate_old_uploads' ), 10, 2 );
	}

	/**
	 * Plugin activation: Create the default folder if it doesn't exist.
	 */
	public function activate_plugin() {
		$upload_path = get_option( self::OPTION_NAME, 'wp-content/uploads' );
		$this->create_directory_if_not_exists( $upload_path );

		// Record this as "previous" path so we can detect changes later
		update_option( self::PREVIOUS_OPTION_NAME, $upload_path );
	}

	/**
	 * Register our admin settings page.
	 */
	public function register_admin_page() {
		add_options_page(
			__( 'Change Upload Directory', 'change_up_path' ),
			__( 'Change Upload Directory', 'change_up_path' ),
			'manage_options',
			'change-upload-directory',
			array( $this, 'settings_page_html' )
		);
	}

	/**
	 * Register the custom upload path setting with WordPress.
	 */
	public function register_settings() {
		register_setting(
			'cud_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_upload_path' ),
				'default'           => 'wp-content/uploads',
			)
		);
	}

	/**
	 * Sanitize user input for the upload path (remove traversals, invalid chars, etc.).
	 *
	 * @param string $path
	 * @return string
	 */
	public function sanitize_upload_path( $path ) {
		$path = trim( $path );

		// Remove directory traversal and null bytes
		$path = str_replace( array( '../', '..\\', "\0" ), '', $path );

		// Disallow certain characters
		$path = preg_replace( '/[\?\*\:\<\>\|"]/', '', $path );

		// Ensure the directory exists (after sanitization)
		$this->create_directory_if_not_exists( $path );

		return $path;
	}

	/**
	 * Render the settings page in wp-admin.
	 */
	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Change Upload Directory', 'change_up_path' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'cud_settings_group' ); ?>
				<?php do_settings_sections( 'cud_settings_group' ); ?>

				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPTION_NAME ); ?>">
								<?php esc_html_e( 'Custom Upload Directory Path', 'change_up_path' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="text" 
								id="<?php echo esc_attr( self::OPTION_NAME ); ?>" 
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>" 
								value="<?php echo esc_attr( get_option( self::OPTION_NAME, 'wp-content/uploads' ) ); ?>" 
								class="regular-text"
							/>
							<p class="description">
								<?php esc_html_e( 'Examples:', 'change_up_path' ); ?>
								<br/>
								<code><?php esc_html_e( 'wp-content/custom-uploads', 'change_up_path' ); ?></code>
								<br/>
								<code><?php esc_html_e( 'my-uploads', 'change_up_path' ); ?></code>
								<br/>
								<code><?php esc_html_e( '/absolute/path/outside/wp', 'change_up_path' ); ?></code>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Changes', 'change_up_path' ) ); ?>
			</form>

			<hr>
			<p>
				<strong><?php esc_html_e( 'Important:', 'change_up_path' ); ?></strong>
				<?php esc_html_e( 'All old media and thumbnails will be moved to the new folder, and the database references updated. This can be time-consuming for large sites. Always back up your database and uploads folder before changing this setting.', 'change_up_path' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Override WordPress's default upload directory with the custom path.
	 *
	 * @param array $upload
	 * @return array
	 */
	public function override_upload_dir( $upload ) {
		$custom_path = get_option( self::OPTION_NAME, 'wp-content/uploads' );

		// Convert relative path to absolute
		if ( ! $this->is_absolute_path( $custom_path ) ) {
			$custom_dir = ABSPATH . $custom_path;
		} else {
			$custom_dir = $custom_path;
		}
		$custom_dir = rtrim( $custom_dir, '/\\' );

		$upload['basedir'] = $custom_dir;
		$upload['path']    = $custom_dir . $upload['subdir'];

		// Build base URL
		$site_url    = get_option( 'siteurl' );
		$option_path = get_option( self::OPTION_NAME );

		if ( ! $this->is_absolute_path( $option_path ) ) {
			// Relative URL
			$upload['baseurl'] = rtrim( $site_url, '/' ) . '/' . ltrim( $option_path, '/' );
		} else {
			// Absolute path inside WP; best guess
			$upload['baseurl'] = site_url() . '/' . ltrim( str_replace( ABSPATH, '', $custom_dir ), '/' );
		}
		$upload['url'] = $upload['baseurl'] . $upload['subdir'];

		return $upload;
	}

	/**
	 * If the user changes the custom path, migrate all existing uploads (main + thumbnails).
	 *
	 * @param string $old_value
	 * @param string $new_value
	 */
	public function maybe_migrate_old_uploads( $old_value, $new_value ) {
		$prev_path = get_option( self::PREVIOUS_OPTION_NAME, 'wp-content/uploads' );

		// If no actual change, do nothing
		if ( $prev_path === $new_value ) {
			return;
		}

		$abs_old_dir = $this->make_absolute_path( $prev_path );
		$abs_new_dir = $this->make_absolute_path( $new_value );

		// If old folder doesn't exist, nothing to migrate
		if ( ! is_dir( $abs_old_dir ) ) {
			update_option( self::PREVIOUS_OPTION_NAME, $new_value );
			return;
		}

		// Migrate physical files + DB references
		$this->migrate_attachments( $abs_old_dir, $abs_new_dir );

		// Update the previous path option
		update_option( self::PREVIOUS_OPTION_NAME, $new_value );
	}

	/**
	 * Move main image files to the new folder and update `_wp_attached_file`.
	 *
	 * @param string $abs_old_dir
	 * @param string $abs_new_dir
	 */
	private function migrate_attachments( $abs_old_dir, $abs_new_dir ) {
		$this->create_directory_if_not_exists( $abs_new_dir );

		global $wpdb;
		$attachments = $wpdb->get_results("
			SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_wp_attached_file'
		");

		if ( ! $attachments ) {
			return;
		}

		foreach ( $attachments as $attachment ) {
			$old_meta_value = $attachment->meta_value; 

			$old_file_path = rtrim( $abs_old_dir, '/\\' ) . '/' . $old_meta_value;
			$old_file_path = str_replace( '//', '/', $old_file_path );

			if ( ! file_exists( $old_file_path ) ) {
				continue;
			}

			$new_file_path = rtrim( $abs_new_dir, '/\\' ) . '/' . $old_meta_value;
			wp_mkdir_p( dirname( $new_file_path ) );

			// Attempt to move the main file
			if ( @rename( $old_file_path, $new_file_path ) ) {
				// Move thumbnails and update metadata
				$this->maybe_move_thumbnails( $attachment->post_id, $abs_old_dir, $abs_new_dir, $old_meta_value );

				// Update _wp_attached_file if it contains a partial path
				$relative_old_path_fragment = str_replace( ABSPATH, '', $abs_old_dir ) . '/';
				$cleaned_meta_value        = str_replace( $relative_old_path_fragment, '', $old_meta_value );

				if ( $cleaned_meta_value !== $old_meta_value ) {
					update_post_meta( $attachment->post_id, '_wp_attached_file', $cleaned_meta_value );
				}
			}
		}
	}

	/**
	 * Move thumbnail files (sub-sizes) to the new folder and update `_wp_attachment_metadata`.
	 *
	 * @param int    $attachment_id
	 * @param string $abs_old_dir
	 * @param string $abs_new_dir
	 * @param string $old_meta_file  e.g. '2025/01/image.jpg'
	 */
	private function maybe_move_thumbnails( $attachment_id, $abs_old_dir, $abs_new_dir, $old_meta_file ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! $metadata || ! is_array( $metadata ) ) {
			return;
		}

		/**
		 * `metadata['file']`  => '2025/01/image.jpg'
		 * `metadata['sizes']` => array( 'thumbnail' => array('file' => 'image-150x150.jpg'), ...)
		 */
		$old_subdir = dirname( $old_meta_file );
		$old_subdir = ( '.' === $old_subdir ) ? '' : $old_subdir;

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_key => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}

				$thumb_filename = $size_data['file'];

				$old_thumb_path = rtrim( $abs_old_dir, '/\\' ) . '/' . $old_subdir . '/' . $thumb_filename;
				$new_thumb_path = rtrim( $abs_new_dir, '/\\' ) . '/' . $old_subdir . '/' . $thumb_filename;

				if ( file_exists( $old_thumb_path ) ) {
					wp_mkdir_p( dirname( $new_thumb_path ) );
					if ( @rename( $old_thumb_path, $new_thumb_path ) ) {
						// If `_wp_attachment_metadata` used partial absolute paths, strip them
						$relative_old_path_fragment = str_replace( ABSPATH, '', $abs_old_dir ) . '/';
						$new_thumb_relative         = str_replace( $relative_old_path_fragment, '', $thumb_filename );

						if ( $new_thumb_relative !== $thumb_filename ) {
							$metadata['sizes'][$size_key]['file'] = $new_thumb_relative;
						}
					}
				}
			}
		}

		// Update metadata if changed
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Create directory if it doesn't exist, using WP's built-in function.
	 *
	 * @param string $path
	 */
	private function create_directory_if_not_exists( $path ) {
		$abs_path = $this->make_absolute_path( $path );
		if ( ! is_dir( $abs_path ) ) {
			wp_mkdir_p( $abs_path );
		}
	}

	/**
	 * Convert user setting (possibly relative) to absolute path.
	 *
	 * @param string $path
	 * @return string
	 */
	private function make_absolute_path( $path ) {
		if ( ! $this->is_absolute_path( $path ) ) {
			return rtrim( ABSPATH . $path, '/\\' );
		}
		return rtrim( $path, '/\\' );
	}

	/**
	 * Check if a path is absolute (starts with / or C:\).
	 *
	 * @param string $path
	 * @return bool
	 */
	private function is_absolute_path( $path ) {
		return (bool) preg_match( '#^(/|[a-zA-Z]:)#', $path );
	}
}

// Instantiate the plugin
new Change_Upload_Directory();
