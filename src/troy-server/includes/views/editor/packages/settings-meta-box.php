<?php
/**
 * @package Troy\Server\Views\Editor\Packages
 */

namespace Troy\Server\Views\Editor\Packages;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use Troy\Server\{
	API,
	Packages\CPT\Store,
	Packages\Data,
};

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

[ $post ] = $view_args;

if ( $post ) {
	$data = new Data( post_id: $post->ID );

	$package = $data->get_packages_row();
	$meta    = $data->get_metas_row(); // can be empty if post is never saved.
} else {
	$package = null;
	$meta    = null;
}

if ( ! $meta ) {

	$current_user = \wp_get_current_user();

	$meta = (object) array_merge(
		Store::get_default_package_data(),
		[
			'author'     => $current_user->display_name ?: '',
			'author_uri' => $current_user->user_url ?: '',
		],
	);
}
?>
<table class=form-table>
	<tr>
		<th><label for=troy-server-package-slug><?= \esc_html__( 'Slug', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy-server-package-slug
				name="troy-server-package[slug]"
				pattern="[a-z1-9][a-z0-9\-]*"
				maxlength=191
				value="<?= \esc_attr( $package->slug ?? '' ) ?>"
				class=regular-text
			>
			<p class=description>
				<?= \esc_html__( 'The slug used in the download URL or Composer package name:', 'troy-server' ) ?>
			</p>
			<ul class="description troy-server-slug-examples">
				<li><code class=troy-server-copyable><?= \esc_html( API\Server::get_full_repo_url() ) ?>package/get/zip/<span class=troy-server-slug-example>&lt;slug&gt;</span></code></li>
				<li><code class=troy-server-copyable><?= \esc_html( API\Server::get_composer_vendor() ) ?>-package/<span class=troy-server-slug-example>&lt;slug&gt;</span></code></li>
			</ul>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-version><?= \esc_html__( 'Version', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy-server-package-version
				name=troy-server-package[version]
				value="<?= \esc_attr( $meta->version ) ?>"
				class=regular-text
				required
			>
			<p class=description><?= \esc_html__( 'Package version number (e.g., 1.0.0).', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th>
			<label for=troy-server-package-description><?= \esc_html__( 'Description', 'troy-server' ) ?></label>
			<br>
			<small id=troy-server-package-description__counter class=form-input-tip></small>
		</th>
		<td>
			<textarea
				id=troy-server-package-description
				name=troy-server-package[description]
				class=large-text
				maxlength=191
				rows=2
				required
				style="resize:none"
			><?= \esc_html( $meta->description ) ?></textarea>
			<p class=description><?= \esc_html__( 'Brief description of the package contents.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-plugin-uri><?= \esc_html__( 'Plugin URI', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=url
				id=troy-server-package-plugin-uri
				name="troy-server-package[plugin_uri]"
				value="<?= \esc_attr( $meta->plugin_uri ) ?>"
				class=regular-text
				maxlength=250
			>
			<p class=description><?= \esc_html__( 'URL to the package homepage or documentation.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-author><?= \esc_html__( 'Author', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy-server-package-author
				name=troy-server-package[author]
				value="<?= \esc_attr( $meta->author ) ?>"
				class=regular-text
			>
			<p class=description><?= \esc_html__( 'Package author name.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-author-uri><?= \esc_html__( 'Author URI', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=url
				id=troy-server-package-author-uri
				name="troy-server-package[author_uri]"
				value="<?= \esc_attr( $meta->author_uri ) ?>"
				class=regular-text
			>
			<p class=description><?= \esc_html__( 'URL to the author website.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-requires-wp><?= \esc_html__( 'Requires WordPress', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy-server-package-requires-wp
				name=troy-server-package[requires_wp]
				value="<?= \esc_attr( $meta->requires_wp ) ?>"
				class=small-text
			>
			<p class=description><?= \esc_html__( 'Minimum WordPress version required (e.g., 6.7). Troy Client requires at least WordPress 6.7.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy-server-package-requires-php><?= \esc_html__( 'Requires PHP', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy-server-package-requires-php
				name=troy-server-package[requires_php]
				value="<?= \esc_attr( $meta->requires_php ) ?>"
				class=small-text
			>
			<p class=description><?= \esc_html__( 'Minimum PHP version required (e.g., 7.4). Troy Client requires at least PHP 7.4.', 'troy-server' ) ?></p>
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
				<option value=require <?php \selected( $meta->network_activation, 'require' ); ?>><?= \esc_html__( 'Require', 'troy-server' ) ?></option>
			</select>
			<p class=description>
				<?= \esc_html__( 'Controls multisite behavior. Block prevents network activation. Activate All allows network activation, by then activating all plugins. Require makes network activation mandatory.', 'troy-server' ) ?>
			</p>
		</td>
	</tr>
</table>
