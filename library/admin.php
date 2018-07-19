<?php 
namespace Sheetsync;

use Sheetsync\Gapi;
use Sheetsync\Sheet;
use Sheetsync\Drive;

final class Admin
{
  /**
   * Prevent populating these post types
   * @var array
   */
  private static $excluded_post_types = array(
    'revision',
    'custom_css',
    'customize_changeset',
    'oembed_cache',
    'user_request',
    'nav_menu_item',
    'acf',
    'attachment'
  );

  /**
   * Initialize Admin Stuff
   * @return [type] [description]
   */
  public static function init()
  {
    add_action('admin_menu', array('Sheetsync\\Admin','sheetsync_menu'));

    self::register_settings();

    wp_enqueue_script('sheetsync', SS_URL . '/assets/js/sheetsync.js', array('jquery'));

  }

  /**
   * Insert the plugin menuitem
   * as a submenu of "Tools"
   * @return [type] [description]
   */
  public static function sheetsync_menu()
  {
    add_submenu_page(
      'tools.php',
      'Sheet Sync',
      'Sheet Sync',
      'manage_options',
      'sheet-sync',
      array('Sheetsync\\Admin', 'sheetsync_settings')
    );

  }

  /**
   * Register available settings
   * @return [type] [description]
   */
  public static function register_settings()
  {
    register_setting( 'ss_auth', 'ss_app_name' );
  }


  /**
   * Main settings page entry point
   * @return [type] [description]
   */
  public static function sheetsync_settings()
  {
    $mode = sanitize_text_field($_GET['mode']);

    $gapi = new Gapi();

    // Default
    if( empty($mode) ) $mode = 'main';

    // Force setup if client or token file doesn't exist
    if( $mode != 'oauthcallback' && ( ! $gapi->hasClientfile() OR ! $gapi->hasTokenFile() ))
    {
      $mode = 'authenticate';
    }

    call_user_func('\\Sheetsync\\Admin::sheetsync_'.$mode);

  }

  /**
   * Main Settings page after setup is complete
   * @return [type] [description]
   */
  public static function sheetsync_main()
  {
    $gapi = new Gapi();

    // Must authenticate first
    if( ! $gapi->hasClientfile() OR ! $gapi->hasTokenFile() ) {

      $url = self::page_url('authenticate');
      self::redirect($url);
    }
    else 
    {

    // Get sheets
    $sheets = get_option('ss_sheets', array()); 

    ?>
  
    <div class="wrap">
    <!-- Begin Options -->
    <h1>Sheet Sync Options</h1>
    <hr/>
    <h2>Sheets</h2>

    <table class="widefat">
      <thead>
        <tr>
          <th>Name</th>
          <th>Post Type</th>
          <th>Sheed ID</th>
          <th>&nbsp;</th>
        </tr>
      </thead>
      <tbody>


    <?php 
    if( empty($sheets) )
    {
      echo '<tr><td colspan="4">No sheets available</td></tr>';
    }
    else
    {
      foreach($sheets as $config)
      {
        $sheet = new Sheet($config);
        $sheet->render_row();
      }
    }
    ?>
      </tbody>
    </table>
    
    <form action="<?= self::page_url('add') ?>" method="post">
    <?= submit_button('Add New Sheet'); ?>
    </form>

    </div>

    <?php 
    }

  }

  /**
   * Add sheet page
   * @return [type] [description]
   */
  public static function sheetsync_add()
  { 
      $sheet = new Sheet();

      // Form submitted
      if( isset($_POST['submit']) && $_POST['submit'] == 'Save Sheet' )
      {  
        if( $sheet->save( $_POST ) )
        {
           $mode = 'edit&sheet_id=' . $sheet->sheet_id;
           $url  = self::page_url($mode);
           self::redirect($url);
           return;
        }
      }

      ?>
        <div class="wrap">
          <h1>Sheet Sync : Add New Sheet</h1>
          <hr/>
          <?php 
          self::render_form($sheet);
          ?>
        </div>
      <?php




  }

  /**
   * Sheet Edit Page
   * @return [type] [description]
   */
  public static function sheetsync_edit()
  {
     $sheet_id = isset($_GET['sheet_id']) ? urldecode($_GET['sheet_id']) : FALSE;

     // Back out if no sheet ID
     if( empty($sheet_id) )
     {
        self::redirect(self::page_url());
        return;
     }

     $sheet = new Sheet($sheet_id);
     $notice = '';

     if( isset($_POST['submit']) && $_POST['submit'] == 'Save Sheet' ) 
     {
        if( $sheet->save($_POST) )
        {
          $notice = __("Sheet Saved!");
        }
     }

     ?> 
     <div class="wrap">
      <h1>Sheet Sync : Edit Sheet</h1>
      <hr/>

      <?php 
      if( ! empty($notice) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>'.$notice.'</p></div>';
      }
      ?>

      <?php 
      self::render_form($sheet);
      ?>

     </div>

     <?php 



  }

