<?php
/**
 * LLMS_Engagements class file
 *
 * @package LifterLMS/Classes
 *
 * @since 2.3.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Engagements Class
 *
 * @since 2.3.0
 * @since 3.30.3 Fixed spelling errors.
 * @since 5.3.0 Replace singleton code with `LLMS_Trait_Singleton`.
 */
class LLMS_Engagements {

	use LLMS_Trait_Singleton;

	/**
	 * Singleton instance.
	 *
	 * @deprecated 5.3.0 Use {@see LLMS_Trait_Singleton::instance()}.
	 *
	 * @var LLMS_Engagements
	 */
	protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Deprecated.

	/**
	 * Enable debug logging
	 *
	 * @since 2.7.9
	 * @var boolean
	 */
	private $debug = false;

	/**
	 * Constructor
	 *
	 * Adds actions to events that trigger engagements.
	 *
	 * @since 2.3.0
	 * @since [version] Added deprecation warning when using constant `LLMS_ENGAGEMENT_DEBUG`.
	 *
	 * @return void
	 */
	private function __construct() {

		if ( defined( 'LLMS_ENGAGEMENT_DEBUG' ) && LLMS_ENGAGEMENT_DEBUG ) {
			_deprecated_function( 'Constant: LLMS_ENGAGEMENT_DEBUG', '[version]' );
			$this->debug = true;
		}

		$this->add_actions();
		$this->init();

	}

	/**
	 * Register all actions that trigger engagements
	 *
	 * @since 2.3.0
	 * @since 3.11.0 Unknown.
	 * @since 3.39.0 Added `llms_rest_student_registered` as action hook.
	 * @since [version] Moved the list of hooks to the `get_trigger_hooks()` method.
	 *
	 * @return void
	 */
	private function add_actions() {

		foreach ( $this->get_trigger_hooks() as $action ) {
			add_action( $action, array( $this, 'maybe_trigger_engagement' ), 777, 3 );
		}

		add_action( 'lifterlms_engagement_send_email', array( $this, 'handle_email' ), 10, 1 );
		add_action( 'lifterlms_engagement_award_achievement', array( $this, 'handle_achievement' ), 10, 1 );
		add_action( 'lifterlms_engagement_award_certificate', array( $this, 'handle_certificate' ), 10, 1 );

		add_action( 'deleted_post', array( $this, 'unschedule_delayed_engagements' ), 20, 2 );
		add_action( 'trashed_post', array( $this, 'unschedule_delayed_engagements' ), 20 );

	}

	/**
	 * Retrieve a group id used when scheduling delayed engagement action triggers.
	 *
	 * @since [version]
	 *
	 * @param int $engagement_id WP_Post ID of the `llms_engagement` post type.
	 * @return string
	 */
	private function get_delayed_group_id( $engagement_id ) {
		return sprintf( 'llms_engagement_%d', $engagement_id );
	}

