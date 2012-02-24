<?php
/*
Plugin Name: Gunner Technology Shortcodes
Plugin URI: http://gunnertech.com/2012/02/shortcodes-wordpress-plugin/
Description: A plugin that adds a plethora of shortcodes we use
Version: 0.0.1
Author: gunnertech, codyswann
Author URI: http://gunnnertech.com
License: GPL2
*/


define('GT_SHORTCODES_VERSION', '0.0.1');
define('GT_SHORTCODES_URL', plugin_dir_url( __FILE__ ));

class GtShortcodes {
  private static $instance;
  public static $is_https_request;
  
  public static function activate() {
    update_option("gt_shortcodes_db_version", GT_SHORTCODES_VERSION);
  }
  
  public static function deactivate() { }
  
  public static function uninstall() { }
  
  public static function update_db_check() {
    
    $installed_ver = get_option( "gt_shortcodes_db_version" );
    
    if( $installed_ver != GT_SHORTCODES_VERSION ) {
      self::activate();
    }
  }
  
  private function __construct() {
    $_this = $this;
    
    add_action('in_widget_form', function($obj,$return,$instance) use ($_this) {
      echo '<p>
        <label>Shortcode:</label><br />
        [widget id=\'' . $obj->id . '\']
      </p>';
    }, 10, 3);
    
    add_shortcode('date', function($atts, $content=null, $code="") {
      extract(shortcode_atts(array(
        'format' => 'Y'
      ), $atts));
      
      return date($format);
    });
    
    add_shortcode('strip_markup', function($atts, $content=null, $code="") {
      extract(shortcode_atts(array(
      ), $atts));
      
      return do_shortcode(strip_tags($content));
    });
    
    
    add_shortcode('widget', function($atts, $content=null, $code="") use ($_this) {
      global $hbgs_inline_count, $hbgs_scripts, $hbgs_type_of_assets_to_return;
      
      if(!isset($hbgs_inline_count)) {
        $hbgs_inline_count = array();
      }
      
      extract(shortcode_atts(array(
        "id" => ''
      ), $atts));
      
      $echo = false;
      
      $count_key = isset($hbgs_type_of_assets_to_return) ? $hbgs_type_of_assets_to_return : 'html';
      $args = $_this->get_widget_instance($id);
      $obj = $args['obj'];

      if(!$args['obj']) {
        return false;
      }
      
      $out = '';
      $atts = (array)$atts;
      $unique_id = $id;

      if(isset($atts['id'])) {
        $hbgs_inline_count[$count_key] = isset($hbgs_inline_count[$count_key]) ? $hbgs_inline_count[$count_key] : 0;
        $hbgs_inline_count[$count_key]++;
        $atts['nocache'] = 1;
        $unique_id = ($id.'-'.$hbgs_inline_count[$count_key]);

        unset($atts['id']);

        $args['instance']['styles'] = isset($args['instance']['styles']) ? preg_replace(array('/#widget-id/'),array('#'.$unique_id), $args['instance']['styles']) : '';
        $args['instance']['scripts'] = isset($args['instance']['scripts']) ? preg_replace(array('/#widget-id/'),array('#'.$unique_id), $args['instance']['scripts']) : '';
        $args['args'] = isset($args['args']) ? $args['args'] : array(
           'before_widget' => '<div id="%1$s" class="moreclasses widget-container %2$s"><div class="widget-content">',
           'after_widget' => ('</div></div>'),
           'before_title' => '<hgroup class="title-wrapper"><h3 class="widget-title">',
           'after_title' => ('</h3></hgroup>')
        );
        $args['args']['before_widget'] = preg_replace("/id=\"".$id."\"/", 'id="'.$unique_id.'"', $args['args']['before_widget']);

        $filter = function($instance,$obj,$args) use ($atts) {
          $instance = array_merge($instance,$atts);
          remove_all_filters('widget_display_callback',77);
        
          return $instance;
        };
        
        add_filter('widget_display_callback',$filter,77,3); 
      }

      if($echo) {
        $obj->print_styles($id,$args['instance']);
        $obj->display_callback($args['args'], array('number' => $args['number'] ) );
      } else {
        ob_start();
        if($hbgs_type_of_assets_to_return == 'css') {
          //hbgs_render_styles($unique_id,null,$args['instance']['styles']);
        } else if($hbgs_type_of_assets_to_return == 'js') {
          //hbgs_render_scripts($unique_id,null,$args['instance']['scripts'],$atts);
        }
        $out = ob_get_contents();
        ob_end_clean();

        ob_start();
        $obj->widget($args['args'], array_merge($args['instance'],array('number' => $args['number']) ) );
        $html = ob_get_contents();
        ob_end_clean();

        if($hbgs_type_of_assets_to_return != 'css' && $hbgs_type_of_assets_to_return != 'js') {
          $out .= $html;
        }

        return $out;
      }
    });
    
  }
  
  
  public static function setup() {
    self::update_db_check();
    self::singleton();
  }
  
  public static function singleton() {
    if (!isset(self::$instance)) {
      $className = __CLASS__;
      self::$instance = new $className;
    }
    
    return self::$instance;
  }
  
  public function get_widget_instance($id) {
    global $wp_registered_widgets;

    preg_match('/-(\d+$)/',$id,$matches);
    $number = $matches[1];

    foreach($wp_registered_widgets as $key => $wid) {
      if($key == $id) {
        $widget_object = $wid['callback'][0];
        $instances = $widget_object->get_settings();
        $before_widget = '<div id="%1$s" class="moreclasses widget-container %2$s %3$s"><div class="widget-content">';

        $before_widget = sprintf($before_widget, $id, $widget_object->widget_options['classname'], $id);

        $args = array(
          'name' => $widget_object->name,
          'id' => $widget_object->id_base, 
          'description' => $widget_object->widget_options['description'],
          'before_widget' => $before_widget,
          'after_widget' => '</div></div>',
          'before_title' => '<hgroup class="title-wrapper"><h3 class="widget-title">',
          'after_title' => '</h3></hgroup>',
          'widget_id' => $widget_object->id, 
          'widget_name' => $widget_object->name
        );
        return array('obj' => $widget_object, 'instance' => $instances[$number], 'number' => $number, 'args' => $args);
      }
    }

    return null;
  }
  
}

register_activation_hook( __FILE__, array('GtShortcodes', 'activate') );
register_activation_hook( __FILE__, array('GtShortcodes', 'deactivate') );
register_activation_hook( __FILE__, array('GtShortcodes', 'uninstall') );

add_action('plugins_loaded', array('GtShortcodes', 'setup') );

