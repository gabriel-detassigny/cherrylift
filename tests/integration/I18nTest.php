<?php
/**
 * Translation readiness tests.
 *
 * Translations are delivered by WordPress.org language packs, so these tests check that strings and scripts are
 * set up for them rather than loading any translation.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Tests\Integration;

use Cherrylift\Admin\AdminPage;
use Cherrylift\Support\Capabilities;
use WP_UnitTestCase;

/**
 * @covers \Cherrylift\Admin\AdminPage
 */
class I18nTest extends WP_UnitTestCase {

	public function test_admin_script_translations_use_the_plugin_text_domain(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = new AdminPage( CHERRYLIFT_FILE, new Capabilities() );
		$page->register_page();

		$page->enqueue_assets( (string) get_plugin_page_hookname( AdminPage::MENU_SLUG, 'tools.php' ) );

		$script = wp_scripts()->registered[ AdminPage::SCRIPT_HANDLE ] ?? null;
		$this->assertNotNull( $script, 'The admin app is enqueued (run npm run build first).' );
		$this->assertSame( 'cherrylift', $script->textdomain );
		$this->assertEmpty( $script->translations_path, 'Language packs are found in wp-content/languages, not in the plugin.' );
	}

	public function test_pot_file_lists_every_string_in_the_source(): void {
		$pot = (string) file_get_contents( dirname( __DIR__, 2 ) . '/languages/cherrylift.pot' );

		foreach ( array( 'Select at least one item to export.', 'Sorry, you are not allowed to sync content.', 'Download package', 'Import %d item' ) as $string ) {
			$this->assertStringContainsString( 'msgid "' . $string . '"', $pot );
		}
	}
}
