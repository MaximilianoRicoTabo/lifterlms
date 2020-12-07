<?php
/**
 * User Handling for login and registration (mostly)
 *
 * @package LifterLMS/Classes
 *
 * @since 3.0.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Person_Handler class.
 *
 * @since 3.0.0
 * @since 3.35.0 Sanitize field data when filling field with user-submitted data.
 * @since [version] Deprecated LLMS_Person_Handler::register() method.
 *               Deprecated LLMS_Person_Handler::update() method.
 */
class LLMS_Person_Handler {

	/**
	 * Prefix for all user meta field keys
	 *
	 * @var string
	 */
	private static $meta_prefix = 'llms_';

	/**
	 * Prevents the hacky voucher script from being output multiple times
	 *
	 * @var boolean
	 */
	private static $voucher_script_output = false;

	/**
	 * Locate password fields from a given form location.
	 *
	 * @since [version]
	 *
	 * @param string $location From location.
	 * @return false|array[]
	 */
	protected static function find_password_fields( $location ) {

		$forms = LLMS_Forms::instance();
		$all   = $forms->get_form_fields( $location );

		$pwd = $forms->get_field_by( (array) $all, 'id', 'password' );

		// If we don't have a password in the form return early.
		if ( ! $pwd ) {
			return false;
		}

		// Setup the return array.
		$fields = array( $pwd );

		// Add confirmation and strength meter if they exist.
		foreach ( array( 'password_confirm', 'llms-password-strength-meter' ) as $id ) {

			$field = $forms->get_field_by( $all, 'id', $id );
			if ( $field ) {

				// If we have a confirmation field ensure that the fields sit side by side.
				if ( 'password_confirm' === $id ) {

					$fields[0]['columns']         = 6;
					$fields[0]['last_column']     = false;
					$fields[0]['wrapper_classes'] = array();

					$field['columns']         = 6;
					$field['last_column']     = true;
					$field['wrapper_classes'] = array();

				}

				$fields[] = $field;
			}
		}

		return $fields;

	}

	/**
	 * Generate a unique login based on the user's email address
	 *
	 * @since 3.0.0
	 * @since 3.19.4 Unknown.
	 *
	 * @param string $email User's email address.
	 * @return string
	 */
	public static function generate_username( $email ) {

		/**
		 * Allow custom username generation
		 *
		 * @since 3.0.0
		 *
		 * @param string $custom_username Return a non-null value to use that as the username.
		 * @param string $email User's email address.
		 */
		$custom_username = apply_filters( 'lifterlms_generate_username', null, $email );
		if ( $custom_username ) {
			return $custom_username;
		}

		$username      = sanitize_user( current( explode( '@', $email ) ), true );
		$orig_username = $username;
		$i             = 1;
		while ( username_exists( $username ) ) {

			$username = $orig_username . $i;
			$i++;

		}

		return apply_filters( 'lifterlms_generated_username', $username, $email );

	}

