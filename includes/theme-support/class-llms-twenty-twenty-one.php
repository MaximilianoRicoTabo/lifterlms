<?php
/**
 * Theme Support: Twenty Twenty-One
 *
 * @package LifterLMS/ThemeSupport/Classes
 *
 * @since [version]
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Twenty_Twenty_One class..
 *
 * @since [version]
 */
class LLMS_Twenty_Twenty_One {

	/**
	 * Static "constructor"
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function init() {

		// This theme doesn't have a sidebar.
		remove_action( 'lifterlms_sidebar', 'lifterlms_get_sidebar', 10 );

		// Handle content wrappers.
		remove_action( 'lifterlms_before_main_content', 'lifterlms_output_content_wrapper', 10 );
		remove_action( 'lifterlms_after_main_content', 'lifterlms_output_content_wrapper_end', 10 );

		add_action( 'lifterlms_before_main_content', array( __CLASS__, 'handle_page_header_wrappers' ) );

		// Theme has no extra wrappers, add this class to the main list element to fix the layout.
		add_filter( 'llms_get_loop_list_classes', array( __CLASS__, 'add_max_width_class' ) );
		add_filter( 'llms_get_pagination_wrapper_classes', array( __CLASS__, 'add_pagination_classes' ) );

		// Modify catalog & checkout columns when the catalog page isn't full width.
		add_filter( 'lifterlms_loop_columns', array( __CLASS__, 'modify_columns_count' ) );
		add_filter( 'llms_checkout_columns', array( __CLASS__, 'modify_columns_count' ) );

		add_filter( 'navigation_markup_template', array( __CLASS__, 'maybe_disable_post_navigation' ) );

		// Use theme colors for various LifterLMS elements.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_inline_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'add_inline_editor_styles' ) );

	}

	/**
	 * Enqueue inline styles for the block editor.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function add_inline_editor_styles() {
		wp_add_inline_style( 'twenty-twenty-one-custom-color-overrides', self::generate_inline_styles( 'editor' ) );
	}

	/**
	 * Enqueue inline styles on the frontend
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function add_inline_styles() {
		wp_add_inline_style( 'twenty-twenty-one-style', self::generate_inline_styles() );
	}

	/**
	 * Adds the 2021 theme max-width class around the catalog loop element.
	 *
	 * This adds catalog theme support.
	 *
	 * @since [version]
	 *
	 * @param string[] $classes List of css classes.
	 * @return string[]
	 */
	public static function add_max_width_class( $classes ) {
		$classes[] = 'default-max-width';
		return $classes;
	}

	/**
	 * Add 2021 theme classes to the LLMS pagnation element
	 *
	 * Makes the pagination on catalogs look like the 2021 pagination on post type archives
	 *
	 * @since [version]
	 *
	 * @param string[] $classes List of css classes.
	 * @return string[]
	 */
	public static function add_pagination_classes( $classes ) {
		$classes[] = 'navigation';
		$classes[] = 'pagination';
		return $classes;
	}

