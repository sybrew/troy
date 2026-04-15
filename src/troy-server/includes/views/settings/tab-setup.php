<?php
/**
 * @package Troy\Server\Views\Settings
 */

namespace Troy\Server\Views\Settings;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use Troy\Server\API;

/**
 * Troy Server
 *
 * Copyright (c) 2025 - 2026 Sybre Waaijer, CyberWire B.V.
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

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- We're not in the global space.

$repo_url        = API\Server::get_repo_url();
$composer_vendor = API\Server::get_composer_vendor();
$site_slug       = API\Server::get_site_slug();
?>
<h2><?= \esc_html__( 'Troy Server Setup', 'troy-server' ) ?></h2>

<p><?= \esc_html__( 'Inspect and modify your Troy Server setup.', 'troy-server' ) ?></p>

<hr class=hr-separator>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=true class=troy-server-settings-accordion-trigger aria-controls=troy-server-settings-accordion-block-server-info type=button>
			<span class=title><?= \esc_html__( 'Server Info', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-settings-accordion-block-server-info class=troy-server-settings-accordion-panel>
		<table class="widefat striped form-table troy-server-settings-form">
			<tr>
				<th scope=col><?= \esc_html__( 'Property', 'troy-server' ) ?></th>
				<th scope=col><?= \esc_html__( 'Value', 'troy-server' ) ?></th>
			</tr>
			<tr>
				<td>
					<span class=troy-server-setup-property><?= \esc_html__( 'Troy plugin header', 'troy-server' ) ?></span>
				</td>
				<td>
					<p class=description><?= \esc_html__( 'Add this header to your main plugin file to connect it to this Troy Server.', 'troy-server' ) ?></p>
					<pre class="troy-server-pre troy-server-copyable"><code><?= \esc_html( "Troy: $repo_url" ) ?></code></pre>
					<p class=description><?= \esc_html__( 'Example plugin header:', 'troy-server' ) ?></p>
					<pre class=troy-server-pre><code><?= \esc_html( "<?php\n/**\n * Plugin Name: Example Plugin\n * Troy: $repo_url\n * Version: 1.0.0\n */" ) ?></code></pre>
				</td>
			</tr>
		</table>
	</div>
</div>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=true class=troy-server-settings-accordion-trigger aria-controls=troy-server-settings-accordion-block-composer type=button>
			<span class=title><?= \esc_html__( 'Composer Settings', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-settings-accordion-block-composer class=troy-server-settings-accordion-panel>
		<table class="widefat striped form-table troy-server-settings-form">
			<tr>
				<th scope=col><?= \esc_html__( 'Setting', 'troy-server' ) ?></th>
				<th scope=col><?= \esc_html__( 'Details', 'troy-server' ) ?></th>
			</tr>
			<tr>
				<td>
					<label for=troy-server-composer-vendor class=troy-server-setup-property><?= \esc_html__( 'Composer vendor', 'troy-server' ) ?></label>
					<p class=description><?= \esc_html__( 'The vendor prefix for Composer packages.', 'troy-server' ) ?></p>
				</td>
				<td>
					<p>
						<input type=text id=troy-server-composer-vendor value="<?= \esc_attr( $composer_vendor ) ?>" placeholder="<?= \esc_attr( $site_slug ) ?>" class=regular-text>
					</p>
					<p class=description>
						<strong><?= \esc_html__( 'Warning:', 'troy-server' ) ?></strong>
						<?= \esc_html__( 'Changing this will break Composer updates for existing consumers.', 'troy-server' ) ?>
					</p>
					<p class=description id=troy-server-composer-vendor-preview>
						<?= \esc_html__( 'Preview:', 'troy-server' ) ?>
						<code><?= \esc_html( "$composer_vendor-package/example-slug" ) ?></code>
					</p>
				</td>
			</tr>
		</table>
	</div>
</div>

<div id=troy-server-setup-notice hidden></div>
<p class=submit>
	<button type=button id=troy-server-setup-save class="button button-primary"><?= \esc_html__( 'Save Settings', 'troy-server' ) ?></button>
</p>
<?php
