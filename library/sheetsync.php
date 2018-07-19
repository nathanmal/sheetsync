<?php 
namespace Sheetsync;

use Sheetsync\Admin;

final class Sheetsync 
{

  private static $initialized = FALSE;


  private static $post_attributes = array(
    'post.id'       => 'Post ID',
    'post.title'    => 'Post Title',
    'post.content'  => 'Post Content',
    'post.excerpt'  => 'Post Excerpt',
    'post.date'     => 'Post Date',
    'post.status'   => 'Post Status',
    'post.name'     => 'Post Name',
    'post.modified' => 'Post Modified Date',
    'post.meta'     => 'Post Meta'
  );

  
  public static function init() 
  {
    // Only allow init once
    if( self::$initialized ) return;

    // Initialize Admin Stuff
    if( is_admin() ) Admin::init();
    
    // Mark initialized
    self::$initialized = TRUE;

  }

  /**
   * PSR-4 Plugin autoloader
   * Used with spl_autoload_register
   * @see    http://php.net/manual/en/function.spl-autoload-register.php
   * @param  string $class Class name
   */
  public static function autoload($class)
  { 
    $prefix = 'Sheetsync\\';

    $len    = strlen($prefix);
    
    $base   = SS_PATH . 'library/';

    // Only if namespace is within the current plugin
    if( 0 !== strpos($class, $prefix) ) return;
    
    $path = strtolower(substr($class,$len));

    $file =  $base . str_replace('\\','/',$path) . '.php';

    if( is_file($file) ) require $file;
  }

  /**
   * Check if Advanced Custom Fields plugin is being used
   */
  public static function is_acf_active()
  {
    return is_plugin_active( 'advanced-custom-fields/acf.php' );
  }


  /**
   * Get all available data attributes for mapping columns to
   * based on post type
   * @return [type] [description]
   */
  public static function get_attributes( $post_type )
  {
     $attributes = self::$post_attributes;

     if( self::is_acf_active() )
     {
        // Get all groups
        $groups = self::get_acf_fields_post_type($post_type);


        foreach($groups as $group)
        {
          $gname = $group['name'];

          foreach($group['fields'] as $field)
          {
             $key = $field['key'];
             $label = $group['title'] . ': ' . $field['label'];
             $attributes[$key] = $label;
          }
        }
     }

     return $attributes;
  }

  /**
   * Get custom field attributes defined by ACF
   * @param  [type] $post_type [description]
   * @return [type]            [description]
   */
  public static function get_acf_fields_post_type( $post_type )
  {
      global $wpdb;

      $sql = "SELECT p.ID, p.post_title, p.post_name, pm.meta_value as rule
              FROM $wpdb->posts p 
              LEFT JOIN $wpdb->postmeta pm 
              ON ( p.ID = pm.post_id AND pm.meta_key = 'rule' ) 
              WHERE p.post_type = 'acf'";

      $result = $wpdb->get_results($sql);

      $groups = array();

      foreach($result as $row){

        $rule = unserialize($row->rule);

        if( $rule['param'] == 'post_type' && $rule['operator'] == '==' && $rule['value'] == $post_type )
        { 
          $groups[$row->ID] = array('title'=>$row->post_title,'name'=>$row->post_name);
        }

      }

      foreach($groups as $post_id => $data)
      {   
          $fsql = "SELECT * FROM $wpdb->postmeta WHERE post_id = '$post_id' AND meta_key LIKE 'field_%';";

          $fields = $wpdb->get_results($fsql);

          $field_array = array();
          foreach($fields as $field)
          {
            $f = unserialize($field->meta_value);
            $field_array[$field->meta_key] = $f; 
          }
          $groups[$post_id]['fields'] = $field_array;
      }

      return $groups;

  }


  
}