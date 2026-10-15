<?php
/**
 * WP-CLI command.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Cli;

use Cherrylift\Exception\SyncException;
use Cherrylift\Export\Exporter;
use Cherrylift\Import\Importer;
use Cherrylift\Import\ImportPlan;
use Cherrylift\Package\PackageReader;
use Cherrylift\Storage\TempStorage;
use Cherrylift\Support\PlainText;
use WP_CLI;

/**
 * Sync selected content between WordPress sites.
 */
class Command {

	/**
	 * Exporter.
	 *
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * Temporary storage.
	 *
	 * @var TempStorage
	 */
	private $storage;

	/**
	 * Export store.
	 *
	 * @var ExportStore
	 */
	private $exports;

	/**
	 * Package reader.
	 *
	 * @var PackageReader
	 */
	private $reader;

	/**
	 * Importer.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @param Exporter      $exporter Exporter.
	 * @param TempStorage   $storage  Temporary storage.
	 * @param ExportStore   $exports  Export store.
	 * @param PackageReader $reader   Package reader.
	 * @param Importer      $importer Importer.
	 */
	public function __construct( Exporter $exporter, TempStorage $storage, ExportStore $exports, PackageReader $reader, Importer $importer ) {
		$this->exporter = $exporter;
		$this->storage  = $storage;
		$this->exports  = $exports;
		$this->reader   = $reader;
		$this->importer = $importer;
	}

