<?php
/*
 * Plugin Name: WP Journalize - Auto Tag Articles
 * Plugin URI: 
 * Description: Automatically tags journal articles
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */

function journalize_auto_tag_activate() {
	$options = get_option('journalize');
	$options['auto_tag'] = true;
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_auto_tag_activate');


function journalize_auto_tag_deactivate() {
	$options = get_option('journalize');
	$options['auto_tag'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_auto_tag_deactivate');

?>