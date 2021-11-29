<?php
/**
 * Single certificate main content.
 *
 * @package LifterLMS/Templates/Certificates
 *
 * @since [version]
 * @version [version]
 *
 * @param LLMS_User_Certificate $certificate Certificate object.
 */

defined( 'ABSPATH' ) || exit;

?>
<div id="certificate-<?php the_ID(); ?>" <?php post_class( array( 'llms-certificate-container', 'cert-template-v2' ) ); ?>>

	<?php llms_print_notices(); ?>

	<?php
		/**
		 * Output content prior to the main content of a single certificate.
		 *
		 * @since Unknown.
		 * @since [version] Added the `$certificate` parameter.
		 *
		 * @param LLMS_User_Certificate $certificate Certificate object.
		 */
		do_action( 'before_lifterlms_certificate_main_content', $certificate );
	?>

	<?php echo llms_get_certificate_content(); ?>

	<?php
		/**
		 * Output content after to the main content of a single certificate.
		 *
		 * @since Unknown.
		 * @since [version] Added the `$certificate` parameter.
		 *
		 * @param LLMS_User_Certificate $certificate Certificate object.
		 */
		do_action( 'after_lifterlms_certificate_main_content', $certificate );
	?>

</div>
