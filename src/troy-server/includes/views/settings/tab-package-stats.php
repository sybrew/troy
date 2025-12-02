<?php
/**
 * @package Troy\Server\Views\Settings
 */

namespace Troy\Server\Views\Settings;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use Troy\Server\Settings;

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

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- We're not in the global space.

// Get initial data for Server Side Rendering.
$package_overview = Settings\Stats::get_package_overview();
$packages_summary = Settings\Stats::get_packages_summary();

?>
<h2><?= \esc_html__( 'Package Stats', 'troy-server' ) ?></h2>

<p><?= \esc_html__( 'Download statistics for installer packages.', 'troy-server' ) ?></p>

<hr class=hr-separator>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=true class=troy-server-settings-accordion-trigger aria-controls=troy-server-package-stats-overview type=button>
			<span class=title><?= \esc_html__( 'Overview and Totals', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-package-stats-overview class=troy-server-settings-accordion-panel>
		<div class=troy-server-stats-cards id=troy-server-package-stats-overview-cards>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Downloads', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value><?= \esc_html( \number_format_i18n( $package_overview['total_downloads'] ) ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Packages', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value><?= \esc_html( \number_format_i18n( $package_overview['total_packages'] ) ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Last Snapshot', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value><?= \esc_html( $package_overview['last_snapshot'] ?? '-' ) ?></span>
			</div>
		</div>
	</div>
</div>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=true class=troy-server-settings-accordion-trigger aria-controls=troy-server-stats-packages type=button>
			<span class=title><?= \esc_html__( 'Package Downloads', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-stats-packages class=troy-server-settings-accordion-panel>
		<table class="widefat striped troy-server-stats-table" id=troy-server-stats-packages-table>
			<thead>
				<tr>
					<th scope=col><?= \esc_html__( 'Package', 'troy-server' ) ?></th>
					<th scope=col><?= \esc_html__( 'Downloads', 'troy-server' ) ?></th>
					<th scope=col><?= \esc_html__( 'Actions', 'troy-server' ) ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( empty( $packages_summary ) ) {
					?>
					<tr>
						<td colspan=3><?= \esc_html__( 'No package data available.', 'troy-server' ) ?></td>
					</tr>
					<?php
				} else foreach ( $packages_summary as $package ) {
					?>
					<tr data-package-id="<?= \esc_attr( $package['package_id'] ) ?>">
						<td>
							<strong><?= \esc_html( $package['name'] ) ?></strong> <code>(<?= \esc_html( $package['slug'] ) ?>)</code>
						</td>
						<td><?= \esc_html( \number_format_i18n( $package['downloads'] ) ) ?></td>
						<td>
							<button type=button class="button button-small troy-server-stats-package-details-btn" data-package-id="<?= \esc_attr( $package['package_id'] ) ?>">
								<?= \esc_html__( 'Details', 'troy-server' ) ?>
							</button>
						</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
	</div>
</div>

<div id=troy-server-stats-modal class=troy-server-stats-modal hidden>
	<div class=troy-server-stats-modal-content>
		<div class=troy-server-stats-modal-header>
			<h3 id=troy-server-stats-modal-title><?= \esc_html__( 'Details', 'troy-server' ) ?></h3>
			<button type=button class=troy-server-stats-modal-close aria-label="<?= \esc_attr__( 'Close', 'troy-server' ) ?>">&times;</button>
		</div>
		<div class=troy-server-stats-modal-body id=troy-server-stats-modal-body>
			<p><?= \esc_html__( 'Loading...', 'troy-server' ) ?></p>
		</div>
	</div>
</div>
