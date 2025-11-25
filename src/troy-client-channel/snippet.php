<?php
/**
 * This is not a plugin, but a snippet for enabling the beta channel in Troy
 * Client. When this constant is set to 'beta', Troy Client will request beta
 * versions in addition to stable tag versions from Troy Server.
 *
 * You can add this to your `wp-config.php` file or to a custom plugin.
 * This is meant to be implemented by the site owner or manager only.
 *
 * Available channels:
 * - 'tag'  (default) Only receive stable tagged releases.
 * - 'beta' Receive both beta and stable releases, whichever is newer.
 *
 * Note: The channel only affects which versions are offered for updates.
 * Beta versions must still be published on your Troy Server with type 'beta'.
 *
 * @package Troy\Channel
 */

define( 'Troy\Client\CHANNEL', 'beta' );
