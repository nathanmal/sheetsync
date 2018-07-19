<?php 
namespace Sheetsync;

use \Sheetsync\Gapi;

class Drive  
{

  const DRIVE_MIMETYPE_FOLDER      = 'application/vnd.google-apps.folder';
  const DRIVE_MIMETYPE_DOCUMENT    = 'application/vnd.google-apps.document';
  const DRIVE_MIMETYPE_SPREADSHEET = 'application/vnd.google-apps.spreadsheet';
  const DRIVE_MIMETYPE_JPEG        = 'image/jpeg';
  const DRIVE_MIMETYPE_PDF         = 'application/pdf';


  /**
   * GAPI object
   * @var \Sheetsync\Gapi;
   */
  private $gapi;

  /**
   * GAPI Service
   * @var \Google_Drive_Service
   */
  private $service;


  /**
   * Holds last error
   * @var [type]
   */
  public $error;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->gapi = new Gapi();

    $this->service = $this->gapi->getService('drive');
  }


  public function getChildren()
  {
    return $this->service->children->listFiles();

  }

  public function getFiles( $params = array() )
  {
    $list = $this->service->files->listFiles( $params );

    return $list->files;
  }


  public function searchFiles( $params = array() )
  {
    $list = $this->service->files->listFiles( $params );

    return $list->files;

  }

  public function getFolders()
  { 

    $q = "mimeType = 'application/vnd.google-apps.folder'";

    $params = array('q' => $q, 'pageSize' => 1000 );

    return $this->searchFiles( $params );
    
  }

  /**
   * Get file by ID
   * @param  string $fileID [description]
   * @return \Google_Service_Drive_DriveFile
   */
  public function get( $fileID )
  {
    try {
      return $this->service->files->get( $fileID );
    } catch ( \Google_Service_Exception $e ) {
      $this->error = $e->getMessage();
      return FALSE;
    }
    
  }

  /**
   * Get folder by ID
   * returns false if file is not a folder
   * @param  string $fileID ID of the folder
   * @return Google_Service_Drive_DriveFile         
   */
  public function getFolder( $fileID )
  {
    $file = $this->get($fileID);

    if( $file && $file->mimeType == self::DRIVE_MIMETYPE_FOLDER ) {
      return $file;
    }

    return FALSE;
  }




}