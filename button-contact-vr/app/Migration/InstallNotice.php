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
 * Soft requirement on Buttonizer.
 *
 * This plugin keeps working exactly as before; the notice only offers the
 * move. Nothing is installed without the user clicking, and the frontend is
 * never touched. Buttonizer takes the connection over and deactivates this
 * plugin from its own side, but only once the click told it to — a Buttonizer
 * that is simply installed next to this plugin leaves it alone.
 *
 * Only reachable on cloud installs — a site still running the pre-Buttonizer
 * system never loads this code path.
 */
class InstallNotice
{
    const INSTALL_ACTION    = 'bz_contact_button_install_buttonizer';
    const DEACTIVATE_ACTION = 'bz_contact_button_deactivate_self';
    const DISMISS_ACTION    = 'bz_contact_button_dismiss_buttonizer_notice';

    /** The two notices are dismissed separately: they are different decisions. */
    const NOTICE_OFFER    = 'offer';
    const NOTICE_HANDOVER = 'handover';

    /** When the migration offer was last dismissed (timestamp). */
    const OFFER_DISMISSED_OPTION = 'bz_contact_button_offer_dismissed_at';

    /** Whether the post-migration notice was dismissed (permanent). */
    const HANDOVER_DISMISSED_OPTION = 'bz_contact_button_handover_notice_dismissed';

    /** Overrides OFFER_SNOOZE_DAYS when set. */
    const OFFER_SNOOZE_OPTION = 'bz_contact_button_offer_snooze_days';

    /** Query argument that writes the override. */
    const SNOOZE_ARG = 'bz_contact_button_snooze_days';

    /**
     * How long the migration offer stays hidden after "Not now".
     *
     * Dismissing the offer forever would mean losing that user for good, so it
     * comes back once. The post-migration notice does not: by then the user is
     * already where we want them, and the old plugin is inert.
     */
    const OFFER_SNOOZE_DAYS = 5;

    /**
     * When editing stops happening in this plugin.
     */
    const EDITING_DEADLINE = 'November 1st, 2026';

    /**
     * Where "Learn more" goes.
     */
    const LEARN_MORE_URL = 'https://community.buttonizer.pro/knowledgebase/3232-plugin-features-moving-to-main-buttonizer-plugin';

    /**
     * @var int|null Snooze value just written, for the confirmation notice.
     */
    private static $snoozeChangedTo = null;

    /**
     * Register the hooks.
     */
    public static function boot(): void
    {
        register_activation_hook(BZ_CONTACT_BUTTON_PLUGIN_FILE, [self::class, 'onActivate']);

        if (!is_admin()) {
            return;
        }

        add_action('admin_init', [self::class, 'handleDismiss'], 1);
        add_action('admin_init', [self::class, 'handleSnoozeOverride'], 1);
        add_action('admin_notices', [self::class, 'render']);
        add_action('admin_post_' . self::INSTALL_ACTION, [self::class, 'handleInstall']);
        add_action('admin_post_' . self::DEACTIVATE_ACTION, [self::class, 'handleDeactivate']);
    }

    /**
     * Reset the dismissal when the plugin is activated.
     *
     * Someone who reactivates this plugin gets the offer again, instead of a
     * "not now" from months ago silencing it forever.
     */
    public static function onActivate(): void
    {
        delete_option(self::OFFER_DISMISSED_OPTION);
        delete_option(self::HANDOVER_DISMISSED_OPTION);
    }

