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
 * The plugin this one hands its users over to.
 *
 * Mirrors the SourcePlugin descriptor on the Buttonizer side: the folder name
 * differs between the development checkout and the wordpress.org build, so
 * the basename is resolved at runtime instead of being hardcoded.
 */
class TargetPlugin
{
    /** wordpress.org slug, used for the one-click install. */
    const WP_ORG_SLUG = 'buttonizer-multifunctional-button';

    /** Text domain, used to recognise an install under any folder name. */
    const TEXT_DOMAIN = 'buttonizer-multifunctional-button';

    /** Admin page slug of the target plugin. */
    const PAGE_SLUG = 'Buttonizer';

    /** Where the target plugin records what it has absorbed. */
    const MIGRATION_STATE_OPTION = 'buttonizer_migration_state';

    /** This plugin's module id in the target's registry. */
    const MODULE_ID = 'chat-button';

    /**
     * Result the target records when it embeds this plugin's old system.
     *
     * Mirrors MigrationManager::RESULT_LEGACY_MODULE on the target side. The
     * two plugins share no code, so the value is spelled out here.
     */
    const RESULT_LEGACY_MODULE = 'legacy_module_embedded';

    /**
     * First Buttonizer version that can absorb this plugin.
     *
     * Anything older has no migration code, so activating it hands the user
     * nothing: both plugins stay active and nothing moves over.
     */
    const MIN_VERSION = '3.6.0';

    /**
     * @var string|null Resolved basename, cached per request.
     */
    private static $resolvedBaseName = null;

    /**
     * @var string|null Installed version, cached per request.
     */
    private static $installedVersion = null;

    /**
     * Product name shown to the user.
     */
    public static function label(): string
    {
        return 'Buttonizer';
    }

    /**
     * Known basenames, development checkout first.
     *
     * @return string[]
     */
    public static function baseNameCandidates(): array
    {
        return [
            'buttonizer-for-wordpress/buttonizer.php',
            self::WP_ORG_SLUG . '/buttonizer.php',
        ];
    }