	/**
	 * Retrieve an array of fields for a specific screen
	 *
	 * Each array represents a form field that can be passed to llms_form_field().
	 *
	 * An array of data or a user ID can be passed to fill the fields via self::fill_fields().
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Unknown.
	 *
	 * @todo Deprecate
	 *
	 * @param string    $screen Name os the screen [account|checkout|registration].
	 * @param array|int $data   Array of data to fill fields with or a WP User ID.
	 * @return array
	 */
	public static function get_available_fields( $screen = 'registration', $data = array() ) {

		$uid = get_current_user_id();

		// Setup all the fields to load.
		$fields = array();

		// This isn't needed if we're on an account screen or.
		if ( 'account' !== $screen && ( 'checkout' !== $screen || ! $uid ) ) {
			$fields[] = array(
				'columns'     => 12,
				'id'          => 'user_login',
				'label'       => __( 'Username', 'lifterlms' ),
				'last_column' => true,
				'required'    => true,
				'type'        => ( 'yes' === get_option( 'lifterlms_registration_generate_username' ) ) ? 'hidden' : 'text',
			);
		}

		// On the checkout screen, if we already have a user we can remove these fields:.
		// Username, email, email confirm, password, password confirm, password meter.
		if ( 'checkout' !== $screen || ! $uid ) {
			$email_con = get_option( 'lifterlms_user_info_field_email_confirmation_' . $screen . '_visibility' );
			$fields[]  = array(
				'columns'     => ( 'no' === $email_con ) ? 12 : 6,
				'id'          => 'email_address',
				'label'       => __( 'Email Address', 'lifterlms' ),
				'last_column' => ( 'no' === $email_con ) ? true : false,
				'matched'     => 'email_address_confirm',
				'required'    => true,
				'type'        => 'email',
			);
			if ( 'yes' === $email_con ) {
				$fields[] = array(
					'columns'     => 6,
					'id'          => 'email_address_confirm',
					'label'       => __( 'Confirm Email Address', 'lifterlms' ),
					'last_column' => true,
					'match'       => 'email_address',
					'required'    => true,
					'type'        => 'email',
				);
			}

			// Account screen has password updates at the bottom.
			if ( 'account' !== $screen ) {
				$fields = self::get_password_fields( $screen, $fields );
			}
		}

		$names = get_option( 'lifterlms_user_info_field_names_' . $screen . '_visibility' );
		if ( 'hidden' !== $names ) {
			$fields[] = array(
				'columns'     => 6,
				'id'          => 'first_name',
				'label'       => __( 'First Name', 'lifterlms' ),
				'last_column' => false,
				'required'    => ( 'required' === $names ) ? true : false,
				'type'        => 'text',
			);
			$fields[] = array(
				'columns'     => 6,
				'id'          => 'last_name',
				'label'       => __( 'Last Name', 'lifterlms' ),
				'last_column' => true,
				'required'    => ( 'required' === $names ) ? true : false,
				'type'        => 'text',
			);
		}

		$address = get_option( 'lifterlms_user_info_field_address_' . $screen . '_visibility' );

		if ( 'hidden' !== $address ) {
			$fields[] = array(
				'columns'     => 8,
				'id'          => self::$meta_prefix . 'billing_address_1',
				'label'       => __( 'Street Address', 'lifterlms' ),
				'last_column' => false,
				'required'    => ( 'required' === $address ) ? true : false,
				'type'        => 'text',
			);
			$fields[] = array(
				'columns'     => 4,
				'id'          => self::$meta_prefix . 'billing_address_2',
				'label'       => '&nbsp;',
				'last_column' => true,
				'placeholder' => __( 'Apartment, suite, or unit', 'lifterlms' ),
				'required'    => false,
				'type'        => 'text',
			);
			$fields[] = array(
				'columns'     => 6,
				'id'          => self::$meta_prefix . 'billing_city',
				'label'       => __( 'City', 'lifterlms' ),
				'last_column' => false,
				'required'    => ( 'required' === $address ) ? true : false,
				'type'        => 'text',
			);
			$fields[] = array(
				'columns'     => 3,
				'id'          => self::$meta_prefix . 'billing_state',
				'label'       => __( 'State', 'lifterlms' ),
				'last_column' => false,
				'required'    => ( 'required' === $address ) ? true : false,
				'type'        => 'text',
			);
			$fields[] = array(
				'columns'     => 3,
				'id'          => self::$meta_prefix . 'billing_zip',
				'label'       => __( 'Zip Code', 'lifterlms' ),
				'last_column' => true,
				'required'    => ( 'required' === $address ) ? true : false,
				'type'        => 'text',
			);
			$fields[] = array(
				'columns'     => 12,
				'default'     => get_lifterlms_country(),
				'id'          => self::$meta_prefix . 'billing_country',
				'label'       => __( 'Country', 'lifterlms' ),
				'last_column' => true,
				'options'     => get_lifterlms_countries(),
				'required'    => ( 'required' === $address ) ? true : false,
				'type'        => 'select',
			);
		}

		$phone = get_option( 'lifterlms_user_info_field_phone_' . $screen . '_visibility' );
		if ( 'hidden' !== $phone ) {
			$fields[] = array(
				'columns'     => 12,
				'id'          => self::$meta_prefix . 'phone',
				'label'       => __( 'Phone Number', 'lifterlms' ),
				'last_column' => true,
				'placeholder' => _x( '(123) 456 - 7890', 'Phone Number Placeholder', 'lifterlms' ),
				'required'    => ( 'required' === $phone ) ? true : false,
				'type'        => 'text',
			);
		}

		$voucher = get_option( 'lifterlms_voucher_field_' . $screen . '_visibility', '' );
		if ( 'registration' === $screen && 'hidden' !== $voucher ) {

			$toggleable    = apply_filters( 'llms_voucher_toggleable', ( 'required' === $voucher ) ? false : true );
			$voucher_label = __( 'Have a voucher?', 'lifterlms' );
			if ( $toggleable ) {
				$voucher_label = '<a class="llms-voucher-toggle" id="llms-voucher-toggle" href="#">' . $voucher_label . '</a>';
				add_action( 'wp_print_footer_scripts', array( __CLASS__, 'voucher_toggle_script' ) );
			}

			$fields[] = array(
				'columns'     => 12,
				'id'          => self::$meta_prefix . 'voucher',
				'label'       => $voucher_label,
				'last_column' => true,
				'placeholder' => __( 'Voucher Code', 'lifterlms' ),
				'required'    => ( 'required' === $voucher ) ? true : false,
				'style'       => $toggleable ? 'display: none;' : '',
				'type'        => 'text',
			);

		}

		// Add account password fields.
		if ( 'account' === $screen ) {
			$fields = self::get_password_fields( $screen, $fields );
		}

		$fields = apply_filters( 'lifterlms_get_person_fields', $fields, $screen );

		// Populate fields with data, if we have any.
		if ( $data ) {
			$fields = self::fill_fields( $fields, $data );
		}

		return $fields;

	}

