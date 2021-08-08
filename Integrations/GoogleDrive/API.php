<?php

namespace FFexternalFileUpload\Integrations\GoogleDrive;

class API
{
    protected $optionKey = '_fluentform_' . FFGDRIVE_INT_KEY . '_settings';
    protected $baseUrl = 'https://www.googleapis.com/drive/v3/';
    protected $client;
    protected $service;
    protected $scopes = array('https://www.googleapis.com/auth/drive',);
    private $clientId = '706994733947-201mucfeg4eqopc2jip266q5n16s3tcc.apps.googleusercontent.com';
    private $clientSecret = 'cxDUmL7UGPtBo3kc96ihH4Rs';
    private $redirect = 'urn:ietf:wg:oauth:2.0:oob';

    public function __construct()
    {
        if (defined('FF_GDRIVE_CLIENT_ID')) {
            $this->clientId = FF_GDRIVE_CLIENT_ID;
        }

        if (defined('FF_GDRIVE_CLIENT_SECRET')) {
            $this->clientSecret = FF_GDRIVE_CLIENT_SECRET;
        }


    }

    public function generateAccessKey($token)
    {
        $body = [
            'code' => $token,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
        return $this->makeRequest('https://accounts.google.com/o/oauth2/token', $body, 'POST');
    }

    public function makeRequest($url, $bodyArgs, $type = 'GET', $headers = false)
    {
        if (!$headers) {
            $headers = array(
                'Content-Type' => 'application/http',
                'Content-Transfer-Encoding' => 'binary',
                'MIME-Version' => '1.0',
            );
        }

        $args = [
            'headers' => $headers
        ];
        if ($bodyArgs) {
            $args['body'] = json_encode($bodyArgs);
        }

        $args['method'] = $type;
        $request = wp_remote_request($url, $args);

        if (is_wp_error($request)) {
            $message = $request->get_error_message();
            return new \WP_Error(423, $message);
        }

        $body = json_decode(wp_remote_retrieve_body($request), true);
        if (!empty($body['error'])) {
            $error = 'Unknown Error';
            if (isset($body['error_description'])) {
                $error = $body['error_description'];
            } else {
                if (!empty($body['error']['message'])) {
                    $error = $body['error']['message'];
                }
            }
            return new \WP_Error(423, $error);
        }

        return $body;
    }

    public function getAUthUrl()
    {
        return 'https://accounts.google.com/o/oauth2/auth?access_type=offline&approval_prompt=force&client_id=' . $this->clientId . '&redirect_uri=urn%3Aietf%3Awg%3Aoauth%3A2.0%3Aoob&response_type=code&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fdrive';
    }

    public function folderList()
    {
        //query for folders
        $parameter = "mimeType='application/vnd.google-apps.folder' and 'root' in parents and trashed=false";
        $endPoint = 'files';
        return $this->makeRequest($this->baseUrl . $endPoint . '?q=' . $parameter, [], 'GET',
            $this->getStandardHeader());
    }

    private function getStandardHeader()
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getAccessToken()
        ];
    }

    public function getAccessToken()
    {
        $tokens = get_option($this->optionKey);

        if (!$tokens) {
            return false;
        }

        if (($tokens['created_at'] + $tokens['expires_in'] - 30) < time()) {
            // It's expired so we have to re-issue again
            $refreshTokens = $this->refreshToken($tokens);

            if (!is_wp_error($refreshTokens)) {
                $tokens['access_token'] = $refreshTokens['access_token'];
                $tokens['expires_in'] = $refreshTokens['expires_in'];
                $tokens['created_at'] = time();
                update_option($this->optionKey, $tokens, 'no');
            } else {
                return false;
            }
        }

        return $tokens['access_token'];
    }

    private function refreshToken($tokens)
    {
        $args = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $tokens['refresh_token'],
            'grant_type' => 'refresh_token'
        ];
        return $this->makeRequest('https://accounts.google.com/o/oauth2/token', $args, 'POST');
    }


    /**
     *  Upload file to given folder
     * @param string $parentfileId parent folder id or root where folder will be upload
     * @param string $filePath file local path of file which will be upload
     * @param string $fileName file name of the uploaded copy at google drive
     * @return string id of uploaded file
     */
    public function uploadFile($parentfileId, $filePath, $fileName = "none")
    {
        if ($fileName == "none") {
            $tmp = explode('/', $filePath);
            $fileName = end($tmp);
        }
        $localPath = wp_upload_dir()['basedir'] . FLUENTFORM_UPLOAD_DIR . '/' . basename($fileName);
        $file = $this->getFile($fileName, $localPath, $parentfileId);

        // Getting file into variable
        $fileContent = wp_remote_get($filePath, array(
            'sslverify' => false
        ));
        //set api client using google api   lib
        $client = $this->getClient();
        $service = new \Google_Service_Drive($client);
        //upload file data  using lib
        try {
            $uploadedFile = $service->files->create($file, array(
                'data' => $fileContent['body'],
                'mimeType' => mime_content_type($localPath),
                'uploadType' => 'multipart'
            ));
            if (!$uploadedFile->id) {
                return false;
            }
            return $uploadedFile->id;
        } catch (\Exception $e) {
            return new \WP_Error(423, $e->getMessage());
        }
        // todo: without api library

    }

    /**
     * @param bool|string $fileName
     * @param string $localPath
     * @param string $parentfileId
     * @return \Google_Service_Drive_DriveFile
     */
    protected function getFile($fileName, string $localPath, string $parentfileId)
    {
        $file = new \Google_Service_Drive_DriveFile();
        $file->setName($fileName);
        $file->setDescription('Uploaded by FF');
        $file->setMimeType(mime_content_type($localPath));
        $file->setParents([$parentfileId]);
        return $file;
    }

    /**
     * @return \Google_Client
     */
    protected function getClient(): \Google_Client
    {
        $client = new \Google_Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirect);
        $client->setScopes($this->scopes);
        $client->setAccessToken($this->getAccessToken());
        return $client;
    }

}
