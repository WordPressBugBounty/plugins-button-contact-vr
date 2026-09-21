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

namespace BZContactButton\Admin;

use BZContactButton\Core\Admin\BaseAdmin;
use BZContactButton\Core\Utils\PermissionCheck;
use BZContactButton\Migration\NewInstallHandover;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

class Admin extends BaseAdmin
{
    /**
     * Admin constructor.
     */
    public function __construct()
    {
        // Register shared admin hooks (assets, modules, notices)
        parent::__construct();

        // Add to admin menu
        add_action('admin_menu', [$this, 'pluginAdminMenu']);

        // Plugin information, add links
        add_filter("plugin_action_links_" . BZ_CONTACT_BUTTON_BASE_NAME, function ($actions) {
            $links = [
                '<a href="' . admin_url('admin.php?page=bz_button_contact#/support') . '">' . __('Support', 'button-contact-vr') . '</a>',
                '<a href="' . admin_url('admin.php?page=bz_button_contact#/editor') . '">' . __('Edit buttons', 'button-contact-vr') . '</a>',
                '<a href="' . admin_url('admin.php?page=bz_button_contact#/settings') . '">' . __('Settings', 'button-contact-vr') . '</a>',
            ];

            return array_merge($actions, $links);
        });

        // Redirect old plugin to updated version
        add_action('admin_init', function () {
            if ((isset($_GET["page"]) && $_GET["page"] === "contact_vr_setting")) {
                wp_redirect(admin_url("admin.php?page=bz_button_contact"));
            }
        });
    }

    /**
     * Tell the dashboard when this signup is going to hand the site over.
     *
     * The screen says so under the button that does it, which is where the
     * user is actually looking and deciding. Nothing is decided in the browser:
     * with this missing, the signup behaves exactly as it always has.
     */
    protected function getLocalizeData(): array
    {
        $data = parent::getLocalizeData();
        $handover = $this->handoverData();

        if ($handover !== null) {
            $data['handover'] = $handover;
        }

        return $data;
    }

    /**
     * What the welcome screen needs to announce the handover, or null.
     *
     * The capability is part of the question: someone who may not install
     * plugins is never moved, so they are never told they will be.
     *
     * @return array|null
     */
    private function handoverData()
    {
        if (!NewInstallHandover::isPending() || !current_user_can('install_plugins')) {
            return null;
        }

        return [
            // Named as "this plugin" rather than by name: the only installs
            // that reach this screen are new ones, which never carry the old
            // system's name, so there is nothing for a name to disambiguate.
            'notice' => __('This plugin is now a part of Buttonizer. Creating your account installs Buttonizer and takes you straight there.', 'button-contact-vr'),

            // Back here on a full request, which is the only place the
            // handover can run. It forwards to Buttonizer from there.
            'continueUrl' => admin_url('admin.php?page=bz_button_contact'),
        ];
    }

    /**
     * Create Admin menu
     */
    public function pluginAdminMenu()
    {
        if (!PermissionCheck::hasPermission()) return;

        // Admin menu
        add_menu_page('Chat Button', 'Chat Button', 'read', 'bz_button_contact', [$this, 'page'], plugins_url('/assets/images/wp-icon.png', BZ_CONTACT_BUTTON_PLUGIN_DIR), 81);

        // Add submenu
        add_submenu_page('bz_button_contact', 'Edit buttons',  __('Edit buttons', 'button-contact-vr'), 'read', 'admin.php?page=bz_button_contact#/editor');

        // Add support link
        add_submenu_page('bz_button_contact', __('I need support', 'button-contact-vr'),  __('I need support', 'button-contact-vr'), 'read', 'admin.php?page=bz_button_contact#/support');

        // Add community link
        add_submenu_page('bz_button_contact', __('Community', 'button-contact-vr'),  __('Community', 'button-contact-vr'), 'read', 'https://r.buttonizer.io/support/community?referral=wp-contact-button-plugin-menu');

        // Add knowledge base link
        add_submenu_page('bz_button_contact', __('Knowledge base', 'button-contact-vr'),  __('Knowledge base', 'button-contact-vr'), 'read', 'https://r.buttonizer.io/support/knowledgebase?referral=wp-contact-button-plugin-menu');

        // Add old plugin redirect page
        add_submenu_page('', "Setting redirect",  "Setting redirect", 'read', 'contact_vr_setting', [$this, 'plugin_update_redirect_placeholder']);
    }

    public function plugin_update_redirect_placeholder()
    {
        wp_redirect(admin_url("admin.php?page=bz_button_contact"));
    }
}
