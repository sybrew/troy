<?php
/**
 * @package Troy\Server\Views\Editor\Packages
 */

namespace Troy\Server\Views\Editor\Packages;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

use const Troy\Server\PACKAGES_CPT;

use Troy\Server\Packages\Data;

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

$post = \get_post();

if ( ! $post || PACKAGES_CPT !== $post->post_type )
	return;

$data = new Data( post_id: $post->ID );

$has_slug    = ! empty( $data->get_packages_row()?->slug );
$has_plugins = ! empty( $data->get_metas_row()?->plugins );
?>
<div class=troy-server-publish-checklist>
	<h4 class=troy-server-publish-checklist-title><?= \esc_html__( 'Package checklist', 'troy-server' ) ?></h4>
	<ul>
		<li data-checklist=slug class="<?= $has_slug ? 'is-ok' : 'is-missing' ?>">
			<?= \esc_html__( 'Slug', 'troy-server' ) ?>
		</li>
		<li data-checklist=plugins class="<?= $has_plugins ? 'is-ok' : 'is-missing' ?>">
			<?= \esc_html__( 'Included plugins', 'troy-server' ) ?>
		</li>
	</ul>
</div>