	/**
	 * Retrieve engagements based on the trigger type
	 *
	 * Joins rather than nested loops and sub queries ftw.
	 *
	 * @since 2.3.0
	 * @since 3.13.1 Unknown.
	 * @since [version] Removed engagement debug logging & moved filter onto the return instead of calling in `maybe_trigger_engagement()`.
	 *
	 * @param string     $trigger_type    Name of the trigger to look for.
	 * @param int|string $related_post_id The WP_Post ID of the related post or an empty string.
	 * @return object[] {
	 *     Array of objects from the database.
	 *
	 *     @type int    $engagement_id WP_Post ID of the engagement post (email, certificate, achievement).
	 *     @type int    $trigger_id    WP_Post ID of the llms_engagement post.
	 *     @type string $trigger_event The triggering action (user_registration, course_completed, etc...).
	 *     @type string $event_type    The engagement event action (certificate, achievement, email).
	 *     @type int    $delay         The engagement send delay (in days).
	 * }
	 */
	private function get_engagements( $trigger_type, $related_post_id = '' ) {

		global $wpdb;

		$related_select = '';
		$related_join   = '';
		$related_where  = '';

		if ( $related_post_id ) {

			$related_select = ', relation_meta.meta_value AS related_post_id';
			$related_join   = "LEFT JOIN $wpdb->postmeta AS relation_meta ON triggers.ID = relation_meta.post_id";
			$related_where  = $wpdb->prepare( "AND relation_meta.meta_key = '_llms_engagement_trigger_post' AND relation_meta.meta_value = %d", $related_post_id );

		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				  DISTINCT triggers.ID AS trigger_id
				, triggers_meta.meta_value AS engagement_id
				, engagements_meta.meta_value AS trigger_event
				, event_meta.meta_value AS event_type
				, delay.meta_value AS delay
				$related_select

			FROM $wpdb->postmeta AS engagements_meta

			LEFT JOIN $wpdb->posts AS triggers ON triggers.ID = engagements_meta.post_id
			LEFT JOIN $wpdb->postmeta AS triggers_meta ON triggers.ID = triggers_meta.post_id
			LEFT JOIN $wpdb->posts AS engagements ON engagements.ID = triggers_meta.meta_value
			LEFT JOIN $wpdb->postmeta AS event_meta ON triggers.ID = event_meta.post_id
			LEFT JOIN $wpdb->postmeta AS delay ON triggers.ID = delay.post_id
			$related_join

			WHERE
				    triggers.post_type = 'llms_engagement'
				AND triggers.post_status = 'publish'
				AND triggers_meta.meta_key = '_llms_engagement'

				AND engagements_meta.meta_key = '_llms_trigger_type'
				AND engagements_meta.meta_value = %s
				AND engagements.post_status = 'publish'

				AND event_meta.meta_key = '_llms_engagement_type'

				AND delay.meta_key = '_llms_engagement_delay'

				$related_where
			",
				// Prepare variables.
				$trigger_type
			),
			OBJECT
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		/**
		 * Filters the list of engagements to be triggered for a given trigger type and related post.
		 *
		 * @since [version]
		 *
		 * @param object[] $results         Array of engagement objects.
		 * @param string   $trigger_type    Name of the engagement trigger.
		 * @param int      $related_post_id WP_Post ID of the related post.
		 */
		return apply_filters( 'lifterlms_get_engagements', $results, $trigger_type, $related_post_id );

	}

	/**
	 * Retrieve a list of hooks that trigger engagements to be awarded.
	 *
	 * @since [version]
	 *
	 * @return string[]
	 */
	protected function get_trigger_hooks() {

		/**
		 * Filters the list of hooks which can trigger engagements to be sent/awarded
		 *
		 * @since Unknown
		 *
		 * @param string[] $hooks List of hook names..
		 */
		return apply_filters(
			'lifterlms_engagement_actions',
			array(
				'lifterlms_access_plan_purchased',
				'lifterlms_course_completed',
				'lifterlms_course_track_completed',
				'lifterlms_created_person',
				'llms_rest_student_registered',
				'lifterlms_lesson_completed',
				'lifterlms_product_purchased',
				'lifterlms_quiz_completed',
				'lifterlms_quiz_passed',
				'lifterlms_quiz_failed',
				'lifterlms_section_completed',
				'llms_user_enrolled_in_course',
				'llms_user_added_to_membership_level',
			)
		);

	}

	/**
	 * Include engagement types (excluding email)
	 *
	 * @since Unknown
	 *
	 * @return void
	 */
	public function init() {

		include 'class.llms.certificates.php';
		include 'class.llms.achievements.php';

	}

	/**
	 * Award an achievement
	 *
	 * @since 2.3.0
	 * @since [version] Use `llms() in favor of deprecated `LLMS()` and removed engagement debug logging.
	 *
	 * @param array $args {
	 *     Indexed array of arguments.
	 *
	 *     @type int        $0 WP_User ID.
	 *     @type int        $1 WP_Post ID of the achievement template post.
	 *     @type int|string $2 WP_Post ID of the related post that triggered the award or an empty string.
	 * }
	 * @return void
	 */
	public function handle_achievement( $args ) {
		$achievements = llms()->achievements();
		$achievements->trigger_engagement( $args[0], $args[1], $args[2] );
	}

	/**
	 * Award a certificate
	 *
	 * @since 2.3.0
	 * @since [version] Use `llms() in favor of deprecated `LLMS()` and removed engagement debug logging.
	 *
	 * @param array $args {
	 *     Indexed array of arguments.
	 *
	 *     @type int        $0 WP_User ID.
	 *     @type int        $1 WP_Post ID of the certificate template post.
	 *     @type int|string $2 WP_Post ID of the related post that triggered the award or an empty string.
	 * }
	 * @return void
	 */
	public function handle_certificate( $args ) {
		$certs = llms()->certificates();
		$certs->trigger_engagement( $args[0], $args[1], $args[2] );
	}

