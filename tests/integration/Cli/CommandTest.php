<?php
/**
 * Tests for the WP-CLI command.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Tests\Integration\Cli;

use Cherrylift\Cli\Command;
use Cherrylift\Cli\ExportStore;
use Cherrylift\Manifest\ManifestCodec;
use Cherrylift\Manifest\Schema;
use Cherrylift\Package\PackageLimits;
use Cherrylift\Package\PackageReader;
use Cherrylift\Storage\TempStorage;
use Cherrylift\Storage\UploadsStorage;
use Cherrylift\Tests\Integration\Export\BuildsExporter;
use Cherrylift\Tests\Integration\Import\BuildsImporter;
use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI\Loggers\Execution;
use WP_UnitTestCase;

// WP-CLI loads its helper functions itself when it boots; tests load them directly.
require_once dirname( __DIR__, 3 ) . '/vendor/wp-cli/wp-cli/php/utils.php';

/**
 * @covers \Cherrylift\Cli\Command
 */
class CommandTest extends WP_UnitTestCase {

	use BuildsExporter;
	use BuildsImporter;

	/**
	 * @var string
	 */
	private $work_dir;

	/**
	 * Records WP-CLI output.
	 *
	 * @var Execution
	 */
	private $logger;

	public function set_up(): void {
		parent::set_up();
		$this->work_dir = ( new TempStorage() )->create_directory();
		// The WP_CLI constant (trusted CLI runs) isn't defined under PHPUnit; act as an administrator instead.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// Real WP-CLI (installed for the i18n tooling): record output, and make error() throw instead of exiting.
		$this->logger = new Execution();
		WP_CLI::set_logger( $this->logger );
		$this->set_capture_exit( true );
		$this->delete_exports();
	}

	public function tear_down(): void {
		$this->set_capture_exit( false );
		if ( is_dir( $this->work_dir . '/readonly' ) ) {
			chmod( $this->work_dir . '/readonly', 0700 );
		}
		( new TempStorage() )->delete( $this->work_dir );
		$this->delete_exports();
		parent::tear_down();
	}

	/**
	 * Runs an export with --porcelain and returns what it printed (WP_CLI::line() bypasses the logger).
	 */
	private function export_porcelain( int $post_id ): string {
		ob_start();
		$this->command()->export(
			array(),
			array(
				'post_ids'  => (string) $post_id,
				'porcelain' => true,
			)
		);

		return trim( (string) ob_get_clean() );
	}

	private function delete_exports(): void {
		foreach ( glob( trailingslashit( wp_upload_dir()['basedir'] ) . 'cherrylift/exports/*.zip' ) as $file ) {
			unlink( $file );
		}
	}

	private function set_capture_exit( bool $capture ): void {
		$property = new \ReflectionProperty( WP_CLI::class, 'capture_exit' );
		$property->setAccessible( true );
		$property->setValue( null, $capture );
	}

	private function command(): Command {
		return new Command( $this->build_exporter(), new TempStorage(), new ExportStore( new UploadsStorage() ), new PackageReader( new ManifestCodec( new Schema() ), new PackageLimits() ), $this->build_importer() );
	}

	public function test_export_writes_the_package_to_the_protected_uploads_folder(): void {
		$post = self::factory()->post->create();

		$this->command()->export( array(), array( 'post_ids' => (string) $post ) );

		$folder = trailingslashit( wp_upload_dir()['basedir'] ) . 'cherrylift/exports';
		$files  = glob( $folder . '/*.zip' );
		$this->assertCount( 1, $files );
		$this->assertMatchesRegularExpression( '/^cherrylift-export-\d{8}-\d{6}-[a-z0-9]{12}\.zip$/', basename( $files[0] ), 'The name has a random part, so it cannot be guessed.' );
		$this->assertFileExists( $folder . '/.htaccess', 'The folder denies web access.' );
		$this->assertFileExists( $folder . '/index.php' );
		$this->assertFileExists( dirname( $folder ) . '/.htaccess' );
		$this->assertStringContainsString( 'Success: Exported 2 entities to ' . $files[0], $this->logger->stdout );
	}

	public function test_export_ignores_paths_given_on_the_command_line(): void {
		$post = self::factory()->post->create();
		$file = $this->work_dir . '/export.zip';

		// The --file option was removed: WP-CLI rejects it, and the command never writes outside its folder.
		$this->command()->export(
			array(),
			array(
				'post_ids' => (string) $post,
				'file'     => $file,
			)
		);

		$this->assertFileDoesNotExist( $file );
		$this->assertCount( 1, glob( trailingslashit( wp_upload_dir()['basedir'] ) . 'cherrylift/exports/*.zip' ) );
	}

	public function test_porcelain_outputs_only_the_package_path(): void {
		$post = self::factory()->post->create();

		$path = $this->export_porcelain( $post );

		$this->assertFileExists( $path );
		$this->assertStringStartsWith( trailingslashit( wp_upload_dir()['basedir'] ) . 'cherrylift/exports/', $path );
	}

	public function test_export_fails_cleanly_before_exporting_when_uploads_are_not_writable(): void {
		$post     = self::factory()->post->create();
		$readonly = $this->work_dir . '/readonly';
		mkdir( $readonly );
		chmod( $readonly, 0500 );
		$set_basedir = static function ( array $dirs ) use ( $readonly ) {
			$dirs['basedir'] = $readonly;
			return $dirs;
		};
		add_filter( 'upload_dir', $set_basedir );

		try {
			$this->command()->export( array(), array( 'post_ids' => (string) $post ) );
			$this->fail( 'The export should have stopped.' );
		} catch ( ExitException $e ) {
			$this->assertStringContainsString( 'uploads directory', $this->logger->stderr );
		} finally {
			remove_filter( 'upload_dir', $set_basedir );
		}

		$this->assertNull( ( new \Cherrylift\Identity\EntityUuid() )->find( 'post', $post ), 'Nothing is exported (no UUIDs assigned) when the destination is unusable.' );
	}

	public function test_scheduled_cleanup_removes_expired_exports(): void {
		$path = $this->export_porcelain( self::factory()->post->create() );
		touch( $path, time() - DAY_IN_SECONDS - 60 );

		do_action( TempStorage::CRON_HOOK );

		$this->assertFileDoesNotExist( $path );
	}

	public function test_errors_are_printed_as_plain_text(): void {
		$file = $this->work_dir . '/invalid.zip';
		$zip  = new \ZipArchive();
		$zip->open( $file, \ZipArchive::CREATE );
		$zip->addFromString( 'manifest.json', '{"schema_version":1}' );
		$zip->close();

		try {
			$this->command()->import( array( $file ), array() );
			$this->fail( 'The import should have stopped.' );
		} catch ( ExitException $e ) {
			$this->assertStringContainsString( 'invalid: "', $this->logger->stderr, 'Terminal output must not hold HTML entities.' );
		}
	}
}
