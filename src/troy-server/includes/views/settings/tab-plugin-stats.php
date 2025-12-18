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
$overview         = Settings\Stats::get_overview();
$top_plugins      = Settings\Stats::get_top_plugins();
$epoch_comparison = Settings\Stats::get_epoch_comparison();
$php_versions     = Settings\Stats::get_php_version_stats();
$wp_versions      = Settings\Stats::get_wp_version_stats();
$locales          = Settings\Stats::get_locale_stats();

// Sort versions descending (high to low).
usort(
	$php_versions['versions'],
	fn( $a, $b ) => version_compare( $b['version'], $a['version'] ),
);
usort(
	$wp_versions['versions'],
	fn( $a, $b ) => version_compare( $b['version'], $a['version'] ),
);

$epoch_time_format = 'D j, ' . \get_option( 'time_format' );

?>
<h2><?= \esc_html__( 'Troy Server Stats', 'troy-server' ) ?></h2>

<p><?= \esc_html__( 'Troy Server collects anonymized data from your plugin users. Here, you can inspect the details.', 'troy-server' ) ?></p>
<p><?= \esc_html__( 'The data is aggregated automatically every 10 minutes.', 'troy-server' ) ?></p>
<p><?= \esc_html__( 'Unless otherwise specified, the data shown below is combined from all plugins hosted on this Troy Server instance.', 'troy-server' ) ?></p>

<div class=troy-server-stats-date-range>
	<label for=troy-server-stats-range><?= \esc_html__( 'Date Range:', 'troy-server' ) ?></label>
	<select id=troy-server-stats-range>
		<option value=all selected><?= \esc_html__( 'All Time', 'troy-server' ) ?></option>
		<option value=365><?= \esc_html__( 'Last Year', 'troy-server' ) ?></option>
		<option value=90><?= \esc_html__( 'Last 90 Days', 'troy-server' ) ?></option>
		<option value=30><?= \esc_html__( 'Last 30 Days', 'troy-server' ) ?></option>
	</select>
</div>
<div class=troy-server-stats-date-range>
	<label class=troy-server-stats-auto-refresh>
		<input type=checkbox id=troy-server-stats-auto-refresh>
		<?= \esc_html__( 'Auto-refresh every 15 seconds', 'troy-server' ) ?>
	</label>
</div>

