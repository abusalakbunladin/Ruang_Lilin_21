/* File: JSS_2/JSS_LOBBY_ENHANCE.js
   Proyek: 21: Ruang Lilin
   Versi: V.0.9.1.0
   Fungsi:
   (1) Panel "Duel" di layar tunggu (overlay-quickmatch) -- menampilkan kamu
       & lawan berdampingan (avatar, nama, tingkatan) + lencana
       Peringkat/Latihan, menggantikan spinner polos yang berdiri sendiri.
   (2) Tooltip deskripsi kustom + lencana info pada tiap ubin kartu trump di
       Pengaturan Ruangan (overlay-room-settings) -- hover di desktop
       langsung menampilkan deskripsi, tap lencana "i" utk perangkat sentuh.
   (3) Penjelasan singkat Peringkat vs Latihan di layar Buat/Gabung Ruangan.
   (4) Memperlebar ketiga overlay di atas (kelas + !important, lihat
       CSS_2/lobby_enhance.css) supaya lebih lapang sesuai permintaan
       "lobby lebih luas".

   POLA: IIFE mandiri, SAMA PERSIS dgn JSS_HISTORY.js/JSS_REPORTS.js --
   berkas ini TIDAK PERNAH mengedit isi JSS_2/JSS_MULTIPLAYER.js. Cuma
   bergantung pada variabel global yang memang sudah diekspos
   (window.mpUser, window.mpRoom), fungsi global dari JSSSYSTEM.js
   (iconSvg/window.ICONS -- berkas itu TIDAK dibungkus IIFE jadi memang
   sengaja bisa dipakai lintas berkas), elemen DOM yang sudah ada, & endpoint
   API yang sudah ada (room.php action=get/card_pool). Alasan dipisah:
   JSS_MULTIPLAYER.js padat & jadi jantung alur pencocokan lawan yang
   riwayat bug-nya sudah beberapa kali butuh perbaikan hati-hati (lihat
   CHANGELOG_LENGKAP.md) -- menambah fitur lewat berkas terpisah yang cuma
   MENGAMATI (MutationObserver/polling baca-saja) & MENYUNTIK elemen baru
   berarti nol risiko ke alur pencocokan lawan yang sudah teruji, dan fitur
   ini bisa dicabut/dimatikan kapan pun cukup dgn menghapus 1 baris <script>
   di index.html tanpa menyentuh berkas lain sama sekali. */

