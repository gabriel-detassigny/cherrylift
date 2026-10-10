<?php
/**
 * Entities REST controller.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Rest;

use Cherrylift\Export\ExportSettings;
use Cherrylift\Identity\EntityUuid;
use Cherrylift\Support\Capabilities;
use Cherrylift\Support\ObjectPermissions;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /cherrylift/v1/entities: lists content that can be selected for export.
 */
class EntitiesController extends Controller {

	/**
	 * WP_Query argument holding the statuses each post type lists from every author (see restrict_to_readable()).
	 */
	private const READABLE_QUERY_ARG = 'cherrylift_readable';

	/**
	 * Export settings.
	 *
	 * @var ExportSettings
	 */
	private $settings;

	/**
	 * UUID service.
	 *
	 * @var EntityUuid
	 */
	private $uuids;

	/**
	 * Per-object permissions.
	 *
	 * @var ObjectPermissions
	 */
	private $permissions;

	/**
	 * Constructor.
	 *
	 * @param Capabilities      $capabilities Capability checker.
	 * @param ExportSettings    $settings     Export settings.
	 * @param EntityUuid        $uuids        UUID service.
	 * @param ObjectPermissions $permissions  Per-object permissions.
	 */
	public function __construct( Capabilities $capabilities, ExportSettings $settings, EntityUuid $uuids, ObjectPermissions $permissions ) {
		parent::__construct( $capabilities );
		$this->settings    = $settings;
		$this->uuids       = $uuids;
		$this->permissions = $permissions;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		parent::register_hooks();
		add_filter( 'posts_where', array( $this, 'restrict_to_readable' ), 10, 2 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/entities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'search'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => '',
					),
					'status'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'orderby'   => array(
						'type'    => 'string',
						'default' => 'modified',
						'enum'    => array( 'modified', 'date', 'title' ),
					),
					'order'     => array(
						'type'    => 'string',
						'default' => 'desc',
						'enum'    => array( 'asc', 'desc' ),
					),
				),
			)
		);
	}

	/**
	 * Lists exportable content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( WP_REST_Request $request ) {
		$post_types = $this->settings->get_exportable_post_types();
		$statuses   = $this->settings->get_exportable_statuses();

		$post_type = (string) $request['post_type'];
		if ( '' !== $post_type ) {
			if ( ! in_array( $post_type, $post_types, true ) ) {
				return $this->invalid_param( 'post_type' );
			}
			$post_types = array( $post_type );
		}

		$status = (string) $request['status'];
		if ( '' !== $status ) {
			if ( ! in_array( $status, $statuses, true ) ) {
				return $this->invalid_param( 'status' );
			}
			$statuses = array( $status );
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $statuses,
				's'                      => (string) $request['search'],
				'paged'                  => (int) $request['page'],
				'posts_per_page'         => (int) $request['per_page'],
				'orderby'                => (string) $request['orderby'],
				'order'                  => strtoupper( (string) $request['order'] ),
				'ignore_sticky_posts'    => true,
				self::READABLE_QUERY_ARG => $this->get_readable_statuses( $post_types, $statuses ),
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			// The query already skips what the user can't read; this also honours custom map_meta_cap rules.
			if ( $post instanceof WP_Post && $this->permissions->can_read_post( $post->ID ) ) {
				$items[] = $this->prepare_item( $post );
			}
		}

		$response = new WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * Returns, per post type, the statuses the current user can read from every author.
	 *
	 * Administrators read everything, so this only restricts the listing when a site lowers the plugin's
	 * capability (for example to edit_posts): users then see published content, their own posts, and other
	 * people's drafts or private posts only where their role allows it.
	 *
	 * @param string[] $post_types Listed post types.
	 * @param string[] $statuses   Listed statuses.
	 * @return array<string, string[]>|null Null when the user can read everything listed.
	 */
	private function get_readable_statuses( array $post_types, array $statuses ): ?array {
		$readable   = array();
		$restricted = false;

		foreach ( $post_types as $post_type ) {
			$type_object = get_post_type_object( $post_type );
			$allowed     = array();

			foreach ( $statuses as $status ) {
				if ( 'publish' === $status ) {
					$can_read = true;
				} elseif ( 'private' === $status ) {
					$can_read = null !== $type_object && current_user_can( $type_object->cap->read_private_posts );
				} else {
					// Drafts, pending and scheduled posts are readable by those who can edit them.
					$can_read = null !== $type_object && current_user_can( $type_object->cap->edit_others_posts );
				}

				if ( $can_read ) {
					$allowed[] = $status;
				} else {
					$restricted = true;
				}
			}

			$readable[ $post_type ] = $allowed;
		}

		return $restricted ? $readable : null;
	}

	/**
	 * Limits the listing query to posts the current user can read: the statuses from get_readable_statuses(),
	 * plus the user's own posts.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public function restrict_to_readable( string $where, WP_Query $query ): string {
		$readable = $query->get( self::READABLE_QUERY_ARG );
		if ( ! is_array( $readable ) ) {
			return $where;
		}

		global $wpdb;
		$clauses = array( $wpdb->prepare( "{$wpdb->posts}.post_author = %d", get_current_user_id() ) );

		foreach ( $readable as $post_type => $statuses ) {
			if ( array() === $statuses ) {
				continue;
			}
			$clauses[] = $wpdb->prepare(
				"( {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status IN (" . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ') )',
				array_merge( array( (string) $post_type ), array_values( $statuses ) )
			);
		}

		return $where . ' AND ( ' . implode( ' OR ', $clauses ) . ' )';
	}

	/**
	 * Prepares one item.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private function prepare_item( WP_Post $post ): array {
		$type_object   = get_post_type_object( $post->post_type );
		$status_object = get_post_status_object( $post->post_status );
		$author        = get_userdata( (int) $post->post_author );

		return array(
			'id'              => $post->ID,
			'title'           => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'post_type'       => $post->post_type,
			'post_type_label' => null !== $type_object ? $type_object->labels->singular_name : $post->post_type,
			'status'          => $post->post_status,
			'status_label'    => null !== $status_object ? (string) $status_object->label : $post->post_status,
			'modified_gmt'    => mysql_to_rfc3339( $this->get_modified_gmt( $post ) ),
			'author'          => false !== $author ? $author->display_name : '',
			'edit_link'       => (string) get_edit_post_link( $post->ID, 'raw' ),
			'uuid'            => $this->uuids->find( 'post', $post->ID ),
		);
	}

	/**
	 * Returns the GMT modification date. Some drafts have an empty GMT date, so fall back to the local one.
	 *
	 * @param WP_Post $post Post.
	 * @return string MySQL datetime.
	 */
	private function get_modified_gmt( WP_Post $post ): string {
		if ( '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
			return $post->post_modified_gmt;
		}

		return get_gmt_from_date( $post->post_modified );
	}

	/**
	 * Returns an invalid-parameter error.
	 *
	 * @param string $param Parameter name.
	 * @return WP_Error
	 */
	private function invalid_param( string $param ): WP_Error {
		return new WP_Error(
			'rest_invalid_param',
			sprintf(
				/* translators: %s: Parameter name. */
				__( 'Invalid parameter: %s', 'cherrylift' ),
				$param
			),
			array( 'status' => 400 )
		);
	}
}