	/**
	 * Get the fields for the login form
	 *
	 * @since 3.0.0
	 * @since 3.0.4 Unknown.
	 * @since [version] Remove usage of the deprecated `lifterlms_registration_generate_username`.
	 *
	 * @param string $layout Form layout. Accepts "columns" (default) or "stacked".
	 * @return array[] An array of form field arrays.
	 */
	public static function get_login_fields( $layout = 'columns' ) {

		$usernames = LLMS_Forms::instance()->are_usernames_enabled();

		/**
		 * Customize the fields used to build the user login form
		 *
		 * @since 3.0.0
		 * @param array[] $fields An array of form field arrays.
		 */
		return apply_filters(
			'lifterlms_person_login_fields',
			array(
				array(
					'columns'     => ( 'columns' == $layout ) ? 6 : 12,
					'id'          => 'llms_login',
					'label'       => ! $usernames ? __( 'Email Address', 'lifterlms' ) : __( 'Username or Email Address', 'lifterlms' ),
					'last_column' => ( 'columns' == $layout ) ? false : true,
					'required'    => true,
					'type'        => ! $usernames ? 'email' : 'text',
				),
				array(
					'columns'     => ( 'columns' == $layout ) ? 6 : 12,
					'id'          => 'llms_password',
					'label'       => __( 'Password', 'lifterlms' ),
					'last_column' => ( 'columns' == $layout ) ? true : true,
					'required'    => true,
					'type'        => 'password',
				),
				array(
					'columns'     => ( 'columns' == $layout ) ? 3 : 12,
					'classes'     => 'llms-button-action',
					'id'          => 'llms_login_button',
					'value'       => __( 'Login', 'lifterlms' ),
					'last_column' => ( 'columns' == $layout ) ? false : true,
					'required'    => false,
					'type'        => 'submit',
				),
				array(
					'columns'     => ( 'columns' == $layout ) ? 6 : 6,
					'id'          => 'llms_remember',
					'label'       => __( 'Remember me', 'lifterlms' ),
					'last_column' => false,
					'required'    => false,
					'type'        => 'checkbox',
				),
				array(
					'columns'         => ( 'columns' == $layout ) ? 3 : 6,
					'id'              => 'llms_lost_password',
					'last_column'     => true,
					'description'     => '<a href="' . esc_url( llms_lostpassword_url() ) . '">' . __( 'Lost your password?', 'lifterlms' ) . '</a>',
					'type'            => 'html',
					'wrapper_classes' => 'align-right',
				),
			)
		);

	}

