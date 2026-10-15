<?php
/**
 * Plugin composition root.
 *
 * @package Cherrylift
 */

namespace Cherrylift;

use Cherrylift\Admin\AdminPage;
use Cherrylift\Cli\CliCommands;
use Cherrylift\Cli\Command;
use Cherrylift\Cli\ExportStore;
use Cherrylift\Content\BlockReferenceMap;
use Cherrylift\Content\ContentReferenceFinder;
use Cherrylift\Content\ContentRewriter;
use Cherrylift\Contracts\Hookable;
use Cherrylift\Export\Collectors\AttachmentCollector;
use Cherrylift\Export\Collectors\MetaCollector;
use Cherrylift\Export\Collectors\PostCollector;
use Cherrylift\Export\Collectors\TermCollector;
use Cherrylift\Export\Exporter;
use Cherrylift\Export\ExportSettings;
use Cherrylift\Identity\EntityUuid;
use Cherrylift\Import\AuthorResolver;
use Cherrylift\Import\BatchLimits;
use Cherrylift\Import\Handlers\AttachmentHandler;
use Cherrylift\Import\Handlers\PostHandler;
use Cherrylift\Import\Handlers\TermHandler;
use Cherrylift\Import\Importer;
use Cherrylift\Import\ImportJobStore;
use Cherrylift\Import\ImportPlanner;
use Cherrylift\Import\MetaImporter;
use Cherrylift\Import\PackageStore;
use Cherrylift\Manifest\ManifestCodec;
use Cherrylift\Manifest\Schema;
use Cherrylift\Package\PackageLimits;
use Cherrylift\Package\PackageReader;
use Cherrylift\Package\PackageWriter;
use Cherrylift\Rest\EntitiesController;
use Cherrylift\Rest\ExportController;
use Cherrylift\Rest\ImportController;
use Cherrylift\Storage\TempStorage;
use Cherrylift\Storage\UploadsStorage;
use Cherrylift\Support\Capabilities;
use Cherrylift\Support\ObjectPermissions;

/**
 * Builds the plugin services and registers their hooks.
 */
class Plugin {

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $version     Plugin version.
	 */
	public function __construct( string $plugin_file, string $version ) {
		$this->plugin_file = $plugin_file;
		$this->version     = $version;
	}

	/**
	 * Returns the plugin version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Builds services and registers hooks. Safe to call more than once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( $this->get_services() as $service ) {
			$service->register_hooks();
		}

		/**
		 * Fires once Cherrylift has registered all of its services.
		 *
		 * Add-ons should hook here to register their own integrations.
		 *
		 * @since 0.2.0
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'cherrylift_loaded', $this );
	}

	/**
	 * Builds the list of hookable services.
	 *
	 * @return Hookable[]
	 */
	private function get_services(): array {
		$capabilities = new Capabilities();
		$storage      = new TempStorage();
		$uploads      = new UploadsStorage();
		$settings     = new ExportSettings();
		$uuids        = new EntityUuid();
		$schema       = new Schema();
		$codec        = new ManifestCodec( $schema );
		$block_map    = new BlockReferenceMap();
		$finder       = new ContentReferenceFinder( $block_map );
		$meta         = new MetaCollector( $settings );
		$permissions  = new ObjectPermissions();
		$exporter     = new Exporter(
			$settings,
			$uuids,
			array(
				new PostCollector( $settings, $meta, $finder ),
				new AttachmentCollector( $meta ),
				new TermCollector( $meta ),
			),
			new PackageWriter( $codec, $schema ),
			$storage,
			$this->version,
			$permissions
		);

		$meta_importer = new MetaImporter( $settings );
		$authors       = new AuthorResolver();
		$importer      = new Importer(
			new ImportPlanner(
				$uuids,
				array(
					new PostHandler( $settings, $meta_importer, $authors, $finder, new ContentRewriter( $block_map ), $permissions ),
					new AttachmentHandler( $meta_importer, $authors, $permissions ),
					new TermHandler( $meta_importer, $permissions ),
				)
			),
			$uuids
		);
		$reader        = new PackageReader( $codec, new PackageLimits() );
		$package_store = new PackageStore( $uploads );
		$export_store  = new ExportStore( $uploads );
		$job_store     = new ImportJobStore();

		return array(
			new AdminPage( $this->plugin_file, $capabilities ),
			$storage,
			$package_store,
			$export_store,
			$job_store,
			new EntitiesController( $capabilities, $settings, $uuids, $permissions ),
			new ExportController( $capabilities, $settings, $exporter, $storage ),
			new ImportController( $capabilities, $package_store, $reader, $importer, $storage, $job_store, new BatchLimits() ),
			new CliCommands( new Command( $exporter, $storage, $export_store, $reader, $importer ) ),
		);
	}
}