	/**
	 * Exports content and its dependencies to a package.
	 *
	 * Dependencies (parent pages, terms, featured images, media and synced
	 * patterns used in content) are included automatically.
	 *
	 * The package is written to wp-content/uploads/cherrylift/exports/, a
	 * folder that blocks web access, under a name with a random part. Move it
	 * from there to where you need it: exports are deleted after a day.
	 *
	 * ## OPTIONS
	 *
	 * --post_ids=<ids>
	 * : Comma-separated IDs of the posts, pages or other content to export.
	 *
	 * [--dry-run]
	 * : List what would be exported without writing a package.
	 *
	 * [--porcelain]
	 * : Output only the path of the package.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export two pages and everything they depend on.
	 *     $ wp cherrylift export --post_ids=12,34
	 *
	 *     # Export, then move the package somewhere else.
	 *     $ mv "$(wp cherrylift export --post_ids=12,34 --porcelain)" ~/sync.zip
	 *
	 *     # See what would be exported.
	 *     $ wp cherrylift export --post_ids=12 --dry-run
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function export( array $args, array $assoc_args ): void {
		$post_ids  = array_filter( array_map( 'intval', explode( ',', (string) ( $assoc_args['post_ids'] ?? '' ) ) ) );
		$dry_run   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$porcelain = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain', false );

		try {
			if ( $dry_run ) {
				$this->print_summary( $this->exporter->plan( $post_ids )->get_summary() );
				return;
			}

			// Check the destination before exporting anything (exports assign UUIDs on this site).
			$this->exports->prepare();

			$result = $this->exporter->export( $post_ids );
		} catch ( SyncException $e ) {
			WP_CLI::error( PlainText::from_exception( $e ) );
			return;
		}

		try {
			$destination = $this->exports->save( $result->get_package_path() );
		} catch ( SyncException $e ) {
			WP_CLI::error( PlainText::from_exception( $e ) );
			return;
		} finally {
			$this->storage->delete( $result->get_directory() );
		}

		if ( $porcelain ) {
			WP_CLI::line( $destination );
			return;
		}

		$this->print_summary( $result->get_plan()->get_summary() );
		WP_CLI::success( sprintf( 'Exported %d entities to %s.', count( $result->get_manifest()->get_entities() ), $destination ) );
	}

	/**
	 * Imports a package.
	 *
	 * Existing content is matched by UUID (then by slug, or by file for media)
	 * and updated; everything else is created. IDs and media URLs in content
	 * are remapped to this site's.
	 *
	 * Run it as an administrator (`--user=<login>`): without a user, content is
	 * filtered like it would be for an untrusted author (no scripts, iframes, …).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path of the package (.zip) to import.
	 *
	 * [--dry-run]
	 * : Show what would be created, updated or skipped, without writing anything.
	 *
	 * [--skip=<uuids>]
	 * : Comma-separated UUIDs of entities not to write.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview an import.
	 *     $ wp cherrylift import ~/sync.zip --dry-run
	 *
	 *     # Import as an administrator.
	 *     $ wp cherrylift import ~/sync.zip --user=admin
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = (string) ( $args[0] ?? '' );

		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			try {
				$plan = $this->importer->plan( $this->reader->read_manifest( $file ) );
			} catch ( SyncException $e ) {
				WP_CLI::error( PlainText::from_exception( $e ) );
				return;
			}

			$this->print_plan( $plan );
			return;
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			WP_CLI::warning( 'No administrator is set: content will be filtered. Use --user=<login> to import content as-is.' );
		}

		$skip      = array_filter( array_map( 'trim', explode( ',', (string) ( $assoc_args['skip'] ?? '' ) ) ) );
		$directory = null;

		try {
			$directory = $this->storage->create_directory();
			$report    = $this->importer->import( $this->reader->read( $file, $directory ), $skip );
		} catch ( SyncException $e ) {
			WP_CLI::error( PlainText::from_exception( $e ) );
			return;
		} finally {
			if ( null !== $directory ) {
				$this->storage->delete( $directory );
			}
		}

		$rows = array();
		foreach ( $report->get_items() as $item ) {
			$rows[] = array(
				'result'   => $item['result'],
				'type'     => $item['subtype_label'],
				'title'    => $item['title'],
				'local_id' => $item['local_id'],
				'message'  => $item['message'],
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'result', 'type', 'title', 'local_id', 'message' ) );

		foreach ( $report->get_warnings() as $warning ) {
			WP_CLI::warning( $warning );
		}

		$counts  = $report->get_counts();
		$summary = sprintf( '%d created, %d updated, %d skipped, %d linked, %d missing, %d failed.', $counts['created'], $counts['updated'], $counts['skipped'], $counts['linked'], $counts['missing'], $counts['failed'] );

		if ( $report->has_failures() ) {
			WP_CLI::error( 'Import finished with errors: ' . $summary );
			return;
		}

		WP_CLI::success( 'Imported: ' . $summary );
	}

	/**
	 * Prints an import plan.
	 *
	 * @param ImportPlan $plan Plan.
	 * @return void
	 */
	private function print_plan( ImportPlan $plan ): void {
		$rows = array();
		foreach ( $plan->get_items() as $item ) {
			$rows[] = array(
				'action'   => $item['action'],
				'type'     => $item['subtype_label'],
				'title'    => $item['title'],
				'match'    => $item['match'],
				'local_id' => $item['local_id'],
				'uuid'     => $item['uuid'],
				'reason'   => $item['reason'],
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'action', 'type', 'title', 'match', 'local_id', 'uuid', 'reason' ) );

		$counts = $plan->get_counts();
		WP_CLI::log( sprintf( 'Dry run: %d to create, %d to update, %d to skip, %d to link, %d missing.', $counts['create'], $counts['update'], $counts['skip'], $counts['link'], $counts['missing'] ) );
	}

	/**
	 * Prints an export summary.
	 *
	 * @param array{entities: array<int, array<string, mixed>>, file_count: int, file_bytes: int, file_size: string, warnings: string[]} $summary Summary.
	 * @return void
	 */
	private function print_summary( array $summary ): void {
		$rows = array();
		foreach ( $summary['entities'] as $entity ) {
			$rows[] = array(
				'type'      => $entity['type'],
				'subtype'   => $entity['subtype'],
				'source_id' => $entity['source_id'],
				'title'     => $entity['title'],
				'included'  => $entity['reference_only'] ? 'reference only' : ( $entity['selected'] ? 'selected' : 'dependency' ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'type', 'subtype', 'source_id', 'title', 'included' ) );
		WP_CLI::log( sprintf( '%d file(s), %s.', $summary['file_count'], size_format( $summary['file_bytes'] ) ) );

		foreach ( $summary['warnings'] as $warning ) {
			WP_CLI::warning( $warning );
		}
	}
}