	/**
	 * Retrieve fields for password recovery
	 *
	 * Used to generate the form where a username/email is entered to start the password reset process.
	 *
	 * @since 3.8.0
	 * @since [version] Use LLMS_Forms::are_usernames_enabled() in favor of deprecated option "lifterlms_registration_generate_username".
	 *               Remove field values set to the default value for a form field.
	 *
	 * @return array[] An array of form field arrays.
	 */
	public static function get_lost_password_fields() {

		$usernames = LLMS_Forms::instance()->are_usernames_enabled();

		if ( ! $usernames ) {
			$message = __( 'Lost your password? Enter your email address and we will send you a link to reset it.', 'lifterlms' );
		} else {
			$message = __( 'Lost your password? Enter your username or email address and we will send you a link to reset it.', 'lifterlms' );
		}

		/**
		 * Filter the message displayed on the lost password form.
		 *
		 * @since Unknown.
		 *
		 * @param string $message The message displayed before the form.
		 */
		$message = apply_filters( 'lifterlms_lost_password_message', $message );

		/**
		 * Filter the form fields displayed for the lost password form.
		 *
		 * @since 3.8.0
		 *
		 * @param array[] $fields An array of form field arrays.
		 */
		return apply_filters(
			'lifterlms_lost_password_fields',
			array(
				array(
					'id'    => 'llms_lost_password_message',
					'type'  => 'html',
					'value' => $message,
				),
				array(
					'id'       => 'llms_login',
					'label'    => ! $usernames ? __( 'Email Address', 'lifterlms' ) : __( 'Username or Email Address', 'lifterlms' ),
					'required' => true,
					'type'     => ! $usernames ? 'email' : 'text',
				),
				array(
					'classes' => 'llms-button-action auto',
					'id'      => 'llms_lost_password_button',
					'value'   => __( 'Reset Password', 'lifterlms' ),
					'type'    => 'submit',
				),
			)
		);

	}

	/**
	 * Retrieve an array of password fields.
	 *
	 * This is only used on the password rest form as a fallback
	 * when no "custom" password fields can be found in either of the default
	 * checkout or registration forms.
	 *
	 * @since 3.7.0
	 * @since [version] Removed optional parameters
	 *
	 * @return array[]
	 */
	private static function get_password_fields() {

		$fields = array();

		$fields[] = array(
			'columns'     => 6,
			'classes'     => 'llms-password',
			'id'          => 'password',
			'label'       => __( 'Password', 'lifterlms' ),
			'last_column' => false,
			'match'       => 'password_confirm',
			'required'    => true,
			'type'        => 'password',
		);
		$fields[] = array(
			'columns'  => 6,
			'classes'  => 'llms-password-confirm',
			'id'       => 'password_confirm',
			'label'    => __( 'Confirm Password', 'lifterlms' ),
			'match'    => 'password',
			'required' => true,
			'type'     => 'password',
		);

		$fields[] = array(
			'classes'      => 'llms-password-strength-meter',
			'description'  => __( 'A strong password is required. The password must be at least 6 characters in length. Consider adding letters, numbers, and symbols to increase the password strength.', 'lifterlms' ),
			'id'           => 'llms-password-strength-meter',
			'type'         => 'html',
			'min_length'   => 6,
			'min_strength' => 'strong',
		);

		return $fields;

	}

	/**
	 * Retrieve form fields used on the password reset form.
	 *
	 * This method will attempt to the "custom" password fields in the checkout form
	 * and then in the registration form. At least a password field must be found. If
	 * it cannot be found this function falls back to a set of default fields as defined
	 * in the LLMS_Person_Handler::get_password_fields() method.
	 *
	 * @since Unknown
	 * @since [version] Get fields from the checkout or registration forms before falling back to default fields.
	 *               Changed filter on return from "lifterlms_lost_password_fields" to "llms_password_reset_fields".
	 *
	 * @param string $key User password reset key, usually populated via $_GET vars.
	 * @param string $login User login (username), usually populated via $_GET vars.
	 * @return array[]
	 */
	public static function get_password_reset_fields( $key = '', $login = '' ) {

		$fields = array();
		foreach ( array( 'checkout', 'registration' ) as $location ) {
			$fields = self::find_password_fields( $location );
			if ( $fields ) {
				break;
			}
		}

		// Fallback if no custom fields are found.
		if ( ! $fields ) {
			$location = 'fallback';
			$fields   = self::get_password_fields();
		}

		// Add button.
		$fields[] = array(
			'classes' => 'llms-button-action auto',
			'id'      => 'llms_lost_password_button',
			'type'    => 'submit',
			'value'   => __( 'Reset Password', 'lifterlms' ),
		);

		// Add hidden fields.
		$fields[] = array(
			'id'    => 'llms_reset_key',
			'type'  => 'hidden',
			'value' => $key,
		);
		$fields[] = array(
			'id'    => 'llms_reset_login',
			'type'  => 'hidden',
			'value' => $login,
		);

		/**
		 * Filter password reset form fields.
		 *
		 * @since [version]
		 *
		 * @param array[] $fields Array of form field arrays.
		 * @param string $key User password reset key, usually populated via $_GET vars.
		 * @param string $login User login (username), usually populated via $_GET vars.
		 * @param string $location Location where the fields were retrieved from. Either "checkout", "registration", or "fallback".
		 *                         Fallback denotes that no password field was located in either of the previous forms so a default
		 *                         set of fields is generated programmatically.
		 */
		return apply_filters( 'llms_password_reset_fields', $fields, $key, $login, $location );

	}

