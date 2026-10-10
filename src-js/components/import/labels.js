/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Returns the label of a planned action.
 *
 * @param {string} action Plan action.
 * @return {string} Label.
 */
export function actionLabel( action ) {
	return (
		{
			create: __( 'Create', 'cherrylift' ),
			update: __( 'Update', 'cherrylift' ),
			skip: __( 'Skip', 'cherrylift' ),
			link: __( 'Link to existing', 'cherrylift' ),
			missing: __( 'Missing', 'cherrylift' ),
		}[ action ] || action
	);
}

/**
 * Returns the label of an import result.
 *
 * @param {string} result Report result.
 * @return {string} Label.
 */
export function resultLabel( result ) {
	return (
		{
			created: __( 'Created', 'cherrylift' ),
			updated: __( 'Updated', 'cherrylift' ),
			skipped: __( 'Skipped', 'cherrylift' ),
			linked: __( 'Linked', 'cherrylift' ),
			missing: __( 'Missing', 'cherrylift' ),
			failed: __( 'Failed', 'cherrylift' ),
		}[ result ] || result
	);
}

/**
 * Describes how an entity was matched to existing content.
 *
 * @param {string|null} match Match method.
 * @return {string} Description, or an empty string.
 */
export function matchLabel( match ) {
	return (
		{
			uuid: __( 'Previously synced', 'cherrylift' ),
			slug: __( 'Same slug', 'cherrylift' ),
			file: __( 'Same file', 'cherrylift' ),
			filter: __( 'Matched by customization', 'cherrylift' ),
		}[ match ] || ''
	);
}
