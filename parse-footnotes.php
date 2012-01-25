<?php
/*
 * Plugin Name: WP Journalize - Parse Footnotes
 * Plugin URI: 
 * Description: Converts Footnotes from Word to SimpleFootnotes
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */
 
function journalize_parse_footnotes_activate() {
	$options = get_option('journalize');
	$options['parse_footnotes'] = true;
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_parse_footnotes_activate');


function journalize_parse_footnotes_deactivate() {
	$options = get_option('journalize');
	$options['parse_footnotes'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_parse_footnotes_deactivate');
