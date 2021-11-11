<?php

namespace FFexternalFileUpload\Integrations\GoogleDrive;

use FFexternalFileUpload\Integrations\GoogleDrive\API;
use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;


class Bootstrap extends IntegrationManager
{
    private $key = FFGDRIVE_INT_KEY;

    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            ucfirst($this->key),
            $this->key,
            '_fluentform_' . $this->key . '_settings',
            $this->key . '_feeds',
            98
        );
        $this->logo = FFDROPBOX_URL . 'assets/gdrive.png';
        $this->description = 'Connect Google Drive with FluentForm. Upload files directly to drive from your forms.';
        $this->registerAdminHooks();
//        add_filter('fluentform_notifying_async_' . FFGDRIVE_INT_KEY, '__return_false');
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => admin_url(
                'admin.php?page=fluent_forms_settings#general-' . $this->key . '-settings'
            ),
            'configure_message' => $this->key . ' is not configured yet! Please configure your ' . $this->key . ' api first',
            'configure_button_text' => 'Set ' . $this->key
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
            'logo' => $this->logo,
            'menu_title' => __(ucfirst($this->key) . ' Integration Settings', FF_DROPBOX_GDRIVE_TEXTDOM),
            'menu_description' => __(
                'Copy the ' . ucfirst(
                    $this->key
                ) . ' Access Code from other window and paste it here, then click on Verify Code button.',
                FF_DROPBOX_GDRIVE_TEXTDOM
            ),
            'valid_message' => __('Your ' . ucfirst($this->key) . ' API Key is valid', FF_DROPBOX_GDRIVE_TEXTDOM),
            'invalid_message' => __('Your ' . ucfirst($this->key) . ' API Key is not valid', FF_DROPBOX_GDRIVE_TEXTDOM),
            'save_button_text' => __('Save Settings', FF_DROPBOX_GDRIVE_TEXTDOM),
            'fields' => [
                'apiKey' => [
                    'type' => 'text',
                    'placeholder' => 'Access Code',
                    'label_tips' => __(
                        "Enter your  " . ucfirst(
                            $this->key
                        ) . " Access Key, Copy the Access Code from other window and paste it here, then click on Verify Code button",
                        FF_DROPBOX_GDRIVE_TEXTDOM
                    ),
                    'label' => __(ucfirst($this->key) . ' Access Code', FF_DROPBOX_GDRIVE_TEXTDOM),
                ],
                'button_link' => [
                    'type' => 'link',
                    'link_text' => 'Get ' . $this->key . ' Access Code',
                    'link' => $api->getAUthUrl(),
                    'target' => '_blank',
                    'tips' => 'Please click on this link get get Access Code From ' . $this->key
                ]
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => 'Your ' . $this->key . ' API integration is up and running',
                'button_text' => 'Disconnect ' . $this->key,
                'data' => [
                    'apiKey' => ''
                ],
                'show_verify' => true
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
                'message' => __('Your settings has been updated', FF_DROPBOX_GDRIVE_TEXTDOM),
                'status' => false
            ], 200);
        }


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
            'message' => __('Your Google DRIVE api key has been verified and successfully set', FF_DROPBOX_GDRIVE_TEXTDOM),
            'status' => true
        ], 200);
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'list_id' => '',
            'delete_files' => false,
            'shared_link' => false,
            'files' => '',
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        $api = new API();

        return [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => 'Folder name',
                    'placeholder' => 'Select folder to upload',
                    'required' => true,
                    'tips' => 'Select the Folder you would like to add your files to.',
                    'component' => 'list_ajax_options',
                    'options' => $this->getFolderLists(),
                ],
                [
                    'key' => 'files',
                    'label' => 'Select file input field',
                    'required' => true,
                    'placeholder' => 'files to upload',
                    'component' => 'value_text'
                ],
                [
                    'key' => 'conditionals',
                    'label' => 'Conditional Logics',
                    'tips' => 'Allow DemoIntegration integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ],

            ],
            'button_require_list' => true,
            'integration_title' => $this->title
        ];
    }

    /**
     * Get dropbox folder List
     * @return array
     */
    protected function getFolderLists()
    {
        $formattedList = [];

        $api = new API();
        $folders = $api->folderList();
        if ($folders['files']) {
            foreach ($folders['files'] as $folder) {
                $formattedList[$folder['id']] = $folder['name'];
            }
        }


        return $formattedList;
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return [];
    }

    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];

        $files = explode(',', $feedData['files']);

        // need to improve this using parser maybe
        // fetch input field name which was selected for uploading
        $uploadFieldName = array_search($files[0], $entry->user_inputs);
        preg_match('/{(.*?)}/', $feed['settings']['files'], $uploadFieldName);
        $temp = array_pop($uploadFieldName);
        $uploadFieldName = explode('.', $temp);
        $uploadFieldName = array_pop($uploadFieldName);

        if (!isset($formData[$uploadFieldName])) {
            return;
        }


        $uploadFolderID = $feedData['list_id'];

        if (empty($files) || empty($uploadFolderID)) {
            do_action('ff_integration_action_result', $feed, 'failed', 'Missing file and folder path');
            return;
        }

        $api = new API();
        $responseArray = [];
        foreach ($files as $file) {
            $response = $api->uploadFile($uploadFolderID, $file);
            $responseArray[] = $response;
            if (is_wp_error($response)) {
                do_action('ff_integration_action_result', $feed, 'failed', $response->get_error_message());
                return;
            }
        }
        do_action('ff_integration_action_result', $feed, 'success', 'Drive Upload Success');
    }


}
