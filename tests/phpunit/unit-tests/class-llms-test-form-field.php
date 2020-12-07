<?php
/**
 * Test LLMS_Form_Field class
 *
 * @package LifterLMS/Tests
 *
 * @group form_field
 *
 * @since [version]
 * @version [version]
 */
class LLMS_Test_Form_Field extends LLMS_Unit_Test_Case {

	/**
	 * Retrive a new user with specified user meta data.
	 *
	 * @since [version]
	 *
	 * @param string $meta_key Meta key name.
	 * @param string $meta_val Meta value (optional).
	 * @return int WP_User ID.
	 */
	private function get_user_with_meta( $meta_key, $meta_val = '' ) {

		$uid = $this->factory->user->create();
		update_user_meta( $uid, $meta_key, $meta_val );

		wp_set_current_user( $uid );

		return $uid;

	}

	/**
	 * teardown the test case.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function tearDown() {

		parent::tearDown();
		wp_set_current_user( null );

	}

	/**
	 * Test output of a hidden input field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_hidden() {

		$this->assertEquals( '<input class="llms-field-input" id="mock-id" name="mock-id" type="hidden" value="1" />', llms_form_field( array( 'type' => 'hidden', 'id' => 'mock-id', 'value' => '1' ), false ) );

	}

	/**
	 * Test output of a select field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_select() {

		$opts = array(
			'type' => 'select',
			'options' => array(
				'mock' => 'MOCK',
				'fake' => 'FAKE',
			),
		);

		$html = llms_form_field( $opts, false );

		$this->assertStringContains( '<select class="llms-field-select', $html );
		$this->assertStringContains( '<option value="mock">MOCK</option>', $html );
		$this->assertStringContains( '<option value="fake">FAKE</option>', $html );

		// With selected value.
		$opts['selected'] = 'fake';
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<option value="fake" selected="selected">FAKE</option>', $html );

		unset( $opts['selected'] );

		// With default value.
		$opts['default'] = 'fake';
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<option value="fake" selected="selected">FAKE</option>', $html );

	}

	/**
	 * Test select field with user data.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_select_with_user_data() {

		$opts = array(
			'type'           => 'select',
			'data_store_key' => 'select_data',
			'selected'       => 'mock',
			'options'        => array(
				'mock' => 'MOCK',
				'fake' => 'FAKE',
			),
		);

		// Uses default value.
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<option value="mock" selected="selected">MOCK</option>', $html );
		$this->assertStringNotContains( '<option value="fake" selected="selected">FAKE</option>', $html );

		// No meta saved for user, uses default.
		$this->get_user_with_meta( 'other', '' );
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<option value="mock" selected="selected">MOCK</option>', $html );
		$this->assertStringNotContains( '<option value="fake" selected="selected">FAKE</option>', $html );

		// Use user's value.
		$this->get_user_with_meta( 'select_data', 'fake' );
		$html = llms_form_field( $opts, false );
		$this->assertStringNotContains( '<option value="mock" selected="selected">MOCK</option>', $html );
		$this->assertStringContains( '<option value="fake" selected="selected">FAKE</option>', $html );

	}

	/**
	 * Test select field with an option group.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_select_opt_group() {

		$opts = array(
			'type'           => 'select',
			'data_store_key' => 'select_data',
			'options'        => array(
				array(
					'label' => __( 'Group 1', 'lifterlms' ),
					'options' => array(
						'opt1' => __( 'Option 1', 'lifterlms' ),
						'opt2' => __( 'Option 2', 'lifterlms' ),
					),
				),
				array(
					'label' => __( 'Group 2', 'lifterlms' ),
					'options' => array(
						'opt3' => __( 'Option 3', 'lifterlms' ),
						'opt4' => __( 'Option 4', 'lifterlms' ),
					),
				),
			),
		);

		$html = llms_form_field( $opts, false );

		$this->assertStringContains( '<optgroup label="Group 1" data-key="0">', $html );
		$this->assertStringContains( '<optgroup label="Group 2" data-key="1">', $html );

		for ( $i = 1; $i <= 4; $i++ ) {
			$this->assertStringContains( sprintf( '<option value="opt%1$d">Option %1$d</option>', $i ), $html );
		}

	}

	/**
	 * Test radio field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_radio() {

		$opts = array(
			'type'  => 'radio',
			'value' => 'mock_val',
		);

		$html = llms_form_field( $opts, false );

		$this->assertStringContains( '<div class="llms-form-field type-radio', $html );
		$this->assertStringContains( '<input class="llms-field-radio"', $html );
		$this->assertStringContains( 'type="radio"', $html );
		$this->assertStringContains( 'value="mock_val"', $html );
		$this->assertStringNotContains( 'checked="checked"', $html );

		// checked.
		$opts['checked'] = true;
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( 'checked="checked"', $html );

	}

	/**
	 * Test radio field with a user.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_radio_with_user() {

		$opts = array(
			'id'    => 'radio_store',
			'type'  => 'radio',
			'value' => 'mock_val',
		);

		// User doesn't have value stored.
		$this->get_user_with_meta( 'radio_store' );
		$html = llms_form_field( $opts, false );
		$this->assertStringNotContains( 'checked="checked"', $html );

		$this->get_user_with_meta( 'radio_store', 'mock_val' );
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( 'checked="checked"', $html );

	}

	/**
	 * Test a radio group field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_radio_group() {

		$opts = array(
			'id'      => 'radio-id',
			'label'   => 'Radio Label',
			'type'    => 'radio',
			'options' => array(
				'opt1' => 'Option1',
				'opt2' => 'Option2',
			),
		);

		$html = llms_form_field( $opts, false );

		$this->assertStringContains( '<div class="llms-form-field type-radio is-group', $html );
		$this->assertStringContains( '<label for="radio-id">Radio Label</label><div class="llms-field-radio llms-input-group"', $html );
		$this->assertStringContains( '<div class="llms-form-field type-radio llms-cols-12 llms-cols-last"><input class="llms-field-radio" id="radio-id--opt1" name="radio-id" type="radio" value="opt1" /><label for="radio-id--opt1">Option1</label></div>', $html );
		$this->assertStringContains( '<div class="llms-form-field type-radio llms-cols-12 llms-cols-last"><input class="llms-field-radio" id="radio-id--opt2" name="radio-id" type="radio" value="opt2" /><label for="radio-id--opt2">Option2</label></div>', $html );

		// default value.
		$opts['default'] = 'opt1';
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<input checked="checked" class="llms-field-radio" id="radio-id--opt1" name="radio-id" type="radio" value="opt1" /><label for="radio-id--opt1">Option1</label>', $html );

		// user has saved data.
		$this->get_user_with_meta( 'radio-id', 'opt2' );
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<input checked="checked" class="llms-field-radio" id="radio-id--opt2" name="radio-id" type="radio" value="opt2" /><label for="radio-id--opt2">Option2</label>', $html );
		$this->assertStringNotContains( '<input checked="checked" class="llms-field-radio" id="radio-id--opt1" name="radio-id" type="radio" value="opt1" /><label for="radio-id--opt1">Option1</label>', $html );

	}

	/**
	 * Test a checkbox field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_checkbox() {

		$opts = array(
			'type'  => 'checkbox',
			'value' => 'mock_val',
		);

		$html = llms_form_field( $opts, false );

		$this->assertStringContains( '<div class="llms-form-field type-checkbox', $html );
		$this->assertStringContains( '<input class="llms-field-checkbox"', $html );
		$this->assertStringContains( 'type="checkbox"', $html );
		$this->assertStringContains( 'value="mock_val"', $html );
		$this->assertStringNotContains( 'checked="checked"', $html );

		// checked.
		$opts['checked'] = true;
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( 'checked="checked"', $html );

	}

	/**
	 * Test checkbox with a user.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_checkbox_with_user() {

		$opts = array(
			'id'    => 'checkbox_store',
			'type'  => 'checkbox',
			'value' => 'mock_val',
		);

		// User doesn't have value stored.
		$this->get_user_with_meta( 'checkbox_store' );
		$html = llms_form_field( $opts, false );
		$this->assertStringNotContains( 'checked="checked"', $html );

		$this->get_user_with_meta( 'checkbox_store', 'mock_val' );
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( 'checked="checked"', $html );

	}

	/**
	 * Test checkbox group.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_checkbox_group() {

		$opts = array(
			'id'      => 'checkbox-id',
			'label'   => 'Checkbox Label',
			'type'    => 'checkbox',
			'options' => array(
				'opt1' => 'Option1',
				'opt2' => 'Option2',
			),
		);

		$html = llms_form_field( $opts, false );

		$this->assertStringContains( '<div class="llms-form-field type-checkbox is-group', $html );
		$this->assertStringContains( '<label for="checkbox-id">Checkbox Label</label><div class="llms-field-checkbox llms-input-group"', $html );
		$this->assertStringContains( '<div class="llms-form-field type-checkbox llms-cols-12 llms-cols-last"><input class="llms-field-checkbox" id="checkbox-id--opt1" name="checkbox-id[]" type="checkbox" value="opt1" /><label for="checkbox-id--opt1">Option1</label></div>', $html );
		$this->assertStringContains( '<div class="llms-form-field type-checkbox llms-cols-12 llms-cols-last"><input class="llms-field-checkbox" id="checkbox-id--opt2" name="checkbox-id[]" type="checkbox" value="opt2" /><label for="checkbox-id--opt2">Option2</label></div>', $html );

		// default value.
		$opts['default'] = 'opt1';
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<input checked="checked" class="llms-field-checkbox" id="checkbox-id--opt1" name="checkbox-id[]" type="checkbox" value="opt1" /><label for="checkbox-id--opt1">Option1</label>', $html );

		// user has saved data.
		$this->get_user_with_meta( 'checkbox-id', 'opt2' );
		$html = llms_form_field( $opts, false );
		$this->assertStringContains( '<input checked="checked" class="llms-field-checkbox" id="checkbox-id--opt2" name="checkbox-id[]" type="checkbox" value="opt2" /><label for="checkbox-id--opt2">Option2</label>', $html );
		$this->assertStringNotContains( '<input checked="checked" class="llms-field-checkbox" id="checkbox-id--opt1" name="checkbox-id[]" type="checkbox" value="opt1" /><label for="checkbox-id--opt1">Option1</label>', $html );


	}

	/**
	 * Test button field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_button() {

		$html = llms_form_field( array(
			'type'  => 'button',
			'value' => 'Button Text',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-button', $html );
		$this->assertStringContains( '<button class="llms-field-button"', $html );
		$this->assertStringContains( 'type="button"', $html );
		$this->assertStringContains( '>Button Text</button>', $html );

	}

	/**
	 * Test submit button field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_submit() {

		$html = llms_form_field( array(
			'type'  => 'submit',
			'value' => 'Button Text',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-submit', $html );
		$this->assertStringContains( '<button class="llms-field-button"', $html );
		$this->assertStringContains( 'type="submit"', $html );
		$this->assertStringContains( '>Button Text</button>', $html );

	}

	/**
	 * Test reset button field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_reset() {

		$html = llms_form_field( array(
			'type'  => 'reset',
			'value' => 'Button Text',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-reset', $html );
		$this->assertStringContains( '<button class="llms-field-button"', $html );
		$this->assertStringContains( 'type="reset"', $html );
		$this->assertStringContains( '>Button Text</button>', $html );
	}

	/**
	 * Test output of a text input field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_text() {

		$html = llms_form_field( array(), false );

		$this->assertStringContains( '<div class="llms-form-field type-text', $html );
		$this->assertStringContains( '<input ', $html );
		$this->assertStringContains( 'type="text"', $html );

	}

	/**
	 * Test email field type.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_email() {

		$html = llms_form_field( array(
			'type' => 'email',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-email', $html );
		$this->assertStringContains( '<input ', $html );
		$this->assertStringContains( 'type="email"', $html );

	}

	/**
	 * Test tel field type.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_tel() {

		$html = llms_form_field( array(
			'type' => 'tel',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-tel', $html );
		$this->assertStringContains( '<input ', $html );
		$this->assertStringContains( 'type="tel"', $html );

	}

	/**
	 * Test number field type.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_number() {

		$html = llms_form_field( array(
			'type' => 'number',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-number', $html );
		$this->assertStringContains( '<input ', $html );
		$this->assertStringContains( 'type="number"', $html );

	}

	/**
	 * Test textarea field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_textarea() {

		$html = llms_form_field( array(
			'type' => 'textarea',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-textarea', $html );
		$this->assertStringContains( '<textarea class="llms-field-textarea"', $html );
		$this->assertStringContains( '></textarea>', $html );

	}

	/**
	 * Test textarea field with user data.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_textarea_with_user_data() {

		$this->get_user_with_meta( 'textarea-id', 'Lorem ipsum dolor sit.' );

		$html = llms_form_field( array(
			'id'   => 'textarea-id',
			'type' => 'textarea',
		), false );

		$this->assertStringContains( '>Lorem ipsum dolor sit.</textarea>', $html );

	}

	/**
	 * Test custom html field.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_type_html() {

		$html = llms_form_field( array(
			'type' => 'html',
			'value' => '<h2>HTML Content.</h2>',
		), false );

		$this->assertStringContains( '<div class="llms-form-field type-html', $html );
		$this->assertStringContains( '<div class="llms-field-html"', $html );
		$this->assertStringContains( '><h2>HTML Content.</h2></div>', $html );

	}

	/**
	 * Test attributes setting.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_attributes() {

		$this->assertStringContains( 'data-custom="whatever', llms_form_field( array( 'attributes' => array( 'data-custom' => 'whatever' ) ), false ) );

		$multi = llms_form_field( array( 'attributes' => array( 'data-custom' => 'whatever', 'maxlength' => 5 ) ), false );
		$this->assertStringContains( 'maxlength="5"', $multi );
		$this->assertStringContains( 'data-custom="whatever', $multi );

	}

	/**
	 * Test columns setting.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_columns() {

		// Default.
		$this->assertStringContains( 'llms-cols-12 llms-cols-last', llms_form_field( array(), false ) );
		$this->assertStringContains( '<div class="clear"></div>', llms_form_field( array(), false ) );

		// Set cols.
		$this->assertStringContains( 'llms-cols-5 llms-cols-last', llms_form_field( array( 'columns' => 5 ), false ) );
		$this->assertStringContains( 'llms-cols-8 llms-cols-last', llms_form_field( array( 'columns' => 8 ), false ) );

		// Not last.
		$this->assertStringNotContains( 'llms-cols-last', llms_form_field( array( 'last_column' => false ), false ) );
		$this->assertStringNotContains( '<div class="clear"></div>', llms_form_field( array( 'last_column' => false ), false ) );

	}

	/**
	 * Test id setting.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_id() {

		$this->assertStringContains( 'id="', llms_form_field( array(), false ) );
		$this->assertStringContains( 'id="mock"', llms_form_field( array( 'id' => 'mock' ), false ) );

	}

	/**
	 * Test wrapper classes setting.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_wrapper_classes() {

		// Strings.
		$this->assertStringContains( 'mock-wrapper-class">', llms_form_field( array( 'wrapper_classes' => 'mock-wrapper-class' ), false ) );
		$this->assertStringContains( 'mock-wrapper-class alt-class">', llms_form_field( array( 'wrapper_classes' => 'mock-wrapper-class alt-class' ), false ) );

		// Arrays.
		$this->assertStringContains( 'mock-wrapper-class">', llms_form_field( array( 'wrapper_classes' => array( 'mock-wrapper-class' ) ), false ) );
		$this->assertStringContains( 'mock-wrapper-class alt-class">', llms_form_field( array( 'wrapper_classes' => array( 'mock-wrapper-class', 'alt-class' ) ), false ) );

	}

	/**
	 * Test field `value` attribute.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_value() {

		// No specified value.
		$this->assertStringNotContains( 'value="', llms_form_field( array(), false ) );

		// Value is specified.
		$this->assertStringContains( 'value="mock"', llms_form_field( array( 'value' => 'mock' ), false ) );

		// Default value specified.
		$this->assertStringContains( 'value="mock"', llms_form_field( array( 'default' => 'mock' ), false ) );

		// Default value not added if a value is specified.
		$this->assertStringContains( 'value="mock"', llms_form_field( array( 'value' => 'mock', 'default' => 'fake' ), false ) );
		$this->assertStringNotContains( 'value="fake"', llms_form_field( array( 'value' => 'mock', 'default' => 'fake' ), false ) );

	}

	/**
	 * Test field `name` attribute.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_name() {

		// No name specified, fallback to the field id.
		$this->assertStringContains( 'name="mock"', llms_form_field( array( 'id' => 'mock' ), false ) );

		// Name specified.
		$this->assertStringContains( 'name="mock"', llms_form_field( array( 'name' => 'mock', 'id' => 'fake' ), false ) );

		// Name explicitly disabled.
		$this->assertStringNotContains( 'name="', llms_form_field( array( 'name' => false ), false ) );

	}

	/**
	 * Test field `placeholder` attribute.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_placeholder() {

		$this->assertStringContains( 'placeholder="test"', llms_form_field( array( 'placeholder' => 'test' ), false ) );

	}

	/**
	 * Test field `style` attribute.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_deprecated_attributes() {

		// No style.
		$this->assertStringNotContains( 'style="', llms_form_field( array(), false ) );

		// Has style.
		$this->assertStringContains( 'style="test"', llms_form_field( array( 'style' => 'test' ), false ) );

		$this->assertStringContains( 'maxlength="1"', llms_form_field( array( 'max_length' => '1' ), false ) );
		$this->assertStringContains( 'minlength="25"', llms_form_field( array( 'min_length' => '25' ), false ) );

	}

	/**
	 * Test field description.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_description() {

		// No description.
		$this->assertStringNotContains( '<span class="llms-description">', llms_form_field( array(), false ) );

		// Has Description.
		$this->assertStringContains( '<span class="llms-description">Test Description</span>', llms_form_field( array( 'description' => 'Test Description' ), false ) );

	}

	/**
	 * Test field `required` attribute.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_required() {

		// Not required.
		$this->assertStringNotContains( '<span class="llms-required">*</span>', llms_form_field( array( 'label' => 'mock' ), false ) );
		$this->assertStringNotContains( 'required="required"', llms_form_field( array(), false ) );

		// Is required.
		$this->assertStringContains( '<span class="llms-required">*</span>', llms_form_field( array( 'required' => true, 'label' => 'mock' ), false ) );
		$this->assertStringContains( 'required="required"', llms_form_field( array( 'required' => true ), false ) );

		// Required but no label.
		$this->assertStringNotContains( '<span class="llms-required">*</span>', llms_form_field( array( 'required' => true ), false ) );

	}

	/**
	 * Test field `label` attribute.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_label() {

		$this->assertStringContains( '<label for="fake">mock</label>', llms_form_field( array( 'id' => 'fake', 'label' => 'mock' ), false ) );
		$this->assertStringContains( '<label for="fake">mock<span class="llms-required">*</span></label>', llms_form_field( array( 'id' => 'fake', 'label' => 'mock', 'required' => true ), false ) );

	}

	/**
	 * No label element output when label is empty.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_label_empty() {

		$this->assertStringNotContains( '<label', llms_form_field( array( 'id' => 'fake' ), false ) );

	}

	/**
	 * Output an empty label element if `label_show_empty` is true and `label` is empty.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_label_show_empty() {

		$this->assertStringContains( '<label for="fake"></label>', llms_form_field( array( 'id' => 'fake', 'label_show_empty' => true ), false ) );

	}

	/**
	 * Test field `disabled` attribute.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_field_disabled() {

		// No disabled.
		$this->assertStringNotContains( 'disabled="disabled"', llms_form_field( array(), false ) );

		// Has disabled.
		$this->assertStringContains( 'disabled="disabled"', llms_form_field( array( 'disabled' => true ), false ) );

	}

}
