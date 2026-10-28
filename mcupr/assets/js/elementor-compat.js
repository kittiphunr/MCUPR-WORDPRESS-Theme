/*
 * The little bit of life an Elementor page needs when the plugin is gone (inc/elementor*.php prints the page itself):
 * tabs, accordion and toggle, the entrance animations, the counters, the countdown, the slides, the carousel, the alert's
 * close button and the click on a video cover. Everything else on the page is plain HTML and CSS.
 *
 * It does nothing when the visitor asked for less motion (animations only) and nothing at all on a page without Elementor content.
 */
(function () {
	'use strict';
	var root = document.documentElement;
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	function motionOn() {
		return root.getAttribute('data-motion') !== '1' && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	/* ------------------------------------------- nested accordion and nested tabs (Elementor 3.15+) */
	$$('.e-n-accordion[data-one]').forEach(function (acc) {
		var items = $$('details.e-n-accordion-item', acc);
		items.forEach(function (d) {
			d.addEventListener('toggle', function () {
				if (d.open) { items.forEach(function (o) { if (o !== d) { o.open = false; } }); }
			});
		});
	});
	$$('.e-n-tabs').forEach(function (w) {
		var titles = $$('.e-n-tab-title', w);
		var panes = $$('.e-n-tab-panel', w);
		if (!titles.length) { return; }
		function show(n, focus) {
			titles.forEach(function (t) {
				var on = t.getAttribute('data-tab-index') === String(n);
				t.setAttribute('aria-selected', String(on));
				t.setAttribute('tabindex', on ? '0' : '-1');
				t.classList.toggle('e-active', on);
				if (on && focus) { t.focus(); }
			});
			panes.forEach(function (p) { p.hidden = p.getAttribute('data-tab-index') !== String(n); });
		}
		show(1, false);
		titles.forEach(function (t, i) {
			t.addEventListener('click', function () { show(t.getAttribute('data-tab-index'), false); });
			t.addEventListener('keydown', function (e) {
				var to = -1;
				if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { to = (i + 1) % titles.length; }
				else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { to = (i - 1 + titles.length) % titles.length; }
				else if (e.key === 'Home') { to = 0; }
				else if (e.key === 'End') { to = titles.length - 1; }
				if (to > -1) { e.preventDefault(); show(titles[to].getAttribute('data-tab-index'), true); }
			});
		});
	});

	/* ---------------------------------------------------------------- tabs */
	$$('.elementor-widget-tabs').forEach(function (w) {
		var titles = $$('.elementor-tab-title', w);
		var panes = $$('.elementor-tab-content', w);
		if (!titles.length) { return; }
		function show(n) {
			titles.forEach(function (t) {
				var on = t.getAttribute('data-tab') === String(n);
				t.classList.toggle('elementor-active', on);
				t.setAttribute('aria-selected', String(on));
				if (t.classList.contains('elementor-tab-desktop-title')) { t.setAttribute('tabindex', on ? '0' : '-1'); }
			});
			panes.forEach(function (p) {
				var on = p.getAttribute('data-tab') === String(n);
				p.hidden = !on;
				p.classList.toggle('elementor-active', on);
			});
		}
		show(1);
		titles.forEach(function (t) {
			t.addEventListener('click', function () { show(t.getAttribute('data-tab')); });
			t.addEventListener('keydown', function (e) {
				var list = titles.filter(function (x) { return x.offsetParent !== null; });
				var i = list.indexOf(t);
				if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); list[(i + 1) % list.length].focus(); list[(i + 1) % list.length].click(); }
				else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); list[(i - 1 + list.length) % list.length].focus(); list[(i - 1 + list.length) % list.length].click(); }
				else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); t.click(); }
			});
		});
	});

	/* ------------------------------------------------- accordion and toggle */
	$$('.elementor-widget-accordion, .elementor-widget-toggle').forEach(function (w) {
		var acc = w.classList.contains('elementor-widget-accordion');
		var items = $$('.elementor-accordion-item, .elementor-toggle-item', w);
		items.forEach(function (item, n) {
			var title = item.querySelector('.elementor-tab-title');
			var pane = item.querySelector('.elementor-tab-content');
			if (!title || !pane) { return; }
			function set(open) {
				pane.hidden = !open;
				title.classList.toggle('elementor-active', open);
				item.classList.toggle('elementor-active', open);
				title.setAttribute('aria-expanded', String(open));
			}
			set(acc && n === 0);
			function toggle() {
				var open = pane.hidden;
				if (acc && open) {
					items.forEach(function (other) {
						if (other !== item) {
							var op = other.querySelector('.elementor-tab-content');
							var ot = other.querySelector('.elementor-tab-title');
							if (op) { op.hidden = true; }
							if (ot) { ot.classList.remove('elementor-active'); ot.setAttribute('aria-expanded', 'false'); }
							other.classList.remove('elementor-active');
						}
					});
				}
				set(open);
			}
			title.addEventListener('click', toggle);
			title.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
		});
	});

	/* ------------------------------------------------------------ animations */
	var anim = $$('.elementor-element[data-settings]').filter(function (el) {
		try { return !!JSON.parse(el.getAttribute('data-settings')).animation; } catch (e) { return false; }
	});
	if (anim.length) {
		if (!motionOn() || !('IntersectionObserver' in window)) {
			anim.forEach(function (el) { el.classList.remove('elementor-invisible'); });
		} else {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (!en.isIntersecting) { return; }
					var el = en.target;
					io.unobserve(el);
					var set = {};
					try { set = JSON.parse(el.getAttribute('data-settings')) || {}; } catch (e) { /* ignore */ }
					window.setTimeout(function () {
						el.classList.remove('elementor-invisible');
						el.classList.add('animated', set.animation);
					}, parseInt(set.animation_delay, 10) || 0);
				});
			}, { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });
			anim.forEach(function (el) { io.observe(el); });
		}
	}

	/* -------------------------------------------------------------- counters */
	var counters = $$('.elementor-counter-number');
	if (counters.length) {
		var runCounter = function (el) {
			var to = parseFloat(el.getAttribute('data-to-value'));
			var from = parseFloat(el.getAttribute('data-from-value')) || 0;
			var dur = parseInt(el.getAttribute('data-duration'), 10) || 2000;
			var delim = el.getAttribute('data-delimiter') || '';
			if (isNaN(to)) { return; }
			function fmt(n) {
				var s = String(Math.round(n));
				return delim ? s.replace(/\B(?=(\d{3})+(?!\d))/g, delim) : s;
			}
			if (!motionOn()) { el.textContent = fmt(to); return; }
			var t0 = 0;
			function step(ts) {
				if (!t0) { t0 = ts; }
				var p = Math.min(1, (ts - t0) / dur);
				el.textContent = fmt(from + (to - from) * (1 - Math.pow(1 - p, 3)));
				if (p < 1) { window.requestAnimationFrame(step); }
			}
			window.requestAnimationFrame(step);
		};
		if ('IntersectionObserver' in window) {
			var cio = new IntersectionObserver(function (es) {
				es.forEach(function (en) { if (en.isIntersecting) { cio.unobserve(en.target); runCounter(en.target); } });
			}, { threshold: 0.2 });
			counters.forEach(function (el) { cio.observe(el); });
		} else {
			counters.forEach(runCounter);
		}
	}

	/* ------------------------------------------------------------- countdown */
	$$('.elementor-countdown-wrapper[data-date]').forEach(function (w) {
		var when = parseInt(w.getAttribute('data-date'), 10) * 1000;
		if (!when) { return; }
		var map = { days: 86400000, hours: 3600000, minutes: 60000, seconds: 1000 };
		function tick() {
			var left = Math.max(0, when - Date.now());
			Object.keys(map).forEach(function (k) {
				var el = w.querySelector('.elementor-countdown-' + k);
				if (!el) { return; }
				var v = Math.floor(left / map[k]);
				if (k !== 'days') { v = v % (k === 'hours' ? 24 : 60); }
				el.textContent = (v < 10 ? '0' : '') + v;
			});
			if (left > 0) { window.setTimeout(tick, 1000); }
		}
		tick();
	});

	/* ---------------------------------------------------------------- slides */
	$$('.elementor-slides-wrapper').forEach(function (w) {
		var slides = $$('.elementor-slide', w);
		if (slides.length < 1) { return; }
		var at = 0;
		var nav = w.getAttribute('data-nav') || 'both';
		function show(n) {
			at = (n + slides.length) % slides.length;
			slides.forEach(function (s, i) { s.classList.toggle('is-active', i === at); });
			$$('.elementor-slides-dots button', w).forEach(function (b, i) { b.classList.toggle('is-active', i === at); b.setAttribute('aria-current', i === at ? 'true' : 'false'); });
		}
		if (slides.length > 1 && (nav === 'both' || nav === 'arrows')) {
			['prev', 'next'].forEach(function (dir) {
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'elementor-slides-nav elementor-slides-' + dir;
				b.setAttribute('aria-label', dir === 'prev' ? 'Previous slide' : 'Next slide');
				b.innerHTML = dir === 'prev' ? '&#8249;' : '&#8250;';
				b.addEventListener('click', function () { show(at + (dir === 'prev' ? -1 : 1)); stop(); });
				w.appendChild(b);
			});
		}
		if (slides.length > 1 && (nav === 'both' || nav === 'dots')) {
			var dots = document.createElement('div');
			dots.className = 'elementor-slides-dots';
			slides.forEach(function (s, i) {
				var b = document.createElement('button');
				b.type = 'button';
				b.setAttribute('aria-label', 'Slide ' + (i + 1));
				b.addEventListener('click', function () { show(i); stop(); });
				dots.appendChild(b);
			});
			w.appendChild(dots);
		}
		show(0);
		var timer = 0;
		function stop() { if (timer) { window.clearInterval(timer); timer = 0; } }
		if (slides.length > 1 && w.getAttribute('data-autoplay') === '1' && motionOn()) {
			timer = window.setInterval(function () { show(at + 1); }, Math.max(1500, parseInt(w.getAttribute('data-speed'), 10) || 5000));
			w.addEventListener('mouseenter', stop);
			w.addEventListener('focusin', stop);
		}
	});

	/* -------------------------------------------------------------- carousel */
	$$('.elementor-image-carousel-wrapper[data-show]').forEach(function (w) {
		var show = parseInt(w.getAttribute('data-show'), 10) || 3;
		var track = w.querySelector('.elementor-image-carousel');
		if (track) { track.style.setProperty('--show', show); }
	});

	/* ------------------------------------------------- alert, video overlay */
	document.addEventListener('click', function (e) {
		var x = e.target.closest ? e.target.closest('.elementor-alert-dismiss') : null;
		if (x) {
			var box = x.closest('.elementor-alert');
			if (box) { box.style.display = 'none'; }
			return;
		}
		var ov = e.target.closest ? e.target.closest('.elementor-custom-embed-image-overlay') : null;
		if (ov) {
			var wrap = ov.closest('[data-src]');
			if (!wrap) { return; }
			var f = document.createElement('iframe');
			f.className = 'elementor-video-iframe';
			f.setAttribute('allowfullscreen', '');
			f.setAttribute('allow', 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen');
			f.setAttribute('title', 'Video');
			var src = wrap.getAttribute('data-src');
			f.src = src + (src.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
			ov.remove();
			wrap.appendChild(f);
		}
	});
})();