	/**
	 * Field an array of user fields retrieved from self::get_available_fields() with data
	 *
	 * The resulting array will be the data retrieved from self::get_available_fields() with "value" keys filled for each field.
	 *
	 * @since 3.0.0
	 * @since 3.35.0 Sanitize field data when filling field with user-submitted data.
	 *
	 * @param array $fields Array of fields from self::get_available_fields().
	 * @param array $data   Array of data (from a $_POST or function).
	 * @return array
	 */
	private static function fill_fields( $fields, $data ) {

		if ( is_numeric( $data ) ) {
			$user = new LLMS_Student( $data );
		}

		foreach ( $fields as &$field ) {

			if ( 'password' === $field['type'] || 'html' === $field['type'] ) {
				continue;
			}

			$name = isset( $field['name'] ) ? $field['name'] : $field['id'];
			$val  = false;

			if ( isset( $data[ $name ] ) ) {

				$val = $data[ $name ];

			} elseif ( isset( $user ) ) {

				if ( 'email_address' === $name ) {
					$name = 'user_email';
				}
				$val = $user->get( $name );

			}

			if ( $val ) {
				if ( 'checkbox' === $field['type'] ) {
					if ( $val == $field['value'] ) {
						$field['selected'] = true;
					}
				} else {
					$field['value'] = self::sanitize_field( $val, $field['type'] );
				}
			}
		}

		return $fields;

	}

	/**
	 * Insert user data during registrations and updates
	 *
	 * @since 3.0.0
	 * @since 3.24.0
	 *
	 * @param array  $data   Array of user data to be passed to WP core functions.
	 * @param string $action Either registration or update.
	 * @return WP_Error|int  WP_Error on error or the WP User ID
	 */
	private static function insert_data( $data = array(), $action = 'registration' ) {

		if ( 'registration' === $action ) {
			$insert_data = array(
				'role'                 => 'student',
				'show_admin_bar_front' => false,
				'user_email'           => $data['email_address'],
				'user_login'           => $data['user_login'],
				'user_pass'            => $data['password'],
			);

			$extra_data = array(
				'first_name',
				'last_name',
			);

			$insert_func = 'wp_insert_user';
			$meta_func   = 'add_user_meta';

		} elseif ( 'update' === $action ) {

			$insert_data = array(
				'ID' => $data['user_id'],
			);

			// Email address if set.
			if ( isset( $data['email_address'] ) ) {
				$insert_data['user_email'] = $data['email_address'];
			}

			// Update password if both are set.
			if ( isset( $data['password'] ) && isset( $data['password_confirm'] ) ) {
				$insert_data['user_pass'] = $data['password'];
			}

			$extra_data = array(
				'first_name',
				'last_name',
			);

			$insert_func = 'wp_update_user';
			$meta_func   = 'update_user_meta';

		} else {

			return new WP_Error( 'invalid', __( 'Invalid action', 'lifterlms' ) );

		}

		foreach ( $extra_data as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$insert_data[ $field ] = $data[ $field ];
			}
		}

		// Attempt to insert the data.
		$person_id = $insert_func( apply_filters( 'lifterlms_user_' . $action . '_insert_user', $insert_data, $data, $action ) );

		// Return the error object if registration fails.
		if ( is_wp_error( $person_id ) ) {
			return apply_filters( 'lifterlms_user_' . $action . '_failure', $person_id, $data, $action );
		}

		// Add user ip address.
		$data[ self::$meta_prefix . 'ip_address' ] = llms_get_ip_address();