	/**
	 * Send an email engagement
	 *
	 * This is called via do_action() by the 'maybe_trigger_engagement' function in this class.
	 *
	 * @since 2.3.0
	 * @since 3.8.0 Unknown.
	 * @since 4.4.1 Use postmeta helpers for dupcheck and postmeta insertion.
	 *              Add a return value in favor of `void`.
	 *              Log successes and failures to the `engagement-emails` log file instead of the main `llms` log.
	 * @since 4.4.3 Fixed different emails triggered by the same related post not sent because of a wrong duplicate check.
	 *              Fixed dupcheck log message and error message which reversed the email and person order.
	 * @since [version] Removed engagement debug logging.
	 *
	 * @param mixed[] $args {
	 *     An array of arguments from the triggering hook.
	 *
	 *     @type int        $0 WP_User ID.
	 *     @type int        $1 WP_Post ID of the email.
	 *     @type int|string $2 WP_Post ID of the related triggering post or an empty string for engagements with no related post.
	 * }
	 * @return bool|WP_Error Returns `true` on success or a WP_Error when the email has failed or is prevented.
	 */
	public function handle_email( $args ) {

		$person_id  = $args[0];
		$email_id   = $args[1];
		$related_id = $args[2];
		$meta_key   = '_email_sent';

		$msg = sprintf( __( 'Email #%1$d to user #%2$d triggered by %3$s', 'lifterlms' ), $email_id, $person_id, $related_id ? '#' . $related_id : 'N/A' );

		if ( $related_id ) {

			if ( in_array( get_post_type( $related_id ), llms_get_enrollable_status_check_post_types(), true ) && ! llms_is_user_enrolled( $person_id, $related_id ) ) {

				// User is no longer enrolled in the triggering post. We should skip the send.
				llms_log( $msg . ' ' . __( 'not sent due to user enrollment issues.', 'lifterlms' ), 'engagement-emails' );
				return new WP_Error( 'llms_engagement_email_not_sent_enrollment', $msg, $args );
			} elseif ( absint( $email_id ) === absint( llms_get_user_postmeta( $person_id, $related_id, $meta_key ) ) ) {

				// User has already received this email, don't send it again.
				llms_log( $msg . ' ' . __( 'not sent because of dupcheck.', 'lifterlms' ), 'engagement-emails' );
				return new WP_Error( 'llms_engagement_email_not_sent_dupcheck', $msg, $args );
			}
		}

		// Setup the email.
		$email = LLMS()->mailer()->get_email( 'engagement', compact( 'person_id', 'email_id', 'related_id' ) );
		if ( $email && $email->send() ) {

			if ( $related_id ) {
				llms_update_user_postmeta( $person_id, $related_id, $meta_key, $email_id );
			}

			llms_log( $msg . ' ' . __( 'sent successfully.', 'lifterlms' ), 'engagement-emails' );
			return true;
		}

		// Error sending email.
		llms_log( $msg . ' ' . __( 'not sent due to email sending issues.', 'lifterlms' ), 'engagement-emails' );
		return new WP_Error( 'llms_engagement_email_not_sent_error', $msg, $args );

	}

