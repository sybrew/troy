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
$integration_history = Settings\Logs::get_integration_history();
$integration_logs    = Settings\Logs::get_integration_logs();

?>
<h2><?= \esc_html__( 'Server Logs', 'troy-server' ) ?></h2>

<p><?= \esc_html__( 'View Troy Server logs from various syncing processes.', 'troy-server' ) ?></p>

<div class=troy-server-logs-controls>
	<button type=button class="button button-secondary" id=troy-server-logs-refresh>
		<?= \esc_html__( 'Refresh Logs', 'troy-server' ) ?>
	</button>
	<label class=troy-server-logs-auto-refresh>
		<input type=checkbox id=troy-server-logs-auto-refresh-toggle>
		<?= \esc_html__( 'Auto-refresh every 20 seconds', 'troy-server' ) ?>
	</label>
</div>

<hr class=hr-separator>

<?php // Integration History Section ?>
<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=false class=troy-server-settings-accordion-trigger aria-controls=troy-server-logs-history type=button>
			<span class=title>
				<?= \esc_html__( 'Integration History', 'troy-server' ) ?>
				<span class=troy-server-logs-count id=troy-server-logs-history-count>(<?= \count( $integration_history ) ?>)</span>
			</span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-logs-history class=troy-server-settings-accordion-panel hidden>
		<p class=description>
			<?= \esc_html__( 'Integration attempts are logged here. Blocked entries may require manual intervention.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-logs-table-actions>
			<button type=button class="button button-secondary button-small troy-server-logs-clear-btn" data-log-type=history>
				<?= \esc_html__( 'Clear History', 'troy-server' ) ?>
			</button>
			<span data-troy-server-tooltip="<?= \esc_attr__( 'This history is used to monitor process queues. Clearing this will permit reprocessing.', 'troy-server' ) ?>"></span>
		</div>
		<div class=troy-server-logs-table-wrap id=troy-server-logs-history-table-wrap>
			<table class="widefat striped troy-server-logs-table" id=troy-server-logs-history-table>
				<thead>
					<tr>
						<th scope=col><?= \esc_html__( 'Plugin ID', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Type', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Tag name', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Mode', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Message', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Attempts', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Time', 'troy-server' ) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( empty( $integration_history ) ) {
						?>
						<tr>
							<td colspan=7><?= \esc_html__( 'No history recorded.', 'troy-server' ) ?></td>
						</tr>
						<?php
					} else foreach ( $integration_history as $entry ) {
						switch ( $entry['status'] ) {
							case 'SUCCESS':
								$type       = 'success';
								$type_label = \__( 'Success', 'troy-server' );
								break;
							case 'FAILED':
								$type       = 'warning';
								$type_label = \__( 'Retrying', 'troy-server' );
								break;
							case 'BLOCKED':
								$type       = 'error';
								$type_label = \__( 'Blocked', 'troy-server' );
								break;
							default:
								$type       = 'info';
								$type_label = \__( 'Unknown', 'troy-server' );
						}
						?>
						<tr data-history-id="<?= \esc_attr( $entry['id'] ) ?>" class="troy-server-logs-type-<?= \esc_attr( $type ) ?>">
							<td>
								<?= \esc_html( $entry['plugin_id'] ) ?> <code>(<?= \esc_html( $entry['plugin_slug'] ) ?>)</code>
							</td>
							<td>
								<span class="troy-server-logs-type troy-server-logs-type-<?= \esc_attr( $type ) ?>">
									<?= \esc_html( $type_label ) ?>
								</span>
							</td>
							<td><code><?= \esc_html( $entry['package_version'] ) ?></code></td>
							<td><?= \esc_html( $entry['mode'] ) ?></td>
							<td class=troy-server-logs-message><?= \esc_html( $entry['reason'] ) ?></td>
							<td><?= \esc_html( $entry['attempts'] ) ?></td>
							<td class=troy-server-logs-timestamp><?= \esc_html( $entry['updated_at'] ) ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php // Integration Logs Section ?>
<div class=troy-server-settings-accordion>
	<h3 class=troy-server-settings-accordion-heading>
		<button aria-expanded=false class=troy-server-settings-accordion-trigger aria-controls=troy-server-logs-entries type=button>
			<span class=title>
				<?= \esc_html__( 'Integration Logs', 'troy-server' ) ?>
				<span class=troy-server-logs-count id=troy-server-logs-entries-count>(<?= \count( $integration_logs ) ?>)</span>
			</span>
			<span class=icon></span>
		</button>
	</h3>
	<div id=troy-server-logs-entries class=troy-server-settings-accordion-panel hidden>
		<p class=description>
			<?= \esc_html__( 'General integration activity logs including errors, warnings, and informational messages.', 'troy-server' ) ?>
		</p>
		<div class=troy-server-logs-table-actions>
			<button type=button class="button button-secondary button-small troy-server-logs-clear-btn" data-log-type=logs>
				<?= \esc_html__( 'Clear Logs', 'troy-server' ) ?>
			</button>
		</div>
		<div class=troy-server-logs-table-wrap id=troy-server-logs-entries-table-wrap>
			<table class="widefat striped troy-server-logs-table" id=troy-server-logs-entries-table>
				<thead>
					<tr>
						<th scope=col><?= \esc_html__( 'Plugin ID', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Type', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Message', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Time', 'troy-server' ) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( empty( $integration_logs ) ) {
						?>
						<tr>
							<td colspan=4><?= \esc_html__( 'No log entries found.', 'troy-server' ) ?></td>
						</tr>
						<?php
					} else foreach ( $integration_logs as $log ) {
						?>
						<tr data-log-id="<?= \esc_attr( $log['id'] ) ?>" class="troy-server-logs-type-<?= \esc_attr( $log['type'] ) ?>">
							<td>
								<?= \esc_html( $log['plugin_id'] ) ?> <code>(<?= \esc_html( $log['plugin_slug'] ) ?>)</code>
							</td>
							<td>
								<span class="troy-server-logs-type troy-server-logs-type-<?= \esc_attr( $log['type'] ) ?>">
									<?= \esc_html( $log['type'] ) ?>
								</span>
							</td>
							<td class=troy-server-logs-message><?= \esc_html( $log['message'] ) ?></td>
							<td class=troy-server-logs-timestamp><?= \esc_html( $log['created_at'] ) ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
	</div>
</div>