    /**
     * Store the dismissal of one of the notices.
     */
    public static function handleDismiss(): void
    {
        if (!isset($_GET[self::DISMISS_ACTION])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET[self::DISMISS_ACTION]));

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], self::DISMISS_ACTION . $notice)) {
            return;
        }

        if ($notice === self::NOTICE_OFFER) {
            update_option(self::OFFER_DISMISSED_OPTION, time());
        }

        if ($notice === self::NOTICE_HANDOVER) {
            update_option(self::HANDOVER_DISMISSED_OPTION, true);
        }
    }

    /**
     * Is the migration offer still snoozed?
     */
    private static function isOfferSnoozed(): bool
    {
        $dismissedAt = (int) get_option(self::OFFER_DISMISSED_OPTION, 0);

        if ($dismissedAt <= 0) {
            return false;
        }

        return (time() - $dismissedAt) < (self::snoozeDays() * DAY_IN_SECONDS);
    }

    /**
     * How many days "Not now" hides the offer for.
     *
     * OFFER_SNOOZE_DAYS is the shipped answer. The option overrides it so the
     * wait can be shortened without a release — waiting a month to find out
     * whether the offer comes back is not a test anyone runs twice.
     */
    private static function snoozeDays(): int
    {
        $override = get_option(self::OFFER_SNOOZE_OPTION, null);

        if ($override === null || $override === '' || $override === false) {
            return self::OFFER_SNOOZE_DAYS;
        }

        return max(0, (int) $override);
    }

    /**
     * Set the snooze length from the address bar.
     *
     * Add ?bz_contact_button_snooze_days=3 to any admin URL. Zero brings the
     * offer straight back, "default" drops the override. Administrators only,
     * and it changes nothing but when a notice reappears.
     */
    public static function handleSnoozeOverride(): void
    {
        if (!isset($_GET[self::SNOOZE_ARG]) || !current_user_can('manage_options')) {
            return;
        }

        $value = sanitize_text_field(wp_unslash($_GET[self::SNOOZE_ARG]));

        if ($value === 'default' || $value === '') {
            delete_option(self::OFFER_SNOOZE_OPTION);
            self::$snoozeChangedTo = self::OFFER_SNOOZE_DAYS;

            return;
        }

        if (!is_numeric($value)) {
            return;
        }

        $days = max(0, (int) $value);

        update_option(self::OFFER_SNOOZE_OPTION, $days);
        self::$snoozeChangedTo = $days;
    }

    /**
     * Confirm a snooze change, so the tester knows it landed.
     */
    private static function renderSnoozeConfirmation(): void
    {
        if (self::$snoozeChangedTo === null) {
            return;
        }

        $message = self::$snoozeChangedTo === 0
            ? __('Migration offer snooze set to 0 days: "Not now" no longer hides it.', 'button-contact-vr')
            : sprintf(
                /* translators: %d: number of days. */
                _n(
                    'Migration offer snooze set to %d day.',
                    'Migration offer snooze set to %d days.',
                    self::$snoozeChangedTo,
                    'button-contact-vr'
                ),
                self::$snoozeChangedTo
            );

        echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Show the notice.
     */
    public static function render(): void
    {
        // Buttonizer may have deactivated this plugin earlier in this very
        // request. The code is still loaded and its hooks still fire, so
        // without this the handover lands the user on two notices at once.
        if (!self::isSelfActive()) {
            return;
        }

        // Ahead of every early return below: a tester who just changed the
        // snooze needs to see that it took, whatever the notice decides to do.
        self::renderSnoozeConfirmation();

        self::renderError();

        // Installed, but too old to absorb this plugin. Say nothing: WordPress
        // is already asking the user to update it, and offering to activate it
        // would promise a handover that cannot happen. Once it is updated the
        // flow resumes on its own.
        if (TargetPlugin::isInstalled() && !TargetPlugin::hasMigrationSupport()) {
            return;
        }

        // Buttonizer already took this plugin over: the offer is no longer
        // "install" but "you are done, come this way". Merely being active is
        // not enough — Buttonizer waits for the click below, so until then the
        // offer is the only way across.
        if (TargetPlugin::isActive() && TargetPlugin::hasAdoptedThisPlugin()) {
            if (
                !get_option(self::HANDOVER_DISMISSED_OPTION, false) &&
                current_user_can('activate_plugins')
            ) {
                self::renderHandedOverNotice();
            }

            return;
        }

        if (self::isOfferSnoozed()) {
            return;
        }

        // Asking someone to install a plugin they are not allowed to install
        if (!current_user_can(TargetPlugin::isInstalled() ? 'activate_plugins' : 'install_plugins')) {
            return;
        }

        // A site that has just been installed hears this on its own signup
        // screen, under the button that does it. Repeating it up here would
        // say the same thing twice on that page, and on every other admin page
        // it would push an install at someone who has not opened the plugin
        // yet. Nothing is lost: the signup is the handover.
        if (NewInstallHandover::isPending()) {
            return;
        }

        // One offer, whatever state Buttonizer is in: the user cares about the
        // outcome, not about whether a download is involved. The handler
        // installs it only when it is missing, and activates it either way.
        /* translators: %s: name of this plugin as the user sees it in their menu. */
        $message = sprintf(
            __('The "%s" plugin features are being moved into our main Buttonizer plugin to make it easier to update and maintain. Click "Move to Buttonizer" and we will automatically install it and migrate your settings. You will not notice any changes since everything will look the same to you.', 'button-contact-vr'),
            RunningSystem::name()
        );

        // The reason to move, rather than a notice of its own. A second box
        // beside this one would compete with it for the same click, and the
        // deadline is the argument for taking it — not separate news.
        /* translators: 1: date, already wrapped in <strong>. 2: "Learn more" link, already built. */
        $deadline = sprintf(
            esc_html__('Please be sure to migrate before %1$s. After that date, you will no longer be able to change any settings until you migrate. %2$s', 'button-contact-vr'),
            '<strong>' . esc_html(self::EDITING_DEADLINE) . '</strong>',
            '<a href="' . esc_url(self::LEARN_MORE_URL) . '" target="_blank" rel="noopener">' . esc_html__('Learn more', 'button-contact-vr') . '</a>'
        );

        $buttonLabel = __('Move to Buttonizer', 'button-contact-vr');

        $dismissUrl = self::dismissUrl(self::NOTICE_OFFER);

        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<p>' . $deadline . '</p>';
        self::openActions();

        echo '<form method="post" style="margin:0;" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::INSTALL_ACTION) . '" />';
        wp_nonce_field(self::INSTALL_ACTION);
        echo '<button type="submit" class="button button-primary">' . esc_html($buttonLabel) . '</button>';
        echo '</form>';

        echo '<a href="' . esc_url($dismissUrl) . '">' . esc_html__('Not now', 'button-contact-vr') . '</a>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Report a failed install or activation.
     */
    private static function renderError(): void
    {
        if (empty($_GET['bz_contact_button_migration_error'])) {
            return;
        }

        $errors = [
            'install_failed'    => __('Buttonizer could not be installed. Install it manually from the Plugins screen and your buttons will move over automatically.', 'button-contact-vr'),
            'activation_failed' => __('Buttonizer was installed but could not be activated. Activate it from the Plugins screen to finish moving your buttons.', 'button-contact-vr'),
            /* translators: %s: name of this plugin as the user sees it in their menu. */
            'target_outdated'   => sprintf(
                __('The installed Buttonizer is too old to take over from %s. Update it from the Plugins screen and your buttons will move over automatically.', 'button-contact-vr'),
                RunningSystem::name()
            ),
        ];

        $error = sanitize_text_field(wp_unslash($_GET['bz_contact_button_migration_error']));

        if (!isset($errors[$error])) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html($errors[$error]) . '</p></div>';
    }

    /**
     * Notice for a site that has already been handed over.
     *
     * Reached by reactivating this plugin after the migration. Nothing is
     * deactivated behind the user's back a second time — they are told where
     * their buttons live now, and deactivating again is one click if they want.
     */
    private static function renderHandedOverNotice(): void
    {
        echo '<div class="notice notice-info">';
        // Wording that holds in both worlds: a cloud install already moved its
        // data over, and a legacy install has its old system served from inside
        // Buttonizer as soon as this plugin steps aside.
        /* translators: %s: name of this plugin as the user sees it in their menu. */
        echo '<p>' . esc_html(sprintf(
            __('The "%s" plugin features were recently moved into our main Buttonizer plugin. Your settings can be viewed or modified there or by clicking "Open Buttonizer" below. You can safely deactivate this plugin, as it is not necessary anymore.', 'button-contact-vr'),
            RunningSystem::name()
        )) . '</p>';
        self::openActions();

        echo '<a class="button button-primary" href="' . esc_url(TargetPlugin::dashboardUrl()) . '">' . esc_html__('Open Buttonizer', 'button-contact-vr') . '</a>';

        echo '<form method="post" style="margin:0;" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::DEACTIVATE_ACTION) . '" />';
        wp_nonce_field(self::DEACTIVATE_ACTION);
        /* translators: %s: name of this plugin as the user sees it in their menu. */
        echo '<button type="submit" class="button">' . esc_html(sprintf(
            __('Deactivate %s', 'button-contact-vr'),
            RunningSystem::name()
        )) . '</button>';
        echo '</form>';

        echo '<a href="' . esc_url(self::dismissUrl(self::NOTICE_HANDOVER)) . '">' . esc_html__('Not now', 'button-contact-vr') . '</a>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Deactivate this plugin at the user's request.
     */
    public static function handleDeactivate(): void
    {
        check_admin_referer(self::DEACTIVATE_ACTION);

        if (!current_user_can(is_multisite() ? 'manage_options' : 'activate_plugins')) {
            wp_die(esc_html__('You are not allowed to deactivate plugins on this site.', 'button-contact-vr'));
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        deactivate_plugins(plugin_basename(BZ_CONTACT_BUTTON_PLUGIN_FILE));

        wp_safe_redirect(TargetPlugin::dashboardUrl());
        exit;
    }

    /**
     * Is this plugin still active?
     */
    private static function isSelfActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(plugin_basename(BZ_CONTACT_BUTTON_PLUGIN_FILE));
    }

    /**
     * Open the row holding a notice's buttons and links.
     *
     * A form cannot live inside a <p>, and inline-block buttons next to a
     * plain link wrap badly, so the actions get their own flex row.
     */
    private static function openActions(): void
    {
        echo '<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0 0 12px;">';
    }

    /**
     * URL that dismisses one of the notices.
     *
     * @param string $notice One of the NOTICE_* constants.
     */
    private static function dismissUrl(string $notice): string
    {
        return wp_nonce_url(
            add_query_arg(self::DISMISS_ACTION, $notice),
            self::DISMISS_ACTION . $notice
        );
    }

    /**
     * Install and/or activate the target plugin.
     *
     * Only ever reached through the button in the notice: capability checked,
     * nonce checked, and the download comes from the wordpress.org API.
     */
    public static function handleInstall(): void
    {
        check_admin_referer(self::INSTALL_ACTION);

        $result = Installer::run();

        if ($result === Installer::RESULT_NO_PERMISSION) {
            wp_die(esc_html__('You are not allowed to install plugins on this site.', 'button-contact-vr'));
        }

        if ($result !== Installer::RESULT_DONE) {
            self::redirectBack($result);
        }

        // Buttonizer takes it from here: on the next admin request it adopts
        // this plugin's connection and deactivates it.
        wp_safe_redirect(TargetPlugin::dashboardUrl());
        exit;
    }

    /**
     * Send the user back with an error flag.
     */
    private static function redirectBack(string $error): void
    {
        $referer = wp_get_referer() ?: admin_url('admin.php?page=bz_button_contact');

        wp_safe_redirect(add_query_arg('bz_contact_button_migration_error', $error, $referer));
        exit;
    }
}
