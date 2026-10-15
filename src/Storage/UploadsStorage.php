<?php
/**
 * Private storage under the uploads directory.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Storage;

use Cherrylift\Exception\SyncException;
use WP_Filesystem_Direct;

/**
 * Resolves and protects the plugin's folders under wp-content/uploads/cherrylift/.
 *
 * Every file the plugin keeps beyond a single request (uploaded packages
 * waiting for import, WP-CLI exports) lives here: the uploads directory is
 * resolved at runtime with wp_upload_dir(), is writable on any working site
 * and is shared between web servers, unlike the system temp dir. The folders
 * deny web access (index.php and .htaccess), and files get unguessable names
 * for servers that ignore .htaccess.
 */
class UploadsStorage {

	public const DIRECTORY_NAME = 'cherrylift';

	/**
	 * Returns the base directory, wp-content/uploads/cherrylift.
	 *
	 * @return string Absolute path, without trailing slash.
	 */
	public function get_base_directory(): string {
		return untrailingslashit( wp_upload_dir( null, false )['basedir'] ) . '/' . self::DIRECTORY_NAME;
	}

	/**
	 * Returns the path of a subfolder of the base directory.
	 *
	 * @param string $name Subfolder name, e.g. 'exports'.
	 * @return string Absolute path, without trailing slash.
	 */
	public function get_directory( string $name ): string {
		return $this->get_base_directory() . '/' . sanitize_file_name( $name );
	}

	/**
	 * Creates a subfolder and the access protection of it and of the base directory.
	 *
	 * @param string $name Subfolder name.
	 * @return string Absolute path, without trailing slash.
	 * @throws SyncException When the folder cannot be created.
	 */
	public function ensure_directory( string $name ): string {
		$directory = $this->get_directory( $name );

		if ( ! wp_mkdir_p( $directory ) ) {
			throw new SyncException( esc_html__( 'The plugin folder in the uploads directory could not be created.', 'cherrylift' ) );
		}

		foreach ( array( $this->get_base_directory(), $directory ) as $folder ) {
			$this->protect( $folder );
		}

		return $directory;
	}

	/**
	 * Deletes files with the given extension older than the lifetime from a subfolder.
	 *
	 * @param string $name      Subfolder name.
	 * @param string $extension File extension, without dot.
	 * @param int    $lifetime  Lifetime in seconds.
	 * @return int Number of files deleted.
	 */
	public function delete_expired_files( string $name, string $extension, int $lifetime ): int {
		$directory = $this->get_directory( $name );
		if ( ! is_dir( $directory ) ) {
			return 0;
		}

		$deleted = 0;
		$suffix  = '.' . $extension;
		$entries = $this->filesystem()->dirlist( $directory, false );
		foreach ( is_array( $entries ) ? $entries : array() as $file => $entry ) {
			if ( 'f' === $entry['type'] && substr( (string) $file, -strlen( $suffix ) ) === $suffix && (int) $entry['lastmodunix'] < time() - $lifetime ) {
				wp_delete_file( $directory . '/' . $file );
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Adds the files that deny web access to a folder, if missing.
	 *
	 * @param string $directory Absolute path.
	 * @return void
	 */
	private function protect( string $directory ): void {
		$filesystem = $this->filesystem();
		if ( ! $filesystem->exists( $directory . '/index.php' ) ) {
			// Explicit mode: FS_CHMOD_FILE is only defined once WP_Filesystem() has run, which REST requests don't do.
			$filesystem->put_contents( $directory . '/index.php', "<?php\n// Silence is golden.\n", 0644 );
		}
		if ( ! $filesystem->exists( $directory . '/.htaccess' ) ) {
			$filesystem->put_contents( $directory . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n", 0644 );
		}
	}

	/**
	 * Returns a direct filesystem instance. Only used for the plugin's own folders.
	 *
	 * @return WP_Filesystem_Direct
	 */
	private function filesystem(): WP_Filesystem_Direct {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		return new WP_Filesystem_Direct( null );
	}
}
