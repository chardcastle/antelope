<?php
require_once '../google-api-php-client/src/Google_Client.php';
require_once '../google-api-php-client/src/contrib/Google_DriveService.php';


/** PHPExcel */
include '../PHPExcel/Classes/PHPExcel.php';

/** PHPExcel_Writer_Excel2007 */
include '../PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';


class Build {

  var $logFile;

  public function __construct()
  {  
    $this->logFile = fopen('result.txt', 'w');
  }

  public function log($message, $echo = false)
  {
    fwrite($this->logFile, $message . "\n");
    if ($echo)
    {
      echo $message . "\n";
    }
  }

  protected function instance()
  {

  

    $client = new Google_Client();
    // Get your credentials from the APIs Console
    $client->setClientId(CLIENT_ID);
    $client->setClientSecret(CLIENT_SECRET);
    $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
    $client->setScopes(array('https://www.googleapis.com/auth/drive'));

    $service = new Google_DriveService($client);

    $authUrl = $client->createAuthUrl();

    //Request authorization
    print "Please visit:\n$authUrl\n\n";
    print "Please enter the auth code:\n";
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for access token
    $accessToken = $client->authenticate($authCode);
    $client->setAccessToken($accessToken);
    return $service;

  }

  /**
   * Retrieve a list of File resources.
   *
   * @param Google_DriveService $service Drive API service instance.
   * @return Array List of Google_DriveFile resources.
   */
  public function retrieveAllFiles() {

    $service = $this->instance();

    $result = array();
    $pageToken = NULL;

    do {
      try {
        $parameters = array();
        if ($pageToken) {
          $parameters['pageToken'] = $pageToken;
        }
        $files = $service->files->listFiles($parameters);

        $result = array_merge($result, $files->getItems());
        $pageToken = $files->getNextPageToken();
      } catch (Exception $e) {
        print "An error occurred: " . $e->getMessage();
        $pageToken = NULL;
      }
    } while ($pageToken);
    $this->log(print_r($result,true));
  }


  /**
   * Print a file's metadata.
   *
   * @param Google_DriveService $service Drive API service instance.
   * @param string $fileId ID of the file to print metadata for.
   */
  public function printFile($service, $fileId) {
    try {
      $file = $service->files->get($fileId);

      print "Title: " . $file->getTitle();
      print "Description: " . $file->getDescription();
      print "MIME type: " . $file->getMimeType();
    } catch (Exception $e) {
      print "An error occurred: " . $e->getMessage();
    }
  }

