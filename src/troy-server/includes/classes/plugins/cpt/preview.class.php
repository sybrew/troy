<?php
/**
 * @package Troy\Server\Plugins\CPT
 * @access  private
 */

namespace Troy\Server\Plugins\CPT;

\defined( 'Troy\Server\ABSPATH' ) or die;

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

/**
 * Class Troy\Server\Plugins\CPT\Preview.
 *
 * @since 0.0.1184
 */
final class Preview {

	/**
	 * Preview thickbox functionality.
	 *
	 * @since 0.0.1184
	 * @todo Implement preview functionality
	 */
	public function preview_thickbox() {
		// TODO: What we want is to preview the plugin thickbox and the plugin card.
		// See _nag_install_tsf() in Extension Manager, which includes TB_iframe.
		// We should probably preload those files in the editor. See _prepare_tsf_nag_installer_scripts().
	}
}