		// Metas.
		$possible_metas = apply_filters(
			'llms_person_insert_data_possible_metas',
			array(
				self::$meta_prefix . 'billing_address_1',
				self::$meta_prefix . 'billing_address_2',
				self::$meta_prefix . 'billing_city',
				self::$meta_prefix . 'billing_state',
				self::$meta_prefix . 'billing_zip',
				self::$meta_prefix . 'billing_country',
				self::$meta_prefix . 'ip_address',
				self::$meta_prefix . 'phone',
			)
		);
		$insert_metas   = array();
		foreach ( $possible_metas as $meta ) {
			if ( isset( $data[ $meta ] ) ) {
				$insert_metas[ $meta ] = $data[ $meta ];
			}
		}

		// Record all meta values.
		$metas = apply_filters( 'lifterlms_user_' . $action . '_insert_user_meta', $insert_metas, $data, $action );
		foreach ( $metas as $key => $val ) {
			$meta_func( $person_id, $key, $val );
		}

		// If agree to terms data is present, record the agreement date.
		if ( isset( $data[ self::$meta_prefix . 'agree_to_terms' ] ) && 'yes' === $data[ self::$meta_prefix . 'agree_to_terms' ] ) {

			$meta_func( $person_id, self::$meta_prefix . 'agree_to_terms', current_time( 'mysql' ) );

		}

