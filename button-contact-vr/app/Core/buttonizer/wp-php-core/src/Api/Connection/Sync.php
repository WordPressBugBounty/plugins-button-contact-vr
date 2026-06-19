<?php
/**
 * Sync API endpoint.
 *
 * Synchronizes site data with the Buttonizer cloud API.
 *
 * @package BZContactButton\Core\Api\Connection
 *
 * @license proprietary
 * Modified by buttonizer on 18-June-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace BZContactButton\Core\Api\Connection;

use BZContactButton\Core\PluginConfig;
use BZContactButton\Core\Utils\ApiRequest;
use BZContactButton\Core\Utils\Account;
use BZContactButton\Core\Utils\PermissionCheck;
use BZContactButton\Core\Utils\Settings;

/**
 * Sync API
 *
 * @methods POST
 */
class Sync
{
    /**
     * Register route
     */
    public function registerRoute()
    {
        register_rest_route(PluginConfig::name(), '/sync', [
            [
                'methods'  => ['POST'],
                'args' => [
                    'nonce' => [
                        'validate_callback' => function ($value) {
                            return wp_verify_nonce($value, 'wp_rest');
                        },
                        'required' => true
                    ],
                ],
                'callback' => [$this, 'sync'],
                'permission_callback' => function () {
                    return PermissionCheck::hasPermission();
                }
            ]
        ]);
    }

    /**
     * Sync data
     */
    public function sync()
    {
        // Sync data
        $result = ApiRequest::post("/sync");

        // Handle errors
        if (is_a($result, 'WP_Error')) {
            return $result;
        }

        // Set last synced at
        Settings::setSetting("last_synced_at", new \DateTime('now'));
        Settings::setSetting("include_page_data", isset($result->site->licensed) && $result->site->licensed);

        // Save synced info
        Settings::saveUpdatedSettings();

        // Sync account settings
        Account::syncToDatabase($result);

        return [
            'status' => 'success',
            'data' => Account::getData()
        ];
    }
}

