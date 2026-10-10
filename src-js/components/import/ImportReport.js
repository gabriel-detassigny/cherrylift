/**
 * WordPress dependencies
 */
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { resultLabel } from './labels';

export default function ImportReport( { report, onDone } ) {
	const { counts } = report;
	const failed = counts.failed > 0;

	return (
		<div className="cherrylift-import__report">
			<Notice
				status={ failed ? 'warning' : 'success' }
				isDismissible={ false }
			>
				{ sprintf(
					/* translators: 1: Created count, 2: Updated count, 3: Skipped count, 4: Failed count. */
					__(
						'Import finished: %1$d created, %2$d updated, %3$d skipped, %4$d failed.',
						'cherrylift'
					),
					counts.created,
					counts.updated,
					counts.skipped,
					counts.failed
				) }
			</Notice>

			{ report.warnings.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul>
						{ report.warnings.map( ( warning, index ) => (
							<li key={ index }>{ warning }</li>
						) ) }
					</ul>
				</Notice>
			) }

			<table className="widefat striped cherrylift-import__table">
				<thead>
					<tr>
						<th scope="col">{ __( 'Title', 'cherrylift' ) }</th>
						<th scope="col">{ __( 'Type', 'cherrylift' ) }</th>
						<th scope="col">{ __( 'Result', 'cherrylift' ) }</th>
						<th scope="col">{ __( 'Details', 'cherrylift' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ report.items.map( ( item ) => {
						const title =
							item.title || __( '(no title)', 'cherrylift' );

						return (
							<tr key={ item.uuid }>
								<td>
									{ item.edit_link ? (
										<a href={ item.edit_link }>{ title }</a>
									) : (
										title
									) }
								</td>
								<td>{ item.subtype_label }</td>
								<td>
									<span
										className={ `cherrylift-import__action is-${ item.result }` }
									>
										{ resultLabel( item.result ) }
									</span>
								</td>
								<td>{ item.message }</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>

			<div className="cherrylift-import__actions">
				<Button variant="secondary" onClick={ onDone }>
					{ __( 'Import another package', 'cherrylift' ) }
				</Button>
			</div>
		</div>
	);
}
