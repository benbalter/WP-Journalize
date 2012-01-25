<?php
/*
 * Plugin Name: WP Journalize
 * Plugin URI:
 * Description: Plugin for publishing scholarly journals
 * Version: 0.1
 * Author: Benjamin J. Balter
 * Author URI: http://ben.balter.com/
 */

class journalize {

	private $paras_per_page = '15';			//# of paragraphs per page
	private $paywall_page = '2'; 			//Page # of paywall
	private $post_type = 'journal_article';	//Name of CPT
	private $auto_tag_times = -1;			//# of times to link tags in text, -1 for all, 0 for none
	private $font = "'Courier New', Courier, monospace"; //Default font for journal articles
	
	/**
	 * Get journalize options
	 * @since 0.1
	 * @returns array array of options
	 */
	function get_options() {
		return get_option('journalize');
	}
	
	/**
	 * Init array, adds activation hooks
	 * @since 0.1
	 */
	function journalize() {
	
		//grab options
		$options = $this->get_options();
		
		//plugin-wide hooks	
		add_action('init', array( &$this, 'register_CPT_and_CT' ) );	
		add_filter('init', array( &$this, 'inject_rules' ) );
		add_action( 'post_type_link', array(&$this,'permalink'), 10, 4 );
		add_filter('init', array( &$this, 'set_journal_font' ) );
		add_action( 'wp_head', array(&$this, 'add_style' ) );
		add_filter('rewrite_rules_array', array(&$this, 'add_login_rewrite') );
		wp_register_sidebar_widget( 'journal_issues', 'Journal Issues', array(&$this, 'issue_widget') );
        
        //toggle-specific hook via their own plugin activation
        
    	if ( isset($options['paginate']) && $options['paginate'] )
			add_action( 'loop_start', array(&$this,'paginate'), 1 );
		
		if ( isset($options['paywall']) &&  $options['paywall'] ) {
			add_action( 'template_redirect', array(&$this, 'paywall') );
			add_action( 'template_redirect', array(&$this, 'login_intercept') );
			add_filter( 'query_vars', array(&$this, 'add_query_var' ) );	
		}
		
		if ( isset($options['parse_footnotes']) &&  $options['parse_footnotes'] )
			add_filter( 'content_save_pre', array(&$this, 'parse_footnotes' ), 1 );
		
		if ( isset($options['tag_links']) &&  $options['tag_links'] ) 
			add_filter( 'the_content', array(&$this, 'tag_links' ) );
		
		if ( isset($options['auto_tag']) &&  $options['auto_tag'] )
			add_action( 'save_post', array(&$this, 'auto_tag' ) );

		if ( isset($options['table_of_contents']) && $options['table_of_contents'] ) {
			add_action( 'content_save_pre', array(&$this, 'parse_toc' ), 1 );
			add_shortcode( 'TOC', array( &$this, 'print_toc' ) );
		}
		
		if ( isset($options['link_urls']) &&  $options['link_urls'] )
			add_action( 'content_save_pre', array(&$this, 'link_urls' ), 1 );
		
		if (  isset($options['font_toggle']) && $options['font_toggle'] ) 
			wp_register_sidebar_widget('font_widget', 'Font Picker', array($this, 'font_widget' ) );
		
		
		//let there be jQuery
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery.cookie',  plugins_url('/jquery.cookie.js', __FILE__), array('jquery'), filemtime( dirname(__FILE__) . '/jquery.cookie.js' ), true );
		
		//shortcode bugfix, see http://core.trac.wordpress.org/ticket/8553
		ini_set('pcre.backtrack_limit', 1000000);
		
		//activation hook to flush rules
		register_activation_hook(__FILE__, array( &$this, 'flush_rules' ) );
	}
	
