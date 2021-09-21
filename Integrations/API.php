<?php

namespace FluentFormDropbox\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class API
{
    protected $apiUrl = 'https://api.dropboxapi.com/';
    
    protected $apiKey = 'wjffmo1ds5htae2';
    
    protected $apiSecret = 'y5z8lcxmsjnosmz';
    
    protected $optionKey = '_fluentform_'.FFDROPBOX_INT_KEY.'_settings';
    
    
    public function default_options()
    {
        
        
        return [
            'Authorization' => 'Basic',
            'Content-Type' => 'application/json'
        ];
    }
    
    public function make_request($action, $options, $method = 'GET',$headers="")
    {
        
        $endpointUrl = $this->apiUrl . $action;
        
        if($headers) {
            $args = [
                'headers' => $headers
            ];
        }
        
        if ($options) {
            $args['body'] = $options;
        }
        
        /* Execute request based on method. */
        switch ($method) {
            case 'POST':
                $response = wp_remote_post($endpointUrl, $args);
                break;
            
            case 'GET':
                $response = wp_remote_get($endpointUrl, $args);
                break;
        }
        if(is_wp_error($response) || is_wp_error($response['response'])) {
            $message = $response->get_error_message();
            return new \WP_Error(423, $message);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if(!empty($body['error'])) {
            $error = 'Unknown Error';
            if(isset($body['error_description'])) {
                $error = $body['error_description'];
            } else if(!empty($body['error']['message'])) {
                $error = $body['error']['message'];
            }
            return new \WP_Error(423, $error);
        }
        
        return $body;
    }
    
    
    public function generateAccessKey($token)
    {
        
        $body = [
            'code' => $token,
            'grant_type' => 'authorization_code',
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
            // 'redirect_uri' => site_url(),
        ];
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json'
        ];
        return $this->make_request('oauth2/token', $body, 'POST',false);
        
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
    
    
    public function getAUthUrl(){
        
        return 'https://www.dropbox.com/oauth2/authorize?client_id='.$this->apiKey.'&token_access_type=offline&response_type=code';
        
    }
    
    
    private function refreshToken($tokens)
    {
        $args = [
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
            'refresh_token' => $tokens['refresh_token'],
            'grant_type' => 'refresh_token'
        ];
        
        return $this->make_request('oauth2/token', $args, 'POST');
    }
    
    
 
    private function getStandardHeader()
    {
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => 'Bearer '.$this->getAccessToken()
        ];
    }
    
    public function fileList(){
        
        $headers =$this->getStandardHeader();
        $options = [
            'path'=>'',
            'limit'=>100
        ];
        $options = json_encode ($options);
    
        return   $this->make_request ('2/files/list_folder',$options,'POST', $headers );
        
        
    }
    
    public function fileUpload($files,$uploadFolderPath,$formData){
        
        $temp =    explode ('/',$files);
        $fileName = array_pop ( $temp);
        
        $headers = [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'Content-Type' => 'application/octet-stream',
            'Dropbox-API-Arg' => json_encode([
                'path' => $uploadFolderPath.'/'.$fileName,
                'mode' => 'add',
                'autorename'=>true,
                'mute' => true,
                'strict_conflict' => false
            ]),
        ];
        
        $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
        $path = $DOCUMENT_ROOT. '/wp-content/uploads/fluentform/' . $fileName;
        $filesize = filesize($path);
        $fp =  fopen($path, "rb");
        $body = fread($fp, $filesize);
        if(!$body){
            return new \WP_Error(423, 'File open failed !');
        }
        
        $args = [
            'headers' => $headers,
            'body'=> $body
        ];
        $endpointUrl = 'https://content.dropboxapi.com/2/files/upload';
        
        
        $response = wp_remote_post($endpointUrl, $args);
        
        
        if(is_wp_error($response) || is_wp_error($response['response'])) {
            $message = $response->get_error_message();
            return new \WP_Error(423, $message);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        
        if(!empty($body['error'])) {
            $error = 'Unknown Error';
            if(isset($body['error_description'])) {
                $error = $body['error_description'];
            } else if(!empty($body['error']['message'])) {
                $error = $body['error']['message'];
            }
            return new \WP_Error(423, $error);
        }
        
        
        return $body;
        
        
    }
    
    public function createSharedLink($dropBoxFilePath)
    {
        $headers =$this->getStandardHeader();
        $options = [
            'path'=>$dropBoxFilePath,
        ];
        $options = json_encode ($options);
        return   $this->make_request ('2/sharing/create_shared_link_with_settings',$options,'POST', $headers );
    }
    
    
}
