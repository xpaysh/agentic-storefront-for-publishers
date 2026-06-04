/*!
 * Agentic Storefront widget.
 * Vanilla Web Component + Shadow DOM. No framework, no remote code.
 * Pattern reference: shadow-DOM open-source widgets (chatwoot/widget,
 * n8nio/chat) — uses an open shadow root for easier theming.
 *
 * Reads <script type="application/json" class="asp-recs-config"> blocks
 * inside each .asp-recs mount, calls the decision API, and renders
 * product cards from the returned declarative JSON layout.
 *
 * This file is plain ES2017 with no build step. Reviewers can read it
 * top-to-bottom and verify there is no remote-code path.
 */
(function () {
	'use strict';

	if (window.__aspWidgetLoaded) {
		return;
	}
	window.__aspWidgetLoaded = true;

	var MOUNT_SELECTOR = '.asp-recs[data-asp-mount="1"]';

	function safeJSON(el) {
		try {
			return JSON.parse(el.textContent || el.innerText || '{}');
		} catch (e) {
			return null;
		}
	}

	function escapeHTML(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return ({
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;'
			})[c];
		});
	}

	function styleTag() {
		return [
			':host { all: initial; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }',
			'.asp { display: block; margin: 1.25rem 0; }',
			'.asp h3 { font-size: 1rem; margin: 0 0 .75rem; color: inherit; font-weight: 600; }',
			'.asp-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }',
			'.asp-card { display: flex; flex-direction: column; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: .75rem; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.03); transition: transform .15s ease, box-shadow .15s ease; text-decoration: none; color: inherit; }',
			'.asp-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,.07); }',
			'.asp-card img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: .5rem; background: #f3f4f6; }',
			'.asp-title { font-size: .875rem; line-height: 1.3; margin: 0 0 .35rem; color: #111827; font-weight: 500; }',
			'.asp-meta { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }',
			'.asp-price { font-size: .875rem; font-weight: 600; color: #111827; }',
			'.asp-merchant { font-size: .7rem; color: #6b7280; max-width: 60%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }',
			'.asp-footer { margin-top: .75rem; font-size: .65rem; color: #9ca3af; text-align: right; }',
			'.asp-skeleton .asp-card { background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%); background-size: 200% 100%; animation: shine 1.2s linear infinite; }',
			'@keyframes shine { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }',
			'@media (prefers-color-scheme: dark) { .asp-card { background: #1f2937; border-color: rgba(255,255,255,.08); } .asp-title { color: #f3f4f6; } .asp-price { color: #f3f4f6; } .asp-merchant { color: #9ca3af; } }'
		].join('\n');
	}

	function renderSkeleton(shadow, title, limit) {
		var cards = '';
		for (var i = 0; i < limit; i++) {
			cards += '<div class="asp-card"></div>';
		}
		shadow.innerHTML =
			'<style>' + styleTag() + '</style>' +
			'<section class="asp asp-skeleton" aria-busy="true">' +
				(title ? '<h3>' + escapeHTML(title) + '</h3>' : '') +
				'<div class="asp-grid">' + cards + '</div>' +
			'</section>';
	}

	function renderCards(shadow, title, components, decisionId) {
		if (!components || !components.length) {
			shadow.innerHTML = '';
			return;
		}
		var grid = components.map(function (c) {
			var d = c.data || {};
			var href = d.buy_url || '#';
			var img = d.image_url
				? '<img alt="" loading="lazy" referrerpolicy="no-referrer" src="' + escapeHTML(d.image_url) + '" />'
				: '<div class="asp-card-img" aria-hidden="true"></div>';
			return (
				'<a class="asp-card" rel="sponsored nofollow noopener" target="_blank" href="' + escapeHTML(href) + '">' +
					img +
					'<p class="asp-title">' + escapeHTML(d.title || '') + '</p>' +
					'<div class="asp-meta">' +
						'<span class="asp-price">' + escapeHTML(d.price_display || (d.price || '') + ' ' + (d.currency || '')) + '</span>' +
						'<span class="asp-merchant">' + escapeHTML(d.merchant_domain || '') + '</span>' +
					'</div>' +
				'</a>'
			);
		}).join('');

		shadow.innerHTML =
			'<style>' + styleTag() + '</style>' +
			'<section class="asp" data-asp-decision="' + escapeHTML(decisionId || '') + '">' +
				(title ? '<h3>' + escapeHTML(title) + '</h3>' : '') +
				'<div class="asp-grid">' + grid + '</div>' +
				'<div class="asp-footer">Recommendations by xpay✦</div>' +
			'</section>';
	}

	function decide(cfg) {
		var url = (cfg.apiBase || 'https://api.xpay.sh').replace(/\/+$/, '') + '/storefront/decide';
		var payload = {
			site_id: cfg.siteId,
			url: (cfg.context && cfg.context.url) || (location && location.href),
			title: (cfg.context && cfg.context.title) || '',
			categories: (cfg.context && cfg.context.categories) || [],
			tags: (cfg.context && cfg.context.tags) || [],
			lang: (cfg.context && cfg.context.lang) || 'en',
			layout: cfg.layout || 'cards',
			limit: cfg.limit || 3
		};
		return fetch(url, {
			method: 'POST',
			mode: 'cors',
			credentials: 'omit',
			cache: 'no-store',
			referrer: 'no-referrer',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		}).then(function (r) {
			if (!r.ok) throw new Error('decide failed: ' + r.status);
			return r.json();
		});
	}

	function mountOne(el) {
		if (el.__aspMounted) return;
		el.__aspMounted = true;

		var cfgEl = el.querySelector('.asp-recs-config');
		var cfg = cfgEl ? safeJSON(cfgEl) : null;
		if (!cfg || !cfg.siteId) return;

		var host = document.createElement('div');
		host.className = 'asp-host';
		var shadow = host.attachShadow({ mode: 'open' });
		el.appendChild(host);

		renderSkeleton(shadow, cfg.title || '', cfg.limit || 3);

		decide(cfg).then(function (resp) {
			renderCards(shadow, cfg.title || '', resp.components || [], resp.decision_id || '');
		}).catch(function () {
			// Silent fail — leave the skeleton hidden.
			shadow.innerHTML = '';
		});
	}

	function init() {
		var nodes = document.querySelectorAll(MOUNT_SELECTOR);
		for (var i = 0; i < nodes.length; i++) {
			mountOne(nodes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
