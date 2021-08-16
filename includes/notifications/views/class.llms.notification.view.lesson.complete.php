<?php
/**
 * Notification View: Lesson Complete
 *
 * @package LifterLMS/Notifications/Views/Classes
 *
 * @since 3.8.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notification View: Lesson Complete
 *
 * @since 3.8.0
 */
class LLMS_Notification_View_Lesson_Complete extends LLMS_Abstract_Notification_View {

	/**
	 * Settings for basic notifications
	 *
	 * @var array
	 */
	protected $basic_options = array(
		/**
		 * Time in milliseconds to show a notification
		 * before automatically dismissing it
		 */
		'auto_dismiss' => 10000,
		/**
		 * Enables manual dismissal of notifications
		 */
		'dismissible'  => true,
	);

	/**
	 * Notification Trigger ID
	 *
	 * @var string
	 */
	public $trigger_id = 'lesson_complete';

	/**
	 * Setup body content for output
	 *
	 * @since 3.8.0
	 *
	 * @return string
	 */
	protected function set_body() {
		if ( 'email' === $this->notification->get( 'type' ) ) {
			return sprintf( __( 'Congratulations! %1$s completed %2$s', 'lifterlms' ), '{{STUDENT_NAME}}', '{{LESSON_TITLE}}' );
		}
		$content  = sprintf( __( 'Congratulations! You finished %s', 'lifterlms' ), '{{LESSON_TITLE}}' );
		$content .= "\r\n\r\n{{COURSE_PROGRESS}}";
		return $content;
	}

	/**
	 * Setup footer content for output
	 *
	 * @since 3.8.0
	 *
	 * @return string
	 */
	protected function set_footer() {
		return '';
	}

	/**
	 * Setup notification icon for output
	 *
	 * @since 3.8.0
	 *
	 * @return string
	 */
	protected function set_icon() {
		return $this->get_icon_default( 'positive' );
	}

	/**
	 * Setup merge codes that can be used with the notification
	 *
	 * @since 3.8.0
	 *
	 * @return array
	 */
	protected function set_merge_codes() {
		return array(
			'{{COURSE_PROGRESS}}' => __( 'Course Progress Bar', 'lifterlms' ),
			'{{COURSE_TITLE}}'    => __( 'Course Title', 'lifterlms' ),
			'{{LESSON_TITLE}}'    => __( 'Lesson Title', 'lifterlms' ),
			'{{STUDENT_NAME}}'    => __( 'Student Name', 'lifterlms' ),
		);
	}

	/**
	 * Replace merge codes with actual values
	 *
	 * @since 3.8.0
	 * @since 3.10.1 Unknown.
	 * @since [version] Remove output of "you" when displaying notification to the receiving student.
	 *
	 * @param string $code The merge code to get merged data for.
	 * @return string
	 */
	protected function set_merge_data( $code ) {

		switch ( $code ) {

			case '{{COURSE_PROGRESS}}':
				$progress = $this->user->get_progress( $this->post->get( 'parent_course' ), 'course' );
				$code     = lifterlms_course_progress_bar( $progress, false, false, false );
				break;

			case '{{COURSE_TITLE}}':
				$course = $this->post->get_course();
				if ( $course ) {
					$code = $course->get( 'title' );
				} else {
					$code = '';
				}
				break;

			case '{{LESSON_TITLE}}':
				$code = $this->post->get( 'title' );
				break;

			case '{{STUDENT_NAME}}':
				$code = $this->user->get_name();
				break;

		}

		return $code;

	}

	/**
	 * Setup notification subject for output
	 *
	 * @since 3.8.0
	 *
	 * @return string
	 */
	protected function set_subject() {
		return sprintf( __( 'Congratulations! %1$s completed %2$s', 'lifterlms' ), '{{STUDENT_NAME}}', '{{LESSON_TITLE}}' );
	}

	/**
	 * Setup notification title for output
	 *
	 * @since 3.8.0
	 *
	 * @return string
	 */
	protected function set_title() {
		return sprintf( __( '%s Completed a Lesson', 'lifterlms' ), '{{STUDENT_NAME}}' );
	}

}
