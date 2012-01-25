<?php
/*
 * Plugin Name: WP Journalize - Classic/Web Font Toggle
 * Plugin URI: 
 * Description: Changes journal article font and adds toggle widget
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */
 
function journalize_font_toggle_activate() {
	$options = get_option('journalize');
	$options['font_toggle'] = true;
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_font_toggle_activate');


function journalize_font_toggle_deactivate() {
	$options = get_option('journalize');
	$options['font_toggle'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_font_toggle_deactivate');