	/**
	 * Parse incoming hook / callback data to determine if an engagement should be triggered from a given hook
	 *
	 * @since [version]
	 *
	 * @param string $action Action hook name.
	 * @param array  $args   Array of arguments passed to the callback function.
	 * @return array {
	 *     An associative array of parsed data used to trigger the engagement.
	 *
	 *     @type string $trigger_type    The name of the engagement trigger. See `llms_get_engagement_triggers()` for a list of valid triggers.
	 *     @type int    $user_id         The WP_User ID of the user who the engagement is being awarded or sent to.
	 *     @type int    $related_post_id The WP_Post ID of a related post.
	 *  }
	 */
	private function parse_hook( $action, $args ) {

		$parsed = array(
			'trigger_type'    => null,
			'user_id'         => null,
			'related_post_id' => null,
		);

		// Verify that it's a supported hook.
		if ( ! in_array( $action, $this->get_trigger_hooks(), true ) ) {
			/**
			 * Allows 3rd parties to hook into the core engagement system by parsing data passed to the hook.
			 *
			 * @since Unknown
			 *
			 * @param array $parsed {
			 *     An associative array of parsed data used to trigger the engagement.
			 *
			 *     @type string $trigger_type    (Required) The name of the engagement trigger. See `llms_get_engagement_triggers()` for a list of valid triggers.
			 *     @type int    $user_id         (Required) The WP_User ID of the user who the engagement is being awarded or sent to.
			 *     @type int    $related_post_id (Optional) The WP_Post ID of a related post.
			 *  }
			 *  @param string $action The name of the hook which triggered the engagement.
			 *  @param array  $args   The original arguments provided by the triggering hook.
			 */
			return apply_filters(
				'lifterlms_external_engagement_query_arguments',
				$parsed,
				$action,
				$args
			);
		}

		// The user registration action doesn't have a related post id.
		$related_post_id = isset( $args[1] ) && is_numeric( $args[1] ) ? absint( $args[1] ) : '';

		$parsed['user_id']         = absint( $args[0] );
		$parsed['trigger_type']    = $this->parse_hook_find_trigger_type( $action, $related_post_id );
		$parsed['related_post_id'] = $related_post_id;

		return $parsed;

	}

	/**
	 * Get the engagement trigger type based on the action and related post id
	 *
	 * @since [version]
	 *
	 * @param string     $action          Name of the triggering action hook.
	 * @param int|string $related_post_id WP_Post ID of the related post or an empty string.
	 * @return string
	 */
	private function parse_hook_find_trigger_type( $action, $related_post_id ) {

		$trigger_type = false;

		switch ( $action ) {
			case 'llms_rest_student_registered':
			case 'lifterlms_created_person':
				$trigger_type = 'user_registration';
				break;

			case 'lifterlms_course_completed':
			case 'lifterlms_course_track_completed':
			case 'lifterlms_lesson_completed':
			case 'lifterlms_section_completed':
			case 'lifterlms_quiz_completed':
			case 'lifterlms_quiz_passed':
			case 'lifterlms_quiz_failed':
				$trigger_type = str_replace( 'lifterlms_', '', $action );
				break;

			case 'llms_user_added_to_membership_level':
			case 'llms_user_enrolled_in_course':
				$trigger_type = str_replace( 'llms_', '', get_post_type( $related_post_id ) ) . '_enrollment';
				break;

			case 'lifterlms_access_plan_purchased':
			case 'lifterlms_product_purchased':
				$trigger_type = str_replace( 'llms_', '', get_post_type( $related_post_id ) ) . '_purchased';
				break;
		}

		return $trigger_type;

	}

	/**
	 * Handles all actions that could potentially trigger an engagement
	 *
	 * It will fire or schedule the actions after gathering all necessary data.
	 *
	 * @since 2.3.0
	 * @since 3.11.0 Unknown.
	 * @since 3.39.0 Treat also `llms_rest_student_registered` action.
	 * @since [version] Major refactor to reduce code complexity.
	 *
	 * @return void
	 */
	public function maybe_trigger_engagement() {

		// Parse incoming hook data.
		$hook = $this->parse_hook( current_filter(), func_get_args() );

		// We need a user and a trigger to proceed, related_post is optional though.
		if ( ! $hook['user_id'] || ! $hook['trigger_type'] ) {
			return;
		}

		// Gather triggerable engagements matching the supplied criteria.
		$engagements = $this->get_engagements( $hook['trigger_type'], $hook['related_post_id'] );

		// Loop through the retrieved engagements and trigger them.
		foreach ( $engagements as $engagement ) {

			$handler = $this->parse_engagement( $engagement, $hook );
			$this->trigger_engagement( $handler, $engagement->delay );

		}

	}

