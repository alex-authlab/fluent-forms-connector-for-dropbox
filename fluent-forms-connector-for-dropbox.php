<?php
/**
 * Plugin Name: Fluent Forms Connector for Dropbox
 * Plugin URI:  #
 * Description: Connect Fluent Forms with Dropbox.
 * Author: WPManageNinja Support Team
 * Author URI:  #
 * Version: 1.0.0
 * Text Domain: FFDROPBOX
 */

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright 2019 WPManageNinja LLC. All rights reserved.
 */


defined('ABSPATH') or die;
define('FFDROPBOX_DIR', plugin_dir_path(__FILE__));
define('FFDROPBOX_URL', plugin_dir_url(__FILE__));
define('FFDROPBOX_INT_KEY', 'dropbox');
define('FFGDRIVE_INT_KEY', 'googledrive');

class FFexternalFileUpload
{

    public function boot()
    {
    
    
        if (!defined('FLUENTFORM')) {
            return $this->injectDependency();
        }

        $this->includeFiles();

        if (function_exists('wpFluentForm')) {
            return $this->registerHooks(wpFluentForm());
        }
    }

    protected function includeFiles()
    {
//        include_once FFDROPBOX_DIR . 'DropboxIntegration/Bootstrap.php';
//        include_once FFDROPBOX_DIR . 'DropboxIntegration/API.php';
        require_once 'vendor/autoload.php';
    }

    protected function registerHooks($fluentForm)
    {
       
       new \FFexternalFileUpload\DropboxIntegration\Bootstrap( $fluentForm );
       new FFexternalFileUpload\GoogleDrive\Bootstrap( $fluentForm );

    }
    
    /**
     * Notify the user about the FluentForm dependency and instructs to install it.
     */
    protected function injectDependency()
    {
        add_action('admin_notices', function () {
            $pluginInfo = $this->getFluentFormInstallationDetails();

            $class = 'notice notice-error';

            $install_url_text = 'Click Here to Install the Plugin';

            if ($pluginInfo->action == 'activate') {
                $install_url_text = 'Click Here to Activate the Plugin';
            }

            $message = 'FluentForm MailPoet Add-On Requires Fluent Forms Add On Plugin, <b><a href="' . $pluginInfo->url
                . '">' . $install_url_text . '</a></b>';

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        });
    }

    protected function getFluentFormInstallationDetails()
    {
        $activation = (object)[
            'action' => 'install',
            'url' => ''
        ];

        $allPlugins = get_plugins();

        if (isset($allPlugins['fluentform/fluentform.php'])) {
            $url = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=fluentform/fluentform.php'),
                'activate-plugin_fluentform/fluentform.php'
            );

            $activation->action = 'activate';
        } else {
            $api = (object)[
                'slug' => 'fluentform'
            ];

            $url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $api->slug),
                'install-plugin_' . $api->slug
            );
        }

        $activation->url = $url;

        return $activation;
    }
}

register_activation_hook(__FILE__, function () {
    $globalModules = get_option('fluentform_global_modules_status');
    if (!$globalModules || !is_array($globalModules)) {
        $globalModules = [];
    }

    $globalModules[FFDROPBOX_INT_KEY] = 'yes';
    $globalModules[FFGDRIVE_INT_KEY] = 'yes';
    update_option('fluentform_global_modules_status', $globalModules);
});

add_action('plugins_loaded', function () {
    (new FFexternalFileUpload())->boot();
});