  /**
   * Initial setup & authentication
   * @return [type] [description]
   */
  public static function sheetsync_authenticate()
  {
    // Load google API
    $gapi = new Gapi();

    // Default app name to blog title
    $app_name    = get_option('ss_app_name', get_bloginfo('site_title'));
    $client_file = $gapi->hasClientfile() ? basename($gapi->getClientFile()) : '';

    // Form was submitted
    if( isset($_POST['submit']) && $_POST['submit'] == 'Authenticate' )
    {
        // App name submitted
        if( isset($_POST['ss_app_name']) && ! empty($_POST['ss_app_name']) )
        {
          $app_name = sanitize_text_field($_POST['ss_app_name']);
          update_option('ss_app_name', $app_name);
          $gapi->setAppName($app_name);
        }
        else
        {
          $this->display_error("You must specify an Application Name");
          return;
        }

        // Client file submitted
        if( isset($_POST['ss_client_file']) && ! empty($_POST['ss_client_file']['tmp_name']) )
        {
          if( ! $gapi->saveClientFile($_POST['ss_client_file']) )
          {
             $this->display_error("Could not upload client file");
             return;
          }
          else
          {
            $client_file = basename($gapi->getClientFile());
          }
        }

        // Perform user authentication
        if( ! empty($app_name) && $gapi->hasClientfile() )
        {
            // Get Google auth URL and redirect
            $auth_url = filter_var($gapi->getAuthUrl(), FILTER_SANITIZE_URL);
            self::redirect($auth_url);
        }
    }
    else
    {

      ?>

      <div class="wrap">
      <!-- Begin Options -->
      <h1>Sheet Sync Options</h1>
      <hr/>
      <h2>Authentication</h2>
      <p>In order to authenticate using Google's implementation of oAuth2, you must first go to your user console and 
        generate an oAuth client ID &amp; secret. After creating your credentials, download the Client ID JSON file generated and upload using the form below.<br/><br/><a href="https://developers.google.com/identity/protocols/OAuth2WebServer#prerequisites" target="_blank">Click here to view instructions on how to set up your Client ID</a></p>
      <form method="post" action="tools.php?page=sheet-sync&mode=authenticate" enctype="multipart/form-data"> 
      <?php

      settings_fields( 'ss_auth' ); 

      do_settings_sections( 'ss_auth' );

      // $app_name = esc_attr(get_option('ss_app_name'));

      if( empty($app_name) ) $app_name = get_bloginfo('site_title');

      ?>

      <table class="form-table">
        <tr valign="top">
          <th scope="row">Application Name</th>
          <td><input type="text" class="regular-text" name="ss_app_name" value="<?= $app_name ?>" /></td>
        </tr>
        <tr valign="top">
          <th scope="row">Client File</th>
          <td>
            <?php if( ! empty($client_file) ){ 
              echo '<strong style="padding-right:12px"><span style="position:relative;top:3px;" class="dashicons dashicons-media-default"></span>'.$client_file.'</strong>';
            }
            ?>
            <input type="file" class="regular-text" name="ss_client_file" value="" />
          </td>
        </tr>
        <tr>
          <th><?= submit_button('Authenticate')?></th>
          <td></td>
        </tr>

      </table>
    
      </form>

      <!-- End Options -->
      </div>
      <?php



    }
   

    

  
  }

  

  /**
   * Redirect URL function
   * Google redirects here with auth code
   * @return [type] [description]
   */
  public static function sheetsync_oauthcallback()
  {
    if( isset($_GET['error']) ) {

      self::display_error($_GET['error']);

      return;
    }
    else if( isset($_GET['code']) )
    { 
      $gapi = new Gapi();

      $code = urldecode($_GET['code']);

      if( $gapi->authenticate($code) ) {

        $redirect = self::page_url();
        self::redirect($redirect);
      }
      else
      {
        self::display_error($gapi->error);
      }
    }

  }


  /**
   * Display an error message
   * @param  string $message [description]
   * @return [type]          [description]
   */
  public static function display_error( $message = '' )
  {
    echo '<div class="wrap">';
    echo '<h1>Sheet Sync: Error</h1>';
    echo '<hr/>';
    $a = '<a href="' . self::page_url() . '">Click here to return</a>';
    echo '<div class="notice notice-error">Error: '.$message.'<br/>' . $a . '</div>';
    echo '</div>';
  }

  /**
   * Get a page URL for this plugin
   * @param  string $mode [description]
   * @return [type]       [description]
   */
  private static function page_url($mode = '')
  {
    if( empty($mode) ) $mode = 'main';

    return get_admin_url(NULL, 'tools.php?page=sheet-sync&mode=' . $mode);

  }

  /**
   * Get post types not already used with a sheet
   * @return [type] [description]
   */
  private static function get_available_post_types()
  {   
      $available = array();

      $sheets = get_option('ss_sheets', array());

      $types = get_post_types( array(), 'objects');

      foreach($types as $type => $post_type)
      { 
          // Exclude certain built-in post types and ACF types
          if( in_array($type, self::$excluded_post_types) ) continue;

          // Check if we're already using this type
          foreach($sheets as $config)
          {
            if( $config['post_type'] == $type ) continue 2;
          }

          $available[$type] =  $post_type->label; 
      }

      return $available;

  }

