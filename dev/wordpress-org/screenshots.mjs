/**
 * Takes the WordPress.org screenshots (.wordpress-org/screenshot-1..4.png).
 *
 * Usage: node dev/wordpress-org/screenshots.mjs
 *
 * Needs two running sites with the plugin active, logged in as admin / password:
 * - the source site with demo content (npm run demo:source), at SOURCE_URL (default http://localhost:8898);
 * - an empty target site, at TARGET_URL (default http://localhost:8897).
 *
 * The URLs and site titles appear in the screenshots, so prefer plain localhost URLs and titles such as
 * "Staging" and "Production". The import screenshots create content on the target site.
 */

/**
 * External dependencies
 */
import { chromium } from '@playwright/test';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const out = join(
	dirname( fileURLToPath( import.meta.url ) ),
	'..',
	'..',
	'.wordpress-org'
);
const sourceUrl = process.env.SOURCE_URL || 'http://localhost:8898';
const targetUrl = process.env.TARGET_URL || 'http://localhost:8897';
const selected = [
	'Behind the scenes at the studio',
	'Introducing our spring collection',
	'Pricing',
];

const browser = await chromium.launch();
const context = await browser.newContext( {
	viewport: { width: 1280, height: 860 },
	acceptDownloads: true,
} );
const page = await context.newPage();

/**
 * Logs in and opens the plugin's admin page.
 *
 * @param {string} url Site URL.
 */
async function openPlugin( url ) {
	await page.goto( `${ url }/wp-login.php` );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
	await page.goto( `${ url }/wp-admin/tools.php?page=cherrylift` );
}

/**
 * Saves a screenshot of the viewport.
 *
 * @param {number} number Screenshot number.
 */
async function shoot( number ) {
	// Let animations and spinners settle.
	await page.waitForTimeout( 500 );
	await page.screenshot( {
		path: join( out, `screenshot-${ number }.png` ),
	} );
	// eslint-disable-next-line no-console
	console.log( `Wrote .wordpress-org/screenshot-${ number }.png` );
}

// 1. Export: select content.
await openPlugin( sourceUrl );
for ( const title of selected ) {
	await page
		.getByRole( 'row' )
		.filter( { hasText: title } )
		.getByRole( 'checkbox' )
		.check();
}
// Park the mouse on the page title, away from menus and row actions.
await page.locator( '.wrap h1' ).hover();
await shoot( 1 );

// 2. Export preview with dependencies.
await page
	.locator( '.dataviews-bulk-actions-footer__container' )
	.getByRole( 'button', { name: 'Export' } )
	.click();
const dialog = page.getByRole( 'dialog', { name: 'Export content' } );
await dialog.getByRole( 'button', { name: 'Download package' } ).waitFor();
await shoot( 2 );

const downloadPromise = page.waitForEvent( 'download' );
await dialog.getByRole( 'button', { name: 'Download package' } ).click();
const download = await downloadPromise;
const packagePath = join(
	mkdtempSync( join( tmpdir(), 'cherrylift-' ) ),
	download.suggestedFilename()
);
await download.saveAs( packagePath );

// 3. Import preview on the target site.
await context.clearCookies();
await openPlugin( targetUrl );
await page.getByRole( 'tab', { name: 'Import' } ).click();
await page.locator( 'input[type="file"]' ).setInputFiles( packagePath );
const importButton = page.getByRole( 'button', {
	name: /^Import \d+ items?$/,
} );
await importButton.waitFor();
await shoot( 3 );

// 4. Import report.
await importButton.click();
await page
	.locator( '.components-notice' )
	.filter( { hasText: 'Import finished' } )
	.waitFor( { timeout: 120000 } );
await shoot( 4 );

await browser.close();
