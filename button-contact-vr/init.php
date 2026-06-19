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
