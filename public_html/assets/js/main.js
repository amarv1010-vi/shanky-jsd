/* ============================================================
   JSD Construction — main.js
   Handles: sticky header, mobile nav, GSAP scroll reveals,
   animated counters, project gallery (API + static fallback),
   AJAX forms (enquiry / contact / estimator lead capture),
   and the instant build-cost estimator.
   Dependencies: GSAP 3 + ScrollTrigger (loaded via CDN).
   ============================================================ */

(function () {
  "use strict";

  var API_BASE = "api"; // relative to site root

  /* ---------- Sticky header ---------- */
  var header = document.querySelector(".site-header");
  function onScroll() {
    if (header) header.classList.toggle("scrolled", window.scrollY > 40);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile nav ---------- */
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.querySelector(".main-nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.classList.toggle("open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      document.body.style.overflow = open ? "hidden" : "";
    });
    nav.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        nav.classList.remove("open");
        toggle.classList.remove("open");
        document.body.style.overflow = "";
      });
    });
  }

  /* ---------- GSAP scroll reveals ---------- */
  var gsapReady = typeof window.gsap !== "undefined" && typeof window.ScrollTrigger !== "undefined";
  if (gsapReady) {
    gsap.registerPlugin(ScrollTrigger);
    document.querySelectorAll(".reveal").forEach(function (el, i) {
      gsap.fromTo(
        el,
        { opacity: 0, y: 34 },
        {
          opacity: 1, y: 0,
          duration: 0.9,
          ease: "power3.out",
          delay: (i % 4) * 0.08,
          scrollTrigger: { trigger: el, start: "top 88%", once: true }
        }
      );
    });
    // Hero entrance
    var heroBits = document.querySelectorAll(".hero [data-hero]");
    if (heroBits.length) {
      gsap.fromTo(heroBits, { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 1.05, ease: "power3.out", stagger: 0.14, delay: 0.15 });
    }
    var heroPanel = document.querySelector(".hero-panel");
    if (heroPanel) {
      gsap.fromTo(heroPanel, { opacity: 0, scale: 0.94, rotate: 1.5 }, { opacity: 1, scale: 1, rotate: 0, duration: 1.2, ease: "power3.out", delay: 0.4 });
    }
  } else {
    document.documentElement.classList.add("gsap-off");
  }

  /* ---------- Animated counters ---------- */
  function animateCounter(el) {
    var target = parseFloat(el.getAttribute("data-count") || "0");
    var suffix = el.getAttribute("data-suffix") || "";
    var start = null, dur = 1600;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased) + suffix;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var counters = document.querySelectorAll("[data-count]");
  if (counters.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { animateCounter(e.target); io.unobserve(e.target); }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (c) { io.observe(c); });
  }

  /* ============================================================
     GALLERY (photos) — driven by api/gallery.php (filesystem).
     - Home  : [data-featured-grid] + [data-video-section]
     - Projects: dynamic filter bar + [data-projects-grid] (by collection)
                 + [data-buildtypes-grid] (6 AI build-type boxes)
     Falls back to built-in placeholders so the site never looks empty.
     ============================================================ */

  var FALLBACK_FEATURED = [
    { image: "assets/img/projects/proj-highset-01-01.svg" },
    { image: "assets/img/projects/proj-lowset-02-01.svg" },
    { image: "assets/img/projects/proj-split-03-01.svg" },
    { image: "assets/img/projects/proj-commercial-04-01.svg" },
    { image: "assets/img/projects/proj-highset-01-02.svg" },
    { image: "assets/img/projects/proj-commercial-04-02.svg" }
  ];
  var FALLBACK_BUILDTYPES = [
    { image: "assets/img/projects/proj-highset-01-01.svg", category: "High-Set", location: "Rochedale, QLD", description: "Elevated family home maximising airflow and under-house living on a sloping block." },
    { image: "assets/img/projects/proj-lowset-02-01.svg", category: "Low-Set", location: "Springwood, QLD", description: "Open-plan single-level build with strong street presence on a flat allotment." },
    { image: "assets/img/projects/proj-split-03-01.svg", category: "Split-Level", location: "Eight Mile Plains, QLD", description: "Split-level design following the natural terrain with tiered outdoor living." },
    { image: "assets/img/projects/proj-commercial-04-01.svg", category: "Commercial", location: "Underwood, QLD", description: "Low-rise retail and mixed-use space delivered end-to-end under QLD low-rise licence." },
    { image: "assets/img/projects/proj-highset-01-02.svg", category: "High-Set", location: "Calamvale, QLD", description: "Contemporary high-set with premium timber framing and energy-efficient design." },
    { image: "assets/img/projects/proj-commercial-04-02.svg", category: "Commercial", location: "Ipswich, QLD", description: "Low-rise commercial project with full in-house project management." }
  ];

  function escAttr(s) { return String(s || "").replace(/"/g, "&quot;"); }

  /* ----- shared lightbox ----- */
  var galleryImages = [];
  var lb, lbImg, lbCap, lbIndex = 0;
  function ensureLightbox() {
    if (lb) return;
    lb = document.createElement("div");
    lb.className = "lightbox";
    lb.innerHTML =
      '<button class="lb-close" aria-label="Close">✕</button>' +
      '<button class="lb-nav lb-prev" aria-label="Previous">‹</button>' +
      '<figure class="lb-stage"><img alt=""><figcaption></figcaption></figure>' +
      '<button class="lb-nav lb-next" aria-label="Next">›</button>';
    document.body.appendChild(lb);
    lbImg = lb.querySelector("img");
    lbCap = lb.querySelector("figcaption");
    lb.querySelector(".lb-close").addEventListener("click", closeLightbox);
    lb.querySelector(".lb-prev").addEventListener("click", function (e) { e.stopPropagation(); stepLightbox(-1); });
    lb.querySelector(".lb-next").addEventListener("click", function (e) { e.stopPropagation(); stepLightbox(1); });
    lb.addEventListener("click", function (e) { if (e.target === lb) closeLightbox(); });
    document.addEventListener("keydown", function (e) {
      if (!lb.classList.contains("open")) return;
      if (e.key === "Escape") closeLightbox();
      else if (e.key === "ArrowLeft") stepLightbox(-1);
      else if (e.key === "ArrowRight") stepLightbox(1);
    });
  }
  function openLightbox(list, i) {
    ensureLightbox();
    galleryImages = list; lbIndex = i; showLightbox();
    lb.classList.add("open"); document.body.style.overflow = "hidden";
  }
  function showLightbox() {
    var p = galleryImages[lbIndex]; if (!p) return;
    lbImg.src = p.image; lbImg.alt = p.caption || "";
    lbCap.textContent = p.caption || "";
  }
  function stepLightbox(d) { lbIndex = (lbIndex + d + galleryImages.length) % galleryImages.length; showLightbox(); }
  function closeLightbox() { if (lb) lb.classList.remove("open"); document.body.style.overflow = ""; }

  function animateIn(el) {
    if (gsapReady) gsap.fromTo(el.children, { opacity: 0, y: 26 }, { opacity: 1, y: 0, duration: 0.6, ease: "power2.out", stagger: 0.06 });
    else Array.prototype.forEach.call(el.children, function (c) { c.classList.remove("reveal"); });
  }

  /* ----- fetch the gallery once, then render whatever this page needs ----- */
  var featuredGrid  = document.querySelector("[data-featured-grid]");
  var videoSection  = document.querySelector("[data-video-section]");
  var projectGrid   = document.querySelector("[data-projects-grid]");
  var buildGrid     = document.querySelector("[data-buildtypes-grid]");
  var filterBar     = document.querySelector("[data-filter-bar]");

  if (featuredGrid || videoSection || projectGrid || buildGrid) {
    fetch(API_BASE + "/gallery.php")
      .then(function (r) { return r.ok ? r.json() : null; })
      .catch(function () { return null; })
      .then(function (g) {
        g = g && g.ok ? g : {};
        renderFeatured((g.featured && g.featured.length) ? g.featured : FALLBACK_FEATURED);
        renderVideo(g.video || null);
        renderProjects(g.projects || { collections: [], items: [] });
        renderBuildTypes((g.buildTypes && g.buildTypes.length) ? g.buildTypes : FALLBACK_BUILDTYPES);
      });
  }

  /* ----- Home: Featured (images only, zoom-out hover) ----- */
  function renderFeatured(list) {
    if (!featuredGrid) return;
    var lbList = list.map(function (p) { return { image: p.image, caption: "" }; });
    featuredGrid.innerHTML = "";
    list.forEach(function (p, i) {
      var card = document.createElement("button");
      card.className = "featured-card reveal";
      card.setAttribute("aria-label", "View featured project " + (i + 1));
      card.innerHTML = '<img loading="lazy" src="' + p.image + '" alt="JSD featured project ' + (i + 1) + '">' +
        '<span class="project-zoom" aria-hidden="true">⤢</span>';
      card.addEventListener("click", function () { openLightbox(lbList, i); });
      featuredGrid.appendChild(card);
    });
    animateIn(featuredGrid);
  }

  /* ----- Home: cinematic video ----- */
  function renderVideo(src) {
    if (!videoSection) return;
    if (!src) { videoSection.style.display = "none"; return; }
    videoSection.style.display = "";
    var frame = videoSection.querySelector(".video-frame");
    frame.querySelector("video") || frame.insertAdjacentHTML("afterbegin",
      '<video autoplay muted loop playsinline preload="metadata"></video>');
    var v = frame.querySelector("video");
    v.src = src;
    v.play().catch(function () {}); // some browsers need the muted+playsinline combo (already set)
  }

  /* ----- Projects: filterable photos by collection ----- */
  function renderProjects(data) {
    if (!projectGrid) return;
    var items = data.items || [];
    var collections = data.collections || [];
    var usingReal = items.length > 0;

    if (!usingReal) {
      // graceful placeholder so the page isn't empty pre-upload
      items = FALLBACK_BUILDTYPES.map(function (p, i) {
        return { collection: "sample", collectionLabel: "Portfolio", image: p.image, sort: i };
      });
      collections = [];
    }

    function draw(list) {
      projectGrid.innerHTML = "";
      var lbList = list.map(function (p) { return { image: p.image, caption: p.collectionLabel || "" }; });
      list.forEach(function (p, i) {
        var card = document.createElement("article");
        card.className = "project-card reveal";
        card.setAttribute("data-collection", p.collection);
        card.innerHTML =
          '<button class="project-media" aria-label="View photo">' +
          '<img loading="lazy" src="' + p.image + '" alt="' + escAttr(p.collectionLabel) + '">' +
          '<span class="project-zoom" aria-hidden="true">⤢</span></button>' +
          (p.collectionLabel ? '<div class="project-info"><span class="project-tag">' + p.collectionLabel + "</span></div>" : "");
        card.querySelector(".project-media").addEventListener("click", function () { openLightbox(lbList, i); });
        projectGrid.appendChild(card);
      });
      animateIn(projectGrid);
    }

    // build filter bar dynamically
    if (filterBar) {
      var btns = ['<button class="filter-btn active" data-filter="all">All</button>'];
      collections.forEach(function (c) {
        btns.push('<button class="filter-btn" data-filter="' + c.key + '">' + c.label + "</button>");
      });
      filterBar.innerHTML = btns.join("");
      filterBar.querySelectorAll(".filter-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
          filterBar.querySelectorAll(".filter-btn").forEach(function (b) { b.classList.remove("active"); });
          btn.classList.add("active");
          var f = btn.getAttribute("data-filter");
          draw(f === "all" ? items : items.filter(function (p) { return p.collection === f; }));
        });
      });
    }
    draw(items);
  }

  /* ----- Projects: 6 build-type boxes (AI images + fixed labels) ----- */
  function renderBuildTypes(list) {
    if (!buildGrid) return;
    var lbList = list.map(function (p) { return { image: p.image, caption: p.category + " · " + p.location }; });
    buildGrid.innerHTML = "";
    list.forEach(function (p, i) {
      var card = document.createElement("article");
      card.className = "project-card reveal";
      card.innerHTML =
        '<button class="project-media" aria-label="View ' + escAttr(p.category) + '">' +
        '<img loading="lazy" src="' + p.image + '" alt="' + escAttr(p.category + " " + p.location) + '">' +
        '<span class="project-zoom" aria-hidden="true">⤢</span></button>' +
        '<div class="project-info">' +
        '<span class="project-tag">' + p.category + " · " + p.location + "</span>" +
        "<p>" + p.description + "</p></div>";
      card.querySelector(".project-media").addEventListener("click", function () { openLightbox(lbList, i); });
      buildGrid.appendChild(card);
    });
    animateIn(buildGrid);
  }

  /* ---------- Testimonials (API + fallback) ---------- */
  var FALLBACK_TESTIMONIALS = [
    { name: "Priya & Daniel M.", project: "High-Set Build, Rochedale", rating: 5, quote: "Jagdeep oversaw every stage himself. The timber detailing and finish are beyond what we expected — and we moved in on schedule." },
    { name: "Robert K.", project: "Commercial Fit-Out, Underwood", rating: 5, quote: "End-to-end management meant one point of contact from council approvals to handover. Professional from day one." },
    { name: "Sandeep & Aman G.", project: "Split-Level Home, Calamvale", rating: 5, quote: "Our sloping block scared off other builders. JSD turned it into the best feature of the house." },
    { name: "Harpreet & Simran K.", project: "Investment Duplex, Sunnybank Hills", rating: 5, quote: "Transparent pricing from the first quote to the final invoice — not a single surprise. Our duplex was tenanted within two weeks of handover." },
    { name: "Melanie T.", project: "Knockdown Rebuild, Mount Gravatt", rating: 5, quote: "We live locally and drove past the site every day — it was always tidy, always moving. Twelve months on, the JSD team still answers every little maintenance question." }
  ];
  var tWrap = document.querySelector("[data-testimonials]");
  if (tWrap) {
    function renderTestimonials(list) {
      tWrap.innerHTML = list.map(function (t) {
        var stars = "★★★★★".slice(0, Math.max(1, Math.min(5, t.rating || 5)));
        return '<figure class="testimonial reveal"><div class="stars">' + stars + "</div>" +
          "<blockquote>“" + t.quote + "”</blockquote>" +
          "<figcaption><cite>" + t.name + "<span>" + (t.project || "") + "</span></cite></figcaption></figure>";
      }).join("");
      if (gsapReady) gsap.fromTo(tWrap.children, { opacity: 0, y: 26 }, { opacity: 1, y: 0, duration: 0.6, stagger: 0.1 });
      else Array.prototype.forEach.call(tWrap.children, function (c) { c.classList.remove("reveal"); });
    }
    fetch(API_BASE + "/testimonials.php")
      .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
      .then(function (d) { renderTestimonials(d && d.ok && d.testimonials && d.testimonials.length ? d.testimonials : FALLBACK_TESTIMONIALS); })
      .catch(function () { renderTestimonials(FALLBACK_TESTIMONIALS); });
  }

  /* ---------- Generic AJAX form handler ---------- */
  // Any <form data-endpoint="api/xxx.php"> gets validated + posted as JSON.
  document.querySelectorAll("form[data-endpoint]").forEach(function (form) {
    var statusEl = form.querySelector(".form-status");
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();

      // client-side validation
      var valid = true;
      form.querySelectorAll("[required]").forEach(function (input) {
        var field = input.closest(".field");
        var ok = !!input.value.trim();
        if (ok && input.type === "email") ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
        if (ok && input.getAttribute("data-type") === "phone") ok = /^[+()\d\s-]{8,18}$/.test(input.value);
        if (field) field.classList.toggle("invalid", !ok);
        if (!ok) valid = false;
      });
      if (!valid) return;

      var btn = form.querySelector('button[type="submit"]');
      var btnText = btn ? btn.textContent : "";
      if (btn) { btn.disabled = true; btn.textContent = "Sending…"; }

      var payload = {};
      new FormData(form).forEach(function (v, k) { payload[k] = v; });

      fetch(form.getAttribute("data-endpoint"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json().catch(function () { throw new Error("bad response"); }); })
        .then(function (data) {
          if (!data.ok) throw new Error(data.error || "Submission failed");
          form.reset();
          showStatus(statusEl, "ok", data.message || "Thank you — your enquiry has been received. Our team will be in touch within 1 business day.");
        })
        .catch(function (err) {
          showStatus(statusEl, "err", "Something went wrong (" + err.message + "). Please call us on +61 424 475 767 or email contact@jsdconstruction.com.au.");
        })
        .finally(function () {
          if (btn) { btn.disabled = false; btn.textContent = btnText; }
        });
    });
  });

  function showStatus(el, kind, msg) {
    if (!el) { alert(msg); return; }
    el.className = "form-status " + kind;
    el.textContent = msg;
    el.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  /* ---------- Instant build-cost estimator ---------- */
  // Indicative 2026 SEQ build-rate bands (AUD per m²) by build type & spec.
  var RATES = {
    lowset: { standard: [2400, 2900], premium: [3000, 3800], luxury: [3900, 5200] },
    highset: { standard: [2700, 3300], premium: [3400, 4200], luxury: [4300, 5800] },
    split: { standard: [2900, 3500], premium: [3600, 4500], luxury: [4600, 6200] },
    commercial: { standard: [2200, 2800], premium: [2900, 3600], luxury: [3700, 5000] }
  };
  var SLOPE_LOADING = { flat: 1.0, gentle: 1.06, steep: 1.15 };

  var estForm = document.querySelector("[data-estimator]");
  if (estForm) {
    var out = document.querySelector("[data-estimate-output]");
    function fmt(n) { return "$" + (Math.round(n / 1000) * 1000).toLocaleString("en-AU"); }
    function recalc() {
      var type = estForm.querySelector('[name="build_type"]').value;
      var spec = estForm.querySelector('[name="spec_level"]').value;
      var area = parseFloat(estForm.querySelector('[name="floor_area"]').value) || 0;
      var slope = estForm.querySelector('[name="slope"]').value;
      if (!RATES[type] || !RATES[type][spec] || area < 50) {
        out.textContent = "—";
        return;
      }
      var band = RATES[type][spec];
      var k = SLOPE_LOADING[slope] || 1;
      var lo = band[0] * area * k, hi = band[1] * area * k;
      out.innerHTML = '<span class="gold-text">' + fmt(lo) + " – " + fmt(hi) + "</span>";
      var hidden = estForm.querySelector('[name="estimate_range"]');
      if (hidden) hidden.value = fmt(lo) + " - " + fmt(hi);
    }
    estForm.querySelectorAll("select, input").forEach(function (el) {
      el.addEventListener("input", recalc);
      el.addEventListener("change", recalc);
    });
    recalc();
  }

  /* ---------- Footer year ---------- */
  var yr = document.querySelector("[data-year]");
  if (yr) yr.textContent = new Date().getFullYear();
})();
