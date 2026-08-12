( function () {
	'use strict';

	const settings = window.angieMcpConsumerSettings || {};

	const SERVER_KEY = 'angie';
	const REMOTE_PACKAGE = '@automattic/mcp-wordpress-remote@latest';
	const MCP_REST_ROUTE = '/mcp/angie';
	const PLACEHOLDER_USERNAME = 'your-username';
	const PLACEHOLDER_PASSWORD = 'your-application-password';

	const buildMcpUrl = function ( indexPhpUrl ) {
		const url = new URL( indexPhpUrl );
		url.searchParams.set( 'rest_route', MCP_REST_ROUTE );
		return url.toString();
	};

	const getMergeServerKey = function ( mcpUrl ) {
		try {
			const host = new URL( mcpUrl ).hostname;
			if ( ! host ) {
				return SERVER_KEY;
			}

			return SERVER_KEY + '-' + host.toLowerCase().replace( /\./g, '-' );
		} catch ( error ) {
			return SERVER_KEY;
		}
	};

	const mcpUrl = settings.indexPhpUrl ? buildMcpUrl( settings.indexPhpUrl ) : '';
	const mergeServerKey = mcpUrl ? getMergeServerKey( mcpUrl ) : SERVER_KEY;

	const detectOs = function () {
		const platform = ( navigator.userAgentData && navigator.userAgentData.platform ) || navigator.platform || '';
		if ( /Win/i.test( platform ) ) {
			return 'win32';
		}
		if ( /Mac/i.test( platform ) ) {
			return 'darwin';
		}
		if ( /Linux/i.test( platform ) ) {
			return 'linux';
		}
		return 'darwin';
	};

	const configPathsByOs = {
		darwin: {
			cursor: {
				display: '~/.cursor/mcp.json',
				copy: '~/.cursor/mcp.json',
			},
			claude: {
				display: '~/Library/Application Support/Claude/claude_desktop_config.json',
				copy: '~/Library/Application Support/Claude/claude_desktop_config.json',
			},
		},
		win32: {
			cursor: {
				display: '%USERPROFILE%\\.cursor\\mcp.json',
				copy: '%USERPROFILE%\\.cursor\\mcp.json',
			},
			claude: {
				display: '%APPDATA%\\Claude\\claude_desktop_config.json',
				copy: '%APPDATA%\\Claude\\claude_desktop_config.json',
			},
		},
		linux: {
			cursor: {
				display: '~/.cursor/mcp.json',
				copy: '~/.cursor/mcp.json',
			},
			claude: {
				display: '~/.config/Claude/claude_desktop_config.json',
				copy: '~/.config/Claude/claude_desktop_config.json',
			},
		},
	};

	const pathCopiedMessage = function () {
		const os = detectOs();
		if ( os === 'win32' ) {
			return settings.pathCopiedWin;
		}
		if ( os === 'linux' ) {
			return settings.pathCopiedLinux;
		}
		return settings.pathCopiedMac;
	};

	const copyText = async function ( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			await navigator.clipboard.writeText( text );
			return;
		}

		const helper = document.createElement( 'textarea' );
		helper.value = text;
		helper.setAttribute( 'readonly', '' );
		helper.style.position = 'absolute';
		helper.style.left = '-9999px';
		document.body.appendChild( helper );
		helper.select();
		document.execCommand( 'copy' );
		document.body.removeChild( helper );
	};

	const setPathCopyStatus = function ( pathCopyStatusField, message, isError ) {
		if ( ! pathCopyStatusField ) {
			return;
		}

		pathCopyStatusField.textContent = message;
		pathCopyStatusField.hidden = ! message;
		pathCopyStatusField.classList.toggle( 'is-error', !! isError );
	};

	const clearPathCopyStatus = function ( pathCopyStatusField ) {
		setPathCopyStatus( pathCopyStatusField, '', false );
	};

	const setConsumerStatus = function ( statusField, message, type ) {
		if ( ! statusField ) {
			return;
		}

		statusField.textContent = message;
		statusField.hidden = ! message;
		statusField.classList.remove( 'angie-mcp-consumer-status-success', 'angie-mcp-consumer-status-error' );

		if ( type === 'success' ) {
			statusField.classList.add( 'angie-mcp-consumer-status-success' );
		} else if ( type === 'error' ) {
			statusField.classList.add( 'angie-mcp-consumer-status-error' );
		}
	};

	const initConfigPathLinks = function ( pathCopyStatusField ) {
		const cursorLink = document.getElementById( 'angie-mcp-cursor-path-link' );
		const claudeLink = document.getElementById( 'angie-mcp-claude-path-link' );
		if ( ! cursorLink || ! claudeLink ) {
			return;
		}

		const osPaths = configPathsByOs[ detectOs() ] || configPathsByOs.darwin;

		const bindPathLink = function ( pathElement, pathConfig ) {
			pathElement.textContent = pathConfig.display;
			pathElement.title = pathConfig.copy;

			const copyPath = async function ( event ) {
				event.preventDefault();

				try {
					await copyText( pathConfig.copy );
					setPathCopyStatus( pathCopyStatusField, pathCopiedMessage(), false );
				} catch ( error ) {
					setPathCopyStatus( pathCopyStatusField, settings.pathCopyFailed, true );
				}
			};

			pathElement.addEventListener( 'click', copyPath );
			pathElement.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					copyPath( event );
				}
			} );
		};

		bindPathLink( cursorLink, osPaths.cursor );
		bindPathLink( claudeLink, osPaths.claude );
	};

	const copyButton = document.getElementById( 'angie-mcp-copy-config' );
	const configField = document.getElementById( 'angie-mcp-consumer-config' );
	const includeWrapperCheckbox = document.getElementById( 'angie-mcp-include-wrapper' );
	const statusField = document.getElementById( 'angie-mcp-consumer-status' );
	const pathCopyStatusField = document.getElementById( 'angie-mcp-path-copy-status' );
	const passwordField = document.getElementById( 'angie-mcp-generated-password' );

	if ( ! copyButton || ! configField || ! includeWrapperCheckbox || ! statusField || ! passwordField ) {
		return;
	}

	let configGenerated = false;
	let generatedCredentials = null;

	const shouldIncludeWrapper = function () {
		return includeWrapperCheckbox.checked;
	};

	const buildServerEntry = function ( username, password ) {
		return {
			command: 'npx',
			args: [
				'-y',
				REMOTE_PACKAGE,
			],
			env: {
				WP_API_URL: mcpUrl,
				WP_API_USERNAME: username,
				WP_API_PASSWORD: password,
			},
		};
	};

	const formatMergeSnippet = function ( serverEntry, mergeServerKey ) {
		const wrapped = JSON.stringify( { [ mergeServerKey ]: serverEntry }, null, 2 );
		const lines = wrapped.split( '\n' );
		lines.shift();
		lines.pop();

		return lines
			.map( function ( line ) {
				return line.trim() === '' ? line : '  ' + line;
			} )
			.join( '\n' ) + ',';
	};

	const formatConfig = function ( serverEntry, includeWrapper ) {
		if ( includeWrapper ) {
			return JSON.stringify( { mcpServers: { [ SERVER_KEY ]: serverEntry } }, null, 2 );
		}

		return formatMergeSnippet( serverEntry, mergeServerKey );
	};

	const buildConfigSnippet = function ( username, password, includeWrapper ) {
		return formatConfig( buildServerEntry( username, password ), includeWrapper );
	};

	const updateConfigFieldDisplay = function () {
		if ( ! mcpUrl ) {
			return;
		}

		const includeWrapper = shouldIncludeWrapper();
		const username = configGenerated && generatedCredentials
			? generatedCredentials.username
			: PLACEHOLDER_USERNAME;
		const password = configGenerated && generatedCredentials
			? generatedCredentials.password
			: PLACEHOLDER_PASSWORD;

		configField.value = buildConfigSnippet( username, password, includeWrapper );
	};

	initConfigPathLinks( pathCopyStatusField );

	const selectConfigField = function () {
		configField.focus();
		configField.select();
	};

	configField.addEventListener( 'focus', selectConfigField );
	configField.addEventListener( 'click', selectConfigField );
	includeWrapperCheckbox.addEventListener( 'change', updateConfigFieldDisplay );

	const setCopyButtonDisabled = function ( disabled ) {
		copyButton.disabled = disabled;
	};

	const resetCopyButtonLabel = function () {
		window.setTimeout( function () {
			copyButton.textContent = settings.copyLabel || 'Generate and Copy';
		}, 2000 );
	};

	const buildAppPasswordName = function () {
		const prefix = settings.appPasswordNamePrefix || 'Angie as MCP';
		const stamp = new Date().toISOString().slice( 0, 19 ).replace( 'T', ' ' );

		return prefix + ' ' + stamp;
	};

	const normalizeAppPassword = function ( password ) {
		return String( password || '' ).replace( /\s/g, '' );
	};

	const showGeneratedPassword = function ( password ) {
		passwordField.replaceChildren();
		const label = document.createElement( 'strong' );
		label.textContent = settings.passwordLabel || 'Application password (copy now — shown once):';
		const code = document.createElement( 'code' );
		code.textContent = password;
		passwordField.append( label, document.createTextNode( ' ' ), code );
		passwordField.hidden = false;
	};

	const notifyAppPasswordGenerated = function ( status ) {
		window.dispatchEvent( new CustomEvent( 'angie-mcp-app-password-generated', {
			detail: { status: status },
		} ) );
	};

	const copyExistingConfig = async function () {
		if ( copyButton.disabled || ! configField.value ) {
			return;
		}

		clearPathCopyStatus( pathCopyStatusField );
		setCopyButtonDisabled( true );
		copyButton.textContent = settings.copyingLabel || 'Copying…';

		try {
			await copyText( configField.value );
			copyButton.textContent = settings.copiedLabel || 'Copied!';
			setConsumerStatus( statusField, settings.configCopiedAgain || 'Config copied to clipboard.', 'success' );
			resetCopyButtonLabel();
		} catch ( error ) {
			setConsumerStatus( statusField, settings.pathCopyFailed || 'Could not copy the config automatically.', 'error' );
			copyButton.textContent = settings.copyLabel || 'Generate and Copy';
		} finally {
			setCopyButtonDisabled( false );
		}
	};

	const generateAndCopyConfig = async function () {
		if ( copyButton.disabled ) {
			return;
		}

		let trackStatus = null;

		if ( ! settings.restUrl ) {
			setConsumerStatus( statusField, settings.errorLabel || 'Failed to generate application password.', 'error' );
			notifyAppPasswordGenerated( 'failed' );
			return;
		}

		clearPathCopyStatus( pathCopyStatusField );
		setCopyButtonDisabled( true );
		setConsumerStatus( statusField, settings.generatingLabel || 'Generating…', null );
		passwordField.hidden = true;
		passwordField.replaceChildren();
		copyButton.textContent = settings.generatingLabel || 'Generating…';

		try {
			const response = await fetch( settings.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': settings.nonce,
				},
				body: JSON.stringify( {
					name: buildAppPasswordName(),
				} ),
			} );

			const data = await response.json();

			if ( ! response.ok ) {
				throw new Error( data.message || settings.errorLabel || 'Failed to generate application password.' );
			}

			const password = normalizeAppPassword( data.password );

			if ( ! password ) {
				throw new Error( settings.errorLabel || 'Failed to generate application password.' );
			}

			generatedCredentials = {
				username: settings.username || PLACEHOLDER_USERNAME,
				password,
			};
			configGenerated = true;
			updateConfigFieldDisplay();
			showGeneratedPassword( password );
			trackStatus = 'success';
			await copyText( configField.value );
			copyButton.textContent = settings.copiedLabel || 'Copied!';
			setConsumerStatus( statusField, settings.configCopied || settings.successMessage || '', 'success' );
			resetCopyButtonLabel();
		} catch ( error ) {
			if ( trackStatus !== 'success' ) {
				trackStatus = 'failed';
			}
			setConsumerStatus(
				statusField,
				error?.message || settings.errorLabel || 'Failed to generate application password.',
				'error'
			);
			copyButton.textContent = settings.copyLabel || 'Generate and Copy';
		} finally {
			if ( trackStatus ) {
				notifyAppPasswordGenerated( trackStatus );
			}
			setCopyButtonDisabled( false );
		}
	};

	copyButton.addEventListener( 'click', function () {
		if ( configGenerated ) {
			copyExistingConfig();
			return;
		}

		generateAndCopyConfig();
	} );

	updateConfigFieldDisplay();
}() );
