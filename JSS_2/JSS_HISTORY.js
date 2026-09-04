// File: JSS_2/JSS_HISTORY.js
// Proyek: 21: Ruang Lilin
// Fungsi: (1) Riwayat Pertandingan Online di dalam profil pemain -- lawan
// siapa, menang/kalah, dapat/kehilangan berapa rating & rank points per
// pertandingan. (2) Banner +/- Rating & Rank Points yang muncul otomatis
// begitu layar akhir pertandingan ONLINE tampil (tidak muncul di kampanye).
//
// Sengaja dipisah dari JSS_REPORTS.js karena beda topik (statistik/riwayat
// pertandingan, bukan pelaporan bug/pemain), walau keduanya memakai pola
// yang sama (IIFE mandiri, tidak bergantung isi JSS_MULTIPLAYER.js selain
// beberapa variabel global yang memang sudah diekspos: window.mpUser,
// window.mpRoom, window.onlineMode).
(function () {
  "use strict";

  var scriptEl = document.currentScript;
  var baseUrl = ((scriptEl && scriptEl.src) || "").replace(/JSS_2\/JSS_HISTORY\.js(\?.*)?$/, "") || window.location.pathname.replace(/[^\/]+$/, "") || "/";
  var apiUrl = baseUrl + (baseUrl.endsWith("/") ? "" : "/") + "api/";

  function $(id) { return document.getElementById(id); }
  function esc(str) {
    var d = document.createElement("div");
    d.textContent = str === null || str === undefined ? "" : String(str);
    return d.innerHTML;
  }
  function bindClick(id, handler) {
    var el = $(id);
    if (el) el.addEventListener("click", handler);
  }

  function apiCall(endpointWithMaybeQuery, params) {
    var url = apiUrl + endpointWithMaybeQuery;
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
        try { return JSON.parse(txt); }
        catch (e) { return { ok: false, error: "Server merespons tidak valid (status " + res.status + ")." }; }
      });
    }).catch(function () {
      return { ok: false, error: "Tidak bisa terhubung ke server." };
    });
  }

  function formatDate(s) {
    if (!s) return "";
    var iso = s.indexOf("T") === -1 ? s.replace(" ", "T") : s;
    var d = new Date(iso);
    if (isNaN(d.getTime())) return s;
    var months = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
    return d.getDate() + " " + months[d.getMonth()] + " " + d.getFullYear() + ", " +
      String(d.getHours()).padStart(2, "0") + ":" + String(d.getMinutes()).padStart(2, "0");
  }

  function deltaChip(value, unitLabel) {
    if (value === null || value === undefined) return "";
    var cls = value > 0 ? "gain" : value < 0 ? "loss" : "neutral";
    var sign = value > 0 ? "+" : "";
    return '<div class="match-history-delta-item ' + cls + '">' + sign + value + " " + unitLabel + "</div>";
  }

  // ================================================================
  // Riwayat Pertandingan Online (di dalam overlay-profile)
  // ================================================================
  var HISTORY_PAGE_SIZE = 15;
  var historyOffset = 0;
  var historyLoading = false;

  // Aktifkan/nonaktifkan (BUKAN sembunyikan) tombol "Muat Lebih Banyak" &
  // "Muat Lebih Sedikit" sesuai status saat ini. Kedua tombol SEKARANG
  // selalu tampil di tempat yang sama -- begitu tidak relevan lagi (tidak
  // ada lagi yang bisa dimuat, atau yang tampil masih 1 halaman awal),
  // tombolnya cuma dibuat non-aktif lewat atribut "disabled" bawaan
  // browser (otomatis tidak bisa diklik/di-tap) sekaligus jadi transparan
  // lewat aturan ".btn:disabled{opacity:.35;cursor:not-allowed}" yang
  // sudah ada di CSS_1/core_ui.css -- jadi tidak perlu CSS baru sama
  // sekali di sini. "Lebih Sedikit" cuma aktif kalau yang sudah dimuat
  // lebih banyak dari 1 halaman awal (HISTORY_PAGE_SIZE).
  // [v0.9.1.0] Sebelumnya dua tombol ini classList.toggle("hidden",...) --
  // hilang total & bikin tombol yg tersisa "meloncat". Jangan kembalikan ke
  // pola hidden itu; lihat PENJELASAN_TEKNIS_PERUBAHAN_TEMAN_CHAT_HISTORY.md
  // bagian 3 utk alasan lengkapnya.
  function setHistoryButtonEnabled(btn, enabled) {
    if (!btn) return;
    btn.classList.remove("hidden");
    btn.disabled = !enabled;
  }

  function updateHistoryActionButtons(hasMore) {
    setHistoryButtonEnabled($("btn-profile-history-more"), !!hasMore);
    setHistoryButtonEnabled($("btn-profile-history-less"), historyOffset > HISTORY_PAGE_SIZE);
  }

  // [PERINGKAT VS LATIHAN] V.0.9.1.0 -- `m.ranked` datang dari api/match_history.php
  // (kolom match_history.ranked, lihat api/game_action.php utk logika penentuannya:
  // publik/Cari Lawan = ranked, ruangan kode privat = latihan). Baris lama (sebelum
  // kolom ini ada) selalu ranked=true (default DB 1), jadi tetap tampil "Peringkat"
  // seperti perilaku aslinya -- tidak ada riwayat lama yang tiba-tiba berubah label.
  function modeTag(ranked) {
    return ranked
      ? '<span class="match-history-mode ranked">Peringkat</span>'
      : '<span class="match-history-mode practice">Latihan</span>';
  }

  function renderMatchRow(m) {
    var wonClass = m.won ? "won" : "lost";
    var badgeLetter = m.won ? "M" : "K";
    var deltas = deltaChip(m.my_rating_change, "Rating") + deltaChip(m.my_rank_points_change, "Poin");
    var metaBits = [formatDate(m.played_at)];
    if (m.room_code) metaBits.push(esc(m.room_code));
    var emptyDelta = m.ranked
      ? '<span class="match-history-delta-item neutral">-</span>'
      : '<span class="match-history-delta-item neutral">Latihan</span>';
    return '<div class="match-history-row ' + wonClass + '">' +
      '<div class="match-history-badge ' + wonClass + '">' + badgeLetter + '</div>' +
      '<div class="match-history-info">' +
      '<div class="match-history-opponent">vs ' + esc(m.opponent_username || "?") + modeTag(m.ranked) + '</div>' +
      '<div class="match-history-meta">' + metaBits.join(" &middot; ") + '</div>' +
      '</div>' +
      '<div class="match-history-deltas">' + (deltas || emptyDelta) + '</div>' +
      '</div>';
  }

  function loadMatchHistory(reset) {
    var listEl = $("profile-history-list");
    var moreBtn = $("btn-profile-history-more");
    var lessBtn = $("btn-profile-history-less");
    if (!listEl) return;
    if (reset) {
      historyOffset = 0;
      listEl.innerHTML = '<p class="admin-loading-text">Memuat riwayat...</p>';
      setHistoryButtonEnabled(moreBtn, false);
      setHistoryButtonEnabled(lessBtn, false);
    }
    if (historyLoading) return;
    historyLoading = true;
    if (moreBtn) { moreBtn.disabled = true; moreBtn.textContent = "Memuat..."; }
    apiCall("match_history.php?action=list&limit=" + HISTORY_PAGE_SIZE + "&offset=" + historyOffset).then(function (res) {
      historyLoading = false;
      if (moreBtn) { moreBtn.disabled = false; moreBtn.textContent = "Muat Lebih Banyak"; }
      if (!res.ok) {
        listEl.innerHTML = '<p class="match-history-empty">Gagal memuat riwayat pertandingan.</p>';
        return;
      }
      if (reset) listEl.innerHTML = "";
      if (!res.matches || !res.matches.length) {
        if (reset) listEl.innerHTML = '<p class="match-history-empty">Belum ada pertandingan online yang tercatat.</p>';
      } else {
        listEl.insertAdjacentHTML("beforeend", res.matches.map(renderMatchRow).join(""));
        historyOffset += res.matches.length;
      }
      updateHistoryActionButtons(!!res.has_more);
    });
  }

  // "Muat Lebih Sedikit": lipat kembali daftar ke halaman pertama saja
  // (HISTORY_PAGE_SIZE baris) tanpa perlu fetch ulang ke server -- baris
  // yang sudah pernah dimuat cukup dibuang dari DOM. Karena baris yang baru
  // dibuang itu tadinya ADA, "Muat Lebih Banyak" pasti relevan lagi
  // sesudahnya, jadi langsung ditampilkan lagi tanpa perlu tanya server.
  function collapseMatchHistory() {
    if (historyLoading) return;
    var listEl = $("profile-history-list");
    if (!listEl) return;
    var rows = listEl.querySelectorAll(".match-history-row");
    for (var i = rows.length - 1; i >= HISTORY_PAGE_SIZE; i--) {
      rows[i].parentNode.removeChild(rows[i]);
    }
    historyOffset = Math.min(historyOffset, HISTORY_PAGE_SIZE);
    updateHistoryActionButtons(true);
    // Scroll area riwayat balik ke atas supaya user langsung lihat baris
    // pertama, bukan tertinggal di posisi scroll lama yang kontennya
    // sudah dibuang.
    var scrollHost = listEl.closest(".profile-content") || listEl.parentElement;
    if (scrollHost) scrollHost.scrollTop = 0;
  }

  // ================================================================
  // Banner +/- Rating & Rank Points di layar akhir pertandingan ONLINE.
  // Dideteksi lewat polling ringan terhadap layar mana yang aktif (sama
  // seperti pendekatan di JSS_REPORTS.js) karena tidak ada event/callback
  // yang diekspos saat state pertandingan berubah jadi "selesai".
  // ================================================================
  var lastScreenId = null;

  function hideEndRatingDelta() {
    var el = $("end-rating-delta");
    if (el) { el.classList.add("hidden"); el.innerHTML = ""; }
    var note = $("end-practice-note");
    if (note) { note.classList.add("hidden"); note.innerHTML = ""; }
  }

  function renderEndRatingDelta(m) {
    var el = $("end-rating-delta");
    var note = $("end-practice-note");
    if (!el) return;
    // [PERINGKAT VS LATIHAN] V.0.9.1.0 -- pertandingan latihan (ruangan privat)
    // sengaja tidak pernah punya perubahan rating/poin (lihat api/game_action.php),
    // jadi banner delta di bawah SELALU kosong untuk match ini -- sebelumnya itu
    // membuat banner cuma diam-diam hilang tanpa penjelasan (lihat catatan lama
    // "kedua nilai null"). Sekarang dijelaskan lewat #end-practice-note supaya
    // pemain tidak bingung kenapa tidak dapat rating padahal baru menang.
    if (m.ranked === false) {
      el.classList.add("hidden");
      el.innerHTML = "";
      if (note) {
        note.innerHTML = "Pertandingan latihan (ruangan kode privat) &mdash; tidak memengaruhi Rating/Rank Points. Main di Lobi Publik atau lewat Cari Lawan untuk naik peringkat.";
        note.classList.remove("hidden");
      }
      return;
    }
    if (note) { note.classList.add("hidden"); note.innerHTML = ""; }
    var html = deltaWithLabel(m.my_rating_change, "Rating") + deltaWithLabel(m.my_rank_points_change, "Rank Points");
    if (!html) return; // kedua nilai null (mis. lawan sudah keluar sebelum rating sempat dihitung)
    el.innerHTML = html;
    el.classList.remove("hidden");
  }

  function deltaWithLabel(value, label) {
    if (value === null || value === undefined) return "";
    var cls = value >= 0 ? "gain" : "loss";
    var sign = value > 0 ? "+" : "";
    return '<div class="end-delta-item ' + cls + '"><span class="end-delta-label">' + label + '</span><span class="end-delta-value">' + sign + value + '</span></div>';
  }

  function handleEndScreenShown() {
    hideEndRatingDelta();
    // Layar akhir kampanye (bukan online) tidak punya data rating/poin untuk ditampilkan.
    if (!window.onlineMode || !window.mpRoom || !window.mpRoom.code) return;
    apiCall("match_history.php?action=match_result&room_code=" + encodeURIComponent(window.mpRoom.code)).then(function (res) {
      if (!res.ok || !res.match) return;
      renderEndRatingDelta(res.match);
    });
  }

  function pollScreenForMatchEnd() {
    var activeScreen = document.querySelector(".screen.active");
    var screenId = activeScreen ? activeScreen.id : null;
    if (screenId === lastScreenId) return;
    lastScreenId = screenId;
    if (screenId === "screen-end") handleEndScreenShown();
    else hideEndRatingDelta();
  }

  // ================================================================
  // Wiring
  // ================================================================
  function initHistoryUI() {
    // Setiap kali profil dibuka, muat ulang riwayat dari awal (paginasi direset).
    // Ini TIDAK mengganti handler klik yang sudah ada di JSS_MULTIPLAYER.js untuk
    // tombol yang sama (mengisi profile-head/profile-stats) -- keduanya berjalan
    // berdampingan lewat addEventListener terpisah.
    bindClick("btn-online-profile", function () { loadMatchHistory(true); });
    bindClick("btn-profile-history-more", function () { loadMatchHistory(false); });
    bindClick("btn-profile-history-less", collapseMatchHistory);

    lastScreenId = document.querySelector(".screen.active") ? document.querySelector(".screen.active").id : null;
    setInterval(pollScreenForMatchEnd, 400);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHistoryUI);
  } else {
    initHistoryUI();
  }
})();
