<?php
/*
 * Plugin Name: WP Journalize - Table of Contents
 * Plugin URI: 
 * Description: Automatically parses table of contents from Word format
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */

function journalize_table_of_contents_activate() {
	$options = get_option('journalize');
	$options['table_of_contents'] = true;
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_table_of_contents_activate');


function journalize_table_of_contents_deactivate() {
	$options = get_option('journalize');
	$options['table_of_contents'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_table_of_contents_deactivate');

?>