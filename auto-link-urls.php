<?php
/*
 * Plugin Name: WP Journalize - Auto Link URLs
 * Plugin URI: 
 * Description: Automatically links any URLs in Journal Articles
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */

function journalize_auto_link_urls_activate() {
	$options = get_option('journalize');
	$options['auto_link_urls'] = true;
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_auto_link_urls_activate');


function journalize_auto_link_urls_deactivate() {
	$options = get_option('journalize');
	$options['auto_link_urls'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_auto_link_urls_deactivate');

?>