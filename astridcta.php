<?php
/*
Plugin Name: Call-To-Action Reminders by Astrid
Plugin URI: http://wordpress.org/extend/plugins/call-to-action-reminders-by-astrid/
Description: A plugin that makes it easy to create Remind Me links and buttons within posts and Call-to-Action reminders at the bottom of posts
Version: 0.5.3
Author: Astrid and Friends
Author URI: http://astrid.com/
License: GPLv2
*/

/* Directories and URLs */
define( 'ACTA_URL', plugin_dir_url(__FILE__) );
define( 'ACTA_DIR', plugin_dir_path(__FILE__) );

class AstridCTA {
	static $instance = false;
	
	function __construct() {
		add_filter( 'cmb_meta_boxes', array( &$this, 'acta_meta_boxes' ) );
		add_action( 'init', array( &$this, 'init_acta' ), 9999 );
		add_action( 'wp_print_scripts', array( &$this, 'print_scripts' ) );
		
		add_action( 'cmb_render_acta_actions', array( &$this, 'render_acta_actions' ), 10, 2 );
		add_action( 'cmb_render_acta_button', array( &$this, 'render_acta_button' ), 10, 2 );
		
		add_action( 'cmd_validate_acta_action', array( &$this, 'validate_acta_action' ), 10, 3 );
		
		add_action( 'the_content', array( &$this, 'acta_content_footer' ), 100 );
	}
	
	function acta_meta_boxes( $meta_boxes ) {
		$prefix = 'acta_';
		$acta_title = '';
		$acta_title .= 'Astrid Reminders: Add Action items from your post ' . 
					   'so readers can get reminded via email, calendar, or to-do list.';
		
		$meta_boxes[] = array(
			'id' => 'acta-options',
			'title' => $acta_title,
			'pages' => array('post'), 
			'context' => 'normal',
			'priority' => 'low',
			'show_names' => true, 
			'fields' => array(
				array (
					'id' => $prefix . 'actions',
					'type' => 'acta_actions',
					'name' => 'Actions'
				),
				array (
					'id' => $prefix . 'add_action',
					'type' => 'acta_button',
					'text' => '&#x2713; Add New Action',
					'js_action' => 'return addActaAction();'
				)
			)
		);

		return $meta_boxes;
	}

	function init_acta() {
		if ( !class_exists( 'cmb_Meta_Box' ) ) {
			require_once( ACTA_DIR . '/metaboxes/init.php' );
		}
	}

	function print_scripts() {
		wp_register_script( 'astridcta', ACTA_URL . 'astridcta.js', array( 'jquery' ), '1.0' );
		wp_enqueue_script( 'astridcta' );
		if ( is_singular() && is_main_query() && get_astrid_cta_option('collect_statistics')){
			wp_register_script('astrid_post_loaded', ACTA_URL . 'astrid_post_loaded.js', array('jquery'), '1.0' );
			wp_enqueue_script( 'astrid_post_loaded' );
		}
		wp_register_style( 'astridcta', ACTA_URL . 'astridcta.css' );
		wp_enqueue_style( 'astridcta' );


	}

	function encodeURIComponent($str) {
	    $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
	    return strtr(rawurlencode($str), $revert);
	}	

	function render_acta_actions( $field, $meta ) {
		echo '<ul id="' . $field['id'] . '" name="' . $field['id'] . '">';		
		if ( $meta && is_array( $meta ) ) {
			foreach ( $meta as $val ) {
				echo ('<script>addActaAction("'.
					self::encodeURIComponent($val['text']).'","'.
					self::encodeURIComponent($val['notes']).'","'.
					intval($val['reminder_days']).
					'");</script>');
			}
		} else {
			echo '<span id="acta_no_actions">Add your first action.</span>';
		}
		echo '</ul>';
	}

	function tags_to_todos ($tag) {
		$content = get_the_content();
	    $DOM = new DOMDocument;
	    $DOM->LoadHTML($content);

	    $items = $DOM.getElementsByTagName('h1');

	    /* post all h1 elements, now you can do the same with getElementsByID to get the id's with that you expect. */
	    for ($i = 0; $i < $items->length; $i++) {
	        echo ('<script>addActaAction("'.
				self::encodeURIComponent($items->item($i)->nodeValue).'","notes",2);</script>');
	    }
	}
	
	function render_acta_button( $field, $meta ) {
		echo '<a name="' . $field['id'] . '" id="' . 
				$field['id'] . '" class="button" onclick="' . $field['js_action'] . '">' . $field['text'] . '</a>';
		echo $field['hidden_field'];
	}
	
	function validate_acta_action( $new, $post_id, $fields ) {
		return $new;
	}
	
