<?php 
namespace Sheetsync;

use \Sheetsync\Gapi;
use \Sheetsync\Drive;

class Sheet 
{

  /**
   * Google API instance
   * @var null
   */
  private $gapi = NULL;


  /**
   * Config array
   * @var [type]
   */
  public $config = array();

  /**
   * Name of the sheet
   * @var [type]
   */
  public $name;

  /**
   * Post type used for sheet
   * @var [type]
   */
  public $post_type;

  /**
   * Google sheet ID
   * @var [type]
   */
  public $sheet_id;

  /**
   * Gdrive folder to use for images
   * @var [type]
   */
  public $img_folder;

  /**
   * Column mapping
   * @var [type]
   */
  public $mapping;

  /**
   * Does the sheet use a header row
   * @var [type]
   */
  public $use_headers;

  /**
   * Array of headers, if used
   * @var array
   */
  public $headers = array();

  /**
   * Holds data after retreival
   * @var array
   */
  public $data = NULL;

  /**
   * Instance of Google_Sheet_Service
   * @var [type]
   */
  public $service;

  /**
   * Spreadsheet Object
   * @var \Google_Sheet_Service_Spreadsheet
   */
  public $spreadsheet;

  /**
   * Array of \Google_Sheet_Service_Sheet objects
   * @var array
   */
  public $sheets = array();

  /**
   * The current sheet index being used
   * by default we always assume we're using the first 
   * sheet in the spreadsheet
   * @var integer
   */
  public $current_index = 0;


  public $current_title = '';


  public $current_bounds = array();
  /**
   * Current sheet being used
   * @var null
   */
  public $current_sheet = NULL;

  /**
   * Holds last error message
   * @var [type]
   */
  public $error;


  /**
   * Holds all errors
   * @var array
   */
  public $errors = array();


  
  /**
   * Get sheet configuration by sheet_id
   * @return [type] [description]
   */
  public static function get_config( $sheet_id )
  {
    $sheets = get_option( 'ss_sheets', array() );

    return ! empty($sheet_id) && isset($sheets[$sheet_id]) ? $sheets[$sheet_id] : FALSE;
  }

  /**
   * Save sheet configuration for a particular sheet_id
   * @param  [type] $sheet_id [description]
   * @param  array  $config   [description]
   * @return [type]           [description]
   */
  public static function save_config( $sheet_id, $config = array() )
  {
    // Get the sheets
    $sheets = get_option( 'ss_sheets', array() );

    // Bad dev!
    if( empty($sheet_id) ) return FALSE;

    $sheets[$sheet_id] = $config;

    // Update the stored sheets
    update_option( 'ss_sheets', $sheets );

    return TRUE;

  }

  /**
   * Constructor
   * @param array $config [description]
   */
  public function __construct( $sheet_id = NULL )
  {
    // Load service
    $this->gapi = new Gapi();

    $this->service = $this->gapi->getService('sheets');
      
    $this->init($sheet_id);
   
  }

  /**
   * Initialize the Sheet instance
   * @param  [type] $sheet_id [description]
   * @return [type]           [description]
   */
  public function init( $sheet_id = NULL )
  {
    // Set the sheet ID
    $this->setSheetID($sheet_id);



  }

  /**
   * Sets the sheet ID
   * resets instance values if different than the current sheet
   * @param [type] $sheet_id [description]
   */
  public function setSheetID( $sheet_id = NULL )
  {
    if( ! empty($sheet_id) && $sheet_id != $this->sheet_id ) 
    {  
      $config = Sheet::get_config($sheet_id);

      $drive = new Drive();

      $file = $drive->get($sheet_id);

      $this->name = $file->getName();

      $this->load($config);
    }
  }

  

  /**
   * Get config item (or config array if none specified) for this instance
   * @param  [type] $item [description]
   * @return [type]       [description]
   */
  public function config( $item = NULL ) 
  {
    if( empty($item) ) return $this->config;

    return isset( $this->{$item} ) ? $this->{$item} : NULL;
  }