<hr class=hr-separator>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=true class=troy-server-settings-accordion-trigger aria-controls=troy-server-stats-overview type=button>
			<span class=title><?= \esc_html__( 'Overview and Totals', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-stats-overview class=troy-server-settings-accordion-panel>
		<p class=description>
			<?= \esc_html__( 'The "Installations" count uses the highest value between this and last epoch, since a new epoch may still be catching up.', 'troy-server' ) ?>
		</p>
		<p class=description>
			<?= \esc_html__( 'The "Last Snapshot" indicates when the stats were last aggregated. It will not change if there is no new data.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-stats-cards id=troy-server-stats-overview-cards>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Downloads', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=total_downloads><?= \esc_html( \number_format_i18n( $overview['total_downloads'] ) ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Views', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=total_views><?= \esc_html( \number_format_i18n( $overview['total_views'] ) ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Installations', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=total_installs><?= \esc_html( \number_format_i18n( $overview['total_installs'] ) ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Active Installations', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=active_installs><?= \esc_html( \number_format_i18n( $overview['active_installs'] ) ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Inactive Installations', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=inactive_installs><?= \esc_html( \number_format_i18n( $overview['inactive_installs'] ) ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Epoch', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=this_epoch><?= \esc_html( $overview['this_epoch'] ) ?></span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Epoch End', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=this_epoch_end>
					<?php
					echo \esc_html( \date_i18n(
						$epoch_time_format,
						( $overview['this_epoch'] + 1 ) * \WEEK_IN_SECONDS,
					) );
					?>
				</span>
			</div>
			<div class=troy-server-stats-card>
				<span class=troy-server-stats-card-label><?= \esc_html__( 'Last Snapshot', 'troy-server' ) ?></span>
				<span class=troy-server-stats-card-value data-stat=last_snapshot><?= \esc_html( $overview['last_snapshot'] ?? '-' ) ?></span>
			</div>
		</div>
	</div>
</div>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=true class=troy-server-settings-accordion-trigger aria-controls=troy-server-stats-top-plugins type=button>
			<span class=title><?= \esc_html__( 'Top Plugins by Downloads', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-stats-top-plugins class=troy-server-settings-accordion-panel>
		<p class=description>
			<?= \esc_html__( 'The "Installations" count uses the highest value between this and last epoch, since a new epoch may still be catching up. This count is used publicly to display the number of active installations.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-stats-table-wrap>
			<table class="widefat striped troy-server-stats-table" id=troy-server-stats-plugins-table>
				<thead>
					<tr>
						<th scope=col><?= \esc_html__( 'Plugin', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Downloads', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Views', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Installations', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Active Installations', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Inactive Installations', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Actions', 'troy-server' ) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( empty( $top_plugins ) ) {
						?>
						<tr>
							<td colspan=7><?= \esc_html__( 'No plugin data available.', 'troy-server' ) ?></td>
						</tr>
						<?php
					} else foreach ( $top_plugins as $plugin ) {
						?>
						<tr data-plugin-id="<?= \esc_attr( $plugin['plugin_id'] ) ?>">
							<td>
								<strong><?= \esc_html( $plugin['name'] ) ?></strong> <code>(<?= \esc_html( $plugin['slug'] ) ?>)</code>
							</td>
							<td><?= \esc_html( \number_format_i18n( $plugin['downloads'] ) ) ?></td>
							<td><?= \esc_html( \number_format_i18n( $plugin['views'] ) ) ?></td>
							<td><?= \esc_html( \number_format_i18n( $plugin['total_installs'] ) ) ?></td>
							<td><?= \esc_html( \number_format_i18n( $plugin['active_installs'] ) ) ?></td>
							<td><?= \esc_html( \number_format_i18n( $plugin['inactive_installs'] ) ) ?></td>
							<td>
								<button type=button class="button button-small troy-server-stats-details-btn" data-plugin-id="<?= \esc_attr( $plugin['plugin_id'] ) ?>">
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
</div>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=false class=troy-server-settings-accordion-trigger aria-controls=troy-server-stats-epochs type=button>
			<span class=title><?= \esc_html__( 'Epoch Comparison', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-stats-epochs class=troy-server-settings-accordion-panel hidden>
		<p class=description>
			<?= \esc_html__( 'An epoch spans one week. Installation counts are based on unique update requests per epoch.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-stats-table-wrap>
			<table class="widefat striped troy-server-stats-table" id=troy-server-stats-epochs-table>
				<thead>
					<tr>
						<th scope=col><?= \esc_html__( 'Metric', 'troy-server' ) ?></th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'Last Epoch (%d)', 'troy-server' ),
								$epoch_comparison['last_epoch'],
							) );
							?>
						</th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'This Epoch (%d)', 'troy-server' ),
								$epoch_comparison['this_epoch'],
							) );
							?>
						</th>
						<th scope=col><?= \esc_html__( 'Change', 'troy-server' ) ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?= \esc_html__( 'Update Requests', 'troy-server' ) ?></td>
						<td><?= \esc_html( \number_format_i18n( $epoch_comparison['last_requests'] ) ) ?></td>
						<td><?= \esc_html( \number_format_i18n( $epoch_comparison['this_requests'] ) ) ?></td>
						<td class="<?= $epoch_comparison['requests_change_percent'] >= 0 ? 'troy-server-stats-positive' : 'troy-server-stats-negative' ?>">
							<?php
							if ( \is_infinite( $epoch_comparison['requests_change_percent'] ) ) {
								echo '+∞%';
							} else {
								echo \esc_html( ( $epoch_comparison['requests_change_percent'] >= 0 ? '+' : '' ) . $epoch_comparison['requests_change_percent'] . '%' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?= \esc_html__( 'Active Installations', 'troy-server' ) ?></td>
						<td><?= \esc_html( \number_format_i18n( $epoch_comparison['last_active_installs'] ) ) ?></td>
						<td><?= \esc_html( \number_format_i18n( $epoch_comparison['this_active_installs'] ) ) ?></td>
						<td class="<?= $epoch_comparison['active_change_percent'] >= 0 ? 'troy-server-stats-positive' : 'troy-server-stats-negative' ?>">
							<?php
							if ( \is_infinite( $epoch_comparison['active_change_percent'] ) ) {
								echo '+∞%';
							} else {
								echo \esc_html( ( $epoch_comparison['active_change_percent'] >= 0 ? '+' : '' ) . $epoch_comparison['active_change_percent'] . '%' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?= \esc_html__( 'Inactive Installations', 'troy-server' ) ?></td>
						<td><?= \esc_html( \number_format_i18n( $epoch_comparison['last_inactive_installs'] ) ) ?></td>
						<td><?= \esc_html( \number_format_i18n( $epoch_comparison['this_inactive_installs'] ) ) ?></td>
						<td class="<?= $epoch_comparison['inactive_change_percent'] >= 0 ? 'troy-server-stats-positive' : 'troy-server-stats-negative' ?>">
							<?php
							if ( \is_infinite( $epoch_comparison['inactive_change_percent'] ) ) {
								echo '+∞%';
							} else {
								echo \esc_html( ( $epoch_comparison['inactive_change_percent'] >= 0 ? '+' : '' ) . $epoch_comparison['inactive_change_percent'] . '%' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=false class=troy-server-settings-accordion-trigger aria-controls=troy-server-stats-php-versions type=button>
			<span class=title><?= \esc_html__( 'PHP Version Usage', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-stats-php-versions class=troy-server-settings-accordion-panel hidden>
		<p class=description>
			<?= \esc_html__( 'PHP versions reported by active installations during update checks.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-stats-table-wrap>
			<table class="widefat striped troy-server-stats-table" id=troy-server-stats-php-versions-table>
				<thead>
					<tr>
						<th scope=col><?= \esc_html__( 'PHP Version', 'troy-server' ) ?></th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'Last Epoch (%d)', 'troy-server' ),
								$php_versions['last_epoch'],
							) );
							?>
						</th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'This Epoch (%d)', 'troy-server' ),
								$php_versions['this_epoch'],
							) );
							?>
						</th>
						<th scope=col><?= \esc_html__( 'Change', 'troy-server' ) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( empty( $php_versions['versions'] ) ) {
						?>
						<tr>
							<td colspan=4><?= \esc_html__( 'No PHP version data available.', 'troy-server' ) ?></td>
						</tr>
						<?php
					} else foreach ( $php_versions['versions'] as $version ) {
						?>
						<tr>
							<td><code><?= \esc_html( $version['version'] ?: \__( 'Not reported', 'troy-server' ) ) ?></code></td>
							<td><?= \esc_html( \number_format_i18n( $version['last_count'] ) ) ?></td>
							<td><?= \esc_html( \number_format_i18n( $version['this_count'] ) ) ?></td>
							<td class="<?= $version['change_percent'] >= 0 ? 'troy-server-stats-positive' : 'troy-server-stats-negative' ?>">
								<?php
								if ( \is_infinite( $version['change_percent'] ) ) {
									echo '+∞%';
								} else {
									echo \esc_html( ( $version['change_percent'] >= 0 ? '+' : '' ) . $version['change_percent'] . '%' );
								}
								?>
						</td>
					</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=false class=troy-server-settings-accordion-trigger aria-controls=troy-server-stats-wp-versions type=button>
			<span class=title><?= \esc_html__( 'WordPress Version Usage', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-stats-wp-versions class=troy-server-settings-accordion-panel hidden>
		<p class=description>
			<?= \esc_html__( 'WordPress versions reported by active installations during update checks.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-stats-table-wrap>
			<table class="widefat striped troy-server-stats-table" id=troy-server-stats-wp-versions-table>
				<thead>
					<tr>
						<th scope=col><?= \esc_html__( 'WordPress Version', 'troy-server' ) ?></th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'Last Epoch (%d)', 'troy-server' ),
								$wp_versions['last_epoch'],
							) );
							?>
						</th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'This Epoch (%d)', 'troy-server' ),
								$wp_versions['this_epoch'],
							) );
							?>
						</th>
						<th scope=col><?= \esc_html__( 'Change', 'troy-server' ) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( empty( $wp_versions['versions'] ) ) {
						?>
						<tr>
							<td colspan=4><?= \esc_html__( 'No WordPress version data available.', 'troy-server' ) ?></td>
						</tr>
						<?php
					} else foreach ( $wp_versions['versions'] as $version ) {
						?>
						<tr>
							<td><code><?= \esc_html( $version['version'] ?: \__( 'Not reported', 'troy-server' ) ) ?></code></td>
							<td><?= \esc_html( \number_format_i18n( $version['last_count'] ) ) ?></td>
							<td><?= \esc_html( \number_format_i18n( $version['this_count'] ) ) ?></td>
							<td class="<?= $version['change_percent'] >= 0 ? 'troy-server-stats-positive' : 'troy-server-stats-negative' ?>">
								<?php
								if ( \is_infinite( $version['change_percent'] ) ) {
									echo '+∞%';
								} else {
									echo \esc_html( ( $version['change_percent'] >= 0 ? '+' : '' ) . $version['change_percent'] . '%' );
								}
								?>
						</td>
					</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=false class=troy-server-settings-accordion-trigger aria-controls=troy-server-stats-locales type=button>
			<span class=title><?= \esc_html__( 'Locale Usage', 'troy-server' ) ?></span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-stats-locales class=troy-server-settings-accordion-panel hidden>
		<p class=description>
			<?= \esc_html__( 'Locales reported by active installations during update checks.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-stats-table-wrap>
			<table class="widefat striped troy-server-stats-table" id=troy-server-stats-locales-table>
				<thead>
					<tr>
						<th scope=col><?= \esc_html__( 'Locale', 'troy-server' ) ?></th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'Last Epoch (%d)', 'troy-server' ),
								$locales['last_epoch'],
							) );
							?>
						</th>
						<th scope=col>
							<?php
							echo \esc_html( \sprintf(
								/* translators: %d is the epoch number */
								\__( 'This Epoch (%d)', 'troy-server' ),
								$locales['this_epoch'],
							) );
							?>
						</th>
						<th scope=col><?= \esc_html__( 'Change', 'troy-server' ) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( empty( $locales['locales'] ) ) {
						?>
						<tr>
							<td colspan=4><?= \esc_html__( 'No locale data available.', 'troy-server' ) ?></td>
						</tr>
						<?php
					} else foreach ( $locales['locales'] as $locale ) {
						?>
						<tr>
							<td><code><?= \esc_html( $locale['locale'] ?: \__( 'Not reported', 'troy-server' ) ) ?></code></td>
							<td><?= \esc_html( \number_format_i18n( $locale['last_count'] ) ) ?></td>
							<td><?= \esc_html( \number_format_i18n( $locale['this_count'] ) ) ?></td>
							<td class="<?= $locale['change_percent'] >= 0 ? 'troy-server-stats-positive' : 'troy-server-stats-negative' ?>">
								<?php
								if ( \is_infinite( $locale['change_percent'] ) ) {
									echo '+∞%';
								} else {
									echo \esc_html( ( $locale['change_percent'] >= 0 ? '+' : '' ) . $locale['change_percent'] . '%' );
								}
								?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
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