	/**
	 * Parse engagement objects from the DB and return data needed to trigger the engagements
	 *
	 * @since [version]
	 *
	 * @param object $engagement   The engagement object from the `get_engagements()` query.
	 * @param array  $trigger_data Parsed hook data from `parse_hook()`.
	 * @return array {
	 *     An associative array of parsed data used to trigger the engagement.
	 *
	 *     @type string $handler_action Hook name of the action that will handle awarding the sending the engagement.
	 *     @type array  $handler_args   Arguments passed to the `$handler_action` callback.
	 *  }
	 */
	private function parse_engagement( $engagement, $trigger_data ) {

		$parsed = array(
			'handler_action' => null,
			'handler_args'   => null,
		);

		if ( ! in_array( $engagement->event_type, array_keys( llms_get_engagement_types() ), true ) ) {
			/**
			 * Enable 3rd parties to parse custom engagement types
			 *
			 * @since Unknown
			 *
			 * @param array $parsed {
			 *     An associative array of parsed data used to trigger the engagement.
			 *
			 *     @type string $handler_action (Required) Hook name of the action that will handle awarding the sending the engagement.
			 *     @type array  $handler_args   (Required) Arguments passed to the `$handler_action` callback.
			 * }
			 * @param object $engagement      The engagement object from the `get_engagements()` query.
			 * @param int    $user_id         WP_User ID who will be awarded the engagement.
			 * @param int    $related_Post_id WP_Post ID of the related post.
			 * @param string $event_type      The type of engagement event.
			 */
			return apply_filters(
				'lifterlms_external_engagement_handler_arguments',
				$parsed,
				$engagement,
				$trigger_data['user_id'],
				$trigger_data['related_post_id'],
				$engagement->event_type
			);
		}

		$parsed['handler_args'] = array(
			$trigger_data['user_id'],
			$engagement->engagement_id,
			$trigger_data['related_post_id'],
			absint( $engagement->trigger_id ),
		);

		/**
		 * @todo Fix this
		 *
		 * If there's no related post id we have to send one anyway for certs to work.
		 *
		 * This would only be for registration events @ version 2.3.0 so we pass the engagement_id twice until we find a better solution.
		 */
		if ( 'certificate' === $engagement->event_type && empty( $parsed['handler_args'][2] ) ) {
			$parsed['handler_args'][2] = $parsed['handler_args'][1];
		}

		$parsed['handler_action'] = sprintf(
			'lifterlms_engagement_%1$s_%2$s',
			'email' === $engagement->event_type ? 'send' : 'award',
			$engagement->event_type
		);

		return $parsed;

	}

	/**
	 * Triggers or schedules an engagement
	 *
	 * @since [version]
	 *
	 * @param array $data  Handler data from `parse_engagement()`.
	 * @param int   $delay The engagement send delay (in days).
	 * @return void
	 */
	private function trigger_engagement( $data, $delay ) {

		// Can't proceed without an action and a handler.
		if ( empty( $data['handler_action'] ) || empty( $data['handler_args'] ) ) {
			return;
		}

		// If we have a delay, schedule the engagement handler.
		$delay = absint( $delay );
		if ( $delay ) {

			as_schedule_single_action(
				time() + ( DAY_IN_SECONDS * $delay ),
				$data['handler_action'],
				array( $data['handler_args'] ),
				! empty( $data['handler_args'][3] ) ? $this->get_delayed_group_id( $data['handler_args'][3] ) : null
			);

		} else {

			do_action( $data['handler_action'], $data['handler_args'] );

		}

	}

	/**
	 * Unschedule all scheduled actions for a delayed engagement
	 *
	 * This is the callback function for deleted and trashed engagement posts
	 *
	 * @since [version]
	 *
	 * @param int          $post_id WP_Post ID.
	 * @param WP_Post|null $post_id Post object of the deleted post or `null` when the post was trashed.
	 * @return void
	 */
	public function unschedule_delayed_engagements( $post_id, $post = null ) {

		// During trash, there's no post object sent along.
		$post = empty( $post ) ? get_post( $post_id ) : $post;

		if ( is_a( $post, 'WP_Post' ) && 'llms_engagement' === $post->post_type ) {
			as_unschedule_all_actions( '', array(), $this->get_delayed_group_id( $post_id ) );
		}

	}

	/**
	 * Log debug data to the WordPress debug.log file
	 *
	 * @since 2.7.9
	 * @since 3.12.0 Unknown.
	 * @deprecated [version] Engagement debug logging is removed. Use `llms_log()` directly instead.
	 *
	 * @param mixed $log Data to write to the log.
	 * @return void
	 */
	public function log( $log ) {

		_deprecated_function( 'LLMS_Engagements::log()', '[version]', 'llms_log()' );

		if ( $this->debug ) {
			llms_log( $log, 'engagements' );
		}

	}

}
