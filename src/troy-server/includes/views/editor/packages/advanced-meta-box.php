<?php
/**
 * @package Troy\Server\Views\Editor\Packages
 */

namespace Troy\Server\Views\Editor\Packages;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use Troy\Server\Packages\Data;

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

[ $post ] = $view_args;

if ( $post ) {
	$meta = new Data( post_id: $post->ID )->get_metas_row();
} else {
	$meta = null;
}

if ( ! $meta ) {
	$meta = (object) [
		'install_timeout'          => 30,
		'deactivate_on_completion' => 1,
		'delete_on_completion'     => 0,
		'network_activation'       => 'block',
		'notice_severity'          => 'detailed',
	];
}

?>
<table class=form-table>
	<tr>
		<th><label for=troy-server-package-install-timeout><?= \esc_html__( 'Install Timeout', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=number
				id=troy-server-package-install-timeout
				name="troy-server-package[install_timeout]"
				value="<?= \esc_attr( $meta->install_timeout ) ?>"
				class="small-text"
				min="7"
			/>
			<p class=description><?= \esc_html__( 'Maximum execution time for the installer in seconds (default: 30, min: 7, max: 60).', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-deactivate-on-completion><?= \esc_html__( 'Deactivate on Completion', 'troy-server' ) ?></label></th>
		<td>
			<label>
				<input
					type=checkbox
					id=troy-server-package-deactivate-on-completion
					name="troy-server-package[deactivate_on_completion]"
					value="1"
					<?php \checked( $meta->deactivate_on_completion, 1 ); ?>
				/>
				<?= \esc_html__( 'Deactivate installer after successful installation.', 'troy-server' ) ?>
			</label>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-delete-on-completion><?= \esc_html__( 'Delete on Completion', 'troy-server' ) ?></label></th>
		<td>
			<label>
				<input
					type=checkbox
					id=troy-server-package-delete-on-completion
					name="troy-server-package[delete_on_completion]"
					value="1"
					<?php \checked( $meta->delete_on_completion, 1 ); ?>
				/>
				<?= \esc_html__( 'Delete installer after successful installation (requires deactivation).', 'troy-server' ) ?>
			</label>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-network-activation><?= \esc_html__( 'Network Activation', 'troy-server' ) ?></label></th>
		<td>
			<select
				id=troy-server-package-network-activation
				name="troy-server-package[network_activation]"
			>
				<option value=block <?php \selected( $meta->network_activation, 'block' ); ?>><?= \esc_html__( 'Block', 'troy-server' ) ?></option>
				<option value=activate-all <?php \selected( $meta->network_activation, 'activate-all' ); ?>><?= \esc_html__( 'Activate All', 'troy-server' ) ?></option>
			</select>
			<p class=description>
				<?= \esc_html__( 'Controls behavior when the installer is network-activated on a multisite. Block prevents network activation entirely. Activate All network-activates all plugins.', 'troy-server' ) ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-notice-severity><?= \esc_html__( 'Notice Severity', 'troy-server' ) ?></label></th>
		<td>
			<select
				id=troy-server-package-notice-severity
				name="troy-server-package[notice_severity]"
			>
				<option value=detailed <?php \selected( $meta->notice_severity, 'detailed' ); ?>><?= \esc_html__( 'Detailed', 'troy-server' ) ?></option>
				<option value=verbose <?php \selected( $meta->notice_severity, 'verbose' ); ?>><?= \esc_html__( 'Verbose', 'troy-server' ) ?></option>
				<option value=silent <?php \selected( $meta->notice_severity, 'silent' ); ?>><?= \esc_html__( 'Silent', 'troy-server' ) ?></option>
			</select>
			<p class=description>
				<?= \esc_html__( 'Detailed shows user-friendly progress updates, verbose shows detailed error data (if any), and silent shows no notices.', 'troy-server' ) ?>
			</p>
		</td>
	</tr>
</table>
