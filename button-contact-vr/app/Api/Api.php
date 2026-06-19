<?php
/*
 * SOFTWARE LICENSE INFORMATION
 *
 * Copyright (c) 2017 Buttonizer, all rights reserved.
 *
 * This file is part of Buttonizer
 *
 * For detailed information regarding to the licensing of
 * this software, please review the license.txt or visit:
 * https://buttonizer.pro/license/
 */

namespace BZContactButton\Api;

use BZContactButton\Core\Api\BaseApi;

class Api extends BaseApi
{
    /**
     * Register API endpoints.
     *
     * Shared routes are registered by BaseApi.
     * Legacy routes are plugin-specific.
     */
    public function __construct()
    {
        // Register shared routes (Connect, Disconnect, Sync, Settings, Editor, Analytics)
        parent::__construct();

        // Legacy (plugin-specific)
        (new Utils\DeleteLegacyBackup())->registerRoute();
        (new Utils\RevertToLegacy())->registerRoute();
    }
}
