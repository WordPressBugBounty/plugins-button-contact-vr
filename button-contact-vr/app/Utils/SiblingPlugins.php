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

namespace BZContactButton\Utils;

/**
 * Detects other Buttonizer plugins and allows sharing connection data.
 * This avoids requiring the user to sign up again if they already
 * connected through another Buttonizer plugin on the same site.
 */
class SiblingPlugins
{
    /**
     * Known sibling Buttonizer plugins with their option prefixes.
     */
    private static $siblings = [
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
    ];

    /**
     * Find a sibling plugin that has an active Buttonizer connection.
     *
     * @return array|null Array with token, settings and account data, or null if none found.
     */
    public static function findConnectedSibling(): ?array
    {
        foreach (self::$siblings as $name => $options) {
            $settings = get_option($options['settings_option'], []);

            // Check if the sibling has finished setup
            if (!isset($settings['finished_setup']) || $settings['finished_setup'] === false) {
                continue;
            }

            // Check if the sibling has a valid token
            $token = get_option($options['token_option'], null);

            if (empty($token)) {
                continue;
            }

            // Get account data
            $account = get_option($options['account_option'], []);

            return [
                'source'   => $name,
                'token'    => $token,
                'settings' => $settings,
                'account'  => $account,
            ];
        }

        return null;
    }

    /**
     * Copy connection data from a sibling plugin into this plugin (Button Contact).
     * This replicates what Connect.php does after a successful signup, but using
     * data from an already-connected sibling.
     *
     * @return bool Whether the connection was successfully copied.
     */
    public static function copyConnectionFromSibling(): bool
    {
        $sibling = self::findConnectedSibling();

        if (!$sibling) {
            return false;
        }

        // Save the token
        ApiRequest::saveApiToken($sibling['token']);

        // Copy relevant settings
        $siblingSettings = $sibling['settings'];

        Settings::setSetting("finished_setup", true);
        Settings::setSetting("installed_at", $siblingSettings['installed_at'] ?? new \DateTime('now'));
        Settings::setSetting("last_synced_at", new \DateTime('now'));
        Settings::setSetting("site_id", $siblingSettings['site_id'] ?? null);
        Settings::setSetting("include_page_data", $siblingSettings['include_page_data'] ?? false);
        Settings::saveUpdatedSettings();

        // Copy account data
        $account = $sibling['account'];

        if (!empty($account)) {
            update_option(BZ_CONTACT_BUTTON_NAME . '_account', $account);
        }

        return true;
    }

    /**
     * Disconnect all sibling Buttonizer plugins.
     * Clears their token, resets finished_setup and site_id, and empties account data.
     *
     * @return void
     */
    public static function disconnectAllSiblings(): void
    {
        foreach (self::$siblings as $name => $options) {
            $settings = get_option($options['settings_option'], []);

            // Only disconnect siblings that are actually connected
            if (!isset($settings['finished_setup']) || $settings['finished_setup'] === false) {
                continue;
            }

            // Reset connection settings
            $settings['finished_setup'] = false;
            $settings['last_synced_at'] = null;
            $settings['site_id'] = null;
            update_option($options['settings_option'], $settings);

            // Remove token
            delete_option($options['token_option']);
            delete_transient($options['token_option']);

            // Empty account data
            update_option($options['account_option'], []);
        }
    }
}
