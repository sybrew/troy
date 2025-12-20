<?php
/**
 * @package Troy\Server\Bootstrap
 * @access  private
 */

namespace Troy\Server\Bootstrap\Deactivation;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	Cron,
	Integrations,
	Stats,
};

/**
 * Troy Server
 *
 * Copyright (c) 2025 Sybre Waaijer, CyberWire B.V.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

// Turn off auto-loading for the server settings.
if ( false !== \get_option( 'troy_server_settings' ) )
	\wp_set_options_autoload( [ 'troy_server_settings' ], false );

// Remove cron jobs; not doing so will cause PHP errors on cron.
Cron::remove_cron_jobs();
Integrations\Cron::remove_cron_jobs();
Stats\Cron::remove_cron_jobs();