		return $person_id;

	}

	/**
	 * Login a user
	 *
	 * @since 3.0.0
	 * @since 3.29.4 Unknown.
	 * @since [version] Removed email lookup logic since `wp_authenticate()` supports email addresses as `user_login` since WP 4.5.
	 *
	 * @param array $data {
	 *     User login information.
	 *
	 *     @type string $llms_login User email address or username.
	 *     @type string $llms_password User password.
	 *     @type string $llms_remember Whether to extend the cookie duration to keep the user logged in for a longer period.
	 * }
	 * @return WP_Error|int WP_Error on error or the WP_User ID.
	 */
	public static function login( $data ) {

		/**
		 * Run an action prior to user login.
		 *
		 * @since 3.0.0
		 *
		 * @param array $data {
		 *    User login credentials.
		 *
		 *    @type string $user_login User's username.
		 *    @type string $password User's password.
		 *    @type bool $remeber Whether to extend the cookie duration to keep the user logged in for a longer period.
		 * }
		 */
		do_action( 'lifterlms_before_user_login', $data );

		/**
		 * Filter user submitted login data prior to data validation.
		 *
		 * @since 3.0.0
		 *
		 * @param array $data {
		 *    User login credentials.
		 *
		 *    @type string $user_login User's username.
		 *    @type string $password User's password.
		 *    @type bool $remeber Whether to extend the cookie duration to keep the user logged in for a longer period.
		 * }
		 */
		$data = apply_filters( 'lifterlms_user_login_data', $data );

		// Validate the fields & allow custom validation to occur.
		$valid = self::validate_fields( $data, 'login' );

		// Error.
		if ( is_wp_error( $valid ) ) {
			return apply_filters( 'lifterlms_user_login_errors', $valid, $data, false );
		}

		$creds = array(
			'user_login'    => wp_unslash( $data['llms_login'] ), // Unslash ensures that an email address with an apostrophe is unescaped for lookups.
			'user_password' => $data['llms_password'],
			'remember'      => isset( $data['llms_remember'] ),
		);

		/**
		 * Filter a user's login credentials immediately prior to signing in.
		 *
		 * @param array $creds {
		 *    User login credentials.
		 *
		 *    @type string $user_login User's username.
		 *    @type string $password User's password.
		 *    @type bool $remeber Whether to extend the cookie duration to keep the user logged in for a longer period.
		 * }
		 */
		$creds  = apply_filters( 'lifterlms_login_credentials', $creds );
		$signon = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $signon ) ) {
			$err = new WP_Error( 'login-error', __( 'Could not find an account with the supplied email address and password combination.', 'lifterlms' ) );
			return apply_filters( 'lifterlms_user_login_errors', $err, $data, $signon );
		}

		return $signon->ID;

	}

	/**
	 * Sanitize posted fields
	 *
	 * @since 3.19.4
	 *
	 * @param string $val        Unsanitized user data.
	 * @param string $field_type Field type, allows additional sanitization to run based on field type.
	 * @return string
	 */
	private static function sanitize_field( $val, $field_type = '' ) {

		$val = trim( sanitize_text_field( $val ) );
		if ( $field_type && 'email' === $field_type ) {
			$val = wp_unslash( $val );
		}

		return $val;

	}

	/**
	 * Validate submitted user data for registration or profile update
	 *
	 * @since 3.0.0
	 * @since 3.19.4 Unknown.
	 *
	 * @param array  $data {
	 *      User data array.
	 *
	 *     @type string $user_login             User login/username.
	 *     @type string $email_address          User email.
	 *     @type string $email_address_confirm  Email address confirmation.
	 *     @type string $password               User password.
	 *     @type string $password_confirm       Password confirmation.
	 *     @type string $first_name             User First name.
	 *     @type string $last_name              User last name.
	 *     @type string $llms_billing_address_1 Address line 1.
	 *     @type string $llms_billing_address_2 Address line 2.
	 *     @type string $llms_billing_city      City.
	 *     @type string $llms_billing_state     State.
	 *     @type string $llms_billing_zip       Zip / Postal code.
	 *     @type string $llms_billing_country   Country.
	 *     @type string $llms_phone             Phone number.
	 * }
	 * @param string $screen Screen to validate fields against, accepts "account", "checkout", "registration", or "update".
	 * @return true|WP_Error
	 */
	public static function validate_fields( $data, $screen = 'registration' ) {

		if ( 'login' === $screen ) {

			$fields = self::get_login_fields();

		} elseif ( 'reset_password' === $screen ) {

			$fields = self::get_password_reset_fields();

		} else {

			$fields = self::get_available_fields( $screen );

			// If no current password submitted with an account update.
			// We can remove password fields so we don't get false validations.
			if ( 'account' === $screen && empty( $data['current_password'] ) ) {
				unset( $data['current_password'], $data['password'], $data['password_confirm'] );
				foreach ( $fields as $key => $field ) {
					if ( in_array( $field['id'], array( 'current_password', 'password', 'password_confirm' ) ) ) {
						unset( $fields[ $key ] );
					}
				}
			}
		}

		$e = new WP_Error();

		$matched_values = array();

		foreach ( $fields as $field ) {

			$name  = isset( $field['name'] ) ? $field['name'] : $field['id'];
			$label = isset( $field['label'] ) ? $field['label'] : $name;

			$field_type = isset( $field['type'] ) ? $field['type'] : '';
			$val        = isset( $data[ $name ] ) ? self::sanitize_field( $data[ $name ], $field_type ) : '';

			// Ensure required fields are submitted.
			if ( isset( $field['required'] ) && $field['required'] && empty( $val ) ) {

				$e->add( $field['id'], sprintf( __( '%s is a required field', 'lifterlms' ), $label ), 'required' );
				continue;

			}

			// Check email field for uniqueness.
			if ( 'email_address' === $name ) {

				$skip_email = false;

				// Only run this check when we're trying to change the email address for an account update.
				if ( 'account' === $screen ) {
					$user = wp_get_current_user();
					if ( self::sanitize_field( $data['email_address'], 'email' ) === $user->user_email ) {
						$skip_email = true;
					}
				}

				if ( ! $skip_email && email_exists( $val ) ) {
					$e->add( $field['id'], sprintf( __( 'An account with the email address "%s" already exists.', 'lifterlms' ), $val ), 'email-exists' );
				}
			} elseif ( 'user_login' === $name ) {

				// Blacklist usernames for security purposes.
				$banned_usernames = apply_filters( 'llms_usernames_blacklist', array( 'admin', 'test', 'administrator', 'password', 'testing' ) );

				if ( in_array( $val, $banned_usernames ) || ! validate_username( $val ) ) {

					$e->add( $field['id'], sprintf( __( 'The username "%s" is invalid, please try a different username.', 'lifterlms' ), $val ), 'invalid-username' );

				} elseif ( username_exists( $val ) ) {

					$e->add( $field['id'], sprintf( __( 'An account with the username "%s" already exists.', 'lifterlms' ), $val ), 'username-exists' );

				}
			} elseif ( 'llms_voucher' === $name && ! empty( $val ) ) {

				$v     = new LLMS_Voucher();
				$check = $v->check_voucher( $val );
				if ( is_wp_error( $check ) ) {
					$e->add( $field['id'], $check->get_error_message(), 'voucher-' . $check->get_error_code() );
				}
			} elseif ( 'current_password' === $name ) {
				$user = wp_get_current_user();
				if ( ! wp_check_password( $val, $user->data->user_pass, $user->ID ) ) {
					$e->add( $field['id'], sprintf( __( 'The submitted %s was incorrect.', 'lifterlms' ), $field['label'] ), 'incorrect-password' );
				}
			}

			// Scrub and check field data types.
			if ( isset( $field['type'] ) ) {

				switch ( $field['type'] ) {

					// Ensure it's a selectable option.
					case 'select':
					case 'radio':
						if ( ! in_array( $val, array_keys( $field['options'] ) ) ) {
							$e->add( $field['id'], sprintf( __( '"%1$s" is an invalid option for %2$s', 'lifterlms' ), $val, $label ), 'invalid' );
						}
						break;

					// Make sure the value is numeric.
					case 'number':
						if ( ! is_numeric( $val ) ) {
							$e->add( $field['id'], sprintf( __( '%s must be numeric', 'lifterlms' ), $label ), 'invalid' );
							continue 2;
						}
						break;

					// Validate the email address.
					case 'email':
						if ( ! is_email( $val ) ) {
							$e->add( $field['id'], sprintf( __( '%s must be a valid email address', 'lifterlms' ), $label ), 'invalid' );
						}
						break;

				}
			}

			// Store this fields label so it can be used in a match error later if necessary.
			if ( ! empty( $field['matched'] ) ) {

				$matched_values[ $field['matched'] ] = $label;

			}

			// Match matchy fields.
			if ( ! empty( $field['match'] ) ) {

				$match = isset( $data[ $field['match'] ] ) ? self::sanitize_field( $data[ $field['match'] ], $field_type ) : false;
				if ( ! $match || $val !== $match ) {

					$e->add( $field['id'], sprintf( __( '%1$s must match %2$s', 'lifterlms' ), $matched_values[ $field['id'] ], $label ), 'match' );

				}
			}
		}

		// Return errors if we have errors.
		if ( $e->get_error_messages() ) {
			return $e;
		}

		return true;

	}


	/**
	 * Output Voucher toggle JS in a quick and shameful manner
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function voucher_toggle_script() {

		if ( empty( self::$voucher_script_output ) ) {

			self::$voucher_script_output = true;

			echo "<script type=\"text/javascript\">
			( function( $ ) {
				$( '#llms-voucher-toggle' ).on( 'click', function( e ) {
					e.preventDefault();
					$( '#llms_voucher' ).toggle();
				} );
			} )( jQuery );
			</script>";

		}

	}

	/**
	 * Perform validations according to the registration screen and registers a user
	 *
	 * @since 3.0.0
	 * @since 3.19.4 Unknown.
	 * @since 4.5.0 Use `wp_signon()` in favor of `llms_set_person_auth_cookie()` to sign on upon registration.
	 * @deprecated [version] `LLMS_Person_Handler::register()` is deprecated, in favor of `llms_register_user()`.
	 *
	 * @param array  $data Associative array of form data.
	 * @param string $screen Screen to perform validations for, accepts "registration" or "checkout".
	 * @param bool   $signon If true, also signon the newly created user.
	 * @return int|WP_Error WP_User ID on success or WP_Error on failure.
	 */
	public static function register( $data = array(), $screen = 'registration', $signon = true ) {
		llms_deprecated_function( 'LLMS_Person_Handler::register()', '[version]', 'llms_register_user()' );
		return llms_register_user( $data, $screen, $signon );
	}

	/**
	 * Perform validations according to $screen and update the user
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Unknown.
	 * @deprecated [version] `LLMS_Person_Handler::update()` is deprecated, in favor of `llms_update_user()`.
	 *
	 * @param array  $data Associative array of form data.
	 * @param string $screen Screen to perform validations for, accepts "account" or "checkout".
	 * @return int|WP_Error WP_User ID on success or WP_Error on failure.
	 */
	public static function update( $data = array(), $screen = 'update' ) {
		llms_deprecated_function( 'LLMS_Person_Handler::update()', '[version]', 'llms_update_user()' );
		return llms_update_user( $data, $screen );
	}

}
