<?php
/*
 * Plugin Name: WP Journalize - Auto Link Tags
 * Plugin URI: 
 * Description: Automatically links tags to their tag page
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */

function journalize_tag_links_activate() {
	$options = get_option('journalize');
	$options['tag_links'] = true;
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_tag_links_activate');


function journalize_tag_links_deactivate() {
	$options = get_option('journalize');
	$options['tag_links'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_tag_links_deactivate');

?>