	function acta_content_footer( $content ) {
		global $post;
		if ( is_singular() && is_main_query() ) {
			$actions = get_post_meta( $post->ID, 'acta_actions', true );
			if ( $actions && is_array( $actions ) && count( $actions ) > 0 ) {
				$cta_section = '<div id="acta_actions_fe">';
				$cta_section .= '<h2>' . get_astrid_cta_option('header') . '</h2>';
				$cta_section .= '<p>' . get_astrid_cta_option('description') .'</p>';
				$cta_section .= '<ul>';
				$siteurl = get_site_url();
				$step = 0;
				$cta_list = ''; 
				foreach( $actions as $action ) { 
					$step += 1;
					if (!$action['text'])
						continue;
					$cta_list .= '<li id="acta_action_fe_' . $step . '" class="acta_action_fe">';
					$cta_list .= '<a class= "astrid-reminder-link" href="http://astrid.com/tasks/remind_me?title=' . self::encodeURIComponent($action['text']);
					$cta_list .= '&due_in_days=' . $action['reminder_days'];
					$cta_list .= '&notes='.self::encodeURIComponent($action['notes']);
					$cta_list .= '&source_name='.get_the_title();
					$cta_list .= '&source_url='.post_permalink();
					$cta_list .= '" target="_blank"><span class="a-chk-span">&#x2713;</span> &#x2713; ' . $action['text'] . '</a>';
					$cta_list .= '</li>';
				}
				$cta_section .= $cta_list;
				$cta_section .= '</ul>';
				$cta_section .= '</div>';
				if ($cta_list)
					$content .= $cta_section;
			}
		}

		return $content;
	}
	
	public static function get_instance() {
        if ( !self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }
}

AstridCTA::get_instance();

/*** Admin Panel ***/
add_action('admin_init', 'astrid_cta_init' );
add_action('admin_menu', 'astrid_cta_add_page');

function get_astrid_cta_option($option) {
	$option_default = array(
    	"header" => "Don\'t forget!",
    	"description" => 'Get reminders by email or through <a href="http://astrid.com">Astrid</a> 
    						for iPhone, iPad, or Android.'
	);
	$options = get_option('astrid_cta');
	$option_return = $options[$option] ? $options[$option] : $option_default[$option];
	return stripslashes($option_return);
}

function astrid_cta_init(){
	register_setting( 'astrid_cta_options', 'astrid_cta', 'astrid_cta_validate' );
}

function astrid_cta_add_page() {
	add_options_page('Astrid Calls To Action', 'Astrid Calls-to-Action', 'manage_options', 'astrid_cta', 'astrid_cta_do_page');
}

// Draw menu page
function astrid_cta_do_page() {
	?>
	<div class="wrap">
		<h2>Astrid Calls-To-Action</h2>
		<form method="post" action="options.php">
			<?php settings_fields('astrid_cta_options'); ?>
		</p>
			<table class="form-table acta_action_fieid">
				<tr valign="top"><th scope="row">Call-To-Action Header</th>
					<td><input class="admin_input" name="astrid_cta[header]" type="text" value="<?php echo get_astrid_cta_option('header'); ?>" /></td>
				</tr>
				<tr valign="top"><th scope="row">Description</th>
					<td><textarea class="admin_input" name="astrid_cta[description]" rows="3"><?php echo get_astrid_cta_option('description'); ?></textarea></td>
				</tr>
				<tr valign="top"><th scope="row">Collect Statistics</th>
					<td><input type="checkbox" name="astrid_cta[collect_statistics]" 
						<?php if (get_astrid_cta_option('collect_statistics')) echo "checked='checked'"; ?>
						value="true" /> statistics show the # of people who add reminders complete suggestions.</td>
				</tr>
			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
	<?php	
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function astrid_cta_validate($input) {
	// Say our second option must be safe text with no HTML tags
	$input['header'] =  wp_filter_nohtml_kses($input['header']);
	$input['description'] =  addslashes($input['description']);
	$input['collect_statistics'] = ($input['collect_statistics']) ? 1 : 0;
	return $input;
}

/*** Add Astrid RM button and style to visual editor to add/preview inline content ***/
function add_astrid_reminder_button() {
   if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
     return;
   if ( get_user_option('rich_editing') == 'true') {
     add_filter('mce_external_plugins', 'add_astrid_reminder_tinymce_plugin');
     add_filter('mce_buttons', 'register_astrid_reminder_button');
     add_filter('mce_css', 'ac_style_for_visual_editor');
   }
}

add_action('init', 'add_astrid_reminder_button');
add_shortcode('astridrm', 'addAstridRM');

function ac_style_for_visual_editor($url) {
  return plugins_url() . '/call-to-action-reminders-by-astrid/astridcta.css';
}


function register_astrid_reminder_button($buttons) {
   array_push($buttons, "|", "astrid_reminder");
   return $buttons;
}

function add_astrid_reminder_tinymce_plugin($plugin_array) {
   $plugin_array['astrid_reminder'] = plugins_url() . '/call-to-action-reminders-by-astrid/editor_plugin.js';
   return $plugin_array;
}

function plugin_get_version() {
	$plugin_data = get_plugin_data( __FILE__ );
	$plugin_version = $plugin_data['Version'];
	return $plugin_version;
}
?>
