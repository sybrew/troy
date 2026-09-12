/**
 * Runs wp-plugin-regression against this monorepo.
 *
 * Builds plugin.runtime.json from packages.json, then delegates to
 * wp-plugin-regression:
 *   - .local/playground/wp-plugin-regression (node run.js)
 *
 * Usage: node playground.js <command> [flags]
 */

const { spawnSync } = require( 'child_process' );
const fs   = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '..', '..', '..', '..' );

const ENGINE_DIR =
	   process.env.WP_PLUGIN_REGRESSION_DIR
	|| path.resolve(
		ROOT,
		'.local',
		'playground',
		'wp-plugin-regression',
	);

const PERMISSION_FILE = path.resolve( ROOT, '.local', 'playground', 'permission.txt' );
const PACKAGES_JSON   = path.resolve( __dirname, '..', 'packages.json' );
const RUNTIME_JSON    = path.resolve( ROOT, '.local', 'playground', 'plugin.runtime.json' );
const REPO            = 'https://github.com/theseoframework/wp-plugin-regression';
const ZIP_SOURCE_VFS  = '/wordpress/wp-content/uploads/troy-playground-src';

const ALWAYS_SHIMS = [
	'.cursor/skills/playground/mu-plugin/0-troy-playground-official.php',
	'.cursor/skills/playground/mu-plugin/troy-playwright-admin.php',
	'.cursor/skills/playground/mu-plugin/3-troy-playground-frames.php',
];

/**
 * Reads the Playground permission flag.
 *
 * @return {Boolean}
 */
function readPermission() {

	if ( ! fs.existsSync( PERMISSION_FILE ) )
		throw new Error( 'Missing .local/playground/permission.txt.' );

	const text  = fs.readFileSync( PERMISSION_FILE, 'utf8' );
	const match = /^\s*PLAYGROUND=(True|False)\s*$/m.exec( text );

	if ( ! match )
		throw new Error(
			'permission.txt must contain PLAYGROUND=True|False on its own line.',
		);

	return 'True' === match[1];
}

/**
 * @param {string[]} argv
 * @param {string}   name
 * @return {Boolean}
 */
function hasFlag( argv, name ) {
	return argv.some(
		a => a === `--${name}` || a.startsWith( `--${name}=` ),
	);
}

/**
 * @param {string[]} argv
 * @param {string}   name
 * @return {string|undefined}
 */
function flagValue( argv, name ) {

	for ( const a of argv ) {
		if ( a.startsWith( `--${name}=` ) )
			return a.slice( name.length + 3 );
	}

	const idx = argv.indexOf( `--${name}` );

	if ( -1 === idx )
		return;

	const next = argv[ idx + 1 ];

	if ( next && ! next.startsWith( '--' ) )
		return next;
}

/**
 * Pulls --package / --packages off argv. Default troy-client.
 *
 * @param {string[]} argv
 * @return {{ names: string[], rest: string[] }}
 */
function collectPackages( argv ) {

	const names = [];
	const rest  = [];

	for ( let i = 0; i < argv.length; i++ ) {
		const token = argv[ i ];

		if ( '--package' === token || '--packages' === token ) {
			const next = argv[ i + 1 ];

			if ( ! next || next.startsWith( '--' ) )
				throw new Error( '--package needs a value.' );

			names.push( ...splitPackageList( next ) );
			i++;
			continue;
		}

		if (
			   token.startsWith( '--package=' )
			|| token.startsWith( '--packages=' )
		) {
			names.push( ...splitPackageList( token.slice( token.indexOf( '=' ) + 1 ) ) );
			continue;
		}

		rest.push( token );
	}

	return {
		names: names.length ? names : [ 'troy-client' ],
		rest,
	};
}

/**
 * @param {string} value
 * @return {string[]}
 */
function splitPackageList( value ) {
	return value.split( ',' ).map( s => s.trim() ).filter( Boolean );
}

/**
 * @param {Object}   catalog
 * @param {string[]} names
 * @return {Object[]}
 */
function resolveSelection( catalog, names ) {

	const all  = Object.keys( catalog.packages );
	const want = names.includes( 'all' ) ? all : names;
	const out  = [];

	for ( const name of want ) {
		if ( ! catalog.packages[ name ] )
			throw new Error( `Unknown package ${name}. Known: ${all.join( ', ' )}.` );

		out.push( {
			id: name,
			...catalog.packages[ name ],
		} );
	}

	return out;
}

/**
 * @param {Object} plugin
 * @return {Object}
 */
function toExtraPlugin( plugin ) {
	return {
		slug:     plugin.slug,
		dir:      plugin.dir,
		mainFile: plugin.mainFile,
		mounts:   plugin.mounts,
		activate: false !== plugin.activate,
	};
}

/**
 * Builds engine plugin.json from the selected catalog entries.
 *
 * @param {Object[]} selected
 * @param {Object}   catalog
 * @return {Object}
 */
