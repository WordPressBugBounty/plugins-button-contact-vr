<?php
/**
 * Base API route registration.
 *
 * Registers shared REST API endpoints common to all Buttonizer plugins.
 * Plugin-specific Api.php extends this and adds additional routes.
 *
 * @package BZContactButton\Core\Api
 *
 * @license proprietary
 * Modified by buttonizer on 18-June-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace BZContactButton\Core\Api;

class BaseApi
{
    /**
     * Register shared API endpoints.
     */
    public function __construct()
    {
        // Site synchronization
        (new Connection\Sync())->registerRoute();
        (new Connection\Disconnect())->registerRoute();
        (new Connection\Connect())->registerRoute();

        // Plugin settings
        (new Settings\UpdateSettings())->registerRoute();

        // Editor
        (new Connection\StartEditorSession())->registerRoute();

        // Analytics
        (new Analytics\Overview())->registerRoute();
    }
}

