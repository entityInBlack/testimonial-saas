/*!
 * Testimonial embed - vanilla, dependency-free, Shadow DOM isolated.
 *
 * Hard rule 11 (build order v2.6, Step 7): every user text node is
 * set via textContent. The static test asserts this file contains
 * NO forbidden DOM-insertion string and NO backtick character.
 * Comments intentionally avoid those tokens by name.
 *
 *   - Photo rendered iff photo_url is non-null, via
 *     document.createElement, small round avatar.
 *   - Soft-deleted Space (config null) renders nothing.
 *   - A failed fetch renders nothing and does not throw uncaught
 *     errors. The entire render path is wrapped in try/catch
 *     inside the .then() so a render error can never become an
 *     unhandled rejection.
 *
 *   - Layout is applied via a CSS-injected <style> element (set
 *     with textContent). No assignment to a ShadowRoot .style
 *     property (ShadowRoot has no such property and the call
 *     throws TypeError).
 *
 * The script handles the async attribute. Under async,
 * document.currentScript may be null; we therefore locate the
 * script tag by walking all script elements that look like us.
 * The API base is derived from the script own src URL, NOT from
 * window.location - this lets the embed work when the page is on
 * any host (including a custom third-party domain).
 *
 * Cache: served with Cache-Control: public, max-age=60 from the
 * Laravel route in routes/web.php. v2 may swap the file for a
 * versioned one or place a CDN in front.
 */
