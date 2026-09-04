// ============================================================================
// File: JSS_2/JSS_PROFILE_UI.js
// Proyek: 21: Ruang Lilin
// Fungsi: Perilaku utk overlay Profil versi baru (sidebar + panel: Daftar
// Teman / Inbox / History / Ubah Password), sesuai sketsa desain yang
// diberikan. Sengaja dipisah dari JSS_MULTIPLAYER.js dan JSS_HISTORY.js:
// file mandiri (IIFE sendiri), tidak bergantung pada variabel internal file
// lain, dan tidak mengubah satu baris pun kode lama. Beberapa fungsi lama
// (window.mpLoadFriends) sengaja DITIMPA di sini -- aman karena semua
// pemanggilnya di JSS_MULTIPLAYER.js memanggil lewat "window.mpLoadFriends()"
// (dicek saat dipanggil, bukan disalin saat didefinisikan), jadi otomatis
// teralihkan ke versi baru tanpa menyentuh file itu.
//
// Panel History tetap memakai render aslinya dari JSS_HISTORY.js (id
// profile-history-list / btn-profile-history-more tidak disentuh di sini) --
// file ini hanya menambahkan mekanisme tab/panel di sekitarnya.
//
// [v0.9.1.0] renderFriendsList() di bawah diubah: avatar + area klik utk
// buka profil teman. Penjelasan lengkap + alasan tiap keputusan ada di
// PENJELASAN_TEKNIS_PERUBAHAN_TEMAN_CHAT_HISTORY.md (root proyek, bagian 1).
// ============================================================================
(function () {
  'use strict';

  var script = document.currentScript;
  var apiUrl = script.src.replace(/JSS_2\/JSS_PROFILE_UI\.js.*$/, '') + 'api/';

  function $(id) { return document.getElementById(id); }

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = str === null || str === undefined ? '' : String(str);
    return d.innerHTML;
  }

  // Sama seperti pola escaping username asli di JSS_MULTIPLAYER.js: hanya
  // meng-escape tanda kutip satu supaya aman disisipkan di dalam onclick="...('...')".
  function jsEsc(str) {
    return String(str === null || str === undefined ? '' : str).replace(/'/g, "\\'");
  }

  async function apiCall(endpoint, params, method) {
    method = method || 'POST';
    try {
      var res;
      if (method === 'GET') {
        var qs = new URLSearchParams(params || {}).toString();
        res = await fetch(apiUrl + endpoint + (qs ? '?' + qs : ''), { credentials: 'same-origin', cache: 'no-store' });
      } else {
        res = await fetch(apiUrl + endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(params || {}),
          credentials: 'same-origin'
        });
      }
      return await res.json();
    } catch (e) {
      return { ok: false, error: 'Gagal terhubung ke server.' };
    }
  }

  function bindClick(id, handler) {
    var el = $(id);
    if (el) el.addEventListener('click', handler);
  }

  // Format tanggal disamakan persis dgn JSS_HISTORY.js ("27 Agu 2026, 01:23")
  // supaya konsisten di seluruh panel Profil.
  function formatDate(dateStr) {
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    var d = new Date((dateStr || '').replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr || '';
    var dd = String(d.getDate()).padStart(2, '0');
    var hh = String(d.getHours()).padStart(2, '0');
    var mi = String(d.getMinutes()).padStart(2, '0');
    return dd + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ', ' + hh + ':' + mi;
  }

  function timeAgoShort(dateStr) {
    var d = new Date((dateStr || '').replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    var diffMin = Math.floor((Date.now() - d.getTime()) / 60000);
    if (diffMin < 1) return 'baru saja';
    if (diffMin < 60) return diffMin + ' mnt lalu';
    var diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return diffHr + ' jam lalu';
    var diffDay = Math.floor(diffHr / 24);
    if (diffDay < 7) return diffDay + ' hari lalu';
    return formatDate(dateStr);
  }

  // ==========================================================================
  // 1) SWITCH PANEL (Daftar Teman / Inbox / History / Ubah Password)
  // ==========================================================================
  function setActivePanel(name, silent) {
    document.querySelectorAll('#overlay-profile .profile-nav-btn[data-profile-panel]').forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-profile-panel') === name);
    });
    document.querySelectorAll('#overlay-profile .profile-panel[data-profile-panel]').forEach(function (panel) {
      panel.classList.toggle('active', panel.getAttribute('data-profile-panel') === name);
    });
    if (silent) return;
    if (name === 'inbox') loadInbox();
    else if (name === 'password') loadPasswordRequests();
    else if (name === 'friends') refreshFriendUnreadDots();
  }

  document.querySelectorAll('#overlay-profile .profile-nav-btn[data-profile-panel]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setActivePanel(btn.getAttribute('data-profile-panel'));
    });
  });

  bindClick('profile-nav-logout', function () {
    if (window.mpLogout) window.mpLogout();
  });

  // ==========================================================================
  // 2) Mode "melihat profil pemain lain" -- ikuti class "hidden" yang sudah
  // dipasang/dilepas kode lama (mpViewFriendProfile / mpOpenProfile) pada
  // #profile-friends-section, tanpa mengubah kode lama itu sendiri.
  // ==========================================================================
  function isOtherProfileMode() {
    var section = $('profile-friends-section');
    return !!(section && section.classList.contains('hidden'));
  }

  function syncOtherProfileMode() {
    var other = isOtherProfileMode();
    var nav = $('profile-nav');
    var shell = document.querySelector('#overlay-profile .profile-shell');
    if (nav) nav.classList.toggle('profile-nav-suppressed', other);
    if (shell) shell.classList.toggle('viewing-other-profile', other);
    if (other) setActivePanel('friends', true);
  }

  (function initFriendsSectionWatcher() {
    var section = $('profile-friends-section');
    if (section && window.MutationObserver) {
      new MutationObserver(syncOtherProfileMode).observe(section, { attributes: true, attributeFilter: ['class'] });
    }
  })();

  // ==========================================================================
  // 3) Buka/tutup overlay profil: reset ke panel Daftar Teman & kelola
  // polling badge ringan (hanya aktif selagi overlay profil terbuka).
  // ==========================================================================
  var badgeTimer = null;
  function startBadgePolling() {
    stopBadgePolling();
    badgeTimer = setInterval(refreshAllBadges, 20000);
  }
  function stopBadgePolling() {
    if (badgeTimer) { clearInterval(badgeTimer); badgeTimer = null; }
  }
  async function refreshAllBadges() {
    if (!window.mpUser) return;
    refreshInboxBadge();
    refreshFriendUnreadDots();
  }

  (function initOverlayWatcher() {
    var overlay = $('overlay-profile');
    if (!overlay || !window.MutationObserver) return;
    new MutationObserver(function () {
      var open = !overlay.classList.contains('hidden');
      if (open) {
        syncOtherProfileMode();
        if (!isOtherProfileMode()) setActivePanel('friends', true);
        refreshAllBadges();
        startBadgePolling();
      } else {
        stopBadgePolling();
        closeInboxDetail();
      }
    }).observe(overlay, { attributes: true, attributeFilter: ['class'] });
  })();

  // Tombol "Profil" di menu online -- selain dipakai JSS_HISTORY.js utk
  // mereset paginasi riwayat, di sini dipakai jg utk memastikan panel
  // default Daftar Teman aktif tiap kali overlay dibuka dari menu utama.
  bindClick('btn-online-profile', function () { setActivePanel('friends', true); });

  // ==========================================================================
  // 4) DAFTAR TEMAN -- override window.mpLoadFriends
  // ==========================================================================
  var lastFriendRows = [];

  window.mpLoadFriends = async function () {
    var list = $('profile-friends-list');
    if (!list || !window.mpUser) return;
    var res = await apiCall('friend.php', { action: 'list' }, 'GET');
    if (!res || !res.ok || !res.friends) {
      list.innerHTML = '<p style="color:var(--ink-dim)">Gagal memuat teman.</p>';
      renderFriendRequests();
      return;
    }
    lastFriendRows = res.friends;
    renderFriendRequests();
    renderFriendsList();
    refreshFriendUnreadDots();
  };

  function otherOf(row) {
    var mine = window.mpUser.id;
    var isFirst = row.user_id_1 == mine;
    return { id: isFirst ? row.user_id_2 : row.user_id_1, username: isFirst ? row.user2 : row.user1 };
  }

  function renderFriendRequests() {
    var incoming = lastFriendRows.filter(function (r) { return r.status === 'pending' && r.requester_id != window.mpUser.id; });
    var outgoing = lastFriendRows.filter(function (r) { return r.status === 'pending' && r.requester_id == window.mpUser.id; });

    ['friend-requests-badge', 'friend-requests-nav-badge'].forEach(function (id) {
      var b = $(id);
      if (!b) return;
      b.textContent = String(incoming.length);
      b.classList.toggle('hidden', incoming.length === 0);
    });

    var box = $('friend-requests-list');
    if (!box) return;
    if (incoming.length === 0 && outgoing.length === 0) {
      box.innerHTML = '<div class="friend-request-empty">Tidak ada permintaan pertemanan saat ini.</div>';
      return;
    }
    var html = '';
    incoming.forEach(function (r) {
      var o = otherOf(r);
      html += '<div class="friend-request-row">' +
        '<div class="friend-request-info"><div class="friend-request-name">' + esc(o.username) + '</div>' +
        '<div class="friend-request-sub">Ingin berteman denganmu</div></div>' +
        '<div class="friend-request-actions">' +
        '<button class="btn" type="button" onclick="window.mpAcceptFriend(' + o.id + ')">Terima</button>' +
        '<button class="btn" type="button" onclick="window.mpRemoveFriend(' + o.id + ')">Tolak</button>' +
        '</div></div>';
    });
    outgoing.forEach(function (r) {
      var o = otherOf(r);
      html += '<div class="friend-request-row">' +
        '<div class="friend-request-info"><div class="friend-request-name">' + esc(o.username) + '</div>' +
        '<div class="friend-request-sub">Menunggu konfirmasi dari mereka</div></div>' +
        '<div class="friend-request-actions">' +
        '<button class="btn" type="button" onclick="window.mpRemoveFriend(' + o.id + ')">Batalkan</button>' +
        '</div></div>';
    });
    box.innerHTML = html;
  }

  // [UBAH v0.9.1.0 -- lihat PENJELASAN_TEKNIS_PERUBAHAN_TEMAN_CHAT_HISTORY.md #1]
  // Tiap baris teman sekarang dibungkus jadi 2 blok bersaudara (BUKAN
  // bersarang) di dalam .friend-row:
  //   1. .friend-info  = avatar (initialOf()) + nama + status "Teman",
  //      SELURUHNYA satu target klik ke window.mpViewFriendProfile(...).
  //      Sengaja jadi saudara (bukan pembungkus) dari .friend-actions di
  //      bawah ini supaya klik tombol Chat/Tantang/Hapus TIDAK pernah ikut
  //      men-trigger buka profil (tidak perlu stopPropagation sama sekali).
  //   2. .friend-actions = tombol Chat/Tantang/Hapus, TIDAK diubah dari
  //      versi sebelumnya.
  // Kalau mau ubah lagi: jangan pindahkan onclick mpViewFriendProfile ke
  // .friend-row (elemen terluar) -- itu bakal bikin klik tombol aksi ikut
  // membuka profil karena tombolnya jadi anak dari elemen yg listen klik.
  function renderFriendsList() {
    var list = $('profile-friends-list');
    if (!list) return;
    var accepted = lastFriendRows.filter(function (r) { return r.status === 'accepted'; });
    var q = ($('friend-search-input') && $('friend-search-input').value || '').trim().toLowerCase();
    var rows = accepted.map(otherOf);
    if (q) rows = rows.filter(function (o) { return o.username.toLowerCase().indexOf(q) !== -1; });
    rows.sort(function (a, b) { return a.username.localeCompare(b.username); });

    if (accepted.length === 0) {
      list.innerHTML = '<p style="color:var(--ink-dim)">Belum ada teman.</p>';
      return;
    }
    if (rows.length === 0) {
      list.innerHTML = '<div class="friend-list-empty">Tidak ada teman dengan nama itu.</div>';
      return;
    }
    list.innerHTML = rows.map(function (o) {
      var name = jsEsc(o.username);
      return '<div class="friend-row" data-uid="' + o.id + '">' +
        '<div class="friend-info" onclick="window.mpViewFriendProfile(' + o.id + ", '" + name + '\')" title="Lihat profil ' + esc(o.username) + '">' +
        '<div class="friend-avatar">' + esc(initialOf(o.username)) + '</div>' +
        '<div class="friend-name-wrap">' +
        '<span class="friend-name">' + esc(o.username) + '</span>' +
        '<span class="friend-status">Teman</span>' +
        '</div>' +
        '</div>' +
        '<div class="friend-actions">' +
        '<button class="btn friend-chat-btn" onclick="window.mpOpenFriendChat(' + o.id + ", '" + name + '\')">Chat<span class="friend-chat-badge hidden" data-badge-for="' + o.id + '"></span></button>' +
        '<button class="btn" onclick="window.mpChallengeFriend(' + o.id + ", '" + name + '\')">Tantang</button>' +
        '<button class="btn" onclick="window.mpRemoveFriend(' + o.id + ')">Hapus</button>' +
        '</div></div>';
    }).join('');
  }

  async function refreshFriendUnreadDots() {
    if (!window.mpUser) return;
    var res = await apiCall('message.php', { action: 'unread_counts' }, 'GET');
    var map = (res && res.ok && res.unread) || {};
    document.querySelectorAll('#profile-friends-list .friend-chat-badge[data-badge-for]').forEach(function (badge) {
      var uid = badge.getAttribute('data-badge-for');
      var n = map[uid] || 0;
      badge.textContent = n > 9 ? '9+' : String(n);
      badge.classList.toggle('hidden', n === 0);
    });
  }

  bindClick('btn-toggle-add-friend', function () {
    var panel = $('add-friend-panel');
    if (!panel) return;
    panel.classList.toggle('hidden');
    if (!panel.classList.contains('hidden')) {
      $('friend-requests-panel') && $('friend-requests-panel').classList.add('hidden');
      var input = $('profile-friend-input');
      if (input) input.focus();
    }
  });
  bindClick('btn-toggle-friend-requests', function () {
    var panel = $('friend-requests-panel');
    if (!panel) return;
    panel.classList.toggle('hidden');
    if (!panel.classList.contains('hidden')) {
      $('add-friend-panel') && $('add-friend-panel').classList.add('hidden');
    }
  });
  (function () {
    var input = $('friend-search-input');
    if (input) input.addEventListener('input', renderFriendsList);
  })();

  // ==========================================================================
  // 5) INBOX (khusus pesan sistem/admin -- BUKAN chat teman)
  // ==========================================================================
  var inboxFilter = 'all';
  var lastInboxRows = [];

  async function loadInbox() {
    var box = $('inbox-list');
    if (!box || !window.mpUser) return;
    box.innerHTML = '<div class="inbox-empty">Memuat...</div>';
    var res = await apiCall('message.php', { action: 'inbox', filter: inboxFilter }, 'GET');
    if (!res || !res.ok) {
      box.innerHTML = '<div class="inbox-empty">Gagal memuat inbox.</div>';
      return;
    }
    lastInboxRows = res.messages || [];
    renderInboxNavBadge(res.unread_count || 0);
    renderInboxList();
  }

  function renderInboxNavBadge(n) {
    var b = $('profile-nav-inbox-badge');
    if (!b) return;
    b.textContent = n > 9 ? '9+' : String(n);
    b.classList.toggle('hidden', !n);
  }

  async function refreshInboxBadge() {
    if (!window.mpUser) return;
    var res = await apiCall('message.php', { action: 'inbox', filter: 'unread' }, 'GET');
    if (res && res.ok) renderInboxNavBadge(res.unread_count || 0);
  }

  function initialOf(name) {
    return (name || '?').trim().charAt(0).toUpperCase() || '?';
  }

  function renderInboxList() {
    var box = $('inbox-list');
    if (!box) return;
    var q = ($('inbox-search-input') && $('inbox-search-input').value || '').trim().toLowerCase();
    var rows = lastInboxRows;
    if (q) {
      rows = rows.filter(function (m) {
        return (m.from_username || '').toLowerCase().indexOf(q) !== -1 || (m.body || '').toLowerCase().indexOf(q) !== -1;
      });
    }
    if (rows.length === 0) {
      box.innerHTML = '<div class="inbox-empty">Tidak ada pesan.</div>';
      return;
    }
    box.innerHTML = rows.map(function (m) {
      var roleLabel = m.from_role === 'admin' ? 'ADMIN' : 'SUPPORT';
      var roleClass = m.from_role === 'admin' ? 'role-admin' : 'role-support';
      var unread = !m.read;
      return '<div class="inbox-row' + (unread ? ' unread' : '') + '" data-inbox-id="' + m.id + '">' +
        '<div class="inbox-row-avatar">' + esc(initialOf(m.from_username)) + '</div>' +
        '<div class="inbox-row-body">' +
        '<div class="inbox-row-top">' +
        '<span class="inbox-row-name">' + esc(m.from_username) + '</span>' +
        '<span class="role-badge ' + roleClass + '">' + roleLabel + '</span>' +
        '<span class="inbox-row-time">' + timeAgoShort(m.created_at) + '</span>' +
        '</div>' +
        '<div class="inbox-row-snippet">' + esc(m.body) + '</div>' +
        '</div>' +
        '<div class="inbox-row-dot' + (unread ? '' : ' hidden') + '"></div>' +
        '</div>';
    }).join('');

    box.querySelectorAll('.inbox-row').forEach(function (row) {
      row.addEventListener('click', function () {
        var id = row.getAttribute('data-inbox-id');
        openInboxDetail(id);
      });
    });
  }

  async function openInboxDetail(id) {
    var msg = lastInboxRows.find(function (m) { return String(m.id) === String(id); });
    if (!msg) return;
    var detail = $('inbox-detail-container');
    if (detail) {
      $('inbox-detail-from').textContent = msg.from_username + (msg.from_role === 'admin' ? ' (Admin)' : ' (Support)');
      $('inbox-detail-body').textContent = msg.body;
      detail.classList.remove('hidden');
    }
    if (!msg.read) {
      msg.read = true;
      var row = document.querySelector('.inbox-row[data-inbox-id="' + id + '"]');
      if (row) {
        row.classList.remove('unread');
        var dot = row.querySelector('.inbox-row-dot');
        if (dot) dot.classList.add('hidden');
      }
      await apiCall('message.php', { action: 'inbox_mark_read', id: id });
      refreshInboxBadge();
    }
  }

  function closeInboxDetail() {
    var detail = $('inbox-detail-container');
    if (detail) detail.classList.add('hidden');
  }

  document.querySelectorAll('#inbox-tabs .inbox-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      inboxFilter = tab.getAttribute('data-inbox-filter');
      document.querySelectorAll('#inbox-tabs .inbox-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
      closeInboxDetail();
      loadInbox();
    });
  });
  (function () {
    var input = $('inbox-search-input');
    if (input) input.addEventListener('input', renderInboxList);
  })();
  bindClick('btn-inbox-mark-all-read', async function () {
    await apiCall('message.php', { action: 'inbox_mark_all_read' });
    closeInboxDetail();
    loadInbox();
  });

  // Kotak detail pesan disisipkan sekali di atas daftar inbox (dibuat lewat
  // JS supaya markup index.html tetap ringkas / tidak menambah id lain di sana).
  (function insertInboxDetailBox() {
    var panel = $('profile-panel-inbox');
    var list = $('inbox-list');
    if (!panel || !list) return;
    var box = document.createElement('div');
    box.className = 'inbox-detail hidden';
    box.id = 'inbox-detail-container';
    box.innerHTML = '<div class="inbox-detail-top"><span class="inbox-detail-from" id="inbox-detail-from"></span>' +
      '<button type="button" class="inbox-detail-close" id="btn-inbox-detail-close">Tutup</button></div>' +
      '<div class="inbox-detail-body" id="inbox-detail-body"></div>';
    panel.insertBefore(box, list);
    bindClick('btn-inbox-detail-close', closeInboxDetail);
  })();

  // ==========================================================================
  // 6) UBAH PASSWORD -- panel inline (create / edit / cancel lewat
  // api/password_request.php yang sudah mendukung ketiganya).
  // ==========================================================================
  async function loadPasswordRequests() {
    var box = $('password-req-list');
    if (!box || !window.mpUser) return;
    box.innerHTML = '<div class="password-req-empty">Memuat...</div>';
    var res = await apiCall('password_request.php', { action: 'my_requests' }, 'GET');
    if (!res || !res.ok) {
      box.innerHTML = '<div class="password-req-empty">Gagal memuat riwayat.</div>';
      return;
    }
    renderPasswordRequests(res.requests || []);
  }

  function renderPasswordRequests(rows) {
    var box = $('password-req-list');
    if (!box) return;
    if (rows.length === 0) {
      box.innerHTML = '<div class="password-req-empty">Belum pernah mengajukan permintaan.</div>';
      return;
    }
    box.innerHTML = rows.map(function (r) {
      var canManage = r.status === 'pending';
      var html = '<div class="password-req-card" data-req-id="' + r.id + '">' +
        '<div class="password-req-top">' +
        '<span class="password-req-date">' + formatDate(r.created_at) + '</span>' +
        '<span class="password-req-status ' + r.status + '">' + esc(r.status_label || r.status) + '</span>' +
        '</div>' +
        '<div class="password-req-reason" id="password-req-reason-view-' + r.id + '">' + esc(r.reason || '(tanpa alasan)') + '</div>';
      if (r.admin_note) {
        html += '<div class="password-req-note">Catatan admin: ' + esc(r.admin_note) + '</div>';
      }
      if (canManage) {
        html += '<div class="password-req-actions" id="password-req-actions-' + r.id + '">' +
          '<button type="button" class="btn" data-edit-req="' + r.id + '">Edit</button>' +
          '<button type="button" class="btn" data-cancel-req="' + r.id + '">Batalkan</button>' +
          '</div>';
      }
      html += '</div>';
      return html;
    }).join('');

    box.querySelectorAll('[data-edit-req]').forEach(function (btn) {
      btn.addEventListener('click', function () { beginEditRequest(btn.getAttribute('data-edit-req')); });
    });
    box.querySelectorAll('[data-cancel-req]').forEach(function (btn) {
      btn.addEventListener('click', function () { cancelRequest(btn.getAttribute('data-cancel-req')); });
    });
  }

  function beginEditRequest(id) {
    var viewEl = $('password-req-reason-view-' + id);
    var actionsEl = $('password-req-actions-' + id);
    if (!viewEl || !actionsEl) return;
    var currentText = viewEl.textContent;
    var editRow = document.createElement('div');
    editRow.className = 'password-req-edit-row';
    editRow.innerHTML = '<textarea class="mp-input" rows="3"></textarea>' +
      '<div class="password-req-edit-actions">' +
      '<button type="button" class="btn primary" data-save-edit>Simpan</button>' +
      '<button type="button" class="btn" data-cancel-edit>Batal</button>' +
      '</div>';
    editRow.querySelector('textarea').value = currentText === '(tanpa alasan)' ? '' : currentText;
    viewEl.style.display = 'none';
    actionsEl.style.display = 'none';
    viewEl.insertAdjacentElement('afterend', editRow);

    editRow.querySelector('[data-cancel-edit]').addEventListener('click', function () {
      editRow.remove();
      viewEl.style.display = '';
      actionsEl.style.display = '';
    });
    editRow.querySelector('[data-save-edit]').addEventListener('click', async function () {
      var newReason = editRow.querySelector('textarea').value.trim();
      if (!newReason) return;
      var res = await apiCall('password_request.php', { action: 'edit', id: id, reason: newReason });
      if (res && res.ok) {
        loadPasswordRequests();
      } else {
        alert((res && res.error) || 'Gagal menyimpan perubahan.');
      }
    });
  }

  async function cancelRequest(id) {
    var res = await apiCall('password_request.php', { action: 'cancel', id: id });
    if (res && res.ok) loadPasswordRequests();
    else alert((res && res.error) || 'Gagal membatalkan permintaan.');
  }

  bindClick('btn-password-req-submit', async function () {
    var textarea = $('password-req-reason');
    var errBox = $('password-req-error');
    var msgBox = $('password-req-message');
    if (errBox) { errBox.textContent = ''; errBox.classList.add('hidden'); }
    if (msgBox) { msgBox.textContent = ''; msgBox.classList.add('hidden'); }
    var reason = (textarea && textarea.value || '').trim();
    if (!reason) {
      if (errBox) { errBox.textContent = 'Isi alasan permintaan terlebih dahulu.'; errBox.classList.remove('hidden'); }
      return;
    }
    var res = await apiCall('password_request.php', { action: 'create', reason: reason });
    if (res && res.ok) {
      if (textarea) textarea.value = '';
      if (msgBox) { msgBox.textContent = res.message || 'Permintaan terkirim.'; msgBox.classList.remove('hidden'); }
      loadPasswordRequests();
    } else if (errBox) {
      errBox.textContent = (res && res.error) || 'Gagal mengirim permintaan.';
      errBox.classList.remove('hidden');
    }
  });

})();
