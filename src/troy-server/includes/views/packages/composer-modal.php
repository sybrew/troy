<?php
/**
 * @package Troy\Server\Views\Packages
 */

namespace Troy\Server\Views\Packages;

( \defined( 'Troy\Server\ABSPATH' ) and \Troy\Server\Template::verify_secret( $secret ) ) or die;

/**
 * Troy Server
 *
 * Copyright (c) 2026 Sybre Waaijer, CyberWire B.V.
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
<div id=troy-server-composer-modal class=troy-server-composer-modal hidden>
	<div class=troy-server-composer-modal-content>
		<div class=troy-server-composer-modal-header>
			<h3><?= \esc_html__( 'Composer Setup', 'troy-server' ) ?></h3>
			<button type=button class=troy-server-composer-modal-close aria-label="<?= \esc_attr__( 'Close', 'troy-server' ) ?>">&times;</button>
		</div>
		<div class=troy-server-composer-modal-body id=troy-server-composer-modal-body>
			<p><?= \esc_html__( 'Loading...', 'troy-server' ) ?></p>
		</div>
	</div>
</div>
