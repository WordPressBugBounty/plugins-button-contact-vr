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
 * Sends a brand-new install to Buttonizer as soon as it has an account.
 *
 * Someone who installs this plugin today has no buttons, no settings and no
 * account. There is nothing to carry over and nothing to lose, which makes
 * signup the one moment when moving them costs nothing — waiting until they
 * have built something turns a free handover into the expensive kind.
 *
 * The move follows the signup the user asked for, and the screen says so
 * before they start. It never runs unattended on activation: installing another
 * plugin is only ever the consequence of something the user chose to do.
 *
 * The call in init.php is the switch — comment it out and none of this exists as
 * far as the rest of the plugin is concerned. @see boot()
 */
class NewInstallHandover
{
    /**
     * Whether this site was empty back when there was still a way to tell.
     *
     * Holds 'yes' or 'no'. Missing means nobody has looked yet.
     */
    const PENDING_OPTION = 'bz_contact_button_new_install_pending';

    /**
     * @var bool Whether init.php still calls boot().
     */
    private static $booted = false;

    /**
     * Register the hooks.
     *
     * Comment the call to this out in init.php and the handover is gone: nothing
     * is hooked, and everything that asks isEnabled() gets told so.
     */
    public static function boot(): void
    {
        self::$booted = true;

        register_activation_hook(BZ_CONTACT_BUTTON_PLUGIN_FILE, [self::class, 'onActivate']);

        if (!is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // Ahead of the notice, so a site that is being handed over this very
        // request never paints an offer to do what is already happening.
        add_action('admin_init', [self::class, 'run'], 4);
    }

    /**
     * Is the handover switched on?
     *
     * Answered by whether init.php booted this, so a site that ran the handover
     * and then had it commented out cannot be left half in it: the flag it wrote
     * at activation is only ever read through here.
     */
    public static function isEnabled(): bool
    {
        return self::$booted;
    }

    /**
     * Record what this site looked like on the way in.
     *
     * Activation is the earliest moment there is, but not the only one: a site
     * that has had the plugin sitting on it unused for a year is just as empty
     * as one installed this morning, and only ever arrives here by updating,
     * which fires no activation hook at all. @see remember()
     */
    public static function onActivate(): void
    {
        self::remember();
    }

    /**
     * Write down whether this site is empty, while there is still a way to tell.
     *
     * Once an account exists the site looks like every other one, so the answer
     * has to be on paper before that happens. Until then it is rewritten on
     * every admin request: an install that starts empty and then builds
     * something stops qualifying, which is the point of asking at all.
     */
    public static function remember(): void
    {
        // Too late to tell, and the answer is already written down.
        if (self::hasAccount()) {
            return;
        }

        $answer = self::isEmpty() ? 'yes' : 'no';

        if (get_option(self::PENDING_OPTION, '') !== $answer) {
            update_option(self::PENDING_OPTION, $answer);
        }
    }

    /**
     * Is this a site the signup screen can hand over?
     *
     * Not a question about buttons. Someone whose old buttons are still in the
     * database loses them the moment they sign up, whether or not Buttonizer
     * is installed afterwards: the cloud takes over and the old options go
     * quiet either way. Handing them over costs them nothing more, and leaves
     * them somewhere better.
     *
     * What must not happen is treating a site that is still *running* the old
     * system as empty. Those users never reach a signup screen, so a handover
     * would never fire for them anyway — but marking them would take away the
     * notice, which is the only path they have.
     */
    private static function isEmpty(): bool
    {
        return !self::hasAccount()
            && !RunningSystem::isLegacy()
            && !TargetPlugin::isActive();
    }

    /**
     * Was this site empty when it got here, and still waiting to sign up?
     *
     * The screen asks, so it can say what the signup is going to do before the
     * user commits to it.
     */
    public static function isPending(): bool
    {
        return self::isEnabled() && get_option(self::PENDING_OPTION, '') === 'yes';
    }

    /**
     * Settle the question as "no" for good.
     */
    private static function stopPending(): void
    {
        update_option(self::PENDING_OPTION, 'no');
    }

    /**
     * Hand the site over, once there is an account to hand over.
     */
    public static function run(): void
    {
        // First, while the site can still be read for what it is.
        self::remember();

        if (!self::isPending()) {
            return;
        }

        // Buttonizer arrived some other way and takes over from its own side
        if (TargetPlugin::isActive()) {
            self::stopPending();

            return;
        }

        // Still on the welcome screen. Wait.
        if (!self::hasAccount()) {
            return;
        }

        // Nothing to offer someone who may not install plugins: they stay
        // here, connected and working, and an administrator can move them.
        if (!current_user_can('install_plugins')) {
            return;
        }

        // One attempt, decided before trying. A site that fails would
        // otherwise reach for wordpress.org on every admin page it loads.
        self::stopPending();

        // Say so before handing over, while there is still something that
        // knows this site had nothing on it. @see TargetPlugin::markHandover()
        TargetPlugin::markHandover();

        // On failure the notice is still there, still offering the move by
        // hand, which works on its own.
        if (Installer::run() !== Installer::RESULT_DONE) {
            return;
        }

        wp_safe_redirect(TargetPlugin::dashboardUrl());
        exit;
    }

    /**
     * Has this site finished signing up?
     */
    private static function hasAccount(): bool
    {
        $settings = get_option(BZ_CONTACT_BUTTON_NAME . '_settings', []);

        return is_array($settings) && !empty($settings['finished_setup']) && !empty($settings['site_id']);
    }
}
