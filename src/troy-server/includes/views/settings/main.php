<?php
/**
 * @package Troy\Server\Views\Settings
 */

namespace Troy\Server\Views\Settings;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use Troy\Server\{
	Settings,
	Template,
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

/**
 * @since 0.0.1184
 * @param array The naviational tabs.
 */
$tabs = \apply_filters(
	'troy_server_settings_tabs',
	[
		'settings'      => [
			'title' => \_x( 'Settings', 'Tab title', 'troy-server' ),
			'link'  => \admin_url( 'admin.php?page=' . Settings\Main::SETTINGS_PAGE_SLUG ),
		],
		'plugin-stats'  => [
			'title' => \_x( 'Plugin Stats', 'Tab title', 'troy-server' ),
			'link'  => \admin_url( 'admin.php?page=' . Settings\Main::SETTINGS_PAGE_SLUG . '&tab=plugin-stats' ),
		],
		'package-stats' => [
			'title' => \_x( 'Package Stats', 'Tab title', 'troy-server' ),
			'link'  => \admin_url( 'admin.php?page=' . Settings\Main::SETTINGS_PAGE_SLUG . '&tab=package-stats' ),
		],
		'logs'          => [
			'title' => \_x( 'Logs', 'Tab title', 'troy-server' ),
			'link'  => \admin_url( 'admin.php?page=' . Settings\Main::SETTINGS_PAGE_SLUG . '&tab=logs' ),
		],
	],
);

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affects output view only.
$current_tab = isset( $_GET['tab'], $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : 'settings';

?>
<div class=troy-server-settings-header>
	<div class=troy-server-settings-title-section>
		<h1><?= \esc_html__( 'Troy Server', 'troy-server' ) ?></h1>
	</div>
	<nav class="troy-server-settings-tabs-wrapper hide-if-no-js" aria-label="<?= \esc_attr__( 'Secondary menu', 'default' ) ?>">
		<?php
		$tab_attributes = [
			'active'   => 'class="troy-server-settings-tab active" aria-current="true"',
			'inactive' => 'class="troy-server-settings-tab"',
		];
		foreach ( $tabs as $tab_key => $tab ) {
			printf(
				'<a href="%s" %s>%s</a>',
				\esc_url( $tab['link'] ),
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- String literals.
				$tab_attributes[ $tab_key === $current_tab ? 'active' : 'inactive' ],
				\esc_html( $tab['title'] )
			);
		}
		?>
	</nav>
</div>

<div class="troy-server-settings-body">
	<hr class=wp-header-end>

	<div class="notice notice-error hide-if-js inline">
		<p><?= \esc_html__( 'Troy Server settings require JavaScript.', 'troy-server' ) ?></p>
	</div>

	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affects output view only.
	switch ( (int) ( $_GET[ Settings\Main::SAVED_RESPONSE ] ?? -1 ) ) {
		case 0:
			?>
			<div id=message class="notice notice-error is-dismissible inline"><p>
				<?= \esc_html__( 'Settings failed to save.', 'troy-server' ) ?>
			</p></div>
			<?php
			break;
		case 1:
			?>
			<div id=message class="notice notice-success is-dismissible inline"><p>
				<?= \esc_html__( 'Settings saved.', 'troy-server' ) ?>
			</p></div>
			<?php
			break;
		case 2:
			?>
			<div id=message class="notice notice-info is-dismissible inline"><p>
				<?= \esc_html__( 'No settings were changed.', 'troy-server' ) ?>
			</p></div>
			<?php
	}
	?>
	<div class=hide-if-no-js>
		<?php
		/**
		 * Outputs the notices for the current Troy Server settings page.
		 *
		 * @since 0.0.1184
		 *
		 * @param string $current_tab The current settings tab.
		 */
		\do_action( 'troy_server_settings_notices', $current_tab );

		/**
		 * Outputs the content of the current Troy Server settings page.
		 *
		 * @since 0.0.1184
		 *
		 * @param string $current_tab The current settings tab.
		 */
		\do_action( 'troy_server_settings_tab_content', $current_tab );
		?>
	</div>
</div>
