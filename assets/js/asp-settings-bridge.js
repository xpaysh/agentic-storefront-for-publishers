/* global window, document */
/*
 * Bridge between the embedded xpay control-panel iframe and the
 * plugin's REST endpoints. The iframe holds no credentials and makes
 * no direct HTTP calls — every persistence intent (SAVE, DISCONNECT)
 * arrives here as a postMessage and is forwarded to the wp-rest
 * endpoint with the nonce that PHP printed alongside this script.
 *
 * The configuration values this script needs (REST urls, nonce, embed
 * origin, init payload) are injected via `wp_add_inline_script` before
 * this file loads — see ASP_Settings::render_page().
 */
(function () {
	'use strict';

	var cfg = window.ASP_SETTINGS_BRIDGE_CONFIG;
	if ( ! cfg || ! cfg.iframeId ) {
		return;
	}

	var iframe = document.getElementById( cfg.iframeId );
	if ( ! iframe ) {
		return;
	}

	function postToEmbed( msg ) {
		try {
			iframe.contentWindow.postMessage( msg, cfg.embedOrigin );
		} catch ( e ) {
			/* cross-origin handled silently */
		}
	}

	function envelope( action, payload, requestId ) {
		return {
			v: 1,
			dir: 'WP_TO_EMBED',
			action: action,
			requestId: requestId || undefined,
			timestamp: Date.now(),
			payload: payload
		};
	}

	window.addEventListener( 'message', function ( ev ) {
		if ( ! iframe || ev.source !== iframe.contentWindow ) {
			return;
		}
		var m = ev.data;
		if ( ! m || m.v !== 1 || m.dir !== 'EMBED_TO_WP' ) {
			return;
		}

		if ( m.action === 'READY' ) {
			postToEmbed( envelope( 'INIT', cfg.initPayload ) );
			return;
		}

		if ( m.action === 'SAVE' && m.payload && m.payload.settings ) {
			fetch( cfg.restSave, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify( m.payload.settings )
			} ).then( function ( r ) {
				return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } );
			} ).then( function ( r ) {
				postToEmbed( envelope( 'SAVED', {
					ok: r.ok && r.body && r.body.ok !== false,
					message: r.body && r.body.error ? r.body.error : '',
					settings: r.body && r.body.settings ? r.body.settings : null
				}, m.requestId ) );
			} ).catch( function ( err ) {
				postToEmbed( envelope( 'SAVED', {
					ok: false,
					message: ( err && err.message ) || 'Network error.'
				}, m.requestId ) );
			} );
			return;
		}

		if ( m.action === 'DISCONNECT' ) {
			fetch( cfg.restDisconnect, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce }
			} ).then( function ( r ) {
				postToEmbed( envelope( 'DISCONNECTED', { ok: r.ok } ) );
				if ( r.ok ) {
					setTimeout( function () { window.location.reload(); }, 600 );
				}
			} ).catch( function ( err ) {
				postToEmbed( envelope( 'DISCONNECTED', { ok: false, message: ( err && err.message ) || 'Network error.' } ) );
			} );
			return;
		}
	} );

	// Iframe auto-resizes via xpay-recs/size — match its content height
	// so the settings page does not get a fixed scroll-area.
	window.addEventListener( 'message', function ( ev ) {
		if ( ! iframe || ev.source !== iframe.contentWindow ) {
			return;
		}
		var data = ev.data;
		if ( ! data || data.type !== 'xpay-recs/size' || ! data.height ) {
			return;
		}
		var h = Math.max( 400, Math.min( 1600, Math.ceil( data.height ) ) );
		iframe.style.height = h + 'px';
	} );
})();
