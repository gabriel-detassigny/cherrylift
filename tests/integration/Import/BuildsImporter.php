<?php
/**
 * Importer factory for tests.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Tests\Integration\Import;

use Cherrylift\Content\BlockReferenceMap;
use Cherrylift\Content\ContentReferenceFinder;
use Cherrylift\Content\ContentRewriter;
use Cherrylift\Export\ExportSettings;
use Cherrylift\Identity\EntityUuid;
use Cherrylift\Import\AuthorResolver;
use Cherrylift\Import\Handlers\AttachmentHandler;
use Cherrylift\Import\Handlers\PostHandler;
use Cherrylift\Import\Handlers\TermHandler;
use Cherrylift\Import\Importer;
use Cherrylift\Import\ImportPlanner;
use Cherrylift\Import\MetaImporter;
use Cherrylift\Manifest\ManifestCodec;
use Cherrylift\Manifest\Schema;
use Cherrylift\Package\Package;
use Cherrylift\Package\PackageLimits;
use Cherrylift\Package\PackageReader;
use Cherrylift\Storage\TempStorage;
use Cherrylift\Support\ObjectPermissions;

/**
 * Builds an Importer wired like the plugin does, and reads packages.
 */
trait BuildsImporter {

	/**
	 * Temp directories to delete in tear_down.
	 *
	 * @var string[]
	 */
	protected $temp_dirs = array();

	protected function build_importer(): Importer {
		$settings = new ExportSettings();
		$meta     = new MetaImporter( $settings );
		$authors  = new AuthorResolver();
		$map      = new BlockReferenceMap();
		$perms    = new ObjectPermissions();

		return new Importer(
			new ImportPlanner(
				new EntityUuid(),
				array(
					new PostHandler( $settings, $meta, $authors, new ContentReferenceFinder( $map ), new ContentRewriter( $map ), $perms ),
					new AttachmentHandler( $meta, $authors, $perms ),
					new TermHandler( $meta, $perms ),
				)
			),
			new EntityUuid()
		);
	}

	protected function read_package( string $zip_path ): Package {
		$storage           = new TempStorage();
		$directory         = $storage->create_directory();
		$this->temp_dirs[] = $directory;

		return ( new PackageReader( new ManifestCodec( new Schema() ), new PackageLimits() ) )->read( $zip_path, $directory );
	}

	protected function delete_temp_dirs(): void {
		$storage = new TempStorage();
		foreach ( $this->temp_dirs as $directory ) {
			$storage->delete( $directory );
		}
		$this->temp_dirs = array();
	}
}
