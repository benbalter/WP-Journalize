<?php

if ( $_POST ) {

	$error = false;
	$creds = array();
	$creds['user_login'] = $_POST['login'];
	$creds['user_password'] = $_POST['password'];
	$creds['remember'] = true;
	$user = wp_signon( $creds, false );
	
	//see if they were able to login
	if ( is_wp_error($user) ) {
	    
	    //if there was an error, strip the tags (so we don't have a link to wp-login) and pass it on			 
	   	$msg = strip_tags( $user->get_error_message() );
	    $error = true;
	
	} else {
	
	    //Check to see if they have a target page
	    $redirect_to = apply_filters('login_redirect', $redirect_to, isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '', $user);
	    
	    //If they are not an author, don't let them in the backend
	    if ( !$user->has_cap('edit_posts') && ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) )
	    	$redirect_to = get_bloginfo('home');
	    	
	    //Redirect
	    wp_safe_redirect($redirect_to);
	}
}

get_header();
get_sidebar();

?>
<p>You must login to continue.</p>
<?php if ( isset( $msg ) )  { ?>
<div class="error">
	<?php echo $msg; ?>
</div>
<?php } ?>
<form method="post"> 
	<label for="login">Login:</label>
	<input type="text" name="login" id="login" /><br />
	
	<label for="password">Password:</label>
	<input type="password" name="password" id="password" />
	
	<input type="submit" name="submit" id="submit" value="Login" />
	
</form>
<?php
get_footer();
?>