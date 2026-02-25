<?php
/**
 * @package Troy\Server\Views\Settings
 */

namespace Troy\Server\Views\Settings;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use Troy\Server\{
	API,
	Settings,
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

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- We're not in the global space.

$repo_url = API\Server::get_repo_url();
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
				<td data-colname="<?= \esc_attr__( 'Troy plugin header', 'troy-server' ) ?>">
					<?= \esc_html__( 'Troy plugin header', 'troy-server' ) ?>
				</td>
				<td data-colname="<?= \esc_attr( "Troy: $repo_url" ) ?>">
					<p class=description><?= \esc_html__( 'Add this header to your main plugin file to connect it to this Troy Server.', 'troy-server' ) ?></p>
					<pre class="troy-server-pre troy-server-copyable"><code><?= \esc_html( "Troy: $repo_url" ) ?></code></pre>
					<p class=description><?= \esc_html__( 'Example plugin header:', 'troy-server' ) ?></p>
					<pre class=troy-server-pre><code><?= \esc_html( "<?php\n/**\n * Plugin Name: Example Plugin\n * Troy: $repo_url\n * Version: 1.0.0\n */" ) ?></code></pre>
				</td>
			</tr>
		</table>
	</div>
</div>

<?php
// TODO: Add more setup options.
return;
?>

<form method=post action="<?= \esc_url( \admin_url( 'admin-post.php' ) ) ?>">
	<?php
	\wp_nonce_field( Settings\Main::SAVE_NONCE['action'], Settings\Main::SAVE_NONCE['name'] );
	// The next field allows callback to `admin_post_' . Settings\Main::SAVE_ACTION`
	?>
	<input type=hidden name=action value="<?= \esc_attr( Settings\Main::SAVE_ACTION ) ?>">
	<?php
	$sections = [
		// 'no_settings_yet' => [
		// 	\__( 'No settings yet (you can configure your plugins and packages in-place)', 'troy-server' ),
		// 	[],
		// ],
		// 'general' => [
		// 	\__( 'General Settings', 'troy-server' ),
		// 	[
		// 		'troy_server_settings[test]' => [
		// 			'checkbox',
		// 			\get_option( 'troy_server_settings_test', false ),
		// 			\__( 'Test Mode', 'troy-server' ),
		// 			\__( 'Enable test mode.', 'troy-server' ),
		// 			\__( 'This is a tooltip.', 'troy-server' ),
		// 		],
		// 	],
		// ],
		// 'example' => [
		// 	\__( 'Example Settings', 'troy-server' ),
		// 	[],
		// ],
	];

	$opened_section = false;
	$expand_at_load = true;

	foreach ( $sections as $section => [ $title, $settings ] ) {
		if ( $opened_section ) {
			// Close last opened.
			?>
				</table>
			</div>
		</div>
			<?php
		}

		?>
		<div class=troy-server-settings-accordion>
			<h3 class=troy-server-settings-accordion-heading>
				<button aria-expanded=<?= $expand_at_load ? 'true' : 'false' ?> class=troy-server-settings-accordion-trigger aria-controls="troy-server-settings-accordion-block-<?= \sanitize_key( $section ) ?>" type=button>
					<span class=title><?= \esc_html( $title ) ?></span>
					<span class=icon></span>
				</button>
			</h3>
			<div id="troy-server-settings-accordion-block-<?= \sanitize_key( $section ) ?>" class=troy-server-settings-accordion-panel <?= $expand_at_load ? '' : 'hidden' ?>>
				<table class="widefat striped form-table troy-server-settings-form">
					<tr>
						<th scope=col><?= \esc_html__( 'Setting', 'troy-server' ) ?></th>
						<th scope=col><?= \esc_html__( 'Details', 'troy-server' ) ?></th>
					</tr>
		<?php
		foreach ( $settings as $option_name => [ $type, $value, $label, $description, $tooltip ] ) {
			switch ( $type ) {
				case 'checkbox':
					?>
					<tr>
						<td data-colname="<?= \esc_attr( $label ) ?>">
							<label for="<?= \esc_attr( $option_name ) ?>">
								<input type=checkbox name="<?= \esc_attr( $option_name ) ?>" id="<?= \esc_attr( $option_name ) ?>" value=1 <?php \checked( $value, true ); ?>>
								<?= \esc_html( $label ) ?>
							</label>
						</td>
						<td data-colname="<?= \esc_attr( $description ) ?>">
							<p>
								<?php
								echo \esc_html( $description );

								if ( $tooltip ) {
									\printf(
										' <span data-troy-server-tooltip="%s"></span>',
										\esc_attr( $tooltip ),
									);
								}
								?>
							</p>
						</td>
					</tr>
					<?php
					break;
			}

			$previous_type = $type;
		}

		$opened_section = true;
	}

	if ( $opened_section ) {
		// Close last opened.
		?>
				</table>
			</div>
		</div>
		<?php
	}
	?>

	<hr class=hr-separator>
	<?php
	\submit_button();
	?>
</form>
<?php
