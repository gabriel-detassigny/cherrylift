<?php
/**
 * Exporter factory for tests.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Tests\Integration\Export;

use Cherrylift\Content\BlockReferenceMap;
use Cherrylift\Content\ContentReferenceFinder;
use Cherrylift\Export\Collectors\AttachmentCollector;
use Cherrylift\Export\Collectors\MetaCollector;
use Cherrylift\Export\Collectors\PostCollector;
use Cherrylift\Export\Collectors\TermCollector;
use Cherrylift\Export\Exporter;
use Cherrylift\Export\ExportSettings;
use Cherrylift\Identity\EntityUuid;
use Cherrylift\Manifest\ManifestCodec;
use Cherrylift\Manifest\Schema;
use Cherrylift\Package\PackageWriter;
use Cherrylift\Storage\TempStorage;
use Cherrylift\Support\ObjectPermissions;

/**
 * Builds an Exporter wired like the plugin does.
 */
trait BuildsExporter {

	protected function build_exporter(): Exporter {
		$settings = new ExportSettings();
		$meta     = new MetaCollector( $settings );
		$schema   = new Schema();

		return new Exporter(
			$settings,
			new EntityUuid(),
			array(
				new PostCollector( $settings, $meta, new ContentReferenceFinder( new BlockReferenceMap() ) ),
				new AttachmentCollector( $meta ),
				new TermCollector( $meta ),
			),
			new PackageWriter( new ManifestCodec( $schema ), $schema ),
			new TempStorage(),
			'0.1.0-test',
			new ObjectPermissions()
		);
	}

	/**
	 * Creates an attachment backed by a real uploaded file.
	 */
	protected function create_image_attachment( string $file = 'canola.jpg' ): int {
		return (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/' . $file );
	}
}
