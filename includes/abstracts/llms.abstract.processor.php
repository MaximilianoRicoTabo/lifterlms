<?php
/**
 * Background Processor abstract
 *
 * @package LifterLMS/Abstracts/Classes
 *
 * @since 3.15.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Background Processor abstract class
 *
 * @since 3.15.0
 */
abstract class LLMS_Abstract_Processor extends WP_Background_Process {

	/**
	 * Prefix
	 *
	 * @var string
	 */
	protected $prefix = 'llms';

	/**
	 * Unique identifier for the processor
	 *
	 * @var  string
	 */
	protected $id;

	/**
	 * Initializer
	 *
	 * Acts as a constructor that extending processors should implement
	 * at the very least should populate the $this->actions array
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	abstract protected function init();

	/**
	 * Array of actions that should be watched to trigger
	 * the process(es)
	 *
	 * @var  array
	 */
	protected $actions = array();

	/**
	 * Constructor
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function __construct() {

		$this->action .= '_' . $this->id;

		parent::__construct();

		// Setup.
		$this->init();

		// Add trigger actions.
		$this->add_actions();

	}

	/**
	 * Add actions defined in $this->actions
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function add_actions() {

		foreach ( $this->get_actions() as $action => $data ) {

			$data = wp_parse_args(
				$data,
				array(
					'arguments' => 1,
					'priority'  => 10,
				)
			);

			add_action( $action, array( $this, $data['callback'] ), $data['priority'], $data['arguments'] );

		}

	}

	/**
	 * Disable a processor
	 *
	 * Useful when bulk enrolling into a membership (for example)
	 * so we don't trigger course data calculations a few hundred times.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public function disable() {

		remove_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
		foreach ( $this->get_actions() as $action => $data ) {

			$data = wp_parse_args(
				$data,
				array(
					'arguments' => 1,
					'priority'  => 10,
				)
			);

			remove_action( $action, array( $this, $data['callback'] ), $data['priority'], $data['arguments'] );

		}

	}

	/**
	 * Dispatch
	 *
	 * Overrides the parent method to reset the (saved) `$data` property and
	 * prevent duplicate data being pushed into future batches.
	 *
	 * @since [version]
	 *
	 * @return array|WP_Error Result of wp_remote_post()
	 */
	public function dispatch() {

		// Perform the parent method.
		$ret = parent::dispatch();

		/**
		 * Empty the (saved) data to prevent duplicate data in future batches.
		 *
		 * @link https://github.com/gocodebox/lifterlms/issues/1602
		 */
		$this->data = array();

		return $ret;

	}

	/**
	 * Retrieve a filtered array of actions to be added by $this->add_actions
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function get_actions() {

		return apply_filters( 'llms_data_processor_' . $this->id . '_actions', $this->actions, $this );

	}

	/**
	 * Retrieve data for the current processor that can be used
	 * in future processes
	 *
	 * @since 3.15.0
	 *
	 * @param string $key     If set, return a specific piece of data rather than the whole array.
	 * @param string $default When returning a specific piece of data, allows a default value to be passed.
	 * @return array|mixed
	 */
	public function get_data( $key = null, $default = '' ) {

		// Get the array of processor data.
		$all_data = get_option( 'llms_processor_data', array() );

		// Get data for current processor.
		$data = isset( $all_data[ $this->id ] ) ? $all_data[ $this->id ] : array();

		// Get a specific piece of data.
		if ( $key ) {
			return isset( $data[ $key ] ) ? $data[ $key ] : $default;
		}

		// Return all the data.
		return $data;

	}

	/**
	 * Log data to the processors log when processors debugging is enabled
	 *
	 * @param mixed $data  Data to log.
	 * @return void
	 * @since 3.15.0
	 */
	protected function log( $data ) {

		if ( defined( 'LLMS_PROCESSORS_DEBUG' ) && LLMS_PROCESSORS_DEBUG ) {
			llms_log( $data, 'processors' );
		}

	}

	/**
	 * Persist data to the database related to the processor
	 *
	 * @since 3.15.0
	 *
	 * @param array $data Data to save.
	 * @return void
	 */
	private function save_data( $data ) {

		// Merge the current data with all processor data.
		$all_data = wp_parse_args(
			array(
				$this->id => $data,
			),
			get_option( 'llms_processor_data', array() )
		);

		// Save it.
		update_option( 'llms_processor_data', $all_data );

	}

	/**
	 * Update data to the database related to the processor
	 *
	 * @since 3.15.0
	 *
	 * @param string $key   Key name.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set_data( $key, $value ) {

		// Get the array of processor data.
		$data         = $this->get_data();
		$data[ $key ] = $value;

		$this->save_data( $data );

	}

	/**
	 * Delete a piece of data from the database by key
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Key name to remove.
	 * @return void
	 */
	public function unset_data( $key ) {

		$data = $this->get_data();
		if ( isset( $data[ $key ] ) ) {
			unset( $data[ $key ] );
		}

		$this->save_data( $data );

	}


}
