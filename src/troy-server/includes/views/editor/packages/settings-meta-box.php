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
		<th><label for=troy_package_slug><?= \esc_html__( 'Slug', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy_package_slug
				name="troy_package[slug]"
				pattern="[a-z1-9][a-z0-9\-]*"
				maxlength=191
				value="<?= \esc_attr( $package->slug ?? '' ) ?>"
				class=regular-text
			>
			<p class=description>
				<?php
				printf(
					/* translators: %s: Full download URL example */
					\esc_html__( 'The slug used in the download URL (e.g., %s).', 'troy-server' ),
					'<code>' . \esc_html( API\Server::get_full_repo_url() ) . 'package/get/zip/&lt;slug&gt;</code>'
				);
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for=troy_package_version><?= \esc_html__( 'Version', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy_package_version
				name=troy_package[version]
				value="<?= \esc_attr( $meta->version ) ?>"
				class=regular-text
				required
			>
			<p class=description><?= \esc_html__( 'Package version number (e.g., 1.0.0).', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th>
			<label for=troy_package_description><?= \esc_html__( 'Description', 'troy-server' ) ?></label>
			<br>
			<small id=troy_package_description__counter class=form-input-tip></small>
		</th>
		<td>
			<textarea
				id=troy_package_description
				name=troy_package[description]
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
		<th><label for=troy_package_plugin_uri><?= \esc_html__( 'Plugin URI', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=url
				id=troy_package_plugin_uri
				name="troy_package[plugin_uri]"
				value="<?= \esc_attr( $meta->plugin_uri ) ?>"
				class=regular-text
				maxlength=250
			>
			<p class=description><?= \esc_html__( 'URL to the package homepage or documentation.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy_package_author><?= \esc_html__( 'Author', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy_package_author
				name=troy_package[author]
				value="<?= \esc_attr( $meta->author ) ?>"
				class=regular-text
			>
			<p class=description><?= \esc_html__( 'Package author name.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy_package_author_uri><?= \esc_html__( 'Author URI', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=url
				id=troy_package_author_uri
				name="troy_package[author_uri]"
				value="<?= \esc_attr( $meta->author_uri ) ?>"
				class=regular-text
			>
			<p class=description><?= \esc_html__( 'URL to the author website.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy_package_requires_wp><?= \esc_html__( 'Requires WordPress', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy_package_requires_wp
				name=troy_package[requires_wp]
				value="<?= \esc_attr( $meta->requires_wp ) ?>"
				class=small-text
			>
			<p class=description><?= \esc_html__( 'Minimum WordPress version required (e.g., 6.7). Troy Client requires at least WordPress 6.7.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy_package_requires_php><?= \esc_html__( 'Requires PHP', 'troy-server' ) ?></label></th>
		<td>
			<input
				type=text
				id=troy_package_requires_php
				name=troy_package[requires_php]
				value="<?= \esc_attr( $meta->requires_php ) ?>"
				class=small-text
			>
			<p class=description><?= \esc_html__( 'Minimum PHP version required (e.g., 7.4). Troy Client requires at least PHP 7.4.', 'troy-server' ) ?></p>
		</td>
	</tr>
	<tr>
		<th><label for=troy_package_network><?= \esc_html__( 'Network Activation', 'troy-server' ) ?></label></th>
		<td>
			<label>
				<input
					type=checkbox
					id=troy_package_network
					name=troy_package[network]
					value=1
					<?php \checked( $meta->network, 1 ); ?>
				>
				<?= \esc_html__( 'Force network-wide activation for multisite.', 'troy-server' ) ?>
			</label>
		</td>
	</tr>
</table>
