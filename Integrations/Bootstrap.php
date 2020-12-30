<?php

namespace FluentFormDropbox\Integrations;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;


class Bootstrap extends IntegrationManager
{
    private $key = FFDROPBOX_INT_KEY;
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            ucfirst ($this->key),
            $this->key,
            '_fluentform_'.$this->key.'_settings',
            $this->key.'_feeds',
            98
        );

        $this->logo = FFDROPBOX_URL . 'assets/dropbox.png';

        $this->description = 'Connect Dropbox with FluentForm. Upload files directly to Dropbox from your forms. Option to update database with shared file link & delete files from your server to free up space.';

        $this->registerAdminHooks();

        //add_filter('fluentform_notifying_async_dropbox', '__return_false');
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url'  => admin_url('admin.php?page=fluent_forms_settings#general-'.$this->key.'-settings'),
            'configure_message' => $this->key.' is not configured yet! Please configure your '.$this->key.' api first',
            'configure_button_text' => 'Set '.$this->key
        ];
        return $integrations;
    }
    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'apiKey' => '',
            'status' => ''
        ];
        
        return wp_parse_args($globalSettings, $defaults);
    }
    public function getGlobalFields($fields)
    {
        
        $api = new API();
        return [
            'logo'             => $this->logo,
            'menu_title'       => __(ucfirst($this->key).' Integration Settings', 'fluentformpro'),
            'menu_description' => __('Copy the '.ucfirst($this->key).' Access Code from other window and paste it here, then click on Verify Code button.','fluentformpro'),
            'valid_message'    => __('Your '.ucfirst ($this->key).' API Key is valid', 'fluentformpro'),
            'invalid_message'  => __('Your '.ucfirst ($this->key).' API Key is not valid', 'fluentformpro'),
            'save_button_text' => __('Save Settings', 'fluentformpro'),
            'fields'           => [
                'apiKey' => [
                    'type'        => 'text',
                    'placeholder' => 'Access Code',
                    'label_tips'  => __("Enter your  ".ucfirst($this->key)." Access Key, Copy the Access Code from other window and paste it here, then click on Verify Code button", 'fluentformpro'),
                    'label'       => __(ucfirst ($this->key).' Access Code', 'fluentformpro'),
                ],
                'button_link' => [
                    'type'  => 'link',
                    'link_text' => 'Get '.$this->key.' Access Code',
                    'link'   => $api->getAUthUrl(),
                    'target' => '_blank',
                    'tips'   => 'Please click on this link get get Access Code From '.$this->key
                ]
            ],
            'hide_on_valid'    => true,
            'discard_settings' => [
                'section_description' => 'Your '.$this->key.' API integration is up and running',
                'button_text'         => 'Disconnect '.$this->key,
                'data'                => [
                    'apiKey' => ''
                ],
                'show_verify'         => true
            ]
        ];
    }
    
    public function saveGlobalSettings($settings)
    {
        if (empty($settings['apiKey'])) {
            $integrationSettings = [
                'apiKey' => '',
                'status' => false
            ];
            // Update the reCaptcha details with siteKey & secretKey.
            update_option($this->optionKey, $integrationSettings, 'no');
            wp_send_json_success([
                'message' => __('Your settings has been updated', 'fluentformpro'),
                'status' => false
            ], 200);
        }
        
        // Verify API key now

        try {
            $accessCode = sanitize_textarea_field($settings['apiKey']);
            $api = new API();
            
            $result = $api->generateAccessKey($accessCode);
            
            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }
            
            $result['access_code'] = $accessCode;
            $result['created_at'] = time();
            $result['status'] = true;
            
            update_option($this->optionKey, $result, 'no');
            
        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 400);
        }
        
        wp_send_json_success([
            'message' => __('Your DRIVE api key has been verified and successfully set', 'fluentformpro'),
            'status' => true
        ], 200);
        
        
    }
    
    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name'         => '',
            'list_id'      => '',
            'delete_files' => false,
            'shared_link' => false,
            'files'       => '',
            'conditionals' => [
                'conditions' => [],
                'status'     => false,
                'type'       => 'all'
            ],
            'enabled'      => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        $api = new API();
    
        return [
            'fields'              => [
                [
                    'key'         => 'name',
                    'label'       => 'Name',
                    'required'    => true,
                    'placeholder' => 'Your Feed Name',
                    'component'   => 'text'
                ],
                [
                    'key'         => 'list_id',
                    'label'       => 'Folder name',
                    'placeholder' => 'Select folder to upload',
                    'required'    =>  true,
                    'tips'        => 'Select the DemoIntegration Group you would like to add your files to.',
                    'component'   => 'list_ajax_options',
                    'options'     => $this->getLists(),
                ],
                [
                    'key'         => 'files',
                    'label'       => 'Select file input field',
                    'required'    =>  true,
                    'placeholder' => 'files to upload',
                    'component'   => 'value_text'
                ],
                [
                    'key'            => 'delete_files',
                    'label'          => 'Delete from WordPress after Uploading to DropBox',
                    'component'      => 'checkbox-single',
                    'checkbox_label' => 'Delete Files'
                ],
                [
                    'key'            => 'shared_link',
                    'label'          => 'Create Dropbox share link and store in Database',
                    'component'      => 'checkbox-single',
                    'checkbox_label' => 'Shared Link'
                ],
                [
                    'key'          => 'conditionals',
                    'label'        => 'Conditional Logics',
                    'tips'         => 'Allow DemoIntegration integration conditionally based on your submission values',
                    'component'    => 'conditional_block'
                ],
                [
                    'key'            => 'enabled',
                    'label'          => 'Status',
                    'component'      => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ],
        
            ],
            'button_require_list' => true,
            'integration_title'   => $this->title
        ];
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return [];
    }
    
    
    /**
     * Get dropbox folder List
     * @return array
     */
    protected function getLists()
    {
        $formattedList = [];
    
        $api = new API();
        $folders = $api->fileList();
  
        if ($folders['entries']) {
            foreach ($folders['entries'] as $f) {
            
                if($f['.tag']=="folder"){
                    $formattedList[$f['path_lower']] = $f['name'];
                }
            }
        }
    
    
        return $formattedList;
    }
    
    /**
     * Delete uploaded file from server
     * @param $deletableFiles
     * @param $feed
     */
    private function deleteFiles($deletableFiles, $feed)
    {
        if ($deletableFiles) {
            $success = false;
            foreach ($deletableFiles as $fileUrl) {
                $temp = explode ('/',$fileUrl);
                $fileName = array_pop ($temp);
                $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
                $filePath = $DOCUMENT_ROOT. '/wp-content/uploads/fluentform/' . $fileName;
                
                if(is_readable($filePath) && !is_dir($filePath)) {
                    $success = unlink($filePath);
                }
                
            }
            if($success){
                do_action('ff_integration_action_result', $feed, 'success', 'Dropbox feed file deletion has been successfully completed');
            }
    
    
        }
        
    }
    
    /**
     * Create shared link for dropbox files
     * @param array $responseArray
     * @return array|mixed|\WP_Error
     */
    private function createSharedLink(array $responseArray)
    {
        //create shared link from dropbox
        $sharedLinkArray = [];
        $api = new API();
        foreach ($responseArray as $res){
            $sharedLink = $api->createSharedLink ($res['path_lower']);
            if (is_wp_error($sharedLink)) {
                return $sharedLink;
            }
            $sharedLinkArray[] =  $sharedLink['url'];
            
        }
        return $sharedLinkArray;
    }
    
    /**
     * Update database with the shared link from dropbox
     * @param       $entry
     * @param array $sharedLinkArray
     * @param       $uploadFieldName
     * @param       $feed
     */
    private function updateDBWithSharedLink($entry, array $sharedLinkArray, $uploadFieldName , $feed)
    {
        // update database database with the shared link array
        
        if(is_array ($sharedLinkArray)){
            
            //update from entry
            $entryData = wpFluent()->table('fluentform_submissions')
                                   ->select('response')
                                   ->where('id', $entry->id)
                                   ->where('form_id', $entry->form_id)
                                   ->first();
            
            $decoded =   json_decode ($entryData->response,true );
            if($decoded[$uploadFieldName]){
                
                //override the selected input with shared link
                $decoded[$uploadFieldName] = $sharedLinkArray;
                $update = wpFluent()->table('fluentform_submissions')
                                    ->select('response')
                                    ->where('id', $entry->id)
                                    ->where('form_id', $entry->form_id)
                                    ->update([
                                        'response' => json_encode ( $decoded )
                                    ]);
                //update from entry details
                foreach ( $sharedLinkArray as $key => $value ){
                 
                 $update =   wpFluent()->table('fluentform_entry_details')
                              ->where('submission_id', $entry->id)
                              ->where('form_id', $entry->form_id)
                              ->where('field_name', $uploadFieldName)
                              ->update([
                                  'field_value' => $value,
                                  'sub_field_name' => $key
                              ]);
                }
                
                do_action('ff_log_data', [
                    'parent_source_id' => $entry->id,
                    'source_type'      => 'submission_item',
                    'source_id'        => $entry->id,
                    'component'        => $this->key,
                    'status'           => 'success',
                    'title'            => $feed['settings']['name'],
                    'description'      => 'Entry updated with Dropbox shared File link'
                ]);
                
            }else{
                do_action('ff_log_data', [
                    'parent_source_id' => $entry->id,
                    'source_type'      => 'submission_item',
                    'source_id'        => $entry->id,
                    'component'        => $this->key,
                    'status'           => 'failed',
                    'title'            => $feed['settings']['name'],
                    'description'      => 'Entry update failed with Dropbox shared link'
                ]);
            }
            
        }
    }
    
    
    /*
     * Form Submission Hooks Here
     */
    public function notify($feed, $formData, $entry, $form)
    {
        
        
        $feedData = $feed['processedValues'];
        
        $files = explode (',',$feedData['files']);
   
        // need to improve this using parser maybe
        // fetch input field name which was selected for uploading
        $uploadFieldName = array_search($files[0] ,$entry->user_inputs );
        preg_match ('/{(.*?)}/',$feed['settings']['files'],$uploadFieldName);
        $temp =  array_pop ( $uploadFieldName);
        $uploadFieldName = explode ('.',$temp);
        $uploadFieldName = array_pop ($uploadFieldName);
       
    
        $uploadFolderPath = $feedData['list_id'];
    
        if (empty($files) || empty($uploadFolderPath)) {
            
            do_action('ff_integration_action_result', $feed, 'failed', 'Missing file and folder path');
            return;
        }
      
    
        $api = new API();
        $responseArray = [];
        
        //upload files
        foreach ($files as $file){
            $response = $api->fileUpload($file,$uploadFolderPath,$formData);
            $responseArray[] = $response;
            if (is_wp_error($response)) {
                do_action('ff_integration_action_result', $feed, 'failed', $response->get_error_message());
                return;
            }
        }
       
    
    
        if($responseArray[0]['is_downloadable'] == true){
             
             

                do_action('ff_integration_action_result', $feed, 'success', 'Dropbox feed has been successfully initialed and pushed file(s)');
                
                // delete files
                if( $feedData['delete_files'] == true ){
                    $deleteFileArray = $files;
                    $this->deleteFiles( $deleteFileArray , $feed );
                }
                
                //create shared link
                if( $feedData['shared_link'] == true ){
        
                    $sharedLinkArray = $this->createSharedLink( $responseArray );
    
                    
                    if (is_wp_error( $sharedLinkArray )) {
                        do_action('ff_log_data', [
                            'parent_source_id' => $entry->id,
                            'source_type'      => 'submission_item',
                            'source_id'        => $entry->id,
                            'component'        => $this->key,
                            'status'           => 'failed',
                            'title'            => $feed['settings']['name'],
                            'description'      => 'Dropbox file share link creation failed'
                        ]);
                        return;
                    }
                    $this->updateDBWithSharedLink($entry,$sharedLinkArray,$uploadFieldName, $feed);
                    
                  
                    
                }
        
            }
    
    }
    
    
    
    
}
