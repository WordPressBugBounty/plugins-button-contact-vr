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
 * https://buttonizer.io/license/
 */

namespace BZContactButton\Migration;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Puts Buttonizer on the site.
 *
 * Download, install, activate — and nothing else. What happens next is
 * Buttonizer's own business: on its next admin request it adopts this plugin's
 * connection and deactivates it. It only does so because this is where the
 * user asked for it, and that is written down before anything else happens.
 *
 * Shared by the two ways a user gets here: the button in the migration notice,
 * and finishing signup on a brand-new install.
 */
class Installer
{
    /** Buttonizer is installed and running. */
    const RESULT_DONE = 'done';

    /** This user may not install or activate plugins. */
    const RESULT_NO_PERMISSION = 'no_permission';

    /** The download or the unpacking failed. */
    const RESULT_INSTALL_FAILED = 'install_failed';

    /** Installed, but WordPress refused to activate it. */
    const RESULT_ACTIVATION_FAILED = 'activation_failed';

    /** Installed, but too old to take this plugin over. */
    const RESULT_TARGET_OUTDATED = 'target_outdated';

    /**
     * Install Buttonizer when it is missing, then activate it.
     *
     * @return string One of the RESULT_* constants.
     */
    public static function run(): string
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $installed = TargetPlugin::isInstalled();

        if (!current_user_can($installed ? 'activate_plugins' : 'install_plugins')) {
            return self::RESULT_NO_PERMISSION;
        }

        // Ahead of the install: whatever fails below, the Plugins screen can
        // finish it by hand and Buttonizer still knows it was asked for.
        TargetPlugin::markRequested();

        if (!$installed && !self::download()) {
            return self::RESULT_INSTALL_FAILED;
        }

        TargetPlugin::resetCache();

        $baseName = TargetPlugin::resolveBaseName();

        if (!$baseName) {
            return self::RESULT_INSTALL_FAILED;
        }

        // Whatever it was downloaded from may predate the migration code
        if (!TargetPlugin::hasMigrationSupport()) {
            return self::RESULT_TARGET_OUTDATED;
        }

        if (is_wp_error(activate_plugin($baseName))) {
            return self::RESULT_ACTIVATION_FAILED;
        }

        return self::RESULT_DONE;
    }

    /**
     * Download and install the target plugin from wordpress.org.
     */
    private static function download(): bool
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $api = plugins_api('plugin_information', [
            'slug'   => TargetPlugin::WP_ORG_SLUG,
            'fields' => ['sections' => false],
        ]);

        if (is_wp_error($api) || empty($api->download_link)) {
            return false;
        }

        $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());

        return $upgrader->install($api->download_link) === true;
    }
}