  /**
   * Get the HTML select input for available post types
   * @return [type] [description]
   */
  private static function get_post_type_dropdown() 
  {
    $available = self::get_available_post_types();

    $dropdown = '<select id="ss_post_type" name="ss_post_type">';

    $dropdown .= '<option value="">' . __("Select Post Type") . '</option>';

    foreach($available as $type => $label)
    {
      $dropdown .= '<option value="' . $type .'">' . $label .'</option>';
    }

    $dropdown .= '</select>';

    return $dropdown;

  }

  /**
   * Redirect using javascript
   */
  private static function redirect($url)
  {
    // Send user to Authentication URL to grant consent
    // Use JavaScript if headers already sent
    if( ! empty($url) )
    {
      if( headers_sent() ) {
        echo '<script>jQuery(function($){ window.location.href="'.$url.'"; });</script>';
      } else {
        header("Location: " . $url);
      }
    }
  
  }


  /**
   * Render the add/edit form for this Sheet
   * @return [type] [description]
   */
  private static function render_form( Sheet $sheet )
  {

    // Check if we're adding a new sheet or editing an existing one
    if( empty($sheet->sheet_id) ) 
    { 
      $action = self::page_url('add');
      $input_post_type = self::get_post_type_dropdown();
      $form_id = 'sheetsync_add';
    }
    else
    {
      $action = self::page_url('edit&sheet_id=' . $sheet->sheet_id);
      $input_post_type = '<input type="hidden" name="ss_post_type" value="' . $sheet->post_type .'"/>' . $sheet->post_type;
      $form_id = 'sheetsync_edit';

    }

    $headers_checked = $sheet->use_headers ? 'checked' : '';

    if( ! empty($sheet->error) )
    {
      echo '<div class="notice notice-error">Error: '.$sheet->error. '</div>';
    }

    ?> 

    <form id="<?=$form_id?>" action="<?= $action ?>" method="post">
     <table class="form-table">
      <tr valign="top">
        <th scope="row">Post Type</th>
        <td><?= $input_post_type ?></td>
      </tr>
      <tr valign="top">
        <th scope="row">Sheet ID</th>
        <td><input type="text" class="regular-text" id="ss_sheet_id" name="ss_sheet_id" value="<?= $sheet->sheet_id ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row">Folder ID</th>
        <td><input type="text" class="regular-text" id="ss_folder_id" name="ss_folder_id" value="<?= $sheet->folder_id ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row">Use Header Row</th>
        <td><input type="checkbox" id="ss_use_headers" name="ss_use_headers" value="1" <?=$headers_checked?>/></td>
      </tr>
    </table>


    <?php 
    if( ! empty($sheet->sheet_id) ){


    $config = $sheet->config();
    $attributes = Sheetsync::get_attributes($sheet->post_type);
    $headers = $sheet->getHeaders();

    ?>

    <hr/>
    <h2>Mapping</h2>

    <table id="ss_sheet_mapping_table" class="widefat">
      <thead>
        <tr>
          <th width="250">Column</th>
          <th width="250">Data Attribute</th>
          <th>&nbsp;</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>

      <?php 
      foreach($config['mapping'] as $map)
      { 
        echo '<tr>';
        echo '<td><input type="hidden" name="map_cols[]" value="'.$map['index'].'"/>'.$headers[$map['index']].'</td>';
        echo '<td><input type="hidden" name="map_attrs[]" value="'.$map['attr'].'" />'.$map['attr'].'</td>';
        echo '<td><input type="hidden" name="map_names[]" value="'.$map['name'].'" />'.$map['name'].'</td>';
        echo '</tr>';

      }
      ?>

      </tbody>
    </table>
    <br/>

    <input type="hidden" name="ss_sheet_post_type" id="ss_sheet_post_type" value="<?=$sheet->post_type?>" />
    <input type="hidden" name="ss_attributes" id="ss_attributes" value='<?=json_encode($attributes)?>' />
    <input type="hidden" name="ss_header_labels" id="ss_header_labels" value='<?=json_encode($headers)?>' />

    <button type="button" id="ss_add_mapping" class="button">Add Column Mapping</button>


    <?php 
    }
    ?>


    <?= submit_button('Save Sheet')?> 

    </form>


    <?php 

  }


  public static function render_row( $sheet )
  {
    $url = get_admin_url(NULL,'tools.php?page=sheet-sync&mode=edit&sheet_id=' . $sheet->sheet_id);

    echo '<tr>';
    echo '<td><a href="' . $url . '">' . $sheet->name .'</a></td>';
    echo '<td>' . $sheet->post_type . '</td>';
    echo '<td>' . $sheet->sheet_id . '</td>';
    echo '<td>';
    echo '<a href="'.$url.'">Edit</a>';
    echo '&nbsp;';
    echo '<a href="#">Delete</a>';
    echo '</td>';
  }

}