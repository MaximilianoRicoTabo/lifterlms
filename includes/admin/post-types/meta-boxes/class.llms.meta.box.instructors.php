<?php
/**
 * Automation Creation Interface
 * @since   [version]
 * @version [version]
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LLMS_Metabox_Instructors extends LLMS_Admin_Metabox {

	/**
	 * Configure the metabox
	 * @return  void
	 * @since   [version]
	 * @version [version]
	 */
	public function configure() {

		$this->id = 'llms-instructors';
		$this->title = __( 'Instructors', 'lifterlms' );
		$this->screens = array( 'course', 'llms_membership' );
		$this->capability = 'publish_courses';

	}

	/**
	 * Define metabox fields
	 * @return  array
	 * @since   [version]
	 * @version [version]
	 */
	public function get_fields() {

		return array(
			array(
				'title' => __( 'Instructors', 'lifterlms' ),
				'fields' => array(
					array(
						'button' => array(
							'text' => __( 'Add Instructor', 'lifterlms' ),
						),
						'handler' => 'instructors_mb_store',
						'header' => array(
							'default' => __( 'New Instructor', 'lifterlms' ),
						),
						'id' => $this->prefix . 'instructors_data',
						'label' => '',
						'type' => 'repeater',
						'fields' => array(
							array(
								'allow_null' => false,
								'data_attributes' => array(
									'roles' => 'administrator,lms_manager,instructor,instructors_assistant',
								),
								'class' => 'llms-select2-student',
								'group' => 'd-2of3',
								'id' => $this->prefix . 'id',
								'type' => 'select',
								'label' => __( 'Instructor', 'lifterlms' ),
							),
							array(
								'group' => 'd-1of6',
								'class' => 'input-full',
								'default' => __( 'Author', 'lifterlms' ),
								'id' => $this->prefix . 'label',
								'type' => 'text',
								'label' => __( 'Label', 'lifterlms' ),
							),
							array(
								'allow_null' => false,
								'class' => 'llms-select2',
								'group' => 'd-1of6',
								'id' => $this->prefix . 'visibility',
								'type' => 'select',
								'label' => __( 'Visibility', 'lifterlms' ),
								'value' => array(
									'visible' => esc_html__( 'Visible', 'lifterlms' ),
									'hidden' => esc_html__( 'Hidden', 'lifterlms' ),
								),
							),
						),
					),
				),
			),
		);

	}

	/**
	 * Empty save function prevents repeater field from saving empty meta field
	 * @param    int     $post  WP_Post ID
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function save( $post ) {}

}

return new LLMS_Metabox_Instructors();
