<?php
/*
 * Plugin Name: WP Journalize - Paginate
 * Plugin URI: 
 * Description: Breaks long journal articles into multiple pages
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */

function journalize_paginate_activate() {
	$options = get_option('journalize');
	$options['paginate'] = '123';
	update_option('journalize', $options);
}

register_activation_hook(__FILE__, 'journalize_paginate_activate');


function journalize_paginate_deactivate() {
	$options = get_option('journalize');
	$options['paginate'] = false;
	update_option('journalize', $options);
}

register_deactivation_hook(__FILE__, 'journalize_paginate_deactivate');

?>