  /**
   * Open a file 
   * @param  string $fileId File ID
   * @return object         Google Client $HttpRequest
   */
  function opentTheFile($fileId = false)
  {
    // $fileId = "0Al5E8gaeq0GodG40SHJWRlRIenk1Nk9oSE56SWVrcHc";
    // @todo Reading file data into object
    // @finishme OKKKK???
    // 
    
    // Set Service URL
    $downloadUrl = "https://www.googleapis.com/drive/v2/files/{$fileId}";

    if ($downloadUrl)
    {
      $this->log("Download URL ok: " . $downloadUrl, true);
     
      
      
      // Build request from URL
      $request = new Google_HttpRequest($downloadUrl, 'GET', null, null);
      $this->log("Download URL ok: " . print_r($request, true), true);   

      // Make request       
      $httpRequest = Google_Client::$io->authenticatedRequest($request);

      if ($httpRequest->getResponseHttpCode() == 200) {

        $this->log('Response body being delt with', true);

        // Create new PHPExcel object from response body
        $objPHPExcel = new PHPExcel();
        // $loadable  = $httpRequest->getResponseBody();
        $loadable = $downloadUrl;
        try {
          $objPHPExcel = $objReader->load($loadable);
            foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
              $this->log('Worksheet - ' , $worksheet->getTitle() , EOL);

              foreach ($worksheet->getRowIterator() as $row) {
                $this->log('    Row number - ' , $row->getRowIndex() , EOL);

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false); // Loop all cells, even if it is not set
                foreach ($cellIterator as $cell) {
                  if (!is_null($cell)) {
                    $this->log('        Cell - ' , $cell->getCoordinate() , ' - ' , $cell->getCalculatedValue() , EOL);
                  }
                }
              }
            }
        } catch (Exception $e) {
          $this->log($e->getMessage(), true);
          $this->log($e->getMessage());
        }

        

        $this->log("Download URL ok: " . print_r($httpRequest->getResponseBody()));
          return 'File Read Win!!!';
      } else {
        // An error occurred.
        $this->log('Response header was not 200');
        return null;
      }
    } else {
      // The file doesn't have any content stored on Drive.
      $this->log('There was no data in the document');
      return null;
    }
          
  }

  /**
   * Download a file's content.
   *
   * @param Google_DriveService $service Drive API service instance.
   * @param File $file Drive File instance.
   * @return String The file's content if successful, null otherwise.
   */
  function downloadFile($fileId) {

    $this->log("Downloading with file id {$fileId}");

    $service = $this->instance();
    
    $file = $service->files->get($fileId);
    
    // Download URL is empty for native docs
    // http://stackoverflow.com/questions/13602297/downloadurl-is-empty-for-file-for-some-files
    $this->log("File data: " . print_r($file, true));
    $downloadUrl = $file->getDownloadUrl(); // $file['exportLinks']['application/x-vnd.oasis.opendocument.spreadsheet'];//$file->getDownloadUrl();
    $this->log('Found file url: ' . $downloadUrl, true);
    $this->log("File data: " . print_r($file->getExportLinks(), true));

    $downloadUrl = $file->getExportLinks();
    $this->log('Result of crazy lookup: ' . print_r($downloadUrl, true));
    
    if ($downloadUrl) {
      $request = new Google_HttpRequest($downloadUrl, 'GET', null, null);
      $httpRequest = Google_Client::$io->authenticatedRequest($request);
      if ($httpRequest->getResponseHttpCode() == 200) {
        $this->log('Response body being delt with');
        return $httpRequest->getResponseBody();
      } else {
        // An error occurred.
        $this->log('Response header was not 200');
        return null;
      }
    } else {
      // The file doesn't have any content stored on Drive.
      $this->log('There was no data in the document');
      return null;
    }

  }
}

public function readTheGoogleDoc($app, $client, $service)
{
    /**
     * Gets the metadata and contents for the given file_id.
     */
    $app->get('/svc', function() use ($app, $client, $service) {
      checkUserAuthentication($app);
      checkRequiredQueryParams($app, array('file_id'));
      $fileId = $app->request()->get('file_id');
      try {
        // Retrieve metadata for the file specified by $fileId.
        $file = $service->files->get($fileId);

        // Get the contents of the file.
        $request = new Google_HttpRequest($file->downloadUrl);
        $response = $client->getIo()->authenticatedRequest($request);
        $file->content = $response->getResponseBody();
        $this->log('File Read Win!!!', true);
        $this->log('Result:' . print_r($file->content, true));
        renderJson($app, $file);
      } catch (Exception $ex) {
        renderEx($app, $ex);
      }
    });  
}
/**
 * Run a command
 */
// echo APP_FILE_ID;
// exit;
// 
// 
$build = new Build();
$build->log("Running " . COMMAND, true);

switch (COMMAND)
{
  case 'inspect':
    $build->log("Inspecting files", true);
    $build->retrieveAllFiles();
    
    break;
  case 'get':
   
    $build->log("Downloading file", true);
    
    $build->downloadFile(APP_FILE_ID);
    $build->log(".. finished", true);
    die();  
    // file_put_contents('result.txt', print_r(downloadFile($service, APP_FILE_ID), true));
    
    break;

  case 'read':

    $build->log("Downloading file", true);
    
    $build->opentTheFile(APP_FILE_ID);
    $build->log(".. finished", true);
    die();  
    // file_put_contents('result.txt', print_r(downloadFile($service, APP_FILE_ID), true));
    
    break;  

  default;
    break;


}

exit;
?>