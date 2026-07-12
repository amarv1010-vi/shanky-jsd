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

  /* ---------- Projects gallery ---------- */
  // Static fallback so the gallery renders even before the database
  // has projects (or when hosted without PHP, e.g. GitHub preview).
  var FALLBACK_PROJECTS = [
    { title: "Rochedale High-Set Residence", category: "highset", location: "Rochedale, QLD", image: "assets/img/projects/proj-highset-01-01.svg", description: "Elevated family home maximising airflow and under-house living on a sloping block." },
    { title: "Springwood Low-Set Home", category: "lowset", location: "Springwood, QLD", image: "assets/img/projects/proj-lowset-02-01.svg", description: "Open-plan single-level build with strong street presence on a flat allotment." },
    { title: "Eight Mile Plains Split-Level", category: "split", location: "Eight Mile Plains, QLD", image: "assets/img/projects/proj-split-03-01.svg", description: "Split-level design following the natural terrain with tiered outdoor living." },
    { title: "Underwood Retail Development", category: "commercial", location: "Underwood, QLD", image: "assets/img/projects/proj-commercial-04-01.svg", description: "Low-rise retail and mixed-use space delivered end-to-end under QLD low-rise licence." },
    { title: "Calamvale High-Set Build", category: "highset", location: "Calamvale, QLD", image: "assets/img/projects/proj-highset-01-02.svg", description: "Contemporary high-set with premium timber framing and energy-efficient design." },
    { title: "Ipswich Commercial Fit-Out", category: "commercial", location: "Ipswich, QLD", image: "assets/img/projects/proj-commercial-04-02.svg", description: "Low-rise commercial project with full in-house project management." }
  ];

  var CATEGORY_LABELS = { highset: "High-Set", lowset: "Low-Set", split: "Split-Level", commercial: "Commercial" };

  var galleryImages = []; // flat list for the lightbox (built during render)

  function escAttr(s) { return String(s || "").replace(/"/g, "&quot;"); }

  function renderProjects(list, gridEl) {
    gridEl.innerHTML = "";
    galleryImages = list.slice();
    if (!list.length) {
      gridEl.innerHTML = '<p class="lede">No projects in this category yet — check back soon.</p>';
      return;
    }
    list.forEach(function (p, i) {
      var card = document.createElement("article");
      card.className = "project-card reveal";
      card.setAttribute("data-category", p.category);
      var meta = (CATEGORY_LABELS[p.category] || p.category) + (p.location ? " · " + p.location : "");
      card.innerHTML =
        '<button class="project-media" data-lightbox="' + i + '" aria-label="View ' + escAttr(p.title) + '">' +
        '<img loading="lazy" src="' + p.image + '" alt="' + escAttr(p.title) + '">' +
        '<span class="project-zoom" aria-hidden="true">⤢</span>' +
        "</button>" +
        '<div class="project-info">' +
        '<span class="project-tag">' + meta + "</span>" +
        "<h3>" + p.title + "</h3>" +
        (p.description ? "<p>" + p.description + "</p>" : "") +
        "</div>";
      gridEl.appendChild(card);
    });
    // wire lightbox
    gridEl.querySelectorAll("[data-lightbox]").forEach(function (btn) {
      btn.addEventListener("click", function () { openLightbox(parseInt(btn.getAttribute("data-lightbox"), 10)); });
    });
    if (gsapReady) {
      gsap.fromTo(gridEl.children, { opacity: 0, y: 26 }, { opacity: 1, y: 0, duration: 0.6, ease: "power2.out", stagger: 0.06 });
    } else {
      Array.prototype.forEach.call(gridEl.children, function (c) { c.classList.remove("reveal"); });
    }
  }

  /* ---------- Lightbox (premium full-screen viewer) ---------- */
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
  function openLightbox(i) {
    ensureLightbox();
    lbIndex = i;
    showLightbox();
    lb.classList.add("open");
    document.body.style.overflow = "hidden";
  }
  function showLightbox() {
    var p = galleryImages[lbIndex];
    if (!p) return;
    lbImg.src = p.image;
    lbImg.alt = p.title || "";
    lbCap.textContent = (p.title || "") + (p.location ? " — " + p.location : "");
  }
  function stepLightbox(d) {
    lbIndex = (lbIndex + d + galleryImages.length) % galleryImages.length;
    showLightbox();
  }
  function closeLightbox() {
    if (lb) lb.classList.remove("open");
    document.body.style.overflow = "";
  }

  var projectGrid = document.querySelector("[data-projects-grid]");
  if (projectGrid) {
    var allProjects = FALLBACK_PROJECTS;
    var usingRealPhotos = false;
    var limit = parseInt(projectGrid.getAttribute("data-limit") || "0", 10);

    function applyFilter(cat) {
      var list = cat === "all" ? allProjects : allProjects.filter(function (p) { return p.category === cat; });
      if (limit) list = list.slice(0, limit);
      renderProjects(list, projectGrid);
    }

    function refreshFilterButtons() {
      // hide category buttons that have no photos so the bar always looks intentional
      var present = {};
      allProjects.forEach(function (p) { present[p.category] = true; });
      document.querySelectorAll(".filter-btn").forEach(function (btn) {
        var f = btn.getAttribute("data-filter");
        btn.style.display = (f === "all" || present[f]) ? "" : "none";
      });
    }

    // Merge the filesystem gallery (drop-a-folder) with any admin/DB projects.
    Promise.all([
      fetch(API_BASE + "/gallery.php").then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; }),
      fetch(API_BASE + "/projects.php").then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; })
    ]).then(function (res) {
      var gallery = (res[0] && res[0].ok && res[0].projects) ? res[0].projects : [];
      var db = (res[1] && res[1].ok && res[1].projects) ? res[1].projects : [];
      // Real photos dropped in the gallery folder win outright (no demo mixing).
      // Otherwise use admin/DB projects; otherwise the built-in placeholders.
      if (gallery.length) { allProjects = gallery; usingRealPhotos = true; }
      else if (db.length) { allProjects = db; usingRealPhotos = true; }
      refreshFilterButtons();
      applyFilter("all");
    });

    document.querySelectorAll(".filter-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        document.querySelectorAll(".filter-btn").forEach(function (b) { b.classList.remove("active"); });
        btn.classList.add("active");
        applyFilter(btn.getAttribute("data-filter"));
      });
    });
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
