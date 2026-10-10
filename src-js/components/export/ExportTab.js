/**
 * WordPress dependencies
 */
import { DataViews } from '@wordpress/dataviews';
import { Notice } from '@wordpress/components';
import { dateI18n, getSettings } from '@wordpress/date';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { fetchEntities, fetchExportOptions } from '../../api';
import ExportModal from './ExportModal';

const DEFAULT_VIEW = {
	type: 'table',
	search: '',
	filters: [],
	page: 1,
	perPage: 20,
	sort: { field: 'modified', direction: 'desc' },
	titleField: 'title',
	fields: [ 'post_type', 'status', 'author', 'modified' ],
	layout: {},
};

const DEFAULT_LAYOUTS = { table: {} };

const SORTABLE_FIELDS = [ 'title', 'modified', 'date' ];

/**
 * Converts a DataViews view to REST query arguments.
 *
 * @param {Object} view DataViews view.
 * @return {Object} Query arguments for GET /entities.
 */
function viewToQuery( view ) {
	const query = {
		page: view.page,
		per_page: view.perPage,
		search: view.search || undefined,
	};

	if ( view.sort && SORTABLE_FIELDS.includes( view.sort.field ) ) {
		query.orderby = view.sort.field;
		query.order = view.sort.direction;
	}

	for ( const filter of view.filters || [] ) {
		if (
			filter.value &&
			[ 'post_type', 'status' ].includes( filter.field )
		) {
			query[ filter.field ] = filter.value;
		}
	}

	return query;
}

export default function ExportTab() {
	const [ options, setOptions ] = useState( null );
	const [ view, setView ] = useState( DEFAULT_VIEW );
	const [ data, setData ] = useState( {
		items: [],
		totalItems: 0,
		totalPages: 0,
	} );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		fetchExportOptions()
			.then( setOptions )
			.catch( ( e ) => setError( e.message ) );
	}, [] );

	useEffect( () => {
		let cancelled = false;
		setIsLoading( true );

		fetchEntities( viewToQuery( view ) )
			.then( ( result ) => {
				if ( ! cancelled ) {
					setData( result );
					setError( null );
				}
			} )
			.catch( ( e ) => ! cancelled && setError( e.message ) )
			.finally( () => ! cancelled && setIsLoading( false ) );

		return () => {
			cancelled = true;
		};
	}, [ view ] );

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Title', 'cherrylift' ),
				enableHiding: false,
				enableGlobalSearch: true,
				getValue: ( { item } ) =>
					item.title || __( '(no title)', 'cherrylift' ),
			},
			{
				id: 'post_type',
				label: __( 'Type', 'cherrylift' ),
				elements: ( options?.post_types || [] ).map( ( type ) => ( {
					value: type.name,
					label: type.label,
				} ) ),
				filterBy: { operators: [ 'is' ] },
				enableSorting: false,
				render: ( { item } ) => item.post_type_label,
			},
			{
				id: 'status',
				label: __( 'Status', 'cherrylift' ),
				elements: ( options?.statuses || [] ).map( ( status ) => ( {
					value: status.name,
					label: status.label,
				} ) ),
				filterBy: { operators: [ 'is' ] },
				enableSorting: false,
				render: ( { item } ) => item.status_label,
			},
			{
				id: 'author',
				label: __( 'Author', 'cherrylift' ),
				enableSorting: false,
			},
			{
				id: 'modified',
				label: __( 'Last modified', 'cherrylift' ),
				render: ( { item } ) => (
					<time dateTime={ item.modified_gmt }>
						{ dateI18n(
							getSettings().formats.datetimeAbbreviated,
							item.modified_gmt
						) }
					</time>
				),
			},
		],
		[ options ]
	);

	const actions = useMemo(
		() => [
			{
				id: 'export',
				label: __( 'Export', 'cherrylift' ),
				isPrimary: true,
				supportsBulk: true,
				modalHeader: __( 'Export content', 'cherrylift' ),
				modalSize: 'large',
				RenderModal: ( { items, closeModal } ) => (
					<ExportModal
						postIds={ items.map( ( item ) => item.id ) }
						onClose={ closeModal }
					/>
				),
			},
		],
		[]
	);

	return (
		<div className="cherrylift-export">
			<p className="cherrylift-export__intro">
				{ __(
					'Select the content to export. Parent pages, terms, featured images and media used in the content are added automatically.',
					'cherrylift'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<DataViews
				data={ data.items }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				actions={ actions }
				isLoading={ isLoading }
				paginationInfo={ {
					totalItems: data.totalItems,
					totalPages: data.totalPages,
				} }
				defaultLayouts={ DEFAULT_LAYOUTS }
				config={ { perPageSizes: [ 20, 50, 100 ] } }
				searchLabel={ __( 'Search content', 'cherrylift' ) }
			/>
		</div>
	);
}
