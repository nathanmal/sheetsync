<?php 
namespace Sheetsync;

final class Gapi {

    public $app_name = '';

    public $scopes = array(
      'https://www.googleapis.com/auth/drive',
      'https://www.googleapis.com/auth/spreadsheets'
    );

    /**
     * Access type
     * @var string
     */
    public $access_type = 'offline';

    /**
     * URL to redirect to after user authentication
     * @var string
     */
    public $redirect = '';

    /**
     * Holds error message, if an error occurs
     * @var string
     */
    public $error = '';

    /**
     * GAPI Client
     * @var \Google_Client
     */
    private $client;

    /**
     * GAPI oAuth Access Token
     * @var string
     */
    private $access_token = '';

    /**
     * Path to file containing client ID and other important data
     * @var string
     */
    private $client_file = '';

    /**
     * Path to authenticated token file 
     * @var string
     */
    private $token_file = '';

    /**
     * Holds singleton instances of services
     * @var array
     */
    private static $services = array();

    /**
     * Constructor
     */
    public function __construct()
    {
      // Set vars
      $this->app_name    = get_option('ss_app_name');
      $this->client_file = SS_PATH . 'auth/client.json';
      $this->token_file  = SS_PATH . 'auth/credentials.json';
      $this->redirect    = get_admin_url(NULL, 'tools.php?page=sheet-sync&mode=oauthcallback');

      $this->client = new \Google_Client();


      if( ! empty($this->app_name) && $this->hasClientfile() ) 
      {
        $this->client->setApplicationName($this->app_name);
        $this->client->setScopes($this->scopes);
        $this->client->setAuthConfig($this->client_file);
        $this->client->setAccessType($this->access_type);
        $this->client->setRedirectUri($this->redirect);

        if( $this->hasTokenFile() )
        {
          $token = $this->getToken();

          $this->setToken($token);
        }
        else
        {
        }
      }

    }

    /**
     * Set Application Name
     * @param [type] $name [description]
     */
    public function setAppName($name)
    {
      $this->app_name = $name;
      $this->client->setApplicationName($name);
    }

    /**
     * Check if client file exists
     * (should be uploaded via setup)
     * @return boolean [description]
     */
    public function hasClientfile()
    {
      return is_file($this->client_file);
    }

    /**
     * Save client file
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public function saveClientFile($file)
    {
      if( isset($file['tmp_name']) && ! empty($file['tmp_name']) )
      {
        return move_uploaded_file($file['tmp_name'], $this->client_file);
      }
    }

    /**
     * Get client file location
     * @return [type] [description]
     */
    public function getClientFile()
    {
      return $this->client_file;
    }

    /**
     * Check if token file exists
     * @return boolean [description]
     */
    public function hasTokenFile()
    {
      return file_exists($this->token_file);
    }

    /**
     * Save token file
     * @param  [type] $token [description]
     * @return [type]        [description]
     */
    public function saveTokenFile($token)
    {
      file_put_contents($this->token_file, json_encode($token));
    }

    /**
     * Get access token
     * return false if none generated
     * @return [type] [description]
     */
    public function getToken()
    {
      if( $this->hasTokenFile() )
      {
        return json_decode(file_get_contents($this->token_file), true);
      }

      return FALSE;
    }

    /**
     * Set the access token for the client
     * refresh token if necessary
     * @param [type] $token [description]
     */
    public function setToken( $token )
    {
      if( $token )
      {
        $this->client->setAccessToken($token);
         // Refresh the token if it's expired.
        if ($this->client->isAccessTokenExpired()) 
        { 
            $new_token = $this->client->getRefreshToken();
            $this->client->fetchAccessTokenWithRefreshToken($new_token);
            $this->saveTokenFile($this->client->getAccessToken());
        }
      }
    }

    /**
     * Authenticate using code returned to 'redirect' URL
     * @param  [type] $auth_code [description]
     * @return [type]            [description]
     */
    public function authenticate( $auth_code )
    {   
       try 
       {
          $this->client->authenticate( $auth_code );
       } 
       catch( Exception $e )
       {
          $this->error = $e->getMessage();
          return FALSE;
       }

       $token = $this->client->getAccessToken();

       $this->saveTokenFile( $token );

       return TRUE;
    }

    /**
     * Get the Authentication URL
     * @return [type] [description]
     */
    public function getAuthUrl()
    {
      return $this->client->createAuthUrl();
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    public function getClient()
    { 
      return $this->client;
    }

    public function getService( $type )
    {
      $service = NULL;

      if( isset(self::$services[$type]) ) return self::$services[$type];

      switch($type)
      {
        case 'sheets':
          $service = new \Google_Service_Sheets($this->client);
          break;
        case 'drive':
          $service = new \Google_Service_Drive($this->client);
          break;
        default:
          break;
      }

      self::$services[$type] = $service;

      return self::$services[$type];
    }




}