	/**
	 * Generate inline CSS for a given context
	 *
	 * @since [version]
	 *
	 * @param string|null $context Inline CSS context. Accepts "editor" to define styles loaded within the block editor or `null` for frontend styles.
	 * @return string
	 */
	protected static function generate_inline_styles( $context = null ) {

		$selector_prefix = ( 'editor' === $context ) ? '.editor-styles-wrapper' : '';

		$styles = array();

		// Frontend only.
		if ( is_null( $context ) ) {

			// Fix alignment of content in an access plan.
			$styles[] = '.llms-access-plan-description ul { padding-left: 0; }';

			// Fix checkboxes.
			$styles[] = '.llms-form-field.type-checkbox input { width: 25px; display: inline-block; }';

			// Donuts
			$styles[] = '.llms-donut svg path { stroke: var( --global--color-secondary ); }';

			// Hide header/footer on certificate pages.
			$styles[] = '.single-llms_certificate .site-header, .single-llms_my_certificate .site-header, .single-llms_certificate .widget-area, .single-llms_my_certificate .widget-area { display: none; }';

			// Question layout.
			$styles[] = '.llms-question-wrapper ol.llms-question-choices li.llms-choice .llms-choice-text { width: calc( 100% - 110px); }';

			// Payment gateway stylized radio buttons.
			$styles[] = LLMS_Theme_Support::get_css(
				array( '.llms-form-field.type-radio input[type=radio]:checked+label:before' ),
				array(
					'background-image' => '-webkit-radial-gradient(center,ellipse,var( --global--color-secondary ) 0,var( --global--color-secondary ) 40%,#fafafa 45%)',
					'background-image' => 'radial-gradient(ellipse at center,var( --global--color-secondary ) 0,var( --global--color-secondary ) 40%,#fafafa 45%)',
				)
			);

		}

		// Fix anchor buttons.
		$styles[] = 'a.llms-button-action, a.llms-button-danger, a.llms-button-primary, a.llms-button-secondary { display: inline-block; }';

		// Elements with a light background that become unreadable in darkmode.
		$styles[] = LLMS_Theme_Support::get_css(
			array(
				'.llms-notification',
				'.llms-table tbody tr:nth-child(odd) td',
				'.llms-table tbody tr:nth-child(odd) td a',
				'.llms-certificate-container',
				'a.llms-certificate',
				'.llms-instructor-info',
				'.llms-achievement-loop-item.achievement-item',
				'.llms-achievement',
			),
			array(
				'color' => 'var( --global--color-background )',
			),
			$selector_prefix
		);
		$styles[] = LLMS_Theme_Support::get_css(
			array(
				'.llms-checkout',
				'.llms-access-plan .llms-access-plan-footer',
				'.llms-access-plan .llms-access-plan-content',
			),
			array(
				'background-color' => 'var( --global--color-background )',
			),
			$selector_prefix
		);

		// Add background color to qualifying elements.
		$styles[] = LLMS_Theme_Support::get_css(
			LLMS_Theme_Support::get_selectors_primary_color_background(),
			array(
				'color' => 'var( --global--color-background )',
				'background-color' => 'var( --global--color-secondary )',
			),
			$selector_prefix
		);

		// Add background color to qualifying elements.
		$styles[] = LLMS_Theme_Support::get_css(
			LLMS_Theme_Support::get_selectors_primary_color_border(),
			array(
				'border-color' => 'var( --global--color-secondary )',
			),
			$selector_prefix
		);

		// Add background color to qualifying elements.
		$styles[] = LLMS_Theme_Support::get_css(
			LLMS_Theme_Support::get_selectors_primary_color_text(),
			array(
				'color' => 'var( --global--color-secondary )',
			),
			$selector_prefix
		);

		return implode( "\r", $styles );

	}

	/**
	 * Handle wrapping the catalog page header in 2021 theme elements.
	 *
	 * This method determines if the catalog title are to be displayed and adds additional actions
	 * which will wrap the elements in 2021 theme elements depending on what is meant to be displayed.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function handle_page_header_wrappers() {

		$show_title = apply_filters( 'lifterlms_show_page_title', true );

		if ( $show_title ) {
			add_action( 'lifterlms_before_main_content', array( __CLASS__, 'page_header_wrap' ), 11 );
			add_action( 'lifterlms_archive_description', array( __CLASS__, 'page_header_wrap_end' ), 99999999 );
		}

		if ( $show_title && ! empty( lifterlms_get_archive_description() ) ) {
			add_action( 'lifterlms_archive_description', array( __CLASS__, 'output_archive_description_wrapper' ), -1 );
			add_action( 'lifterlms_archive_description', array( __CLASS__, 'output_archive_description_wrapper_end' ), 99999998 );
		}

	}

	/**
	 * Modify the number of catalog & checkout columns.
	 *
	 * @since [version]
	 *
	 * @param int $cols Number of columns.
	 * @return int
	 */
	public static function modify_columns_count( $cols ) {
		return 1;
	}

	/**
	 * Disable 2021 theme post navigation on LifterLMS post types
	 *
	 * @since [version]
	 *
	 * @param string $html Post navigation HTML.
	 * @return string
	 */
	public static function maybe_disable_post_navigation( $html ) {

		if ( in_array( get_post_type(), array( 'course', 'llms_membership', 'lesson', 'llms_quiz', 'llms_assignment', 'llms_group' ), true ) ) {
			return '';
		}
		return $html;

	}

	/**
	 * Output the catalog archive description 2021 theme wrapper opener
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function output_archive_description_wrapper() {
		echo '<div class="archive-description">';
	}

	/**
	 * Output the catalog archive description 2021 theme wrapper closer
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function output_archive_description_wrapper_end() {
		echo '</div><!-- .archive-description -->';
	}

	/**
	 * Output the catalog page header 2021 theme wrapper opener
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function page_header_wrap() {
		echo '<header class="page-header alignwide">';
	}

	/**
	 * Output the catalog page header 2021 theme wrapper closer
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public static function page_header_wrap_end() {
		echo '</header><!-- .page-header -->';
	}

}

return LLMS_Twenty_Twenty_One::init();