(function () {
  "use strict";

  /* ---- Base URL & pemanggil API -- pola identik dgn helper b() milik
     JSS_MULTIPLAYER.js supaya tetap benar dipanggil dari subfolder apa pun
     proyek ini di-deploy. ---- */
  var scriptEl = document.currentScript;
  var gameBase = ((scriptEl && scriptEl.src) || "").replace(/JSS_2\/JSS_LOBBY_ENHANCE\.js(\?.*)?$/, "") || window.location.pathname.replace(/[^\/]+$/, "") || "/";
  var apiBase = gameBase + (gameBase.endsWith("/") ? "" : "/") + "api/";

  function $(id) { return document.getElementById(id); }

  function esc(str) {
    var d = document.createElement("div");
    d.textContent = str === null || str === undefined ? "" : String(str);
    return d.innerHTML;
  }

  function initials(name) {
    var c = (name || "?").trim().charAt(0);
    return c ? c.toUpperCase() : "?";
  }

  function apiCall(endpoint, params) {
    var url = apiBase + endpoint;
    var opts = { method: params ? "POST" : "GET", credentials: "same-origin", cache: "no-store" };
    if (params) {
      var fd = new FormData();
      for (var k in params) {
        if (Object.prototype.hasOwnProperty.call(params, k) && params[k] !== undefined && params[k] !== null) fd.append(k, params[k]);
      }
      opts.body = fd;
    } else {
      url += (url.indexOf("?") !== -1 ? "&" : "?") + "_=" + Date.now();
    }
    return fetch(url, opts).then(function (res) {
      return res.text().then(function (txt) {
        try { return JSON.parse(txt); } catch (e) { return { ok: false }; }
      });
    }).catch(function () { return { ok: false }; });
  }

  // Ikon diambil dari window.ICONS (ASSETS_GAMBAR/sprite_icons.js) lewat
  // iconSvg() global milik JSSSYSTEM.js supaya gaya garis SVG-nya konsisten
  // dgn ikon lain di seluruh game -- dgn fallback string kosong kalau utk
  // alasan apa pun berkas itu belum/tidak termuat (lihat urutan <script>
  // defer di index.html: berkas ini sengaja dimuat PALING TERAKHIR).
  function safeIcon(name) {
    try {
      if (typeof iconSvg === "function" && window.ICONS && window.ICONS[name]) return iconSvg(name);
    } catch (e) {}
    return "";
  }

  /* ================================================================
     BAGIAN 1 -- Tooltip deskripsi kartu trump di Pengaturan Ruangan
     ================================================================
     #rs-card-list punya overflow-y:auto (lihat matchmaking_ui.css), jadi
     tooltip position:absolute biasa (seperti .chip-tooltip yang sudah ada
     di meja permainan) akan KEPOTONG kalau ubin-nya dekat batas atas/bawah
     area scroll. Solusinya: SATU elemen tooltip "portal" position:fixed,
     posisinya dihitung lewat getBoundingClientRect() tiap kali ditampilkan
     -- jadi tidak pernah terpengaruh scroll container manapun. Katalog
     kartu (nama+deskripsi) diambil sendiri lewat room.php?action=card_pool
     -- endpoint yang SAMA dipakai mpOpenRoomSettings(), aman dipanggil lagi
     krn read-only & datanya statis (bukan spesifik 1 ruangan). */
  var cardCatalogById = null;
  var cardCatalogPromise = null;
  var tooltipEl = null;
  var tooltipOwner = null; // elemen (tile atau badge) yg sedang "memegang" tooltip terbuka via klik/tap

  function ensureTooltipEl() {
    if (tooltipEl) return tooltipEl;
    tooltipEl = document.createElement("div");
    tooltipEl.className = "rl-tooltip";
    tooltipEl.setAttribute("role", "tooltip");
    document.body.appendChild(tooltipEl);
    return tooltipEl;
  }

  function hideTooltip() {
    tooltipOwner = null;
    if (tooltipEl) tooltipEl.classList.remove("show");
  }

  function positionTooltip(el, anchorRect) {
    var vw = window.innerWidth, vh = window.innerHeight;
    var tw = el.offsetWidth, th = el.offsetHeight;
    var left = anchorRect.left + anchorRect.width / 2 - tw / 2;
    left = Math.max(8, Math.min(left, vw - tw - 8));
    var top = anchorRect.top - th - 10;
    if (top < 8) top = Math.min(anchorRect.bottom + 10, vh - th - 8);
    el.style.left = left + "px";
    el.style.top = top + "px";
  }

  function showTooltipFor(anchorEl, info) {
    var el = ensureTooltipEl();
    el.className = "rl-tooltip" + (info.online ? " online" : "");
    var html = '<span class="rl-tooltip-name">' + esc(info.name) + "</span>" + esc(info.desc || "");
    if (info.online) html += '<span class="rl-tooltip-note">Kartu khusus Mode Online.</span>';
    el.innerHTML = html;
    // Ditampilkan dulu (opacity via kelas .show, tapi tetap perlu ukuran
    // nyata utk dihitung) baru diposisikan, supaya offsetWidth/Height valid.
    el.classList.add("show");
    positionTooltip(el, anchorEl.getBoundingClientRect());
  }

  function fetchCardCatalog() {
    if (cardCatalogById) return Promise.resolve(cardCatalogById);
    if (cardCatalogPromise) return cardCatalogPromise;
    cardCatalogPromise = apiCall("room.php?action=card_pool").then(function (res) {
      var map = {};
      if (res && res.ok && Array.isArray(res.cards)) {
        res.cards.forEach(function (c) { map[c.id] = c; });
      }
      cardCatalogById = map;
      return map;
    });
    return cardCatalogPromise;
  }

  function enhanceCardTiles() {
    var box = $("rs-card-list");
    if (!box) return;
    var tiles = box.querySelectorAll(".rs-card-visual:not([data-rl-enhanced])");
    if (!tiles.length) return;
    fetchCardCatalog().then(function (catalog) {
      tiles.forEach(function (tile) {
        tile.setAttribute("data-rl-enhanced", "1");
        var id = tile.getAttribute("data-card-id");
        var info = catalog[id];
        if (!info) return;
        // mpCardVisualTile() juga mengisi title="" bawaan (tooltip native
        // browser) -- dihapus di sini (bukan di sumbernya) supaya tidak
        // tampil berbarengan/dobel dengan tooltip kustom kita di bawah.
        tile.removeAttribute("title");

        var badge = document.createElement("span");
        badge.className = "rs-info-badge";
        badge.textContent = "i";
        badge.setAttribute("role", "button");
        badge.setAttribute("tabindex", "0");
        badge.setAttribute("aria-label", "Lihat deskripsi kartu " + info.name);
        tile.appendChild(badge);

        // Hover (mouse) di mana pun pada ubin -- langsung tampil, tidak
        // mengganggu klik toggle aktif/nonaktif milik ubin (event beda).
        tile.addEventListener("mouseenter", function () {
          if (!tooltipOwner) showTooltipFor(tile, info);
        });
        tile.addEventListener("mouseleave", function () {
          if (!tooltipOwner) hideTooltip();
        });

        // Tap/klik lencana "i" -- dipakai perangkat sentuh yg tidak punya
        // hover. stopPropagation supaya TIDAK ikut men-toggle aktif/nonaktif
        // kartu (klik ubin di luar lencana ini tetap toggle seperti biasa).
        // Pola buka/tutup persis sama dgn .trump-chip.tooltip-open yang
        // sudah ada di meja permainan (lihat JSSSYSTEM.js renderTableTrumps)
        // supaya interaksinya konsisten di seluruh game.
        badge.addEventListener("click", function (e) {
          e.stopPropagation();
          e.preventDefault();
          var wasOpenByMe = tooltipOwner === badge;
          hideTooltip();
          if (!wasOpenByMe) {
            showTooltipFor(tile, info);
            tooltipOwner = badge;
          }
        });
        badge.addEventListener("keydown", function (e) {
          if (e.key === "Enter" || e.key === " " || e.key === "Spacebar") {
            e.preventDefault();
            badge.click();
          }
        });
      });
    });
  }

  function initCardListObserver() {
    var box = $("rs-card-list");
    if (!box) return;
    enhanceCardTiles(); // jaga-jaga kalau sudah terisi lebih dulu
    var mo = new MutationObserver(function () { enhanceCardTiles(); });
    mo.observe(box, { childList: true });
  }

  // Tutup tooltip yg dibuka via tap saat tap di luar / scroll list / resize --
  // event 'click' di document TIDAK akan kebagian klik pada lencana karena
  // stopPropagation() di atas (persis pola .trump-chip yg sudah ada).
  document.addEventListener("click", hideTooltip);
  window.addEventListener("resize", hideTooltip);
  document.addEventListener("scroll", function (e) {
    var t = e.target;
    if (t && t.nodeType === 1 && (t.id === "rs-card-list" || (t.closest && t.closest("#rs-card-list")))) hideTooltip();
  }, true);

  /* ================================================================
     BAGIAN 2 -- Perlebar overlay Buat/Gabung Ruangan, Layar Tunggu, &
     Pengaturan Ruangan (kelas + !important di lobby_enhance.css, supaya
     override style inline max-width bawaan tanpa menyentuh index.html
     lebih dari perlu). Dipanggil sekali saat init -- overlay ini statis di
     DOM (cuma disembunyikan lewat kelas "hidden", tidak dibuat ulang),
     jadi cukup ditandai sekali.
     ================================================================ */
  function widenOverlays() {
    var lobbyCard = document.querySelector("#overlay-lobby .overlay-card");
    if (lobbyCard) lobbyCard.classList.add("rl-lobby-wide");
    var waitCard = document.querySelector("#overlay-quickmatch .overlay-card");
    if (waitCard) waitCard.classList.add("rl-wait-wide");
    var settingsCard = document.querySelector("#overlay-room-settings .overlay-card");
    if (settingsCard) settingsCard.classList.add("rl-settings-wide");
  }

  /* ================================================================
     BAGIAN 3 -- Penjelasan Peringkat vs Latihan di layar Buat/Gabung
     Ruangan, supaya host paham konsekuensinya SEBELUM membuat ruangan.
     ================================================================ */
  function injectLobbyIntro() {
    var main = $("lobby-main");
    if (!main || $("rl-lobby-intro")) return;
    var intro = document.createElement("div");
    intro.className = "rl-lobby-intro";
    intro.id = "rl-lobby-intro";
    intro.innerHTML = safeIcon("perfect") +
      '<p><b>Publik</b> (Lobi Publik maupun <b>Cari Lawan</b>) selalu memakai peraturan klasik &amp; dihitung <span class="rl-badge ranked">Peringkat</span> ke Rating &amp; Rank Points. <b>Privat</b> (kode) boleh diubah bebas tapi berstatus <span class="rl-badge practice">Latihan</span> &mdash; tetap seru dimainkan, hanya saja tidak memengaruhi peringkat.</p>';
    main.insertBefore(intro, main.firstChild);
  }

  /* ================================================================
     BAGIAN 4 -- Panel Duel di layar tunggu (overlay-quickmatch)
     ================================================================
     Menyuntik markup baru ke dalam .qm-card yang sudah ada (BUKAN menulis
     ulang index.html) lalu mengisinya dari window.mpUser (identitas sendiri
     -- sudah tersedia sinkron, tidak perlu fetch) + polling MANDIRI ke
     room.php?action=get (utk data lawan, yg baru diketahui belakangan).
     Polling ini SENGAJA terpisah total dari poll internal
     mpCheckWaitingRoomOnce() milik JSS_MULTIPLAYER.js (yang jalan tiap 3
     detik & yg benar-benar memindah layar ke screen-game) -- berkas ini
     TIDAK PERNAH memanggil pindah layar sendiri, jadi tidak mungkin
     menyebabkan race condition ganda pada alur pencocokan lawan.
     Konsekuensinya: transisi ke screen-game tetap 100% dikendalikan kode
     asli seperti sebelumnya; panel ini murni kosmetik & paling buruk cuma
     "telat sedetik-dua" menampilkan lawan sebelum layar berpindah -- tidak
     pernah memblokir atau menunda pertandingan dimulai. */
  var duelPanelEl = null;
  var duelPollTimer = null;

  function ensureDuelPanel() {
    if (duelPanelEl && document.body.contains(duelPanelEl)) return duelPanelEl;
    var statusText = $("quickmatch-status-text");
    var card = document.querySelector("#overlay-quickmatch .qm-card");
    if (!card || !statusText) return null;
    var badgeRow = $("rl-duel-badge-row") || document.createElement("div");
    badgeRow.className = "rl-duel-badge-row";
    badgeRow.id = "rl-duel-badge-row";
    var panel = $("rl-duel-panel") || document.createElement("div");
    panel.className = "rl-duel-panel";
    panel.id = "rl-duel-panel";
    panel.innerHTML = '<div class="rl-duel-slot you"></div><div class="rl-duel-vs">VS</div><div class="rl-duel-slot opp"></div>';
    if (!panel.parentNode) card.insertBefore(panel, statusText);
    if (!badgeRow.parentNode) card.insertBefore(badgeRow, panel);
    duelPanelEl = panel;
    return panel;
  }

  function renderYouSlot() {
    var panel = ensureDuelPanel();
    var slot = panel && panel.querySelector(".rl-duel-slot.you");
    if (!slot || !window.mpUser) return;
    slot.innerHTML = '<div class="rl-duel-avatar">' + esc(initials(window.mpUser.username)) + '</div>' +
      '<div class="rl-duel-name">' + esc(window.mpUser.username || "Kamu") + '</div>' +
      '<div class="rl-duel-rank">' + esc(window.mpUser.rank || "") + '</div>' +
      '<div class="rl-duel-tag ready">Sudah di Ruangan</div>';
  }

  function renderOppEmpty(hintText) {
    var panel = ensureDuelPanel();
    var slot = panel && panel.querySelector(".rl-duel-slot.opp");
    if (!slot) return;
    var text = hintText || "Menunggu...";
    // Dilacak lewat data-hint (bukan cuma kelas "rl-empty") supaya render
    // pertama (elemen baru, belum punya kelas apa pun) tidak ke-skip, dan
    // supaya sesi menunggu baru dgn teks petunjuk berbeda (mis. privat ->
    // publik di percobaan berikutnya) tetap ter-update, bukan cuma dianggap
    // "sudah sama" krn kelasnya kebetulan sama-sama "rl-empty".
    if (slot.classList.contains("rl-empty") && slot.getAttribute("data-hint") === text) return;
    slot.className = "rl-duel-slot opp rl-empty";
    slot.setAttribute("data-hint", text);
    slot.removeAttribute("data-name");
    slot.innerHTML = '<div class="rl-duel-avatar">?</div>' +
      '<div class="rl-duel-name">' + esc(text) + '</div>' +
      '<div class="rl-duel-rank">&nbsp;</div>';
  }

  function renderOppFilled(name, rank) {
    var panel = ensureDuelPanel();
    var slot = panel && panel.querySelector(".rl-duel-slot.opp");
    if (!slot) return;
    if (slot.classList.contains("rl-filled") && slot.getAttribute("data-name") === name) return; // sudah tampil, jgn animasi ulang
    slot.className = "rl-duel-slot opp rl-filled";
    slot.setAttribute("data-name", name);
    slot.innerHTML = '<div class="rl-duel-avatar">' + esc(initials(name)) + '</div>' +
      '<div class="rl-duel-name">' + esc(name) + '</div>' +
      '<div class="rl-duel-rank">' + esc(rank || "") + '</div>' +
      '<div class="rl-duel-tag ready">Siap</div>';
  }

  function renderRankedBadge(ranked) {
    var row = $("rl-duel-badge-row");
    if (!row) return;
    row.innerHTML = ranked
      ? '<span class="rl-badge ranked">' + safeIcon("royalDecree") + "Peringkat</span>"
      : '<span class="rl-badge practice">Latihan &mdash; tidak memengaruhi Rating</span>';
  }

  // Tombol salin kode ruangan -- pelengkap kecil yg wajar utk "lobby lebih
  // kreatif": sebelumnya kode cuma teks polos yg harus di-blok-select manual.
  function injectCopyButton() {
    var codeEl = $("quickmatch-room-code");
    if (!codeEl || !codeEl.parentNode || codeEl.parentNode.classList.contains("rl-code-callout")) return;
    var wrap = document.createElement("div");
    wrap.className = "rl-code-callout";
    codeEl.parentNode.insertBefore(wrap, codeEl);
    wrap.appendChild(codeEl);
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "rl-copy-btn";
    btn.title = "Salin kode ruangan";
    btn.setAttribute("aria-label", "Salin kode ruangan");
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>';
    btn.addEventListener("click", function () {
      var code = (codeEl.textContent || "").trim();
      if (!code || /^-+$/.test(code)) return; // placeholder "------" sblm kode ada
      var markCopied = function () {
        btn.classList.add("copied");
        setTimeout(function () { btn.classList.remove("copied"); }, 1400);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(markCopied).catch(function () {});
      } else {
        try {
          var ta = document.createElement("textarea");
          ta.value = code;
          ta.style.position = "fixed";
          ta.style.opacity = "0";
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          document.execCommand("copy");
          document.body.removeChild(ta);
          markCopied();
        } catch (e) {}
      }
    });
    wrap.appendChild(btn);
  }

  function stopDuelPolling() {
    if (duelPollTimer) { clearInterval(duelPollTimer); duelPollTimer = null; }
  }

  function pollOpponentOnce() {
    if (!window.mpRoom || !window.mpRoom.code) return;
    apiCall("room.php?action=get&room_code=" + encodeURIComponent(window.mpRoom.code)).then(function (res) {
      if (!res || !res.ok || !res.room) return;
      var room = res.room;
      renderRankedBadge(!!room.ranked);
      var isHost = !!(window.mpRoom && window.mpRoom.is_host);
      var oppName = isHost ? room.guest_name : room.host_name;
      var oppRank = isHost ? room.guest_rank : room.host_rank;
      if (oppName) renderOppFilled(oppName, oppRank);
    });
  }

  function startDuelPolling() {
    stopDuelPolling();
    if (!ensureDuelPanel()) return;
    renderYouSlot();
    injectCopyButton();
    var vis = window.mpRoom && window.mpRoom.visibility;
    renderOppEmpty(vis === "public" ? "Mencari lawan..." : "Menunggu lawan...");
    renderRankedBadge(vis === "public");
    pollOpponentOnce();
    duelPollTimer = setInterval(pollOpponentOnce, 1200);
  }

  function observeWaitOverlay() {
    var overlay = $("overlay-quickmatch");
    if (!overlay) return;
    var mo = new MutationObserver(function () {
      if (overlay.classList.contains("hidden")) stopDuelPolling();
      else startDuelPolling();
    });
    mo.observe(overlay, { attributes: true, attributeFilter: ["class"] });
    if (!overlay.classList.contains("hidden")) startDuelPolling();
  }

  /* ================================================================
     Wiring -- berkas ini dimuat dgn defer="defer" PALING TERAKHIR di
     index.html (setelah JSSSYSTEM.js, sprite_icons.js, & JSS_MULTIPLAYER.js),
     jadi window.ICONS/iconSvg/window.mpUser/window.mpRoom dijamin sudah ada
     & DOM sudah lengkap ter-parse saat kode di bawah ini jalan -- tapi cek
     readyState tetap dipasang sbg jaring pengaman kalau suatu saat urutan
     <script> berubah.
     ================================================================ */
  function init() {
    widenOverlays();
    injectLobbyIntro();
    initCardListObserver();
    observeWaitOverlay();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
