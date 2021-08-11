<?php
/**
 * Test LLMS_Controller_Certificates
 *
 * @package LifterLMS/Tests/Controllers
 *
 * @group controllers
 * @group certificates
 * @group controller_certificates
 *
 * @since 3.37.4
 * @since 4.5.0 Add tests for managing certificate sharing settings.
 */
class LLMS_Test_Controller_Certificates extends LLMS_UnitTestCase {

	/**
	 * Setup the test case.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function set_up() {

		parent::set_up();
		$this->instance = new LLMS_Controller_Certificates();

	}

	/**
	 * Test maybe_allow_public_query(): no authorization data in query string.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_allow_public_query_no_auth() {
		$this->assertEquals( array(), $this->instance->maybe_allow_public_query( array() ) );
	}

	/**
	 * Test maybe_allow_public_query(): authorization present but invalid.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_allow_public_query_invalid_auth() {

		// Doesn't exist.
		$args = array(
			'publicly_queryable' => false,
		);

		$this->mockGetRequest( array(
			'_llms_cert_auth' => 'fake',
		) );

		$this->assertEquals( $args, $this->instance->maybe_allow_public_query( $args ) );

		// Post exists but submitted nocne is incorrect.
		$post_id = $this->factory->post->create( array( 'post_type' => 'llms_certificate' ) );
		update_post_meta( $post_id, '_llms_auth_nonce', 'mock-nonce' );

		$this->mockGetRequest( array(
			'_llms_cert_auth' => 'incorrect-nonce',
		) );
		$this->assertEquals( $args, $this->instance->maybe_allow_public_query( $args ) );

	}

	/**
	 * Test maybe_allow_public_query(): authorization present and exists but on an invalid post type.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_allow_public_query_invalid_post_type() {

		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, '_llms_auth_nonce', 'mock-nonce' );

		$this->mockGetRequest( array(
			'_llms_cert_auth' => 'mock-nonce',
		) );

		$args = array(
			'publicly_queryable' => false,
		);

		$this->assertEquals( $args, $this->instance->maybe_allow_public_query( $args ) );

	}

	/**
	 * Test maybe_allow_public_query(): valid auth and post type.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_allow_public_query_update() {

		$post_id = $this->factory->post->create( array( 'post_type' => 'llms_certificate' ) );
		update_post_meta( $post_id, '_llms_auth_nonce', 'mock-nonce' );

		$this->mockGetRequest( array(
			'_llms_cert_auth' => 'mock-nonce',
		) );

		$args = array(
			'publicly_queryable' => false,
		);
		$expect = array(
			'publicly_queryable' => true,
		);

		$this->assertEquals( $expect, $this->instance->maybe_allow_public_query( $args ) );

	}

	/**
	 * Test maybe_authenticate_export_generation() when no authorization data is passed.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_authenticate_export_generation_no_auth() {

		$this->instance->maybe_authenticate_export_generation();
		$this->assertEquals( 0, get_current_user_id() );

	}

	/**
	 * Test maybe_authenticate_export_generation() when no authorization data is passed.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_authenticate_export_generation_invalid_post_type() {

		global $post;
		$temp = $post;
		$post = $this->factory->post->create_and_get();

		$this->mockGetRequest( array(
			'_llms_cert_auth' => 'fake',
		) );

		$this->instance->maybe_authenticate_export_generation();
		$this->assertEquals( 0, get_current_user_id() );

		// Reset post.
		$post = $temp;

	}

	/**
	 * Test maybe_authenticate_export_generation() when no authorization data is passed.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_authenticate_export_generation_invalid_nonce() {

		foreach ( array( 'llms_certificate', 'llms_my_certificate' ) as $post_type ) {

			global $post;
			$temp = $post;
			$post = $this->factory->post->create_and_get( array( 'post_type' => $post_type ) );

			update_post_meta( $post->ID, '_llms_auth_nonce', 'mock-nonce' );

			$this->mockGetRequest( array(
				'_llms_cert_auth' => 'fake',
			) );

			$this->instance->maybe_authenticate_export_generation();
			$this->assertEquals( 0, get_current_user_id() );

			// Reset post.
			$post = $temp;

		}

	}

	/**
	 * Test maybe_authenticate_export_generation() for a certificate template.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_authenticate_export_generation_for_template() {

		$uid = $this->factory->user->create( array( 'role' => 'lms_manager' ) );

		$template = $this->create_certificate_template();
		update_post_meta( $template, '_llms_auth_nonce', 'mock-nonce' );
		wp_update_post( array(
			'ID' => $template,
			'post_author' => $uid,
		) );

		global $post;
		$temp = $post;
		$post = get_post( $template );

		$this->mockGetRequest( array(
			'_llms_cert_auth' => 'mock-nonce',
		) );

		$this->instance->maybe_authenticate_export_generation();
		$this->assertEquals( $uid, get_current_user_id() );

		// Reset post.
		$post = $temp;

	}

	/**
	 * Test maybe_authenticate_export_generation() for an earned certificate.
	 *
	 * @since 3.37.4
	 *
	 * @return void
	 */
	public function test_maybe_authenticate_export_generation_for_earned_cert() {

		$uid = $this->factory->student->create();

		$template = $this->create_certificate_template();

		$earned = $this->earn_certificate( $uid, $template, $this->factory->post->create() );

		global $post;
		$temp = $post;
		$post = get_post( $earned[1] );
		update_post_meta( $post->ID, '_llms_auth_nonce', 'mock-nonce' );

		$this->mockGetRequest( array(
			'_llms_cert_auth' => 'mock-nonce',
		) );

		$this->instance->maybe_authenticate_export_generation();
		$this->assertEquals( $uid, get_current_user_id() );

		// Reset post.
		$post = $temp;

	}

	/**
	 * Test change_sharing_settings() when user has insufficient permissions
	 *
	 * @since 4.5.0
	 *
	 * @return void
	 */
	public function test_change_sharing_settings_invalid_permissions() {

		$earned = $this->earn_certificate( $this->factory->student->create(), $this->create_certificate_template(), $this->factory->post->create() );

		$res = LLMS_Unit_Test_Util::call_method( $this->instance, 'change_sharing_settings', array( $earned[1], true ) );
		$this->assertIsWPError( $res );
		$this->assertWPErrorCodeEquals( 'insufficient-permissions', $res );

	}

	/**
	 * Test change_sharing_settings()
	 *
	 * @since 4.5.0
	 *
	 * @return void
	 */
	public function test_change_sharing_settings() {

		$uid      = $this->factory->student->create();
		$earned   = $this->earn_certificate( $uid, $this->create_certificate_template(), $this->factory->post->create() );
		$cert_id  = $earned[1];
		$cert = new LLMS_User_Certificate( $cert_id );

		wp_set_current_user( $uid );

		// Enable Sharing
		$this->assertTrue( LLMS_Unit_Test_Util::call_method( $this->instance, 'change_sharing_settings', array( $cert_id, true ) ) );
		$this->assertEquals( 'yes', $cert->get( 'allow_sharing' ) );

		// Already enabled.
		$this->assertFalse( LLMS_Unit_Test_Util::call_method( $this->instance, 'change_sharing_settings', array( $cert_id, true ) ) );
		$this->assertEquals( 'yes', $cert->get( 'allow_sharing' ) );

		// Disable sharing.
		$this->assertTrue( LLMS_Unit_Test_Util::call_method( $this->instance, 'change_sharing_settings', array( $cert_id, false ) ) );
		$this->assertEquals( 'no', $cert->get( 'allow_sharing' ) );

	}

}