  /**
   * Load the spreadsheet, sheets and properties
   * @return [type] [description]
   */
  public function load($config = array())
  { 
    if( empty($config) ) return FALSE;

    $this->reset();

    $this->config      = $config;

    $this->sheet_id    = $config['sheet_id'] ?: '';
    $this->post_type   = $config['post_type'] ?: '';
    $this->folder_id   = $config['folder_id'] ?: '';
    $this->mapping     = $config['mapping'] ?: array();
    $this->use_headers = isset($config['use_headers']) ? (bool) $config['use_headers'] : FALSE;
  
    try {
      $this->spreadsheet = $this->service->spreadsheets->get($this->sheet_id);
    } catch( \Google_Service_Exception $e ) {
      $this->error = $e->getMessage();
      return FALSE;
    }

    $this->sheets = $this->spreadsheet->getSheets();

    $this->setCurrentSheet();

    return TRUE;
  }

  /**
   * Set the current (working) sheet
   * @param integer $index [description]
   */
  public function setCurrentSheet($index = 0)
  {
    // Set current sheet
    $this->current_sheet = $this->getSheet($index);

    $props = $this->current_sheet->getProperties();

    // Set the sheet title
    $this->current_title = $props->title;

    // Get grid range
    $cols = $props->gridProperties->columnCount;
    $rows = $props->gridProperties->rowCount;
    $this->current_bounds = array($cols,$rows);    
  }


  /**
   * Get the spreadsheet object
   * @return object \Google_Service_Sheets_Spreadsheets
   */
  public function getSpreadsheet()
  {
    return ! empty($this->spreadsheet) ? $this->spreadsheet : FALSE;
  }

  /**
   * Get a sheet from this spreadsheet
   * @param  integer $index [description]
   * @return object \Google_Service_Sheets_Sheet
   */
  public function getSheet( $index = NULL )
  {
    // Set to current sheet by default
    if( is_null($index) ) $index = $this->current_index;

    return ! empty($this->spreadsheet) && isset($this->sheets[$index]) ? $this->sheets[$index] : FALSE;
  }


  /**
   * Get the sheet data
   * @return [type] [description]
   */
  public function getData()
  { 
    if( ! is_null($this->data) ) return $this->data;

    $cols = $this->current_bounds[0];
    $rows = $this->current_bounds[1];

    $startcol = 'A';
    $endcol = $this->indexToColumn($cols);

    $startrow = $this->use_headers ? '2' : '1';
    $endrow = $rows;

    $range = $this->current_title . '!' . $startcol . $startrow . ':' . $endcol . $endrow;

    try {
      $response = $this->service->spreadsheets_values->get($this->sheet_id, $range);
      $data = $response->values;
    } catch( \Google_Service_Exception $e ) {
      $this->error = $e->getMessage();
      return FALSE;
    }

    return $data;
  }

  /**
   * Get the sheet headers
   * @return [type] [description]
   */
  public function getHeaders()
  {  
    $headers = array();

    if( empty($this->use_headers) ) 
    {
      $cols = $this->current_bounds[0];

      for($i=0;$i<$cols;$i++)
      {
        $headers[$i] = $this->indexToColumn($i);
      }

    }
    else
    {
      try 
      {
        $response = $this->service->spreadsheets_values->get($this->sheet_id, $this->current_title . '!1:1');
        $headers  = $response->getValues()[0];
      } catch ( \Google_Service_Exception $e ) {
        $this->error = $e->getMessage();
      }
    }

    return $headers;

  }

  /**
   * Convert column index to letter specifier (ie A,Z,WW etc)
   * @param  [type] $index [description]
   * @return [type]        [description]
   */
  public function indexToColumn($index)
  {
     for($r = ""; $index >= 0; $index = intval($index / 26) - 1)
        $r = chr($index%26 + 0x41) . $r;
     
     return $r;
  }

  public function columnToIndex($column)
  {
    
  }

  /**
   * Reset sheet values
   * @return [type] [description]
   */
  public function reset()
  {
    $this->data          = NULL;
    $this->spreadsheet   = NULL;
    $this->sheets        = array();
    $this->error         = NULL;
    $this->errors        = array();
    $this->current_index = 0;
  }

