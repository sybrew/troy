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

$meta = new Data( post_id: $post->ID )->get_metas_row();

$selected_plugins = $meta ? $meta->plugins : [];
$selected_plugins = \is_array( $selected_plugins ) ? $selected_plugins : [];

// Convert to plugin_id => options format for easy lookup
$plugin_options = [];
foreach ( $selected_plugins as $plugin_data ) {
	if ( isset( $plugin_data['id'] ) ) {
		$plugin_options[ $plugin_data['id'] ] = [
			'network'        => ! empty( $plugin_data['network'] ),
			'activate'       => ! empty( $plugin_data['activate'] ),
			'overwrite'      => ! empty( $plugin_data['overwrite'] ),
			'overwrite_troy' => ! empty( $plugin_data['overwrite_troy'] ),
		];
	}
}

global $wpdb;

$plugins = $wpdb->get_results(
	"SELECT p.id, p.slug, m.name
	 FROM `{$wpdb->prefix}troy_plugins` p
	 LEFT JOIN `{$wpdb->prefix}troy_plugin_metas` m ON p.id = m.plugin_id
	 ORDER BY m.name ASC",
);
?>
<div class=troy-package-metabox-plugin-list>
	<div class=troy-package-plugin-item>
		<label class=troy-package-plugin-option>
			<span class=troy-package-plugin-option-box>
				<input type=checkbox checked disabled>
			</span>
			<span class=troy-package-plugin-option-content>
				<span class=troy-package-plugin-option-title>
					<?= \esc_html__( 'Troy Client', 'troy-server' ) ?>
					<code>(troy-client)</code>
				</span>
				<span class=troy-package-plugin-option-desc><?= \esc_html__( 'Required for connecting your Troy Server plugins and themes to this repo.', 'troy-server' ) ?></span>
			</span>
		</label>
	</div>
	<?php
	if ( $plugins ) {
		foreach ( $plugins as $plugin ) {

			$is_selected = isset( $plugin_options[ $plugin->id ] );
			$options     = $plugin_options[ $plugin->id ] ?? [
				'network'        => false,
				'activate'       => true,
				'overwrite'      => true,
				'overwrite_troy' => false,
			];
			?>
			<div class=troy-package-plugin-item data-plugin-id="<?= \esc_attr( $plugin->id ) ?>">
				<label class=troy-package-plugin-main>
					<input
						type=checkbox
						class=troy-package-plugin-checkbox
						data-plugin-id="<?= \esc_attr( $plugin->id ) ?>"
						<?php \checked( $is_selected ); ?>
						>
					<?= \esc_html( $plugin->name ?: $plugin->slug ) ?>
					<code>(<?= \esc_html( $plugin->slug ) ?>)</code>
				</label>
				<div class=troy-package-plugin-options style="<?= $is_selected ? '' : 'display:none;' ?>">
					<label class=troy-package-plugin-option>
						<span class=troy-package-plugin-option-box>
							<input
								type=checkbox
								name="troy_package[plugins][<?= \esc_attr( $plugin->id ) ?>][activate]"
								value=1
								<?php \checked( $options['activate'] ); ?>
								>
						</span>
							<span class=troy-package-plugin-option-content>
								<span class=troy-package-plugin-option-title><?= \esc_html__( 'Activate', 'troy-server' ) ?></span>
								<span class=troy-package-plugin-option-desc><?= \esc_html__( 'Activate the plugin after installation.', 'troy-server' ) ?></span>
							</span>
						</label>
						<label class=troy-package-plugin-option>
						<span class=troy-package-plugin-option-box>
							<input
								type=checkbox
								name="troy_package[plugins][<?= \esc_attr( $plugin->id ) ?>][network]"
								value=1
								<?php \checked( $options['network'] ); ?>
								>
						</span>
							<span class=troy-package-plugin-option-content>
								<span class=troy-package-plugin-option-title><?= \esc_html__( 'Network', 'troy-server' ) ?></span>
								<span class=troy-package-plugin-option-desc><?= \esc_html__( 'Network-activate the plugin in multisite installations.', 'troy-server' ) ?></span>
							</span>
						</label>
						<label class=troy-package-plugin-option>
						<span class=troy-package-plugin-option-box>
							<input
								type=checkbox
								name="troy_package[plugins][<?= \esc_attr( $plugin->id ) ?>][overwrite]"
								value=1
								<?php \checked( $options['overwrite'] ); ?>
								>
						</span>
							<span class=troy-package-plugin-option-content>
								<span class=troy-package-plugin-option-title><?= \esc_html__( 'Overwrite', 'troy-server' ) ?></span>
								<span class=troy-package-plugin-option-desc><?= \esc_html__( 'Overwrite existing plugin files if already installed.', 'troy-server' ) ?></span>
							</span>
						</label>
						<label class=troy-package-plugin-option>
						<span class=troy-package-plugin-option-box>
							<input
								type=checkbox
								name="troy_package[plugins][<?= \esc_attr( $plugin->id ) ?>][overwrite_troy]"
								value=1
								<?php \checked( $options['overwrite_troy'] ); ?>
								>
						</span>
							<span class=troy-package-plugin-option-content>
								<span class=troy-package-plugin-option-title><?= \esc_html__( 'Overwrite Troy', 'troy-server' ) ?></span>
								<span class=troy-package-plugin-option-desc><?= \esc_html__( 'Overwrite Troy Client if already installed.', 'troy-server' ) ?></span>
							</span>
						</label>
					</div>
				</div>
			<?php
		}
	} else {
		?>
		<p><?= \esc_html__( 'No plugins available.', 'troy-server' ) ?></p>
		<?php
	}
	?>
</div>