    /**
     * Resolve the basename of the installed target plugin.
     *
     * @return string|null Null when it is not installed.
     */
    public static function resolveBaseName()
    {
        if (self::$resolvedBaseName !== null) {
            return self::$resolvedBaseName ?: null;
        }

        foreach (self::baseNameCandidates() as $candidate) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $candidate)) {
                return self::$resolvedBaseName = $candidate;
            }
        }

        if (function_exists('get_plugins')) {
            foreach (get_plugins() as $baseName => $plugin) {
                if (isset($plugin['TextDomain']) && $plugin['TextDomain'] === self::TEXT_DOMAIN) {
                    return self::$resolvedBaseName = $baseName;
                }
            }
        }

        self::$resolvedBaseName = '';

        return null;
    }

    /**
     * Is the target plugin installed?
     */
    public static function isInstalled(): bool
    {
        return self::resolveBaseName() !== null;
    }

    /**
     * Is the target plugin active?
     */
    public static function isActive(): bool
    {
        $baseName = self::resolveBaseName();

        if (!$baseName) {
            return false;
        }

        if (function_exists('is_plugin_active')) {
            return is_plugin_active($baseName);
        }

        return in_array($baseName, (array) get_option('active_plugins', []), true);
    }

    /**
     * Version of the installed target plugin.
     *
     * @return string Empty string when it is not installed.
     */
    public static function installedVersion(): string
    {
        if (self::$installedVersion !== null) {
            return self::$installedVersion;
        }

        $baseName = self::resolveBaseName();

        if (!$baseName) {
            return self::$installedVersion = '';
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $baseName, false, false);

        return self::$installedVersion = (string) ($data['Version'] ?? '');
    }

    /**
     * Can the installed target plugin actually absorb this one?
     *
     * Without this check the notice promises a handover that never happens:
     * the user activates an older Buttonizer and simply ends up with two
     * active plugins and two menus.
     */
    public static function hasMigrationSupport(): bool
    {
        $version = self::installedVersion();

        if ($version === '') {
            return false;
        }

        return version_compare($version, self::MIN_VERSION, '>=');
    }

    /**
     * Has the target plugin already taken this plugin over?
     *
     * Read from the target's own bookkeeping, so this stays true even after
     * the user reactivates this plugin.
     */
    public static function hasAdoptedThisPlugin(): bool
    {
        $state = get_option(self::MIGRATION_STATE_OPTION, []);

        if (!is_array($state) || empty($state[self::MODULE_ID]['status'])) {
            return false;
        }

        return $state[self::MODULE_ID]['status'] === 'migrated';
    }

    /**
     * Is the target already serving this plugin's old system?
     *
     * Both copies of that system are the same code in the global namespace, so
     * only one of them can run. This plugin loads first, which means it is the
     * one that has to step aside: reactivating it should not take the user's
     * screens out of the menu they have been using since the migration.
     *
     * Mirrors the target's own decision, which is spelled out here because the
     * two plugins share no code.
     */
    public static function servesOurLegacySystem(): bool
    {
        if (!self::isActive()) {
            return false;
        }

        $state = get_option(self::MIGRATION_STATE_OPTION, []);

        if (!is_array($state) || !isset($state[self::MODULE_ID])) {
            return false;
        }

        $module = $state[self::MODULE_ID];

        if (($module['result'] ?? '') !== self::RESULT_LEGACY_MODULE) {
            return false;
        }

        // The site already had a Buttonizer of its own when the old system was
        // taken over, so a connection there says nothing about this one.
        if (!empty($module['target_connected'])) {
            return true;
        }

        // The user signed up in Buttonizer and moved on to the cloud. The old
        // system is not served there any more, so this plugin is on its own.
        return empty($module['moved_to_cloud']) && !self::isTargetConnected();
    }

    /**
     * Does the target have a connection of its own?
     *
     * Only used to tell "moved to the cloud" from "still on the old system" in
     * the one request before the target writes that down itself.
     */
    private static function isTargetConnected(): bool
    {
        $token = get_option('buttonizer_site_connection', false);

        return !empty($token);
    }

    /**
     * Drop this plugin's entry from the target's migration bookkeeping.
     *
     * Deleting a plugin should not leave the site remembering a migration with
     * no plugin behind it. The leftover entry makes a later install look like
     * one that was already handed over, so it is never offered the move and
     * never deactivated.
     *
     * The exception is a site whose old system is being served by the target's
     * embedded module. There the entry is load-bearing: it decides whether that
     * module runs, and whether a site that moved on to the cloud stays there.
     * It survives until the target itself is uninstalled, which clears the
     * whole option from its own side.
     *
     * Only this plugin's entry is touched — the option is shared with every
     * other module the target has taken over.
     */
    public static function forgetMigrationState(): void
    {
        $state = get_option(self::MIGRATION_STATE_OPTION, []);

        if (!is_array($state) || !isset($state[self::MODULE_ID])) {
            return;
        }

        $embedded = ($state[self::MODULE_ID]['result'] ?? '') === self::RESULT_LEGACY_MODULE;

        if ($embedded && self::isInstalled()) {
            return;
        }

        unset($state[self::MODULE_ID]);

        if ($state === []) {
            delete_option(self::MIGRATION_STATE_OPTION);

            return;
        }

        update_option(self::MIGRATION_STATE_OPTION, $state);
    }

    /**
     * Note, in the target's own bookkeeping, that the user asked for the move.
     *
     * The target never absorbs an installed plugin on its own: it waits for
     * this. Written before the install, so one that fails halfway and gets
     * finished by hand from the Plugins screen still counts as asked for.
     */
    public static function markRequested(): void
    {
        self::mergeIntoState(['requested' => true]);
    }

    /**
     * Note, in the target's own bookkeeping, that this site arrives by handover.
     *
     * Written before the install, because afterwards nothing tells the two
     * apart: a site handed over on signup and one migrated by someone who had
     * been here for years both end up as an adopted connection. The target
     * reads this to know it is greeting a user who never had buttons here, and
     * so must not be told that theirs moved over.
     */
    public static function markHandover(): void
    {
        self::mergeIntoState(['handover' => true]);
    }

    /**
     * Merge values into this plugin's entry in the target's bookkeeping.
     *
     * Merged rather than written whole: the target keeps its own keys in the
     * same place.
     */
    private static function mergeIntoState(array $values): void
    {
        $state = get_option(self::MIGRATION_STATE_OPTION, []);

        if (!is_array($state)) {
            $state = [];
        }

        $module = isset($state[self::MODULE_ID]) && is_array($state[self::MODULE_ID])
            ? $state[self::MODULE_ID]
            : [];

        $state[self::MODULE_ID] = array_merge($module, $values);

        update_option(self::MIGRATION_STATE_OPTION, $state);
    }

    /**
     * Admin URL of the target plugin's dashboard.
     */
    public static function dashboardUrl(): string
    {
        return admin_url('admin.php?page=' . self::PAGE_SLUG);
    }

    /**
     * Forget what was cached before installing.
     */
    public static function resetCache(): void
    {
        self::$resolvedBaseName  = null;
        self::$installedVersion = null;
    }
}
