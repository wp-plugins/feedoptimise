<?php
/*
Plugin Name: Feed Optimise
Plugin URI: https://www.feedoptimise.com/
Description: WooCommerce extension that allows your shop to directly integrate with <a target="_blank" href="https://www.feedoptimise.com/">feedoptimise.com</a> services.
Author: Feed Optimise
Version: 1.0.4
Author URI: https://www.feedoptimise.com/
License: GPLv3
*/


/**
 * Required functions
 **/
//if ( ! function_exists( 'is_woocommerce_active' ) ) require_once( 'libs/woo-functions.php' );


/**
 * Setting up endpoints
 **/
function woocommerce_feedoptimise_endpoints_and_codes() 
{
    add_rewrite_endpoint('woocommerce_feedoptimise', EP_ROOT );
    add_action('wp_enqueue_scripts','feedoptimise_enque_tags');
    add_action('wp_head', 'feedoptimise_tag_variables' );

    add_action('woocommerce_thankyou', 'feedoptimise_conversion_tag_variables');
}

add_action ( 'init', 'woocommerce_feedoptimise_endpoints_and_codes' );


function feedoptimise_conversion_tag_variables($order_id)
{
	$order 			= new WC_Order( $order_id );
	$order_total 	= $order->get_total();

	?>
	<script type="text/javascript">
	     var _fo = _fo || [];
	     _fo.push(["orderTotal","<?php echo $order_total ?>"]);
	     _fo.push(["orderId","<?php echo $order_id ?>"]);
	</script>
	<?php
}

function feedoptimise_tag_variables()
{
	$is_tag = esc_attr( get_option('feedoptimise_tag_container') );
	$cuid = (int) esc_attr( get_option('feedoptimise_cuid') );

	if($is_tag && $cuid)
	{
		echo '<script type="text/javascript">var _fo = _fo || [];_fo.push(["tagManager",1]);</script>';
	}
}

function feedoptimise_enque_tags()
{
	//settings_fields( 'feedoptimise-settings-group' );
	$cuid = (int) esc_attr( get_option('feedoptimise_cuid') );

	if($cuid)
	{
		wp_register_script(
		  'feedoptimise-click-tracker',
		  '//cdn.feedoptimise.com/fo.js#'.$cuid,
		  array(),
		  '2.1.8',
		  true
		);

		wp_enqueue_script(
		  'feedoptimise-click-tracker',
		  '//cdn.feedoptimise.com/fo.js#'.$cuid,
		  array(),
		  '2.1.8',
		  true
		);
	}
}

/**
 * Include the relevant files dependant on the page request type
 */
function woocommerce_feedoptimise_includes() {

    global $wp_query;

    if ( isset ( $wp_query->query_vars['woocommerce_feedoptimise'] ) ) {
        $_REQUEST['action'] = 'woocommerce_feedoptimise';
        $_REQUEST['key'] = $wp_query->query_vars['woocommerce_feedoptimise'];
    }

    if ( ( isset ( $_REQUEST['action'] ) && 'woocommerce_feedoptimise' == $_REQUEST['action'] ) ) {

        require_once ( 'woocommerce-feedoptimise-feed.php' );

        $woofofeed = new woocommerce_feedoptimise_feed();
        $woofofeed->render();

    }

}

add_action ( 'template_redirect', 'woocommerce_feedoptimise_includes');


// Add settings link on plugin page
function your_plugin_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=woocommerce-feedoptimise">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'your_plugin_settings_link' );

// create custom plugin settings menu
add_action('admin_menu', 'feedoptimise_create_menu');

function feedoptimise_create_menu() {

	//create new top-level menu
	add_options_page('FeedOptimise Settings', 'Feed Optimise', 'administrator', 'woocommerce-feedoptimise', 'feedoptimise_settings_page');

	//call register settings function
	add_action( 'admin_init', 'feedoptimise_register_settings' );
}


function feedoptimise_register_settings() {
	//register our settings
	register_setting( 'feedoptimise-settings-group', 'feedoptimise_cuid' );
	register_setting( 'feedoptimise-settings-group', 'feedoptimise_tag_container' );
}

function feedoptimise_settings_page() {
?>
<div class="wrap">
<h2>Feed Optimise Settings</h2>
<p style="background:#fff;padding:50px;border:1px solid #ddd;border-left:2px solid #F44336;font-size:1.3em;"><b>Please note</b><br /><br />In order to take advantage of this plugin you need to have an active account set at <a href="https://www.feedoptimise.com/" target="_blank">feedoptimise.com</a>. <br /><br />To setup your account <a href="https://www.feedoptimise.com/product-feeds-management-trial" target="_blank">sign up for the 30 days free trial</a> or <a href="https://www.feedoptimise.com/contact" target="_blank">contact us</a>.</p>


<form method="post" action="options.php">
    <?php settings_fields( 'feedoptimise-settings-group' ); ?>
    <?php do_settings_sections( 'feedoptimise-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Customer ID</th>
        <td><input type="text" name="feedoptimise_cuid" size="15" value="<?php echo esc_attr( get_option('feedoptimise_cuid') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Enable tag container</th>
        <td><input type="checkbox" name="feedoptimise_tag_container" size="15" value="on" <?php if(esc_attr( get_option('feedoptimise_tag_container') )): ?>checked<?php endif; ?> /></td>
        </tr>
    </table>
    
    <?php submit_button(); ?>

</form>

</div>
<?php } ?>