function buildRuntime( selected, catalog ) {

	const plugins = selected.filter( p => 'mu-plugin' !== p.kind );
	const mu      = selected.filter( p => 'mu-plugin' === p.kind );
	const ids     = new Set( selected.map( p => p.id ) );

	const extraMounts = [];

	if (
		   ( ids.has( 'troy-client-daemon' ) || ids.has( 'troy-installer' ) )
		&& ! ids.has( 'troy-client' )
	) {
		extraMounts.push( [
			catalog.packages['troy-client'].dir,
			`${ZIP_SOURCE_VFS}/troy-client`,
		] );
	}

	// Client dirs are --mount-dir only. When Daemon is also selected,
	// extraMounts hardlinks them into persist and owns the VFS pair so
	// activate_plugin during blueprint sees includes/api.php. Do not also
	// leave them on plugin.mounts — Playground rejects a duplicate mount.
	let clientFileMounts;

	if ( ids.has( 'troy-client-daemon' ) && ids.has( 'troy-client' ) ) {
		const client     = catalog.packages['troy-client'];
		clientFileMounts = [];

		for ( const rel of client.mounts ) {
			const host = path.resolve( ROOT, client.dir, rel );

			if ( fs.statSync( host ).isDirectory() ) {
				extraMounts.push( [
					path.join( client.dir, rel ).replace( /\\/g, '/' ),
					`/wordpress/wp-content/plugins/${client.slug}/${rel.replace( /\\/g, '/' )}`,
				] );
				continue;
			}

			clientFileMounts.push( rel );
		}
	}

	let primary;
	let extraPlugins = [];

	if ( plugins.length ) {
		primary = plugins[0];

		if ( clientFileMounts && 'troy-client' === primary.slug )
			primary = { ...primary, mounts: clientFileMounts };

		extraPlugins = plugins.slice( 1 ).map( p => {
			const extra = toExtraPlugin( p );

			if ( clientFileMounts && 'troy-client' === extra.slug )
				extra.mounts = clientFileMounts;

			return extra;
		} );
	} else {
		const client = catalog.packages['troy-client'];

		primary = {
			slug:     client.slug,
			dir:      client.dir,
			mainFile: client.mainFile,
			mounts:   [],
			activate: false,
		};
	}

	const shims = [ ...ALWAYS_SHIMS ];

	if ( ids.has( 'troy-server' ) )
		shims.push(
			'.cursor/skills/playground/mu-plugin/2-troy-playground-server-sqlite.php',
		);

	for ( const item of mu ) {
		if ( 'troy-client-daemon' === item.id ) {
			extraMounts.push( [
				path.dirname( item.file ).replace( /\\/g, '/' ),
				'/wordpress/wp-content/mu-plugins/troy-client-daemon',
			] );
			shims.push(
				'.cursor/skills/playground/mu-plugin/1-troy-playground-daemon.php',
			);
			continue;
		}

		shims.push( item.file );
	}

	return {
		slug:         primary.slug,
		dir:          primary.dir,
		mainFile:     primary.mainFile,
		activate:     false !== primary.activate,
		mounts:       primary.mounts || [],
		extraPlugins,
		extraMounts,
		shims,
		entries:      [
			{ id: 'front', type: 'front-blog', path: '/' },
			{ id: 'page', type: 'page', path: '/sample-page/' },
		],
	};
}

/**
 * Writes plugin.runtime.json.
 *
 * @param {Object} runtime
 * @return {string}
 */
function writeRuntime( runtime ) {

	fs.mkdirSync( path.dirname( RUNTIME_JSON ), { recursive: true } );
	fs.writeFileSync(
		RUNTIME_JSON,
		JSON.stringify( runtime, null, '\t' ) + '\n',
	);

	return RUNTIME_JSON;
}

/**
 * Runs a wp-plugin-regression command.
 */
function main() {

	if ( ! readPermission() ) {
		console.log( 'Playground skipped (PLAYGROUND=False).' );

		return;
	}

	const runJs = path.join( ENGINE_DIR, 'run.js' );

	if ( ! fs.existsSync( runJs ) )
		throw new Error(
			`wp-plugin-regression engine missing at ${ENGINE_DIR}. Clone ${REPO} there and run npm install.`,
		);

	const argv = process.argv.slice( 2 );

	if ( ! argv.length )
		throw new Error( 'Usage: node playground.js <launch|stop|capture|compare|harness|surfaces> [flags]' );

	const command = argv[0];
	const parsed  = collectPackages( argv.slice( 1 ) );
	const extra   = [ command, ...parsed.rest ];

	if ( hasFlag( extra, 'plugin' ) && 'wporg' === flagValue( extra, 'plugin' ) )
		throw new Error(
			'Troy packages are not on wordpress.org. Do not pass --plugin=wporg.',
		);

	if ( 'launch' === command && ! hasFlag( extra, 'php' ) )
		extra.push( '--php', '8.4' );

	if ( ! hasFlag( extra, 'root' ) )
		extra.push( '--root', ROOT );

	if ( [ 'launch', 'capture', 'compare', 'surfaces' ].includes( command ) ) {
		if ( ! fs.existsSync( PACKAGES_JSON ) )
			throw new Error( `Missing ${PACKAGES_JSON}.` );

		const catalog = JSON.parse( fs.readFileSync( PACKAGES_JSON, 'utf8' ) );

		if ( ! catalog.packages )
			throw new Error( 'packages.json needs a packages object.' );

		writeRuntime( buildRuntime(
			resolveSelection( catalog, parsed.names ),
			catalog,
		) );

		if ( ! hasFlag( extra, 'plugin-json' ) )
			extra.push( '--plugin-json', RUNTIME_JSON );
	}

	const result = spawnSync(
		process.execPath,
		[ runJs, ...extra ],
		{
			cwd:   ENGINE_DIR,
			stdio: 'inherit',
		},
	);

	if ( null !== result.status && result.status )
		process.exit( result.status );
}

try {
	main();
} catch ( err ) {
	console.error( `\nError: ${err.message}\n` );
	process.exit( 1 );
}
