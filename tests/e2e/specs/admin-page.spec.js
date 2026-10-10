/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Admin page', () => {
	// PHP integration tests reinstall the shared tests database, so never assume the plugin is active.
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin(
			'cherrylift-selective-content-sync'
		); // Slug of the plugin's display name.
	} );

	test( 'is reachable from the Tools menu and renders the app', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'tools.php', 'page=cherrylift' );

		await expect(
			page.getByRole( 'heading', {
				level: 1,
				name: 'Cherrylift',
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'tab', { name: 'Export' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'tab', { name: 'Import' } )
		).toBeVisible();
	} );
} );
