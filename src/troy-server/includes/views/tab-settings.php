<?php
/**
 * @package Troy\Server\Views
 */

namespace Troy\Server\Views;

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- We're not in the global space.

\defined( 'Troy\Server\ABSPATH' ) or die;

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

?>
<h2><?= \esc_html__( 'Troy Server Options', 'troy-server' ); ?></h2>

<p><?= \esc_html__( 'Troy Server allows you to manage your bespoke repository.', 'troy-server' ) ?></p>

<hr class=hr-separator>

<form method=post action="<?= \esc_url( \admin_url( 'admin-post.php' ) ) ?>">
	<?php
	\wp_nonce_field( Settings::SAVE_NONCE['action'], Settings::SAVE_NONCE['name'] );
	// The next field allows callback to `admin_post_' . Settings::SAVE_ACTION`
	?>
	<input type=hidden name=action value="<?= \esc_attr( Settings::SAVE_ACTION ) ?>">
	<?php
	$sections = [
		'general' => [
			\__( 'General Settings', 'troy-server' ),
			[
				'troy_server_settings[test]' => [
					'checkbox',
					\get_option( 'troy_server_settings_test', false ),
					\__( 'Test Mode', 'troy-server' ),
					\__( 'Enable test mode.', 'troy-server' ),
					\__( 'This is a tooltip.', 'troy-server' ),
				],
			],
		],
		'example' => [
			\__( 'Example Settings', 'troy-server' ),
			[],
		],
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

	<?php \submit_button(); ?>
</form>
<?php
