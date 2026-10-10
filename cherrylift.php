<?php
/**
 * Plugin Name:       Cherrylift – Selective Content Sync
 * Plugin URI:        https://github.com/gabriel-detassigny/cherrylift
 * Description:       Push selected content (posts, pages, custom post types, terms and media) from one WordPress site to another (e.g. staging to production) using entity manifests, with automatic ID remapping, so live data is never overwritten.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Gabriel de Tassigny
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cherrylift
 * Domain Path:       /languages
 *
 * @package Cherrylift
 */

defined( 'ABSPATH' ) || exit;

define( 'CHERRYLIFT_VERSION', '0.2.0' );
define( 'CHERRYLIFT_FILE', __FILE__ );
define( 'CHERRYLIFT_MIN_PHP', '7.4' );
define( 'CHERRYLIFT_MIN_WP', '6.9' );

/*
 * Bail out early, with an admin notice, if the environment is not supported.
 * This file must stay parseable by old PHP versions, so keep it simple.
 */
if (
	version_compare( PHP_VERSION, CHERRYLIFT_MIN_PHP, '<' )
	|| version_compare( get_bloginfo( 'version' ), CHERRYLIFT_MIN_WP, '<' )
	|| ! is_readable( __DIR__ . '/vendor/autoload.php' )
) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
				$message = __( 'Cherrylift is missing its dependencies. Please run "composer install" or install a release build of the plugin.', 'cherrylift' );
			} else {
				$message = sprintf(
					/* translators: 1: Minimum required PHP version, 2: Minimum required WordPress version. */
					__( 'Cherrylift requires PHP %1$s and WordPress %2$s or higher. The plugin is not running.', 'cherrylift' ),
					CHERRYLIFT_MIN_PHP,
					CHERRYLIFT_MIN_WP
				);
			}

			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	);

	return;
}

require_once __DIR__ . '/vendor/autoload.php';

register_deactivation_hook( __FILE__, array( \Cherrylift\Storage\TempStorage::class, 'unschedule_cleanup' ) );

add_action(
	'plugins_loaded',
	static function () {
		( new \Cherrylift\Plugin( CHERRYLIFT_FILE, CHERRYLIFT_VERSION ) )->boot();
	}
);
