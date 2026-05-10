/*!
 * FB Traffic Detector — snippet.js
 * Loaded as: <script src=".../traffic/snippet.js?d=DOMAIN_KEY" async></script>
 * Captures sections 1, 5, 6, 7, 8 (client), 9 (client) and POSTs to capture.php.
 */
(function () {
  'use strict';

  // ---------- bootstrap ----------
  var SCRIPT = document.currentScript || (function () {
    var ss = document.getElementsByTagName('script');
    for (var i = ss.length - 1; i >= 0; i--) {
      if (ss[i].src && ss[i].src.indexOf('snippet.js') !== -1) return ss[i];
    }
    return null;
  })();
  if (!SCRIPT) return;

  var DOMAIN_KEY = null, ENDPOINT = '';
  try {
    var u = new URL(SCRIPT.src);
    DOMAIN_KEY = u.searchParams.get('d');
    ENDPOINT = u.origin + u.pathname.replace(/\/snippet\.js$/, '/capture.php');
  } catch (e) { return; }
  if (!DOMAIN_KEY) return;

  var DEBUG = /[?&]debug=1\b/.test(window.location.search);
  var T0 = (performance && performance.now) ? performance.now() : Date.now();
  var SENT = false;

  function safe(fn, fallback) { try { return fn(); } catch (e) { return fallback === undefined ? null : fallback; } }
  function dbg() { if (DEBUG && window.console) try { console.log.apply(console, arguments); } catch (e) {} }

  // ============================================================
  // SECTION 1 — URL parameters
  // ============================================================
  function captureURLParams() {
    var p = {};
    var qs = window.location.search;
    var params = safe(function () { return new URLSearchParams(qs); }, null);
    var known = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term',
                 'campaign_id','adset_id','ad_id','placement','site_source',
                 'fbclid','hop','tid','sub1','sub2','sub3','sub4','sub5'];
    var all = {};
    if (params) {
      params.forEach(function (v, k) { all[k] = v; });
    }
    known.forEach(function (k) { p[k] = all.hasOwnProperty(k) ? all[k] : null; });
    p._all_query_params = all;
    p._page_url = safe(function () { return window.location.href; });
    p._page_path = safe(function () { return window.location.pathname; });

    // Validations
    var fbclid = p.fbclid || '';
    p._fbclid_format_valid = /^IwAR[A-Za-z0-9_-]{50,95}$/.test(fbclid);
    p._campaign_id_valid = /^\d{15,16}$/.test(p.campaign_id || '');
    p._adset_id_valid    = /^\d{15,16}$/.test(p.adset_id || '');
    p._ad_id_valid       = /^\d{15,16}$/.test(p.ad_id || '');
    p._site_source_valid = ['fb','ig','an','msg'].indexOf(p.site_source) !== -1;
    return p;
  }

  // ============================================================
  // SECTION 5 — Browser / device properties
  // ============================================================
  function captureBrowserDevice() {
    var n = navigator || {};
    var s = window.screen || {};
    var or = (s.orientation && s.orientation.type) || null;

    var plugins = safe(function () {
      var arr = [];
      if (n.plugins) {
        for (var i = 0; i < n.plugins.length; i++) arr.push(n.plugins[i].name);
      }
      return arr;
    }, []);

    return {
      navigator_user_agent: safe(function () { return n.userAgent; }),
      navigator_platform: safe(function () { return n.platform; }),
      navigator_language: safe(function () { return n.language; }),
      navigator_languages: safe(function () { return n.languages ? Array.prototype.slice.call(n.languages) : null; }),
      navigator_cookie_enabled: safe(function () { return !!n.cookieEnabled; }),
      navigator_do_not_track: safe(function () { return n.doNotTrack; }),
      navigator_webdriver: safe(function () { return typeof n.webdriver === 'undefined' ? 'undefined' : n.webdriver; }),
      navigator_hardware_concurrency: safe(function () { return n.hardwareConcurrency; }),
      navigator_device_memory: safe(function () { return typeof n.deviceMemory === 'undefined' ? 'undefined' : n.deviceMemory; }),
      navigator_max_touch_points: safe(function () { return n.maxTouchPoints; }),
      navigator_vendor: safe(function () { return n.vendor; }),
      navigator_product: safe(function () { return n.product; }),
      navigator_app_name: safe(function () { return n.appName; }),
      navigator_app_version: safe(function () { return n.appVersion; }),
      navigator_online: safe(function () { return !!n.onLine; }),
      navigator_pdf_viewer_enabled: safe(function () { return !!n.pdfViewerEnabled; }),
      screen_width: safe(function () { return s.width; }),
      screen_height: safe(function () { return s.height; }),
      screen_avail_width: safe(function () { return s.availWidth; }),
      screen_avail_height: safe(function () { return s.availHeight; }),
      screen_color_depth: safe(function () { return s.colorDepth; }),
      screen_pixel_depth: safe(function () { return s.pixelDepth; }),
      screen_orientation_type: or,
      window_device_pixel_ratio: safe(function () { return window.devicePixelRatio; }),
      window_inner_width: safe(function () { return window.innerWidth; }),
      window_inner_height: safe(function () { return window.innerHeight; }),
      window_outer_width: safe(function () { return window.outerWidth; }),
      window_outer_height: safe(function () { return window.outerHeight; }),
      window_screen_left: safe(function () { return window.screenLeft != null ? window.screenLeft : window.screenX; }),
      window_screen_top: safe(function () { return window.screenTop != null ? window.screenTop : window.screenY; }),
      timezone: safe(function () { return Intl.DateTimeFormat().resolvedOptions().timeZone; }),
      timezone_offset_min: safe(function () { return new Date().getTimezoneOffset(); }),
      plugins_count: plugins.length,
      plugins_list: plugins,
      mime_types_count: safe(function () { return n.mimeTypes ? n.mimeTypes.length : 0; }),
    };
  }

  // ============================================================
  // SECTION 6 — WebGL / Canvas / Audio fingerprint
  // ============================================================
  function captureWebGL() {
    var c = document.createElement('canvas');
    var gl = safe(function () { return c.getContext('webgl') || c.getContext('experimental-webgl'); });
    if (!gl) return {
      webgl_vendor: null, webgl_renderer: null, webgl_version: null,
      webgl_shading_language_version: null, webgl_unmasked_vendor: null,
      webgl_unmasked_renderer: null, webgl_extensions: []
    };
    var dbgExt = safe(function () { return gl.getExtension('WEBGL_debug_renderer_info'); });
    return {
      webgl_vendor: safe(function () { return gl.getParameter(gl.VENDOR); }),
      webgl_renderer: safe(function () { return gl.getParameter(gl.RENDERER); }),
      webgl_version: safe(function () { return gl.getParameter(gl.VERSION); }),
      webgl_shading_language_version: safe(function () { return gl.getParameter(gl.SHADING_LANGUAGE_VERSION); }),
      webgl_unmasked_vendor: dbgExt ? safe(function () { return gl.getParameter(dbgExt.UNMASKED_VENDOR_WEBGL); }) : null,
      webgl_unmasked_renderer: dbgExt ? safe(function () { return gl.getParameter(dbgExt.UNMASKED_RENDERER_WEBGL); }) : null,
      webgl_extensions: safe(function () { return gl.getSupportedExtensions(); }, []),
    };
  }

  function canvasFingerprint() {
    var out = { canvas_data_url: null, canvas_winding: null };
    try {
      var c = document.createElement('canvas');
      c.width = 280; c.height = 60;
      var ctx = c.getContext('2d');
      ctx.textBaseline = 'top';
      ctx.font = '14px "Arial"';
      ctx.fillStyle = '#f60';
      ctx.fillRect(125, 1, 62, 20);
      ctx.fillStyle = '#069';
      ctx.fillText('FB-Det 🛡️ trustednutra', 2, 15);
      ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
      ctx.fillText('FB-Det 🛡️ trustednutra', 4, 17);
      ctx.beginPath();
      ctx.arc(50, 50, 50, 0, Math.PI * 2, true);
      ctx.closePath();
      ctx.fill();
      out.canvas_data_url = c.toDataURL();
      try {
        ctx.beginPath();
        ctx.rect(0, 0, 10, 10);
        ctx.rect(2, 2, 6, 6);
        out.canvas_winding = !ctx.isPointInPath(5, 5, 'evenodd');
      } catch (e) { out.canvas_winding = null; }
    } catch (e) {}
    return out;
  }

  function sha256Hex(str) {
    if (!window.crypto || !crypto.subtle || !window.TextEncoder) return Promise.resolve(null);
    return crypto.subtle.digest('SHA-256', new TextEncoder().encode(str)).then(function (buf) {
      var b = new Uint8Array(buf), s = '';
      for (var i = 0; i < b.length; i++) s += ('00' + b[i].toString(16)).slice(-2);
      return s;
    }).catch(function () { return null; });
  }

  function audioFingerprint() {
    return new Promise(function (resolve) {
      try {
        var AC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        if (!AC) { resolve({ audio_context_hash: null, audio_context_sample_rate: null, audio_context_state: null }); return; }
        var ctx = new AC(1, 44100, 44100);
        var osc = ctx.createOscillator();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(10000, ctx.currentTime);
        var comp = ctx.createDynamicsCompressor();
        comp.threshold.setValueAtTime(-50, ctx.currentTime);
        comp.knee.setValueAtTime(40, ctx.currentTime);
        comp.ratio.setValueAtTime(12, ctx.currentTime);
        comp.attack.setValueAtTime(0, ctx.currentTime);
        comp.release.setValueAtTime(0.25, ctx.currentTime);
        osc.connect(comp); comp.connect(ctx.destination);
        osc.start(0);
        var done = false, sampleRate = ctx.sampleRate;
        ctx.oncomplete = function (e) {
          if (done) return; done = true;
          var data = e.renderedBuffer.getChannelData(0);
          var sum = 0;
          for (var i = 4500; i < 5000; i++) sum += Math.abs(data[i]);
          resolve({
            audio_context_hash: sum.toString(),
            audio_context_sample_rate: sampleRate,
            audio_context_state: 'closed'
          });
        };
        ctx.startRendering();
        setTimeout(function () { if (!done) { done = true; resolve({ audio_context_hash: null, audio_context_sample_rate: sampleRate, audio_context_state: 'timeout' }); } }, 2000);
      } catch (e) {
        resolve({ audio_context_hash: null, audio_context_sample_rate: null, audio_context_state: 'error' });
      }
    });
  }

  function detectFonts() {
    var out = { available_fonts: [], fonts_test_method: 'css' };
    try {
      var test = ['monospace', 'sans-serif', 'serif'];
      var fonts = ['Arial','Arial Black','Arial Narrow','Arial Rounded MT Bold','Bookman Old Style','Bradley Hand ITC','Century','Century Gothic','Comic Sans MS','Courier','Courier New','Georgia','Gentium','Helvetica','Impact','King','Lucida Console','Lalit','Modena','Monotype Corsiva','Papyrus','Tahoma','TeX','Times','Times New Roman','Trebuchet MS','Verdana','Verona','Apple Chancery','Apple Symbols','Apple Color Emoji','Avenir','Avenir Next','Baskerville','Big Caslon','Brush Script MT','Chalkboard','Chalkduster','Charter','Cochin','Copperplate','Didot','Futura','Geneva','Gill Sans','Helvetica Neue','Hiragino Sans','Hoefler Text','Lucida Grande','Marker Felt','Menlo','Monaco','Optima','Palatino','PingFang SC','SF Pro','Snell Roundhand','Zapfino','Roboto','Roboto Condensed','Noto Sans','Noto Serif','Droid Sans','Cantarell','DejaVu Sans','Liberation Sans','Ubuntu','Segoe UI','Calibri','Cambria','Candara','Consolas','Constantia','Corbel','Microsoft Sans Serif','Segoe Print','Segoe Script','MS Gothic','MS Mincho','SimSun','PMingLiU','MingLiU'];
      var body = document.body || document.documentElement;
      var span = document.createElement('span');
      span.style.cssText = 'position:absolute;left:-9999px;top:-9999px;font-size:72px;visibility:hidden;';
      span.innerHTML = 'mmmmmmmmmmlli';
      body.appendChild(span);
      var baseSizes = {};
      test.forEach(function (b) { span.style.fontFamily = b; baseSizes[b] = { w: span.offsetWidth, h: span.offsetHeight }; });
      fonts.forEach(function (f) {
        var detected = false;
        for (var i = 0; i < test.length; i++) {
          span.style.fontFamily = '"' + f + '",' + test[i];
          if (span.offsetWidth !== baseSizes[test[i]].w || span.offsetHeight !== baseSizes[test[i]].h) { detected = true; break; }
        }
        if (detected) out.available_fonts.push(f);
      });
      body.removeChild(span);
    } catch (e) {}
    return out;
  }

  function captureFingerprintsAsync() {
    var webgl = captureWebGL();
    var canvas = canvasFingerprint();
    var fonts = detectFonts();
    return Promise.all([
      sha256Hex(canvas.canvas_data_url || '').then(function (h) { return h; }),
      audioFingerprint()
    ]).then(function (r) {
      var hash = r[0]; var audio = r[1];
      var out = {};
      Object.keys(webgl).forEach(function (k) { out[k] = webgl[k]; });
      out.canvas_hash = hash;
      out.canvas_winding = canvas.canvas_winding;
      out.audio_context_hash = audio.audio_context_hash;
      out.audio_context_sample_rate = audio.audio_context_sample_rate;
      out.audio_context_state = audio.audio_context_state;
      out.available_fonts = fonts.available_fonts;
      out.fonts_test_method = fonts.fonts_test_method;
      return out;
    });
  }

  // ============================================================
  // SECTION 7 — Behavioral tracker (30s)
  // ============================================================
  function startBehavior() {
    var b = {
      first_interaction_delay_s: null,
      total_touch_events: 0, total_click_events: 0, total_mouse_move_events: 0,
      total_scroll_events: 0, total_keypress_events: 0,
      touch_radius_x_min: null, touch_radius_x_max: null,
      touch_radius_y_min: null, touch_radius_y_max: null,
      touch_pressure_min: null, touch_pressure_max: null,
      multi_finger_events_count: 0,
      mouse_velocity_samples: [],
      mouse_path_curvature: 'none',
      mouse_idle_periods_s: [],
      scroll_pattern: 'none',
      scroll_velocity_samples: [],
      max_scroll_depth_pct: 0,
      scroll_direction_changes: 0,
      idle_periods_s: [],
      tab_visibility_changes: 0,
      window_blur_count: 0,
      window_focus_count: 0,
      cta_click_s: null,
      form_field_focus_times: {},
      keystroke_intervals_ms: [],
    };

    var lastMouse = null, lastMouseT = null, mousePts = [];
    var lastScroll = null, lastScrollT = null, lastScrollDir = 0;
    var lastEventT = T0;
    var lastKeyT = null;
    var formFocusStart = {};
    var firstSet = false;

    function setFirst() { if (!firstSet) { firstSet = true; b.first_interaction_delay_s = ((performance.now() - T0) / 1000); } }

    function logIdle() {
      var now = performance.now();
      var gap = (now - lastEventT) / 1000;
      if (gap > 2) b.idle_periods_s.push(+gap.toFixed(2));
      lastEventT = now;
    }

    function on(target, type, handler, opts) {
      try { target.addEventListener(type, handler, opts || { passive: true, capture: true }); } catch (e) {}
    }

    on(window, 'touchstart', function (e) {
      setFirst(); logIdle();
      b.total_touch_events++;
      if (e.touches && e.touches.length > 1) b.multi_finger_events_count++;
      if (e.touches && e.touches[0]) {
        var t = e.touches[0];
        if (t.radiusX != null) {
          b.touch_radius_x_min = b.touch_radius_x_min == null ? t.radiusX : Math.min(b.touch_radius_x_min, t.radiusX);
          b.touch_radius_x_max = b.touch_radius_x_max == null ? t.radiusX : Math.max(b.touch_radius_x_max, t.radiusX);
        }
        if (t.radiusY != null) {
          b.touch_radius_y_min = b.touch_radius_y_min == null ? t.radiusY : Math.min(b.touch_radius_y_min, t.radiusY);
          b.touch_radius_y_max = b.touch_radius_y_max == null ? t.radiusY : Math.max(b.touch_radius_y_max, t.radiusY);
        }
        if (t.force != null) {
          b.touch_pressure_min = b.touch_pressure_min == null ? t.force : Math.min(b.touch_pressure_min, t.force);
          b.touch_pressure_max = b.touch_pressure_max == null ? t.force : Math.max(b.touch_pressure_max, t.force);
        }
      }
    });

    on(window, 'click', function (e) {
      setFirst(); logIdle();
      b.total_click_events++;
      var tgt = e.target;
      if (tgt && (tgt.tagName === 'A' || tgt.tagName === 'BUTTON' || (tgt.closest && (tgt.closest('a') || tgt.closest('button'))))) {
        if (b.cta_click_s == null) b.cta_click_s = +((performance.now() - T0) / 1000).toFixed(2);
      }
    });

    on(window, 'mousemove', function (e) {
      setFirst();
      b.total_mouse_move_events++;
      var now = performance.now();
      if (lastMouse) {
        var dx = e.clientX - lastMouse.x, dy = e.clientY - lastMouse.y;
        var d = Math.sqrt(dx * dx + dy * dy);
        var dt = (now - lastMouseT) / 1000;
        if (dt > 0 && d > 0) b.mouse_velocity_samples.push(+(d / dt).toFixed(1));
        if (dt > 2) b.mouse_idle_periods_s.push(+dt.toFixed(2));
      }
      lastMouse = { x: e.clientX, y: e.clientY }; lastMouseT = now;
      if (mousePts.length < 1000) mousePts.push([e.clientX, e.clientY]);
      lastEventT = now;
    });

    on(window, 'scroll', function () {
      setFirst();
      b.total_scroll_events++;
      var now = performance.now();
      var y = window.pageYOffset || document.documentElement.scrollTop || 0;
      var docH = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight) - window.innerHeight;
      var pct = docH > 0 ? Math.round((y / docH) * 100) : 0;
      if (pct > b.max_scroll_depth_pct) b.max_scroll_depth_pct = pct;
      if (lastScroll != null) {
        var dy = y - lastScroll;
        var dt = (now - lastScrollT) / 1000;
        if (dt > 0) b.scroll_velocity_samples.push(+(dy / dt).toFixed(1));
        var dir = dy > 0 ? 1 : (dy < 0 ? -1 : 0);
        if (dir !== 0 && lastScrollDir !== 0 && dir !== lastScrollDir) b.scroll_direction_changes++;
        if (dir !== 0) lastScrollDir = dir;
      }
      lastScroll = y; lastScrollT = now;
      lastEventT = now;
    });

    on(window, 'keydown', function (e) {
      setFirst(); logIdle();
      b.total_keypress_events++;
      var now = performance.now();
      if (lastKeyT != null) b.keystroke_intervals_ms.push(Math.round(now - lastKeyT));
      lastKeyT = now;
    });

    on(document, 'visibilitychange', function () { b.tab_visibility_changes++; });
    on(window, 'blur',  function () { b.window_blur_count++;  });
    on(window, 'focus', function () { b.window_focus_count++; });

    on(document, 'focusin', function (e) {
      var t = e.target;
      if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT')) {
        var name = t.name || t.id || (t.tagName + '_' + Math.random().toString(36).slice(2, 7));
        formFocusStart[name] = performance.now();
      }
    });
    on(document, 'focusout', function (e) {
      var t = e.target;
      if (t && formFocusStart[t.name || t.id]) {
        var name = t.name || t.id;
        var dur = (performance.now() - formFocusStart[name]) / 1000;
        b.form_field_focus_times[name] = +(dur).toFixed(2);
        delete formFocusStart[name];
      }
    });

    function computePatterns() {
      // mouse curvature: ratio of total path length to straight-line distance
      if (mousePts.length >= 5) {
        var path = 0;
        for (var i = 1; i < mousePts.length; i++) {
          var ax = mousePts[i][0] - mousePts[i - 1][0];
          var ay = mousePts[i][1] - mousePts[i - 1][1];
          path += Math.sqrt(ax * ax + ay * ay);
        }
        var sx = mousePts[mousePts.length - 1][0] - mousePts[0][0];
        var sy = mousePts[mousePts.length - 1][1] - mousePts[0][1];
        var straight = Math.sqrt(sx * sx + sy * sy);
        var ratio = straight > 0 ? path / straight : 0;
        b.mouse_path_curvature = ratio > 1.5 ? 'curved' : (ratio > 1.05 ? 'linear' : 'none');
      } else if (b.total_mouse_move_events > 0) {
        b.mouse_path_curvature = 'linear';
      }
      // scroll pattern heuristic
      if (b.scroll_velocity_samples.length >= 5) {
        var fast = 0, slow = 0;
        b.scroll_velocity_samples.forEach(function (v) { var a = Math.abs(v); if (a > 1500) fast++; else if (a > 0) slow++; });
        if (fast > 0 && slow > 0 && b.max_scroll_depth_pct > 30) b.scroll_pattern = 'slow_read_fast_skip';
        else if (b.scroll_direction_changes > 5) b.scroll_pattern = 'erratic';
        else b.scroll_pattern = 'linear';
      }
    }

    return {
      snapshot: function () {
        computePatterns();
        var perf = (performance && performance.timing) || null;
        var s = {};
        Object.keys(b).forEach(function (k) { s[k] = b[k]; });
        s.time_on_page_s = +((performance.now() - T0) / 1000).toFixed(2);
        s.page_load_s = perf ? +((perf.loadEventEnd - perf.navigationStart) / 1000).toFixed(2) : null;
        s.dom_complete_s = perf ? +((perf.domComplete - perf.navigationStart) / 1000).toFixed(2) : null;
        try {
          var pe = performance.getEntriesByType ? performance.getEntriesByType('paint') : [];
          pe.forEach(function (e) {
            if (e.name === 'first-paint') s.first_paint_s = +(e.startTime / 1000).toFixed(3);
            if (e.name === 'first-contentful-paint') s.first_contentful_paint_s = +(e.startTime / 1000).toFixed(3);
          });
        } catch (e) {}
        return s;
      }
    };
  }

  // ============================================================
  // SECTION 8 — Facebook signals (client portion)
  // ============================================================
  function captureFBClient(s1) {
    var ref = safe(function () { return document.referrer; }, '');
    var refDomain = '';
    var fbHostMatch = '';
    try {
      if (ref) {
        var ru = new URL(ref);
        refDomain = ru.hostname;
        var fbHosts = ['l.facebook.com','m.facebook.com','lm.facebook.com'];
        for (var i = 0; i < fbHosts.length; i++) if (refDomain === fbHosts[i] || refDomain.endsWith('.' + fbHosts[i])) { fbHostMatch = fbHosts[i]; break; }
      }
    } catch (e) {}
    var hParam = 'absent';
    try {
      if (ref && fbHostMatch === 'l.facebook.com') {
        var ru2 = new URL(ref);
        hParam = ru2.searchParams.get('h') || 'absent';
      }
    } catch (e) {}

    var camp = (s1.utm_campaign || '');
    var geoMatch = camp.match(/(?:^|_)([A-Z]{2})(?:_|$)/);
    var devMatch = camp.match(/(?:^|_)(Mobile|Desktop|MOB|DSK)(?:_|$)/i);
    var objMatch = camp.match(/(?:^|_)(Conv|Leads?|Sales|Awareness|Retarget|RTG|AWR|LDS|CONV|SALES)(?:_|$)/i);

    return {
      fbclid_present: !!s1.fbclid,
      fbclid_value: s1.fbclid || null,
      fbclid_format_valid: !!s1._fbclid_format_valid,
      fbclid_prefix: (s1.fbclid || '').slice(0, 4),
      fbclid_length: (s1.fbclid || '').length,
      referer_present: !!ref,
      referer_full_url: ref || null,
      referer_domain: refDomain || (ref ? 'other' : 'empty'),
      referer_is_facebook: !!fbHostMatch,
      fb_redirect_token_h: hParam,
      site_source_value: s1.site_source || null,
      placement_value: s1.placement || null,
      campaign_geo_target: geoMatch ? geoMatch[1] : null,
      campaign_device_target: devMatch ? devMatch[1] : null,
      campaign_objective: objMatch ? objMatch[1] : null,
      ios_att_permission: 'Unknown',
      // ua_has_* + fb_capi_match are filled by capture.php
    };
  }

  // ============================================================
  // SECTION 9 — Bot detection (client portion)
  // ============================================================
  function botChecksClient(s5, s6) {
    var ua = (navigator.userAgent || '');
    var isMobileUA = /(iPhone|iPad|iPod|Android)/i.test(ua);
    var isiOS = /(iPhone|iPad|iPod)/i.test(ua);
    var isChrome = /Chrome\//.test(ua) && !/Edge\/|Edg\//.test(ua);
    var rend = (s6.webgl_unmasked_renderer || s6.webgl_renderer || '');

    return {
      webdriver_check: (typeof navigator.webdriver === 'undefined' || navigator.webdriver === false) ? 'PASS' : 'FAIL',
      plugins_sane_check: (isiOS ? s5.plugins_count <= 2 : true) ? 'PASS' : 'FAIL',
      permissions_consistency: (typeof navigator.permissions !== 'undefined') ? 'PASS' : (isiOS ? 'PASS' : 'FAIL'),
      headless_marker_check: /HeadlessChrome|PhantomJS|Selenium|Puppeteer/i.test(ua) ? 'FAIL' : 'PASS',
      webgl_real_gpu_check: rend ? (/(SwiftShader|llvmpipe|Mesa Offscreen|Software)/i.test(rend) ? 'FAIL' : 'PASS') : 'FAIL',
      window_dimensions_check: (s5.window_outer_width > 0 && s5.window_outer_height > 0) ? 'PASS' : 'FAIL',
      battery_api_check: ('getBattery' in navigator) ? 'PASS' : (isMobileUA && !isiOS ? 'FAIL' : 'PASS'),
      connection_api_check: ('connection' in navigator || 'mozConnection' in navigator || 'webkitConnection' in navigator) ? 'PASS' : (isMobileUA && !isiOS ? 'FAIL' : 'PASS'),
      notification_permission_check: (function () {
        try { return ['default','granted','denied'].indexOf(Notification.permission) !== -1 ? 'PASS' : 'FAIL'; }
        catch (e) { return isiOS ? 'PASS' : 'FAIL'; }
      })(),
      chrome_runtime_check: isChrome ? ((window.chrome && window.chrome.runtime) ? 'PASS' : 'FAIL') : 'PASS',
      touch_consistency_check: (isMobileUA ? s5.navigator_max_touch_points > 0 : s5.navigator_max_touch_points === 0) ? 'PASS' : 'FAIL',
      ios_webkit_consistency: 'SERVER',  // server fills based on Sec-Ch-Ua absence
      canvas_uniqueness: s6.canvas_hash ? 'UNIQUE' : 'SUSPICIOUS',
      audio_uniqueness: s6.audio_context_hash ? 'UNIQUE' : 'SUSPICIOUS',
    };
  }

  // ============================================================
  // Send / debug
  // ============================================================
  function send(payload, useBeacon) {
    if (SENT) return; SENT = true;
    payload.domain_key = DOMAIN_KEY;
    payload._client_ts = Date.now();
    payload._sent_at_s = +((performance.now() - T0) / 1000).toFixed(2);
    var json = JSON.stringify(payload);
    try {
      if (useBeacon && navigator.sendBeacon) {
        var blob = new Blob([json], { type: 'application/json' });
        navigator.sendBeacon(ENDPOINT, blob);
        return;
      }
      fetch(ENDPOINT, {
        method: 'POST',
        mode: 'cors',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json' },
        body: json,
        keepalive: true
      }).then(function (r) { return r.json(); }).then(function (resp) {
        dbg('captured', resp);
        if (DEBUG) showCopyButton(resp);
      }).catch(function (e) { dbg('capture error', e); });
    } catch (e) { dbg('send error', e); }
  }

  function badge(text, color) {
    var d = document.createElement('div');
    d.style.cssText = 'position:fixed;bottom:12px;right:12px;background:' + color + ';color:#fff;padding:8px 14px;font:12px/1.4 system-ui,sans-serif;border-radius:6px;z-index:2147483647;box-shadow:0 2px 8px rgba(0,0,0,.25);cursor:pointer;';
    d.textContent = text;
    return d;
  }

  function showDebugStatus() {
    if (!DEBUG) return;
    var s = badge('FB-Det: capturing…', '#444');
    s.id = '__fbdet_status';
    document.body && document.body.appendChild(s);
  }

  function showCopyButton(resp) {
    if (!DEBUG) return;
    var prev = document.getElementById('__fbdet_status');
    if (prev) prev.remove();
    var btn = badge('Copy JSON ✓', '#0a8a3a');
    btn.onclick = function () {
      var data = JSON.stringify(resp, null, 2);
      try {
        navigator.clipboard.writeText(data).then(function () { btn.textContent = 'Copied ✓'; });
      } catch (e) {
        var ta = document.createElement('textarea'); ta.value = data; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy'); ta.remove(); btn.textContent = 'Copied ✓';
      }
    };
    document.body && document.body.appendChild(btn);
  }

  // ============================================================
  // Main
  // ============================================================
  var behavior = startBehavior();

  function ready(fn) {
    if (document.readyState === 'complete' || document.readyState === 'interactive') setTimeout(fn, 0);
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    showDebugStatus();
    var s1 = captureURLParams();
    var s5 = captureBrowserDevice();
    var s8 = captureFBClient(s1);

    captureFingerprintsAsync().then(function (s6) {
      // schedule full send at 30s
      var sendTimer = setTimeout(function () {
        send({
          section1_url_params: s1,
          section5_browser_device: s5,
          section6_webgl_canvas: s6,
          section7_behavioral: behavior.snapshot(),
          section8_facebook_client: s8,
          section9_bot_checks_client: botChecksClient(s5, s6),
        }, false);
      }, 30000);

      // pagehide → beacon partial if not yet sent
      window.addEventListener('pagehide', function () {
        if (SENT) return;
        clearTimeout(sendTimer);
        send({
          section1_url_params: s1,
          section5_browser_device: s5,
          section6_webgl_canvas: s6,
          section7_behavioral: behavior.snapshot(),
          section8_facebook_client: s8,
          section9_bot_checks_client: botChecksClient(s5, s6),
          partial: true,
        }, true);
      });
    });
  });
})();
