<?php
/**
 * Tests for Plugin.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Tests\Unit;

use Brain\Monkey\Actions;
use Cherrylift\Plugin;

/**
 * @covers \Cherrylift\Plugin
 */
class PluginTest extends TestCase {

	public function test_boot_registers_hooks_and_fires_loaded_action_once(): void {
		$plugin = new Plugin( '/path/to/cherrylift.php', '1.2.3' );

		Actions\expectDone( 'cherrylift_loaded' )->once()->with( $plugin );

		$plugin->boot();
		$plugin->boot();

		$this->assertNotFalse( has_action( 'admin_menu', 'Cherrylift\Admin\AdminPage->register_page()' ) );
	}

	public function test_get_version(): void {
		$this->assertSame( '1.2.3', ( new Plugin( '/path/to/file.php', '1.2.3' ) )->get_version() );
	}
}
