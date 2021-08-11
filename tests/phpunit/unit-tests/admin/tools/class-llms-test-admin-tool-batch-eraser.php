<?php
/**
 * Tests for the LLMS_Admin_Tool_Batch_Eraser class
 *
 * @package LifterLMS/Tests/Admins/Tools
 *
 * @group admin
 * @group admin_tools
 *
 * @since 3.37.19
 */
class LLMS_Test_Admin_Tool_Batch_Eraser extends LLMS_UnitTestCase {

	/**
	 * Setup before class
	 *
	 * Include abstract class.
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public static function set_up_before_class() {

		parent::set_up_before_class();

		require_once LLMS_PLUGIN_DIR . 'includes/abstracts/llms-abstract-admin-tool.php';
		require_once LLMS_PLUGIN_DIR . 'includes/admin/tools/class-llms-admin-tool-batch-eraser.php';

	}

	/**
	 * Teardown the test case.
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function tear_down() {

		parent::tear_down();
		$this->clear_cache();

	}

	/**
	 * Clear cached batch count data.
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	private function clear_cache() {
		wp_cache_delete( 'batch-eraser', 'llms_tool_data' );
	}

	/**
	 * Setup the test case
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function set_up() {

		parent::set_up();

		$this->main = new LLMS_Admin_Tool_Batch_Eraser();
	}

	/**
	 * Test get_description()
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_get_description() {

		$res = LLMS_Unit_Test_Util::call_method( $this->main, 'get_description' );
		$this->assertTrue( ! empty( $res ) );
		$this->assertTrue( is_string( $res ) );

	}

	/**
	 * Test get_label()
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_get_label() {

		$res = LLMS_Unit_Test_Util::call_method( $this->main, 'get_label' );
		$this->assertTrue( ! empty( $res ) );
		$this->assertTrue( is_string( $res ) );

	}

	/**
	 * Test get_text()
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_get_text() {

		$res = LLMS_Unit_Test_Util::call_method( $this->main, 'get_text' );
		$this->assertTrue( ! empty( $res ) );
		$this->assertTrue( is_string( $res ) );

	}

	/**
	 * Test get_pending_batches()
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_get_pending_batches() {

		$key = 'llms_background_processor_course_data_batch_ast9a0st';
		add_option( $key, array( 'data' ) );
		$this->clear_cache();

		$this->assertEquals( 1, LLMS_Unit_Test_Util::call_method( $this->main, 'get_pending_batches' ) );

		delete_option( $key );

	}

	/**
	 * Test get_pending_batches(): no batches found.
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_get_pending_batches_none_found() {
		$this->assertEquals( 0, LLMS_Unit_Test_Util::call_method( $this->main, 'get_pending_batches' ) );
	}

	/**
	 * Test get_pending_batches(): when there's a cache hit.
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_get_pending_batches_cache_hit() {

		wp_cache_set( 'batch-eraser', 25, 'llms_tool_data' );
		$this->assertEquals( 25, LLMS_Unit_Test_Util::call_method( $this->main, 'get_pending_batches' ) );

	}

	/**
	 * Test handle()
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_handle() {

		$key = 'llms_background_processor_course_data_batch_ast9a0st';
		add_option( $key, array( 'data' ) );
		$key = 'wp_llms_notification_processor_email_batch_ast9a0st';
		add_option( $key, array( 1, 2, 3 ) );

		$this->assertTrue( LLMS_Unit_Test_Util::call_method( $this->main, 'handle' ) );

		$this->clear_cache();

		$this->assertEquals( 0, LLMS_Unit_Test_Util::call_method( $this->main, 'get_pending_batches' ) );

	}

	/**
	 * Test should_load()
	 *
	 * @since 3.37.19
	 *
	 * @return void
	 */
	public function test_should_load() {

		$this->clear_cache();
		$this->assertFalse( LLMS_Unit_Test_Util::call_method( $this->main, 'should_load' ) );

		wp_cache_set( 'batch-eraser', 25, 'llms_tool_data' );
		$this->assertTrue( LLMS_Unit_Test_Util::call_method( $this->main, 'should_load' ) );

	}


}
