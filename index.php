<?php
/**
 * @package Oahu
 * @version 0.1
 */
/*
Plugin Name: Oahu
Plugin URI: http://github.com/sixdegrees/oahu-wordpress-plugin
Description: Oahu integration with your Wordpress
Author: Stephane Bellity
Version: 0.1
Author URI: http://github.com/sixdegrees/oahu-wordpress-plugin
*/

require_once(plugin_dir_path(__FILE__) . 'oahu-php-client/src/Oahu/Client.php');

function oahu_init() {

  echo "<script type='text/javascript'>
    if (typeof window['jQuery'] == 'undefined') {
      var _jqTag = unescape('%3Cscript src=\"//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js\" type=\"text/javascript\"%3E%3C/script%3E');
      document.write(_jqTag);
    }</script>";

  echo "<script src='//" . get_option('oahu_host') . "/assets/oahu.js'></script>";
  echo "<script src='//" . get_option('oahu_host') . "/assets/oahu-apps.js'></script>";
  echo "<script>$(function() { Oahu.init({ 
    appId: '" .   get_option('oahu_app_id') . "',
    debug: " .    get_option('oahu_debug') . ",
    verbose: " .  get_option('oahu_debug') . ",
  }, function() { console.warn('Oahu init ok'); }); });</script>";
}

add_action('wp_head', 'oahu_init');

// Auto load hbs templates
add_action('wp_footer', 'oahu_include_templates');

function oahu_include_templates() {
  echo Oahu_Helpers::includeWidgets(get_template_directory() . '/oahu/widgets/');
  echo Oahu_Helpers::includeTemplates(get_template_directory() . '/oahu/templates/');
}

function get_oahu_client() {
  return new Oahu_Client(array(
    'oahu' => array(
      'host'        => get_option('oahu_host'),
      'clientId'    => get_option('oahu_client_id'),
      'appId'       => get_option('oahu_app_id'),
      'appSecret'   => get_option('oahu_app_secret')
    )
  ));
};

function get_oahu_options() {
  return array(
    'oahu_host'       => array('name' => 'Host', 'default' => 'app-staging.oahu.fr', 'autoload' => 'yes'), 
    'oahu_client_id'  => array('name' => 'Client ID', 'default' => '', 'autoload' => 'no'), 
    'oahu_app_id'     => array('name' => 'App ID', 'default' => '', 'autoload' => 'yes'),
    'oahu_app_secret' => array('name' => 'App Secret', 'default' => '', 'autoload' => 'no'),
    'oahu_debug'      => array('name' => 'Debug', 'default' => 'true', 'values' => array('true', 'false'),  'autoload' => 'no')
  );
}

foreach(get_oahu_options() as $key => $opt) {
  add_option($key, $opt['default'], "", $opt['autoload']);
  if (isset($_POST[$key])) {
    update_option($key, $_POST[$key]);
  }
}


// Admin UI Config page

add_action( 'admin_menu', 'oahu_plugin_menu' );

function oahu_plugin_menu() {
  add_options_page( 'Oahu Options', 'Oahu', 'manage_options', 'oahu-options', 'oahu_plugin_options' );
}

function oahu_plugin_options() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
  echo '<form name="oahu_options_form" method="post" action="">';
  echo '<h1>Oahu Config</h1>';
  echo '<p>templates dir : ' . get_template_directory() . '</p>';
  echo '<table class="form-table"><tbody>';
  foreach(get_oahu_options() as $key => $opt) {
    $val = get_option($key);
    echo '<tr><th scope="row">' . $opt['name'] . '</th><td>';
    if (isset($opt['values'])) {
      echo '<select name="' . $key . '">';
      foreach($opt['values'] as $v) {
        if ($v == $val) {
          $selected = 'selected';  
        } else {
          $selected = '';
        }
        
        echo '<option ' . $selected . '>' . $v . '</option>';
      }
      echo '</select>';
    } else {
      echo '<input type="text" name="' . $key . '" value="' . $val . '" size="36">';  
    }
    echo '</td></tr>';
  }
  echo '</tbody></table>';
  echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>';
  echo '</form>';
}


// Admin UI Post Meta

add_action( 'add_meta_boxes', 'oahu_add_custom_box' );
function oahu_add_custom_box() {
  $screens = array( 'post', 'page' );
    foreach ($screens as $screen) {
        add_meta_box(
            'oahu_meta',
            'Oahu',
            'oahu_meta_box',
            $screen
        );
    }
}

function oahu_meta_box($post) {
  $oahu_id = get_post_meta($post->ID, 'oahu_id', true);
}


// Callback to save post as a Oahu entity
// 
add_action( 'save_post', 'oahu_save_article' );

function oahu_save_article($post_id) {
  $oahu = get_oahu_client();
  $post_title = get_the_title($post_id);
  $post_url   = get_post_permalink($post_id);
  $post_tags  = wp_get_post_tags($post_id, array('fields' => 'names'));
  $oahu_id    = get_post_meta($post_id, 'oahu_id', true);

  $post_attributes = array(
    'name' => $post_title, 
    'uid' => $post_url, 
    'entity_type' => 'article',
    'tags' => $post_tags
  );

  if (!wp_is_post_revision($post_id) && get_post_status($post_id) == 'publish') {
    if ($oahu_id) {
      try {
        $entity = $oahu->getEntity($oahu_id);    
      } catch(Exception $e) {
        $entitiesByUid = $oahu->listEntities(array('filter' => array('uid' => $post_url)));
        if ($entitiesByUid && $entitiesByUid[0]) {
          $oahu_id = $entitiesByUid[0]->id;
          update_post_meta($post_id, 'oahu_id', $oahu_id);
        } else {
          $oahu_id = false;
        }
      }
      
    }
  
    if ($oahu_id) {
      $oahu->updateEntity($oahu_id, $post_attributes);
    } else {
      $entity = $oahu->createEntity($post_attributes);
      if ($entity) {
        add_post_meta($post_id, 'oahu_id', $entity->id, true);
      }
    }  
  }
}

// Helper functions

function oahu_comments_widget($post_id) {
  $oahu_id = get_post_meta($post_id, 'oahu_id', true);
  oahu_widget("comments", array("id" => $oahu_id));
}


function oahu_widget($name, $options=array(), $tagName = "div", $placeholder="") {
  $prms = array('data-oahu-widget="' . $name . '"');
  foreach ($options as $key => $val) {
    $prms[] = 'data-oahu-' . $key . '="'. $val .'"';
  }
  echo "<$tagName " . implode(" ", $prms) . ">$placeholder</$tagName>";
}