	/**
	 * Tell WP of our new permalink structure
	 * @since 0.1
	 */
	function inject_rules(){
	
		global $wp_rewrite;
    	$rw_structure = '/%journal_issue%/%journal_issue2%/%journal_article%/';
    	$wp_rewrite->add_rewrite_tag("%journal_issue%", '(vol-[0-9]+)', "journal_issue=");
    	$wp_rewrite->add_rewrite_tag("%journal_issue2%", '(num-[0-9]+)', "journal_issue=");
    	$wp_rewrite->add_rewrite_tag("%journal_article%", '([^/]+)', $this->post_type . "=");
    	$wp_rewrite->add_permastruct($this->post_type, $rw_structure, false);  
    	      
	}
	
	/**
	 * Flushes rewrite rules on activation
	 * @since 0.1
	 */
	function flush_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
	
	/**
	 * Forces our rewrite structure
	 * @param $link string original link
	 * @param $post object post object
	 * @return string url
	 * @since 0.1
	 */	
	function permalink( $link, $post, $leavename, $sample ){
	
		//if this isn't our post type, kick
		if( $post->post_type != $this->post_type)
		   return $link;
		
		//what are we replacing
		$rewritecode = array(
		  '%journal_issue%',
		  '%journal_issue2%',
		  '%journal_article%'
		);
		
		//get the values
		$issue = wp_get_post_terms($post->ID, 'journal_issue');
		$parent = get_term($issue[0]->parent, 'journal_issue');
		
		//assign to replacements
		$replace_array = array(
		  $parent->slug,
		  $issue[0]->slug,
		  $post->post_name,
		);
		
		//replace and return
		return str_replace($rewritecode, $replace_array, $link);
		
	}
	
	/**
	 * Registers our custom post types and taxonomies
	 * @since 0.1
	 */    		
	function register_CPT_and_CT() {
		
  		//Custom post type labels array
  		$labels = array(
  		  'name' => 'Articles',
  		  'singular_name' => 'Article',
  		  'add_new' => 'Add New Article',
  		  'add_new_item' => 'Add Article',
  		  'edit_item' => 'Edit Article',
  		  'new_item' => 'New Article',
  		  'view_item' => 'View Article',
  		  'search_items' => 'Search Articles',
  		  'not_found' =>  'No Articles Found',
  		  'not_found_in_trash' => 'No Articles Found in Trash',
  		  'parent_item_colon' => ''
  		);
  		
  		//Custom post type settings array
  		$args = array(
  		  'labels' => $labels,
  		  'public' => true,
  		  'publicly_queryable' => true,
  		  'show_ui' => true, 
  		  'query_var' => true,
  			'rewrite' => false,
  		  'capability_type' => 'post',
  		  'hierarchical' => false,
  		  'menu_position' => null,
  		  'has_archive' => true,
  		  'supports' => array( 'title', 'editor', 'author', 'revisions', 'custom-fields', 'excerpt', 'trackbacks', 'comments'),
  		  'taxonomies' => array('category', 'post_tag', 'journal_issue', 'article_type'),
  		); 
  		
  		//Register the "wp_resume_position" custom post type
  		register_post_type( $this->post_type, $args );
  	
  		//Section labels array
		 $labels = array(
 		   'name' => _x( 'Issues', 'taxonomy general name' ),
 		   'singular_name' => _x( 'Issue', 'taxonomy singular name' ),
 		   'search_items' =>  __( 'Search Issues' ),
 		   'all_items' => __( 'All Issues' ),
 		   'parent_item' => __( 'Parent Issue' ),
 		   'parent_item_colon' => __( 'Parent Issue:' ),
 		   'edit_item' => __( 'Edit Issue' ), 
 		   'update_item' => __( 'Update Issue' ),
 		   'add_new_item' => __( 'Add New Issue' ),
 		   'new_item_name' => __( 'New Issue Name' ),
 		 ); 	
 		 
		//Register section taxonomy	
		register_taxonomy( 'journal_issue', $this->post_type, array( 'hierarchical' => true, 'labels' => $labels,  'query_var' => true, 'rewrite' => array('slug'=> null,  'hierarchical' => true ) ) );
  		
  		//Section labels array
		 $labels = array(
 		   'name' => _x( 'Types', 'taxonomy general name' ),
 		   'singular_name' => _x( 'Type', 'taxonomy singular name' ),
 		   'search_items' =>  __( 'Search Types' ),
 		   'all_items' => __( 'All Types' ),
 		   'parent_item' => __( 'Parent Type' ),
 		   'parent_item_colon' => __( 'Parent Type:' ),
 		   'edit_item' => __( 'Edit Type' ), 
 		   'update_item' => __( 'Update Type' ),
 		   'add_new_item' => __( 'Add New Type' ),
 		   'new_item_name' => __( 'New Type Name' ),
 		 ); 	
 		 
		//Register section taxonomy	
		register_taxonomy( 'article_type', $this->post_type, array( 'hierarchical' => true, 'labels' => $labels,  'query_var' => true, 'rewrite' => false ) );
  				
	}
	