  public function save_mapping($mapping = array())
  {
    $sheets = get_option('ss_sheets');

    foreach($sheets as $index => $config)
    {
       if( $config['id'] == $this->sheet_id && $config['post_type'] == $this->post_type )
       {
          $sheets[$index]['mapping'] = $mapping;
          break;
       }
    }

    return update_option('ss_sheets', $sheets);

  }

  /**
   * Save this sheet
   * @param  array  $data data supplied by $_POST array
   * @return [type]       [description]
   */
  public function save( $post_data = array() )
  { 
    // Sanitize post
    $data = $this->sanitize($post_data);

    // Run validation
    if( $this->validate($data) )
    {
      if( Sheet::save_config( $data['sheet_id'], $data) )
      {
        $this->load($data);
        return TRUE;
      }
    }

    // save failed
    return FALSE;
  }

  /**
   * Sanitize post data
   * @param  array  $post_data [description]
   * @return [type]            [description]
   */
  public function sanitize( $post_data = array() )
  {
    $data = array();

    $data['post_type']   = isset($post_data['ss_post_type']) ? sanitize_key($post_data['ss_post_type']) : '';
    $data['sheet_id']    = isset($post_data['ss_sheet_id']) ? sanitize_text_field($post_data['ss_sheet_id']) : '';
    $data['folder_id']   = isset($post_data['ss_folder_id']) ? sanitize_text_field($post_data['ss_folder_id']) : '';
    $data['use_headers'] = isset($post_data['ss_use_headers']) ? (bool) $post_data['ss_use_headers'] : FALSE;

    // Sanitize mapping data
    $mapping = array();

    print_p($post_data);

    if( isset($data['map_cols']) && isset($data['map_attrs']) && isset($data['map_names']) )
    {
      $cols  = $data['map_cols'];
      $attrs = $data['map_attrs'];
      $names = $data['map_names'];

      // Must all be equal
      if( count($cols) == count($attrs) && count($attr) == count($names) )
      {
        foreach($cols as $index => $col)
        {
          $map = array(
            'index'   => $col,                        // Column Index
            'index_a' => $this->indexToColumn($col),  // Column Index A1 Notation
            'attr'    => $attrs[$index],              // Data attribute
            'name'    => $names[$index]               // Meta attribute name (if postmeta)
          );

          $mapping[] = $map;
        }
      }
    }

    $data['mapping'] = $mapping;

    return $data;

  }

  /**
   * Validate post data
   * @param  array  $data [description]
   * @return [type]       [description]
   */
  public function validate( $data = array() )
  { 
    // Sheet ID required
    if( ! isset($data['sheet_id']) OR empty($data['sheet_id']) ) 
    {
      $this->setError( __("Sheet ID required") );
      return FALSE;
    }

    // Check to make sure this isn't a duplicate
    if( empty($this->sheet_id) && self::get_config($data['sheet_id']))
    {
      $this->setError( __("Sheet ID already in use") );
      return FALSE;
    }

    // Check post type
    if( ! isset($data['post_type']) OR empty($data['post_type']) )
    {
      $this->setError( __("Post type required") );
      return FALSE;
    }

    // Check that it's a valid spreadsheet
    $drive = new Drive();

    $file = $drive->get($data['sheet_id']);

    if( empty($file) OR $file->mimeType != Drive::DRIVE_MIMETYPE_SPREADSHEET ) 
    {
      $this->setError( __("Invalid sheet ID") );
      return FALSE;
    }

    if( isset($data['folder_id']) && ! empty($data['folder_id']) )
    {
      if( ! $drive->getFolder($data['folder_id']) )
      {
        $this->setError( __("Invalid Folder ID") );
        return FALSE;
      }
    }

    // All tests passed
    return TRUE;

  }

  /**
   * Set runtime error
   * @param [type] $error [description]
   */
  public function setError( $error )
  {
    if( $error ) {
      $this->error = $error;
      $this->errors[] = $error;
    }
  }


  /**
   * Render table row
   * @return [type] [description]
   */
  public function render_row()
  {



  }



    

}