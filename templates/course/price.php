<?php
/**
 * @author 		codeBOX
 * @package 	lifterLMS/Templates
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $post, $course;

LLMS_log('fdsafdsaf');
?>
<?php if ( ! llms_is_user_enrolled( get_current_user_id(), $course->id ) ) { ?>
	<?php if ( $course->get_price_html() ) { ?>

	<div class="llms-price-wrapper">

		<p class="llms-price">Price: <span class="length"><?php echo $course->get_price_html(); ?></span></p> 

	</div>

<?php }
 } ?>