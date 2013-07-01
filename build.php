<?php
require_once '../google-api-php-client/src/Google_Client.php';
require_once '../google-api-php-client/src/contrib/Google_DriveService.php';


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
}

exit;
?>