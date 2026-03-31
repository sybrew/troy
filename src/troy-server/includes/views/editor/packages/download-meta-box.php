<?php
/**
 * @package Troy\Server\Views\Editor\Packages
 */

namespace Troy\Server\Views\Editor\Packages;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use Troy\Server\{
	API,
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

if ( 'publish' !== $post->post_status ) {
	?>
	<p><?= \esc_html__( 'Publish the package to generate a download link.', 'troy-server' ) ?></p>
	<?php
	return;
}

$package = new Data( post_id: $post->ID )->get_packages_row();

if ( ! $package || ! $package->slug ) {
	?>
	<p><?= \esc_html__( 'No download link available.', 'troy-server' ) ?></p>
	<?php
	return;
}

$download_url = API\Server::get_full_repo_url() . "package/get/zip/{$package->slug}";
?>
<p>
	<a href="<?= \esc_url( $download_url ) ?>" class="button button-primary button-large" target=_blank>
		<?= \esc_html__( 'Download Package', 'troy-server' ) ?>
	</a>
</p>
