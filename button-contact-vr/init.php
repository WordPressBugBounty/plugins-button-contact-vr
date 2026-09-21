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

use BZContactButton\Core\InitBootstrap;

// Boot shared hooks (admin, redirect, page data, embed script, shortcode, admin bar, REST API)
InitBootstrap::boot(
    \BZContactButton\Admin\Admin::class,
    \BZContactButton\Api\Api::class
);

// Offer the move to Buttonizer (admin only, user has to click)
\BZContactButton\Migration\InstallNotice::boot();

// Hand a brand-new install over as soon as it has an account: nothing has been
// built on it yet, so there is nothing to carry across and nothing to lose.
// Comment this out and the only way to Buttonizer is the button in the notice,
// clicked by the user.
\BZContactButton\Migration\NewInstallHandover::boot();
