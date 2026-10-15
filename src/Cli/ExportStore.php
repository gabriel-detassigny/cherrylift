<?php
/**
 * WP-CLI export store.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Cli;

use Cherrylift\Contracts\Hookable;
use Cherrylift\Exception\SyncException;
use Cherrylift\Storage\TempStorage;
use Cherrylift\Storage\UploadsStorage;

/**
 * Keeps packages exported with WP-CLI until the operator collects them.
 *
 * Packages are written to wp-content/uploads/cherrylift/exports, a folder that
 * denies web access, under a name with a random part so they can't be guessed
 * on servers that ignore .htaccess. They are removed by the daily cleanup once
 * older than the temp file lifetime.
 */
class ExportStore implements Hookable {

	public const DIRECTORY_NAME = 'exports';

	/**
	 * Uploads storage.
	 *
	 * @var UploadsStorage
	 */
	private $uploads;

	/**
	 * Constructor.
	 *
	 * @param UploadsStorage $uploads Uploads storage.
	 */
	public function __construct( UploadsStorage $uploads ) {
		$this->uploads = $uploads;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( TempStorage::CRON_HOOK, array( $this, 'run_scheduled_cleanup' ) );
	}

	/**
	 * Creates the export folder, so that a run can fail before anything is exported.
	 *
	 * @return string Absolute path, without trailing slash.
	 * @throws SyncException When the folder cannot be created.
	 */
	public function prepare(): string {
		$directory = $this->uploads->ensure_directory( self::DIRECTORY_NAME );
		if ( ! wp_is_writable( $directory ) ) {
			throw new SyncException( esc_html__( 'The plugin folder in the uploads directory is not writable.', 'cherrylift' ) );
		}

		return $directory;
	}

	/**
	 * Copies a built package into the export folder.
	 *
	 * @param string $package_path Absolute path of the built package.
	 * @return string Absolute path of the stored package.
	 * @throws SyncException When the package cannot be written.
	 */
	public function save( string $package_path ): string {
		$destination = $this->prepare() . '/cherrylift-export-' . gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 12, false ) ) . '.zip';

		if ( ! copy( $package_path, $destination ) ) {
			throw new SyncException( esc_html__( 'The package could not be written to the uploads directory.', 'cherrylift' ) );
		}

		return $destination;
	}

	/**
	 * Cron callback: deletes exports older than the temp file lifetime.
	 *
	 * @return void
	 */
	public function run_scheduled_cleanup(): void {
		/** This filter is documented in src/Storage/TempStorage.php */
		$lifetime = apply_filters( 'cherrylift_temp_file_lifetime', DAY_IN_SECONDS );
		$lifetime = is_int( $lifetime ) && $lifetime > 0 ? $lifetime : DAY_IN_SECONDS;

		$this->uploads->delete_expired_files( self::DIRECTORY_NAME, 'zip', $lifetime );
	}
}