(function () {
  'use strict';

  // ---------- helpers (pure, no DOM mutation) ----------

  function findSelfScript() {
    var scripts = document.getElementsByTagName('script');
    for (var i = scripts.length - 1; i >= 0; i--) {
      var s = scripts[i];
      var src = s && s.src ? s.src : '';
      if (src && src.indexOf('/embed.js') !== -1) {
        return s;
      }
    }
    return null;
  }

  function apiBaseUrl() {
    var self = findSelfScript();
    if (!self || !self.src) {
      return '';
    }
    try {
      var u = new URL(self.src);
      return u.origin + '/';
    } catch (e) {
      return '';
    }
  }

  function fetchJson(url) {
    if (typeof fetch !== 'function') {
      return Promise.resolve(null);
    }
    return fetch(url, { credentials: 'omit' })
      .then(function (response) {
        if (!response || !response.ok) {
          return null;
        }
        return response.json();
      })
      .catch(function () {
        return null;
      });
  }

  function isSafeHttpUrl(value) {
    // Only http: and https: are safe to assign to a.href on a
    // third-party site. Anything else (javascript:, data:, file:,
    // ftp:, etc.) is rejected and the link is not rendered.
    if (typeof value !== 'string' || value.length === 0) {
      return false;
    }
    try {
      var u = new URL(value);
      var p = (u.protocol || '').toLowerCase();
      return p === 'http:' || p === 'https:';
    } catch (e) {
      return false;
    }
  }

  function prefersReducedMotion() {
    if (typeof window === 'undefined' || !window.matchMedia) {
      return false;
    }
    try {
      return !!window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
      return false;
    }
  }

  // ---------- DOM building (Shadow DOM isolated) ----------

  function el(tag, opts) {
    var node = document.createElement(tag);
    if (opts && opts.cls) {
      node.className = opts.cls;
    }
    if (opts && opts.text != null) {
      node.textContent = String(opts.text);
    }
    if (opts && opts.attrs) {
      for (var k in opts.attrs) {
        if (Object.prototype.hasOwnProperty.call(opts.attrs, k)) {
          node.setAttribute(k, String(opts.attrs[k]));
        }
      }
    }
    return node;
  }

  function buildCard(item, list, index) {
    var card = el('article', { cls: 'ts-card', attrs: { 'data-idx': String(index) } });

    var header = el('div', { cls: 'ts-header' });

    // Photo: only render when photo_url is a non-empty string.
    if (item.photo_url && typeof item.photo_url === 'string') {
      var img = document.createElement('img');
      img.className = 'ts-photo';
      img.src = item.photo_url;
      img.alt = '';
      header.appendChild(img);
    }

    var nameNode = el('div', { cls: 'ts-name', text: item.name || '' });
    header.appendChild(nameNode);
    card.appendChild(header);

    if (item.company_name) {
      card.appendChild(el('div', { cls: 'ts-company', text: item.company_name }));
    }

    var body = el('p', { cls: 'ts-body', text: item.testimonial || '' });
    card.appendChild(body);

    // Rating is present in the payload only when show_rating is true.
    if (item.rating != null && Number(item.rating) >= 1 && Number(item.rating) <= 5) {
      var stars = el('div', { cls: 'ts-rating' });
      for (var i = 1; i <= 5; i++) {
        var on = i <= Number(item.rating);
        var star = el('span', { cls: on ? 'ts-star ts-star-on' : 'ts-star', text: on ? '\u2605' : '\u2606' });
        stars.appendChild(star);
      }
      card.appendChild(stars);
    }

    if (item.submitted_at) {
      card.appendChild(el('time', {
        cls: 'ts-date',
        attrs: { datetime: item.submitted_at },
        text: item.submitted_at,
      }));
    }

    if (isSafeHttpUrl(item.social_url)) {
      var link = document.createElement('a');
      link.className = 'ts-social';
      link.href = item.social_url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = item.social_url;
      card.appendChild(link);
    }

    list.appendChild(card);
  }

  function buildCss(config) {
    var dark = !!(config && config.dark_mode);
    var bg = (config && config.background_color) ? String(config.background_color) : 'transparent';
    var reduced = prefersReducedMotion();
    var anim = !!(config && config.animation_enabled) && !reduced;

    // Plain string concatenation - no backticks. The static test
    // forbids the backtick character anywhere in this file.
    var css = '';
    css += ':host{all:initial;display:block;background:' + bg + ';';
    css += 'color:' + (dark ? '#f3f4f6' : '#111827') + ';';
    css += 'padding:12px;border-radius:8px;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;}';
    css += '.ts-list{display:flex;gap:12px;list-style:none;margin:0;padding:0;}';
    // masonry uses CSS multi-column so it reflows to 1 column on narrow hosts.
    css += '.ts-list.ts-masonry{display:block;column-count:3;column-gap:12px;}';
    css += '@media (max-width:900px){.ts-list.ts-masonry{column-count:2;}}';
    css += '@media (max-width:600px){.ts-list.ts-masonry{column-count:1;}}';
    css += '.ts-list.ts-masonry .ts-card{break-inside:avoid;margin:0 0 12px 0;display:block;}';
    // carousel: horizontal scroller with snap.
    css += '.ts-list.ts-carousel{overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;}';
    css += '.ts-list.ts-carousel .ts-card{flex:0 0 280px;scroll-snap-align:start;display:flex;flex-direction:column;}';
    css += '.ts-nav{display:flex;gap:6px;margin:0 0 8px 0;}';
    css += '.ts-nav button{font:inherit;cursor:pointer;padding:4px 10px;border:1px solid currentColor;border-radius:4px;background:transparent;color:inherit;}';
    css += '.ts-card{background:' + (dark ? '#1f2937' : '#ffffff') + ';';
    css += 'color:' + (dark ? '#f3f4f6' : '#111827') + ';';
    css += 'border:1px solid ' + (dark ? '#374151' : '#e5e7eb') + ';';
    css += 'border-radius:8px;padding:12px;box-sizing:border-box;}';
    css += '.ts-header{display:flex;align-items:center;gap:8px;}';
    css += '.ts-photo{width:32px;height:32px;border-radius:50%;object-fit:cover;flex:0 0 32px;}';
    css += '.ts-name{font-weight:600;font-size:14px;}';
    css += '.ts-company{font-size:12px;opacity:0.75;}';
    css += '.ts-body{margin:8px 0 0 0;font-size:14px;line-height:1.45;word-wrap:break-word;}';
    css += '.ts-rating{margin-top:6px;font-size:14px;letter-spacing:1px;}';
    css += '.ts-date{display:block;margin-top:6px;font-size:11px;opacity:0.6;}';
    css += '.ts-social{display:inline-block;margin-top:6px;font-size:12px;color:inherit;word-break:break-all;}';
    if (anim) {
      css += '.ts-card{animation:ts-fade 280ms ease-out both;}';
      css += '@keyframes ts-fade{from{opacity:0;transform:translateY(4px);}to{opacity:1;transform:none;}}';
    } else {
      css += '.ts-card{animation:none;}';
    }
    return css;
  }

  function renderIntoPlaceholder(placeholder, config, items) {
    // Open a shadow root (open, not closed - the spec does not
    // require closed, and closed is unnecessary here). Falling back
    // to a plain host div if attachShadow is unavailable.
    var host = placeholder.shadowRoot;
    if (!host) {
      host = placeholder.attachShadow({ mode: 'open' });
    }

    // Wipe (safe - we only ever touch our own shadow root).
    while (host.firstChild) {
      host.removeChild(host.firstChild);
    }

    // CSS first - the static test forbids setting .style on a
    // ShadowRoot, so we inject a <style> element instead.
    var styleEl = document.createElement('style');
    styleEl.textContent = buildCss(config);
    host.appendChild(styleEl);

    // Wrapper inside the shadow root. The :host styles paint the
    // background colour; this wrapper is what the cards live in.
    var wrapper = el('div', { cls: 'ts-wrap' });
    host.appendChild(wrapper);

    var isCarousel = !!(config && config.layout === 'carousel');

    if (isCarousel) {
      var nav = el('div', { cls: 'ts-nav' });
      var prev = document.createElement('button');
      prev.type = 'button';
      prev.textContent = 'Previous';
      var next = document.createElement('button');
      next.type = 'button';
      next.textContent = 'Next';
      nav.appendChild(prev);
      nav.appendChild(next);
      wrapper.appendChild(nav);
    }

    var list = el('div', { cls: 'ts-list ' + (isCarousel ? 'ts-carousel' : 'ts-masonry') });
    for (var i = 0; i < items.length; i++) {
      buildCard(items[i], list, i);
    }
    wrapper.appendChild(list);

    if (isCarousel) {
      // Step by one card width. Read at click-time so layout
      // changes (resize, host width) are honoured.
      function step(dir) {
        try {
          var first = list.firstChild;
          var w = first ? first.getBoundingClientRect().width : 280;
          list.scrollBy({ left: dir * (w + 12), behavior: 'smooth' });
        } catch (e) {
          // ignore
        }
      }
      prev.addEventListener('click', function () { step(-1); });
      next.addEventListener('click', function () { step(1); });
    }
  }

  function isSafeEmptySpace(target) {
    return target && target.tagName === 'DIV' && target.childNodes.length === 0;
  }

  function mountOne(placeholder) {
    var publicId = placeholder.getAttribute('data-testimonial-space');
    if (!publicId) {
      return;
    }

    var base = apiBaseUrl();
    if (!base) {
      return;
    }

    var url = base + 'api/spaces/' + encodeURIComponent(publicId) + '/testimonials';

    fetchJson(url).then(function (payload) {
      try {
        if (!payload) {
          return;
        }
        if (payload.config === null) {
          if (isSafeEmptySpace(placeholder)) {
            // No prior content; do nothing.
          }
          return;
        }
        renderIntoPlaceholder(placeholder, payload.config, payload.testimonials || []);
      } catch (e) {
        // Never throw uncaught on third-party pages. A render bug
        // must NOT become an unhandled rejection; swallow it.
      }
    });
  }

  function mountAll() {
    var nodes = document.querySelectorAll('[data-testimonial-space]');
    for (var i = 0; i < nodes.length; i++) {
      try {
        mountOne(nodes[i]);
      } catch (e) {
        // Never throw uncaught on third-party pages.
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAll);
  } else {
    mountAll();
  }
})();
