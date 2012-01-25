<?php
/*
 * Plugin Name: WP Journalize - Paywall
 * Plugin URI: 
 * Description: Prevents non-logged in users from viewing content after a certain point
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */

function journaalize_paywall_activate() {
	$options = get_option('journalize');
	$options['paywall'] = true;
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_paywall_activate');


function journalize_paywall_deactivate() {
	$options = get_option('journalize');
	$options['paywall'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_paywall_deactivate');

?>