	/**
	 * Forces pagination after N paragraphs
	 * @since 0.1
	 */
	function paginate() {
		global $post;
		
		//if this is not our post type, kick
		if( $post->post_type != $this->post_type)
			return;
	
		//make array of paragraphs
		$paras = explode('<p>', wpautop( $post->post_content) );
		
		//add nextpage tag after every N paragraphs
		$i = 1;
		while ($i < sizeof($paras) ) {
			if ( ( $i % ($this->paras_per_page+1) )  == 0) {
				array_splice($paras, $i, 0, '<!--nextpage-->');
				$i++;
			}
			$i++;
		}
		
		//convert back to string and return
		$post->post_content = implode('<p>', $paras);

	}
	
	/**
	 * Inforces a paywall on view
	 * @since 0.1
	 */
	function paywall() {
		global $post;
		global $page;
		
		//if this is not our post type or this is not a single post, kick
		if( !isset($post) || $post->post_type != $this->post_type || !is_single() )
			return;
		
		//if the visitor is a spider, let them pass
		if ( $this->is_spider() )
			return;
		
		//if the user is over the limit and is not logged in, redirect them
		if ($page >= $this->paywall_page && !current_user_can('read') )
			wp_redirect( get_bloginfo('url') . '/login/?redirect_to=' . $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Checks user agent to see if they are a spider
	 * @sine 0.1
	 * @return bool true if spider, false if not
	 */
	function is_spider() { 
	
		//list of spiders to check 
		$spiders = array( 'Googlebot', 'Yahoo', 'Slurp', 'msnbot', 'ia_archiver', 'Lycos', 'AltaVista', 'Teoma', 'Googlebot-Mobile', 'Bing' );  
		
		//loop through each spider in our list
		foreach ($spiders as $spider) {  
			
			//If the spider name is in the user agent string, return true
			if ( eregi($spider, $_SERVER['HTTP_USER_AGENT'] ) ) 
				return true;  
				
  		}
  		
  		//we must not have matched anything, return false
		return false;  
		
	}  

	/**
	 * Parses Word footnotes into SimpleFootnotes
	 * @since 0.1
	 * @param string $content the post content
	 * @return string content with footnotes
	 */
	function parse_footnotes( $content ) {
		global $post;
	
		//if this is not our post type, kick
		if ($post->post_type != $this->post_type)
			return $content;
	
		//if we have already parsed, kick
		if ( get_post_meta($post->ID, 'parsed_footnotes') )
			return $content;
	
		$content = stripslashes($content);

		//grab all the Word-style footnotes into an array
		$pattern = '#\<a href\="\#_ftnref([0-9]+)"\>\[([0-9]+)\]\</a\> (.*)#';
		preg_match_all( $pattern, $content, $footnotes, PREG_SET_ORDER);
		
		//build find and replace arrays
		foreach ($footnotes as $footnote) {
			$replace[] = '[ref]' . str_replace( array("\r\n", "\r", "\n"), "", $footnote[3]) . '[/ref]';
			$find[] = '#\<a href\="\#_ftn'.$footnote[1].'"\>\['.$footnote[1].'\]\</a\>#';
		}
		
		//remove all the original footnotes when done
		$find[] = '#\<div\>\s*<a href\="\#_ftnref([0-9]+)"\>\[([0-9]+)\]\</a\> (.*)\s*\</div\>\s+#';
		$replace[] = '';
		
		//make the switch
		$content = preg_replace( $find, $replace, $content );
		
		//add meta so we know it has been parsed
		add_post_meta($post->ID,'parsed_footnotes', true, true);
		
		return addslashes($content);
	}

	/**
	 * Automatically links all tags
	 * Based on plugin at http://linux.rushcj.com/post/wordpress-plugin-automatic-tag-to-link/
	 * @param string $p the post
	 * @return string $p the post, tagged
	 * @since 0.1
	 */
	function tag_links( $p ){
		global $post;
					
		//verify post type
		if ($post->post_type != $this->post_type)
			return $post->post_content;
		
		//get tags array
		$tags = wp_get_post_tags( $post->ID );
	
		//if there are no tags, don't waste time
		if( $tags == null ) 
			return $p;
		
		//loop tags and replace
		foreach($tags as $tag){
			$pattern='/(?<!(<a |<im))(\b'.preg_quote($tag->name).'\b)(?![^<]+(<\/a>|>))/i';
			$replace='<a href="'.get_tag_link($tag->term_id).'" rel="tag" class="inline-tag-link tag-link-'.$tag->term_id.'" title="'.$tag->count.' post';
			if ($tag->count != 1) $replace .= "s";
			$replace .= '">$0</a>';
			$p=preg_replace( $pattern, $replace, $p, $this->auto_tag_times );
		}		
		 
		return $p;
	}		
	
	/**
	 * Automatically tags journal articles with all appropriate tags
	 *  @since 0.1
	 */
	function auto_tag() {
		global $post;
		$post_tags = array();
		
		//check meta if already tagged
		if ( get_post_meta($post->ID, 'autotagged') )
			return;

		//verify post type
		if ($post->post_type != $this->post_type)
			return;
		
		//if the tag is in the content, tag the post
		foreach ( get_tags( array( 'hide_empty'=>false ) ) as $tag ){
			if ( strpos($post->post_content, $tag->name ) != FALSE )
				$post_tags[] = $tag->slug;
		}
		
		//set terms
		wp_set_object_terms($post->ID, $post_tags, 'post_tag', true);

		//set meta flag
		add_post_meta($post->ID,'autotagged', true, true);

	}
	
	/**
	 * Reads font preference cookie and updates font if necessary
	 * @since 0.1
	 */
	function set_journal_font() {
		if ( isset( $_COOKIE[$this->post_type . '_font'] ) )
			$this->font = stripslashes( $_COOKIE[$this->post_type . '_font'] );
	}
	
	/**
	 * Inject journal specific CSS into header
	 * @since 0.1
	 */
	function add_style() { 
	$options = $this->get_options();
	?>
		<style>
			.<?php echo $this->post_type; ?> {font-family: <?php echo $this->font; ?>;}
			#content .<?php echo $this->post_type; ?> h3, .<?php echo $this->post_type; ?>  h4, .<?php echo $this->post_type; ?> h5, .<?php echo $this->post_type; ?> h6 {font-weight: bold;}
			#content .<?php echo $this->post_type; ?>  h4 {font-size: 17px; margin-left: 40px;}
			#content .<?php echo $this->post_type; ?>  h5 {font-size: 15px; margin-left: 80px; }
			#content .<?php echo $this->post_type; ?>  h6 {font-size: 15px; margin-left: 120px;}			
<?php if (isset($options['tag_links']) && $options['tag_links'] ) { ?>
			.<?php echo $this->post_type; ?> .inline-tag-link, .<?php echo $this->post_type; ?> .inline-tag-link:visited {color:#555; text-decoration: none;}
			.<?php echo $this->post_type; ?> .inline-tag-link:hover {text-decoration: underline;}
<?php } ?>
<?php if (isset($options['table_of_contents']) && $options['table_of_contents'] ) { ?>
			.<?php echo $this->post_type; ?> #toc a, .<?php echo $this->post_type; ?> #toc a:visited {color: #444; text-decoration: none;}
			.<?php echo $this->post_type; ?> #toc {margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px;}
			.<?php echo $this->post_type; ?> #toc a:hover {text-decoration: underline;}
			.<?php echo $this->post_type; ?> .toc3 {}
			.<?php echo $this->post_type; ?> .toc4 {margin-left: 20px;} 
			.<?php echo $this->post_type; ?> .toc5 {margin-left: 40px;}
			.<?php echo $this->post_type; ?> .toc6 {margin-left: 60px;}
			.<?php echo $this->post_type; ?> .tocToggle {color: #aaa; margin-bottom: 20px; font-size: 12px;}			.<?php echo $this->post_type; ?> .tocToggle a, .<?php echo $this->post_type; ?> .tocToggle a:visited {color: #aaa; text-decoration: none;}
			.<?php echo $this->post_type; ?> .tocToggle a:hover {text-decoration: underline;}
<?php } ?>
		</style>
		
	<?php }
	
	/**
	 * Parses Word-type TOC into jounnal-type TOC
	 * @since 0.1
	 * @param string $content the post content
	 * @return string the parsed content
	 */
	function parse_toc( $content ) {
		global $post;
		
		//verify post type
		if ($post->post_type != $this->post_type)
			return $content;
		
		//verify meta flag
		if ( get_post_meta($post->ID, 'parsed_toc') )
			return $content;
			
		//parse the original TOC to grab headlines and hierarchy
		//matches: 0 - whole heading (no ...s or #s), 1 - numeral, 2 - heading text
		$pattern = "/^([a-z0-9]+)\. (.*?)(?=\.{4,})/im";
		preg_match_all( $pattern, $content, $matches );
		
		//Map outline numbering to <H{n}> tags
		$mapping = array(	'IVX' => '3',
		    		'A-Z' => '4',		
		    		'ivclcdm' => '5',
		    		'a-z' => '6'
		    	);
		
		//build a regex search out of each headline and a matching replacement array
		$headings = array();
		$replacements = array();
		foreach ($matches[0] as $ID => $heading) {
			$headings[] = '/' . preg_quote($heading, '/') . '\s/';
			
			foreach ($mapping as $patten=>$h) {
				if (preg_match('/^(['.$patten.']*)$/', $matches[1][$ID]) != 0) 
					break;
			}
			
			$replacements[] = '<h' . $h . ' id="'.sanitize_title_with_dashes($heading).'" class="toc">' . trim($heading) . '</h' . $h . '>';
		}
		
		//Replace headlines throughout document
		$content = preg_replace($headings,$replacements, $content);
		
		//kill Original TOC, replace with shortcode
		$pattern = '/Table of Contents(.*)'. preg_quote(end($matches[0])) . '(\.{4,}) ([0-9]*)/s';
		$content = preg_replace($pattern,'[TOC]', $content, 1);
		
		add_post_meta($post->ID,'parsed_toc', true, true);
										
		return $content;
	}
	
	/**
	 * Given properly formatted post, parses and creates TOC
	 * @since 0.1
	 */
	function print_toc() {
		global $post;
		
		//only show on since, not on index pages
		if ( !is_single() )
			return;
		
		//match all headings into array
		//1 - h#, 2 - id, 3, text
		$pattern = '/\<h([1-9]) id="([^\"]+)" class="toc"\>([^\<]+)\<\/h([1-9])\>|(\<\!--nextpage--\>)/is';
		preg_match_all($pattern,$post->post_content,$matches);
		?>
		<div class="tocToggle">[<a href="#" id="toggleTOC">Show Table of Contents</a>]</div>
		<div id="toc"; ?>
		<h3>Table of Contents</h3>
		<?php
		$page = 1;
		foreach ($matches[3] as $ID => $match) { 
		
			//calculate pagination
			if ($matches[5][$ID] == "<!--nextpage-->"){
				$page++;
				continue;
			}
		?>
			<div class="toc<?php echo $matches[1][$ID]; ?>">
				<a href="<?php echo ($page != 1) ? "$page/" : ''; ?>#<?php echo $matches[2][$ID]; ?>">
					<?php echo $match; ?>
				</a>
			</div>
		<?php 
		}
		?>
		</div>
		<script>
			jQuery(document).ready(function($){
				$('#toc').hide();
				$('#toggleTOC').click(function(){
					$('#toc').slideToggle();
					if ( $(this).text() == 'Show Table of Contents' )
						$(this).text('Hide Table of Contents');
					else 
						$(this).text('Show Table of Contents');
					return false;
				});
			});
		</script>
		<?php
	}
	
	/**
	 * Creates sidebar widget to select journal article font
	 * @since 0.1
	 * @params array $args widget args
	 */
	function font_widget($args) { 
		
		extract($args);
		
		//only show widget on journal articles
		global $post;
		if ( !isset($post) || $post->post_type != $this->post_type )
			return;
	?>
	
		<?php echo $before_widget; ?>
		<form>
			<?php echo $before_title; ?>Journal Font<?php echo $after_title; ?>
			<select id="font_select">
				<option <?php selected($this->font, "'Courier New', Courier, monospace"); ?> value="'Courier New', Courier, monospace">Classic</option>
				<option <?php selected($this->font, "Georgia, 'Bitstream Charter', serif"); ?> value="Georgia, 'Bitstream Charter', serif">Web</option>
			</select>
		</form>
		<script>
			jQuery(document).ready(function($){
				$('#font_select').change(function(){
					$('.<?php echo $this->post_type; ?>').css('font-family', $(this).val() );
					$.cookie('<?php echo $this->post_type; ?>_font', $(this).val() );
				});
			})
		</script>
	<?php echo $after_widget; ?>

<?php	}

	/**
	 * Converts URLs in articles into live links
	 * @since 0.1
	 * @param string $content the content of the post
	 * @returns string parsed content
	 *
	 */
	function link_urls( $content ) {
		global $post;
		
		//verify post type		
		if ($post->post_type != $this->post_type)
			return $content;
	
		//verify meta flag		
		if ( get_post_meta($post->ID, 'parsed_URLs') )
			return $content;
		
		//scary regex
		$pattern = "@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@";

		//replace
		$content =  preg_replace($pattern, '<a href="\0">\0</a>', $content);
	
		//set flag
		add_post_meta($post->ID,'parsed_URLs', true, true);
		
		return $content;

	}

	/**
	 * Tells WP that we want /login/ to be a live URL that we will handle
	 * @since 0.1
	 * @param array $rewrite_rules array of existing rules
	 * @returns array modified rules
	 */
	function add_login_rewrite( $rewrite_rules ) {

    	$new_rules = array( 'login/?$' => 'index.php?journalize_login=true' );
    	$rewrite_rules = $new_rules + $rewrite_rules;
    	return $rewrite_rules;

	}
	
	/**
	 * Handles /login/ requests
	 * @since 0.1
	 */
	function login_intercept() {
		global $wp_query;
		if ( isset( $wp_query->query_vars['journalize_login'] ) ) {
			add_filter('template_include', array(&$this, 'template_filter') );
		}
	}	
	
	/**
	 * Helper funtion for login_intercept()
	 * @since 0.1
	 */
	function template_filter() {

        return dirname( __FILE__ ) . '/login.php';

	}
	
	/**
	 * Helper funtion for login_intercept()
	 * @since 0.1
	 */
	function add_query_var( $vars ) {
		$vars[] = 'journalize_login';
		return $vars;
	}
	
	/**
	 * Widget to add issue list to sidebar
	 * @since 0.1
	 * @param array $args widget args
	 */
	function issue_widget($args) {
        extract( $args );
        echo $before_widget;
       	echo $before_title . 'Issues' . $after_title;
		$args = array('taxonomy' => 'journal_issue', 'title_li' => false);
		echo "<ul>";
		wp_list_categories($args);
		echo "</ul>";
		echo $after_widget; 	
	} 
	
}

//init the class and let the fun begin
$journalize = new journalize();


?>