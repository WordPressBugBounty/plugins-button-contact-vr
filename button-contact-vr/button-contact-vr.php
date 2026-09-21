<?php

/**
 * Plugin Name: Buttonizer - Live Chat, AI Chatbot, Call, Chat, Contact Button
 * Plugin URI: https://buttonizer.io
 * Description: Powerful platform with Live Chat, AI Chatbots, and Real-Time Visitor Monitoring! Also, create Call, Email, SMS, & Contact buttons to increase conversions. Supports WhatsApp, Messenger, Live Chat, and 40+ other actions.
 * Version: 5.1.0
 * Author: Buttonizer
 * Author URI: https://buttonizer.io
 * License: GPLv2
 *
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

if (! defined('ABSPATH')) {
    exit;
}

// Is legacy plugin user
$legacyUser = get_option("button_contact_legacy", 'undefined') === "yes";

// Nothing defined yet, check if any option is defined
if (get_option("button_contact_legacy", 'undefined') === "undefined") {
    require_once __DIR__ . "/legacy/legacy-detector.php";
}

// Load legacy version if detected / chosen
if ($legacyUser || defined("BZ_CONTACT_BUTTON_USE_LEGACY")) {
    define('BZ_CONTACT_BUTTON_MAIN_FILE', __FILE__);
    define('BZ_CONTACT_BUTTON_PLUGIN_FILE', __FILE__);

    // Needed before the decision below, and for the notice after it.
    require_once __DIR__ . "/EnvVars.php";
    require_once __DIR__ . "/app/autoloader.php";

    // Buttonizer may already be serving this exact system from its embedded
    // module — it is the same code, declared in the global namespace, so a
    // second copy is a fatal redeclaration.
    //
    // This plugin loads first, so it is the one that steps aside: the user
    // migrated, and reactivating this plugin afterwards should not move their
    // screens back out of the menu they have been using since. The class check
    // stays as the last word for anything that gets here another way.
    $buttonizerServesIt = \BZContactButton\Migration\TargetPlugin::servesOurLegacySystem();

    if (!$buttonizerServesIt && !class_exists('PZF', false) && !defined('PZF_FILE')) {
        // Load in legacy
        require_once __DIR__ . "/legacy/plugin.php";
    }

    // Offer the move to Buttonizer here too. Legacy users are exactly who the
    // embedded module is for, and without this they would never be told.
    \BZContactButton\Migration\InstallNotice::boot();
} else {
    // Define current version
    define('BZ_CONTACT_BUTTON_VERSION', '5.1.0');
    define('BZ_CONTACT_BUTTON_PLUGIN_FILE', __FILE__);

    // Autoloader
    require_once __DIR__ . "/app/autoloader.php";

    // Get environment vars
    require_once __DIR__ . "/EnvVars.php";

    // Initialize shared core configuration
    \BZContactButton\Core\PluginConfig::init([
        'name'        => BZ_CONTACT_BUTTON_NAME,
        'dir'         => BZ_CONTACT_BUTTON_DIR,
        'app_dir'     => BZ_CONTACT_BUTTON_APP_DIR,
        'slug'        => BZ_CONTACT_BUTTON_SLUG,
        'version'     => BZ_CONTACT_BUTTON_VERSION,
        'plugin_file' => BZ_CONTACT_BUTTON_PLUGIN_FILE,
        'base_name'   => BZ_CONTACT_BUTTON_BASE_NAME,
        'api_uri'     => BZ_CONTACT_BUTTON_API_URI,
        'api_timeout' => defined("BZ_CONTACT_BUTTON_API_TIMEOUT") ? BZ_CONTACT_BUTTON_API_TIMEOUT : 20,
        'page_slug'   => 'bz_button_contact',
        'text_domain' => 'button-contact-vr',
        'review_url'  => 'https://wordpress.org/support/plugin/button-contact-vr/reviews/?utm_source=wp-plugin-request-review-btn',
        'product_name' => 'Chat Button',
        'notice_id'   => 'bz-contact-button-admin-notice',
        'notice_js_function' => 'bzContactButtonAdminNotice',
        'referral_slug' => 'wp-contact-button-plugin-menu',
        'siblings'    => [
            'buttonizer' => [
                'token_option'    => 'buttonizer_site_connection',
                'settings_option' => 'buttonizer_settings',
                'account_option'  => 'buttonizer_account',
            ],
            'bz_social_feeds' => [
                'token_option'    => 'bz_social_feeds_site_connection',
                'settings_option' => 'bz_social_feeds_settings',
                'account_option'  => 'bz_social_feeds_account',
            ],
        ],
    ]);

    // Initialize
    require_once __DIR__ . "/init.php";
}

// Uninstall. Registered whichever code path ran: a legacy install is exactly
// the one whose bookkeeping has to go when the plugin does.
register_uninstall_hook(__FILE__, 'bzContactButtonUninstallEvent');

function bzContactButtonUninstallEvent()
{
    // A deleted plugin should not leave the site remembering a migration.
    // Ahead of the disconnect below, which returns early on any install that
    // never had an account — every legacy site among them.
    if (class_exists('\\BZContactButton\\Migration\\TargetPlugin')) {
        \BZContactButton\Migration\TargetPlugin::forgetMigrationState();
    }

    // A legacy install never had a cloud connection, and its own code path
    // never calls PluginConfig::init() — nothing below this can run safely
    // without it, ApiRequest included.
    if (
        class_exists('\\BZContactButton\\Migration\\RunningSystem') &&
        \BZContactButton\Migration\RunningSystem::isLegacy()
    ) {
        return;
    }

    // Only handle uninstall for the new (non-legacy) code path
    if (!class_exists('\\BZContactButton\\Core\\Utils\\ApiRequest')) {
        return;
    }

    if (\BZContactButton\Core\Utils\ApiRequest::getApiToken() === false) {
        return;
    }

    try {
        (new \BZContactButton\Core\Api\Connection\Disconnect)->disconnect(false);
    } catch (\Error $err) {
        // Errored out, nevermind then
    }
}
