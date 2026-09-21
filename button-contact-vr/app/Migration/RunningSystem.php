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
 * Which of the two systems in this plugin is running, and what it is called.
 *
 * The plugin ships its own pre-Buttonizer system alongside the current one,
 * and each labels its menu entry differently: someone on the old one has
 * "Button contact" in their sidebar and has never seen the words "Chat
 * Button" anywhere. A message that names the other system is talking about
 * something the user cannot see, which is worse than saying nothing.
 */
class RunningSystem
{
    /** Set to "yes" while the old system is the one running the site. */
    const LEGACY_FLAG_OPTION = 'button_contact_legacy';

    /** The old system's menu entry. @see legacy/plugin.php */
    const LEGACY_NAME = 'Button contact';

    /** The current system's menu entry. @see \BZContactButton\Admin\Admin */
    const CURRENT_NAME = 'Chat Button';

    /**
     * Is the old system the one serving this site?
     */
    public static function isLegacy(): bool
    {
        return get_option(self::LEGACY_FLAG_OPTION, '') === 'yes';
    }

    /**
     * The name this user has in front of them, in their own sidebar.
     */
    public static function name(): string
    {
        return self::isLegacy() ? self::LEGACY_NAME : self::CURRENT_NAME;
    }
}
