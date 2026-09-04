(function () {
"use strict";
var scriptEl = document.currentScript;
var baseUrl = ((scriptEl && scriptEl.src) || "").replace(/JSS_2\/JSS_REPORTS\.js(\?.*)?$/, "") || window.location.pathname.replace(/[^\/]+$/, "") || "/";
var apiUrl = baseUrl + (baseUrl.endsWith("/") ? "" : "/") + "api/";
function $(id) { return document.getElementById(id); }
function esc(str) {
var d = document.createElement("div");
d.textContent = str === null || str === undefined ? "" : String(str);
return d.innerHTML;
}
function showOverlay(id, show) {
var el = $(id);
if (el) el.classList[show ? "remove" : "add"]("hidden");
}
function toggleHidden(id, hide) {
var el = $(id);
if (el) el.classList.toggle("hidden", !!hide);
}
function apiCall(endpointWithMaybeQuery, params) {
var url = apiUrl + endpointWithMaybeQuery;
var opts = { method: params ? "POST" : "GET", credentials: "same-origin", cache: "no-store" };
if (params) {
var fd = new FormData();
for (var k in params) {
if (Object.prototype.hasOwnProperty.call(params, k) && params[k] !== undefined && params[k] !== null) {
fd.append(k, params[k]);
}
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
return { ok: false, error: "Tidak bisa terhubung ke server. Periksa koneksi internetmu." };
});
}
function popup(msg) {
if (window.gamePopup) return window.gamePopup(msg);
window.alert(msg);
return Promise.resolve();
}
function confirmDialog(msg) {
if (window.gameConfirm) return window.gameConfirm(msg);
return Promise.resolve(window.confirm(msg));
}
function isStaffUser() {
return !!(window.mpUser && (window.mpUser.role === "admin" || window.mpUser.role === "support"));
}
function isFullAdmin() {
return !!(window.mpUser && window.mpUser.role === "admin");
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
var MAX_SHOTS = 3;
var MAX_DIM = 1000;
var JPEG_QUALITY = 0.62;
function compressImageFile(file) {
return new Promise(function (resolve, reject) {
if (!file.type || file.type.indexOf("image/") !== 0) {
reject("File yang dipilih bukan gambar.");
return;
}
var reader = new FileReader();
reader.onerror = function () { reject("Gagal membaca file gambar."); };
reader.onload = function () {
var img = new Image();
img.onerror = function () { reject("File gambar rusak atau tidak didukung."); };
img.onload = function () {
var w = img.width, h = img.height;
if (w > MAX_DIM || h > MAX_DIM) {
if (w >= h) { h = Math.round(h * (MAX_DIM / w)); w = MAX_DIM; }
else { w = Math.round(w * (MAX_DIM / h)); h = MAX_DIM; }
}
var canvas = document.createElement("canvas");
canvas.width = w; canvas.height = h;
var ctx = canvas.getContext("2d");
ctx.drawImage(img, 0, 0, w, h);
try {
resolve(canvas.toDataURL("image/jpeg", JPEG_QUALITY));
} catch (e) {
reject("Gagal memproses gambar (mungkin file rusak).");
}
};
img.src = reader.result;
};
reader.readAsDataURL(file);
});
}
function createShotManager(prefix) {
var shots = [];
var fileInput = $("shot-file-" + prefix);
var addBtn = $("shot-add-" + prefix);
var previewsEl = $("shot-previews-" + prefix);
var hintEl = $("shot-hint-" + prefix);
function render() {
if (!previewsEl) return;
previewsEl.innerHTML = "";
shots.forEach(function (s, idx) {
var div = document.createElement("div");
div.className = "shot-thumb";
div.innerHTML = '<img src="' + s.dataUri + '" data-idx="' + idx + '">' +
'<span class="shot-size-tag">' + s.sizeKb + ' KB</span>' +
'<div class="shot-remove" data-idx="' + idx + '" title="Hapus">&#10005;</div>';
previewsEl.appendChild(div);
});
previewsEl.querySelectorAll(".shot-remove").forEach(function (btn) {
btn.addEventListener("click", function () {
var i = parseInt(btn.getAttribute("data-idx"), 10);
shots.splice(i, 1);
render();
updateHint();
});
});
previewsEl.querySelectorAll("img").forEach(function (img) {
img.addEventListener("click", function () { openLightbox(img.src); });
});
}
function updateHint() {
if (addBtn) addBtn.disabled = shots.length >= MAX_SHOTS;
if (hintEl) hintEl.textContent = shots.length + "/" + MAX_SHOTS + " screenshot ditambahkan. Format JPG/PNG/WEBP, otomatis dikompres agar ringan.";
}
if (addBtn && fileInput) {
addBtn.addEventListener("click", function () { fileInput.click(); });
fileInput.addEventListener("change", function () {
var files = Array.prototype.slice.call(fileInput.files || []);
fileInput.value = "";
if (!files.length) return;
var remaining = MAX_SHOTS - shots.length;
if (remaining <= 0) { popup("Maksimal " + MAX_SHOTS + " screenshot per laporan."); return; }
files = files.slice(0, remaining);
if (hintEl) hintEl.textContent = "Memproses gambar...";
Promise.all(files.map(function (f) {
return compressImageFile(f).then(function (dataUri) {
var sizeKb = Math.round((dataUri.length * 0.75) / 1024);
shots.push({ dataUri: dataUri, sizeKb: sizeKb });
}).catch(function (err) {
popup(typeof err === "string" ? err : "Gagal memproses salah satu gambar.");
});
})).then(function () { render(); updateHint(); });
});
}
updateHint();
return {
getDataUris: function () { return shots.map(function (s) { return s.dataUri; }); },
reset: function () { shots = []; render(); updateHint(); },
count: function () { return shots.length; }
};
}
var bugShotManager = null;
var playerShotManager = null;
function openLightbox(src) {
var img = $("lightbox-image");
if (img) img.src = src;
showOverlay("overlay-image-lightbox", true);
}
function captureDeviceInfo() {
var ua = (navigator.userAgent || "").slice(0, 170);
var w = window.innerWidth, h = window.innerHeight;
return ua + " | layar " + w + "x" + h;
}
var SCREEN_LABELS = {
"screen-main": "Menu Utama",
"screen-start": "Layar Pilih Mode/Kesulitan",
"screen-tutorial": "Tutorial",
"screen-game": "Layar Permainan",
"screen-end": "Layar Akhir Permainan",
"screen-gallery": "Galeri Bonus",
"screen-online": "Menu Multiplayer Online"
};
function getActiveScreenId() {
var activeScreen = document.querySelector(".screen.active");
return activeScreen ? activeScreen.id : null;
}
function captureGameContext() {
var screenId = getActiveScreenId();
var parts = [];
parts.push("Layar: " + (SCREEN_LABELS[screenId] || screenId || "tidak diketahui"));
if (screenId === "screen-game") {
if (window.onlineMode && window.mpRoom) {
parts.push("Mode: Online");
parts.push("Room: " + (window.mpRoom.code || "-"));
if (window.mpSide) parts.push("Sisi: " + window.mpSide);
} else {
parts.push("Mode: Kampanye");
var stageEl = $("tag-stage-name");
var stageNumEl = $("tag-stage-num");
var roundEl = $("tag-round");
if (stageEl && stageEl.textContent) {
parts.push("Tahap: " + stageEl.textContent + (stageNumEl && stageNumEl.textContent ? " (" + stageNumEl.textContent + ")" : ""));
}
if (roundEl && roundEl.textContent) parts.push("Ronde: " + roundEl.textContent);
}
} else if (window.onlineMode && window.mpRoom && (screenId === "screen-end")) {
parts.push("Mode: Online (baru selesai)");
parts.push("Room: " + (window.mpRoom.code || "-"));
}
var openOverlay = document.querySelector(".overlay:not(.hidden)");
if (openOverlay && !/^overlay-(report|my-reports|admin-reports|image-lightbox)/.test(openOverlay.id || "")) {
parts.push("Panel terbuka: " + openOverlay.id);
}
return parts.join(" | ");
}
function resetContextField(displayId, btnId, defaultBtnLabel) {
var input = $(displayId);
var btn = $(btnId);
if (input) { input.value = captureGameContext(); input.readOnly = true; }
if (btn) btn.textContent = defaultBtnLabel;
}
function wireContextEditToggle(displayId, btnId, defaultBtnLabel) {
var input = $(displayId);
var btn = $(btnId);
if (!input || !btn) return;
btn.addEventListener("click", function () {
if (input.readOnly) {
input.readOnly = false;
input.focus();
input.select();
btn.textContent = "Pakai deteksi otomatis lagi";
} else {
input.value = captureGameContext();
input.readOnly = true;
btn.textContent = defaultBtnLabel;
}
});
}
var selectedSeverity = "sedang";
function openReportBug() {
showOverlay("overlay-report-center", false);
if (!window.mpUser) { popup("Silakan login terlebih dahulu."); return; }
$("bug-category").value = "";
document.querySelectorAll("#bug-severity-select .severity-btn").forEach(function (b) {
b.classList.toggle("selected", b.dataset.sev === "sedang");
});
selectedSeverity = "sedang";
$("bug-title").value = "";
$("bug-description").value = "";
$("bug-steps").value = "";
resetContextField("bug-context-display", "btn-bug-context-edit", "Bukan di sini? Ubah manual");
$("bug-report-error").textContent = "";
$("bug-report-message").textContent = "";
if (!bugShotManager) bugShotManager = createShotManager("bug");
bugShotManager.reset();
showOverlay("overlay-report-bug", true);
}
function submitBugReport() {
var category = $("bug-category").value;
var title = $("bug-title").value.trim();
var description = $("bug-description").value.trim();
var steps = $("bug-steps").value.trim();
var errEl = $("bug-report-error");
if (!category) { errEl.textContent = "Pilih kategori bug terlebih dahulu."; return; }
if (title.length < 5) { errEl.textContent = "Judul singkat wajib diisi (minimal 5 karakter)."; return; }
if (description.length < 15) { errEl.textContent = "Jelaskan bug-nya lebih detail (minimal 15 karakter)."; return; }
errEl.textContent = "";
var btn = $("btn-bug-report-submit");
if (btn) { btn.disabled = true; btn.textContent = "Mengirim..."; }
apiCall("bug_report.php", {
action: "create",
category: category,
severity: selectedSeverity,
title: title,
description: description,
steps: steps,
device_info: captureDeviceInfo(),
game_context: ($("bug-context-display") ? $("bug-context-display").value : captureGameContext()),
screenshots: JSON.stringify(bugShotManager.getDataUris())
}).then(function (res) {
if (btn) { btn.disabled = false; btn.textContent = "Kirim Laporan Bug"; }
if (res.ok) {
$("bug-report-message").textContent = res.message || "Laporan terkirim, terima kasih!";
$("bug-title").value = "";
$("bug-description").value = "";
$("bug-steps").value = "";
bugShotManager.reset();
} else {
errEl.textContent = res.error || "Gagal mengirim laporan.";
}
});
}
var playerReportOpenedFromHub = true;
function openReportPlayer(prefill) {
showOverlay("overlay-report-center", false);
if (!window.mpUser) { popup("Silakan login terlebih dahulu."); return; }
playerReportOpenedFromHub = !(prefill && prefill.username);
var uInput = $("player-report-username");
uInput.value = (prefill && prefill.username) ? prefill.username : "";
uInput.readOnly = !!(prefill && prefill.username);
var roomInput = $("player-report-room");
roomInput.value = (prefill && prefill.roomCode) ? prefill.roomCode : "";
$("player-report-category").value = "";
$("player-report-description").value = "";
resetContextField("player-context-display", "btn-player-context-edit", "Ubah manual");
$("player-report-error").textContent = "";
$("player-report-message").textContent = "";
if (!playerShotManager) playerShotManager = createShotManager("player");
playerShotManager.reset();
showOverlay("overlay-report-player", true);
}
function submitPlayerReport() {
var username = $("player-report-username").value.trim();
var category = $("player-report-category").value;
var description = $("player-report-description").value.trim();
var roomCode = $("player-report-room").value.trim();
var errEl = $("player-report-error");
if (!username) { errEl.textContent = "Masukkan username pemain yang ingin dilaporkan."; return; }
if (!category) { errEl.textContent = "Pilih kategori laporan terlebih dahulu."; return; }
if (description.length < 20) { errEl.textContent = "Ceritakan kejadiannya lebih detail (minimal 20 karakter): kapan, di ronde/ruangan mana, apa yang terjadi."; return; }
errEl.textContent = "";
var btn = $("btn-player-report-submit");
if (btn) { btn.disabled = true; btn.textContent = "Mengirim..."; }
apiCall("player_report.php", {
action: "create",
reported_username: username,
room_code: roomCode,
category: category,
description: description,
game_context: ($("player-context-display") ? $("player-context-display").value : captureGameContext()),
screenshots: JSON.stringify(playerShotManager.getDataUris())
}).then(function (res) {
if (btn) { btn.disabled = false; btn.textContent = "Kirim Laporan Pemain"; }
if (res.ok) {
$("player-report-message").textContent = res.message || "Laporan terkirim.";
$("player-report-description").value = "";
playerShotManager.reset();
} else {
errEl.textContent = res.error || "Gagal mengirim laporan.";
}
});
}
function loadMyReports() {
var el = $("my-reports-list");
if (!el) return;
el.innerHTML = '<p class="admin-loading-text">Memuat...</p>';
Promise.all([
apiCall("bug_report.php?action=my_reports"),
apiCall("player_report.php?action=my_reports")
]).then(function (results) {
var bugRes = results[0], playerRes = results[1];
var items = [];
if (bugRes.ok && bugRes.reports) bugRes.reports.forEach(function (r) { items.push({ type: "bug", data: r }); });
if (playerRes.ok && playerRes.reports) playerRes.reports.forEach(function (r) { items.push({ type: "player", data: r }); });
items.sort(function (a, b) { return new Date(b.data.created_at) - new Date(a.data.created_at); });
if (!items.length) { el.innerHTML = '<p class="report-empty-text">Kamu belum pernah mengirim laporan.</p>'; return; }
el.innerHTML = items.map(renderMyReportRow).join("");
});
}
function renderMyReportRow(item) {
var r = item.data;
var typeLabel = item.type === "bug" ? "Laporan Bug" : "Laporan Pemain";
var title = item.type === "bug" ? r.title : ("Melaporkan: " + esc(r.reported_username));
var date = formatDate(r.created_at);
var shots = r.attachment_count ? '<span class="report-chip shots">' + r.attachment_count + ' foto</span>' : "";
var adminNote = r.admin_note
? '<div class="report-row-desc" style="margin-top:6px;color:var(--brass-bright)"><b>Catatan admin:</b> ' + esc(r.admin_note) + '</div>'
: "";
return '<div class="report-row-card">' +
'<div class="report-row-top"><div class="report-row-title">' + esc(title) + '</div>' +
'<span class="admin-status-badge ' + esc(r.status) + '">' + esc(r.status_label) + '</span></div>' +
'<div class="report-row-meta"><b>' + typeLabel + '</b><span>' + date + '</span>' + shots + '</div>' +
'<div class="report-row-desc">' + esc(r.description) + '</div>' +
adminNote +
'</div>';
}
var adminReportType = "bug";
var adminReportStatus = "baru";
function openAdminReports() {
if (!isStaffUser()) { popup("Kamu tidak memiliki akses ke halaman ini."); return; }
adminReportType = "bug";
adminReportStatus = "baru";
document.querySelectorAll("#admin-reports-type-tabs .gallery-tab").forEach(function (b) {
b.classList.toggle("active", b.dataset.type === adminReportType);
});
renderAdminStatusTabs();
showOverlay("overlay-admin-reports", true);
loadAdminReportsList();
}
function statusOptionsFor(type) {
return type === "bug"
? [["baru", "Baru"], ["dilihat", "Dilihat"], ["diproses", "Diproses"], ["selesai", "Selesai"], ["ditolak", "Ditolak"], ["all", "Semua"]]
: [["baru", "Baru"], ["ditinjau", "Ditinjau"], ["ditindak", "Ditindak"], ["ditolak", "Ditolak"], ["all", "Semua"]];
}
function renderAdminStatusTabs() {
var el = $("admin-reports-status-tabs");
if (!el) return;
el.innerHTML = statusOptionsFor(adminReportType).map(function (pair) {
return '<button data-status="' + pair[0] + '" class="' + (adminReportStatus === pair[0] ? "active" : "") + '">' + pair[1] + '</button>';
}).join("");
el.querySelectorAll("button").forEach(function (btn) {
btn.addEventListener("click", function () {
adminReportStatus = btn.dataset.status;
renderAdminStatusTabs();
loadAdminReportsList();
});
});
}
function loadAdminReportsList() {
var el = $("admin-reports-list");
if (!el) return;
el.innerHTML = '<p class="admin-loading-text">Memuat...</p>';
var endpoint = adminReportType === "bug" ? "bug_report.php" : "player_report.php";
apiCall(endpoint + "?action=list&status=" + encodeURIComponent(adminReportStatus)).then(function (res) {
if (!res.ok) { el.innerHTML = '<p class="report-empty-text">Gagal memuat: ' + esc(res.error || "") + '</p>'; return; }
updateReportBadgeCount(res.new_count, adminReportType);
if (!res.reports || !res.reports.length) { el.innerHTML = '<p class="report-empty-text">Tidak ada laporan dengan status ini.</p>'; return; }
el.innerHTML = res.reports.map(function (r) { return renderAdminReportRow(r, adminReportType); }).join("");
el.querySelectorAll("[data-detail-id]").forEach(function (btn) {
btn.addEventListener("click", function () { openReportDetail(adminReportType, btn.dataset.detailId); });
});
});
}
function renderAdminReportRow(r, type) {
var date = formatDate(r.created_at);
var shots = r.attachment_count ? '<span class="report-chip shots">' + r.attachment_count + ' foto</span>' : "";
var top, meta;
if (type === "bug") {
top = '<div class="report-row-title">' + esc(r.title) + '</div>';
meta = '<b>' + esc(r.username) + '</b><span>' + esc(r.category) + '</span><span>Keparahan: ' + esc(r.severity_label) + '</span><span>' + date + '</span>' + shots;
} else {
var repeatChip = (r.total_reports_on_user && r.total_reports_on_user > 1) ? '<span class="report-chip repeat">' + r.total_reports_on_user + 'x dilaporkan</span>' : "";
var bannedChip = (r.reported_currently_banned == 1) ? '<span class="report-chip repeat">Sudah dibanned</span>' : "";
top = '<div class="report-row-title">Melaporkan: ' + esc(r.reported_username) + '</div>';
meta = '<b>Pelapor: ' + esc(r.reporter_username) + '</b><span>' + esc(r.category) + '</span><span>' + date + '</span>' + shots + repeatChip + bannedChip;
}
return '<div class="report-row-card">' +
'<div class="report-row-top">' + top + '<span class="admin-status-badge ' + esc(r.status) + '">' + esc(r.status_label) + '</span></div>' +
'<div class="report-row-meta">' + meta + '</div>' +
'<div class="report-row-desc">' + esc(r.description) + '</div>' +
'<div class="report-row-actions"><button class="btn" data-detail-id="' + r.id + '">Lihat Detail &amp; Tindak Lanjuti</button></div>' +
'</div>';
}
function openReportDetail(type, id) {
var endpoint = type === "bug" ? "bug_report.php" : "player_report.php";
showOverlay("overlay-report-detail", true);
$("report-detail-body").innerHTML = '<p class="admin-loading-text">Memuat detail...</p>';
apiCall(endpoint + "?action=detail&id=" + encodeURIComponent(id)).then(function (res) {
if (!res.ok) { $("report-detail-body").innerHTML = '<p class="report-empty-text">Gagal memuat: ' + esc(res.error || "") + '</p>'; return; }
renderReportDetail(type, res.report);
});
}
function gridRow(label, valueHtml) {
return '<div class="rdg-row"><span class="rdg-label">' + esc(label) + '</span><span class="rdg-value">' + valueHtml + '</span></div>';
}
function renderReportDetail(type, r) {
var html = '<div class="report-detail-grid">';
if (type === "bug") {
html += gridRow("Pelapor", esc(r.username));
html += gridRow("Kategori", esc(r.category));
html += gridRow("Keparahan", esc(r.severity_label));
html += gridRow("Status", esc(r.status_label));
html += gridRow("Perangkat", esc(r.device_info || "-"));
html += gridRow("Lokasi/Konteks Saat Lapor", esc(r.game_context || "-"));
html += gridRow("Dikirim", formatDate(r.created_at));
} else {
html += gridRow("Pelapor", esc(r.reporter_username));
html += gridRow("Dilaporkan", esc(r.reported_username) + (r.reported_user_id ? "" : " <i>(akun tidak ditemukan)</i>"));
html += gridRow("Kategori", esc(r.category));
html += gridRow("Kode Ruangan", esc(r.room_code || "-"));
html += gridRow("Lokasi/Konteks Saat Lapor", esc(r.game_context || "-"));
html += gridRow("Status", esc(r.status_label));
html += gridRow("Dikirim", formatDate(r.created_at));
}
html += "</div>";
if (type === "bug") html += '<div class="report-detail-section"><h4>Judul</h4><p>' + esc(r.title) + '</p></div>';
html += '<div class="report-detail-section"><h4>Deskripsi</h4><p>' + esc(r.description) + '</p></div>';
if (type === "bug" && r.steps) html += '<div class="report-detail-section"><h4>Langkah Reproduksi</h4><p>' + esc(r.steps) + '</p></div>';
if (r.admin_note) html += '<div class="report-detail-section"><h4>Catatan Admin Sebelumnya</h4><p>' + esc(r.admin_note) + '</p></div>';
if (r.screenshots && r.screenshots.length) {
html += '<div class="report-detail-section"><h4>Screenshot Bukti (' + r.screenshots.length + ')</h4><div class="report-detail-shots">';
r.screenshots.forEach(function (s) { html += '<img src="' + s.data_uri + '" data-full="' + s.data_uri + '">'; });
html += "</div></div>";
} else {
html += '<div class="report-detail-section"><h4>Screenshot Bukti</h4><p style="font-style:italic;color:var(--ink-dim)">Tidak ada screenshot dilampirkan.</p></div>';
}
if (isStaffUser()) {
html += '<div class="report-admin-controls">';
html += '<label class="mp-label">Ubah Status</label><select id="report-detail-status" class="mp-input">';
var statuses = type === "bug"
? [["baru", "Baru"], ["dilihat", "Dilihat"], ["diproses", "Diproses"], ["selesai", "Selesai"], ["ditolak", "Ditolak"]]
: [["baru", "Baru"], ["ditinjau", "Ditinjau"], ["ditindak", "Ditindak"], ["ditolak", "Ditolak"]];
statuses.forEach(function (p) { html += '<option value="' + p[0] + '"' + (r.status === p[0] ? " selected" : "") + '>' + p[1] + '</option>'; });
html += "</select>";
if (type === "player") {
html += '<label class="mp-label">Tindakan Diambil</label><select id="report-detail-action" class="mp-input"><option value="">- Belum ada -</option>';
["Tidak ada tindakan", "Peringatan", "Ban Sementara", "Ban Permanen"].forEach(function (a) {
html += '<option value="' + a + '"' + (r.action_taken === a ? " selected" : "") + '>' + a + '</option>';
});
html += "</select>";
}
html += '<label class="mp-label">Catatan Admin (opsional -- akan terlihat oleh pemain terkait)</label>' +
'<textarea id="report-detail-note" class="mp-input" rows="3">' + (r.admin_note ? esc(r.admin_note) : "") + '</textarea>';
html += '<div class="report-admin-row">';
html += '<button class="btn primary" id="btn-report-detail-save">Simpan Perubahan</button>';
if (type === "player" && isFullAdmin()) {
html += '<button class="btn" id="btn-report-detail-ban" style="border-color:var(--blood-bright);color:#f3a7a7">Ban Pemain Ini</button>';
}
if (isFullAdmin()) {
html += '<button class="btn" id="btn-report-detail-delete" style="border-color:var(--ink-dim)">Hapus Laporan</button>';
}
html += "</div>";
html += '<div class="mp-message" id="report-detail-save-msg"></div>';
html += "</div>";
}
$("report-detail-body").innerHTML = html;
$("report-detail-body").querySelectorAll(".report-detail-shots img").forEach(function (img) {
img.addEventListener("click", function () { openLightbox(img.getAttribute("data-full")); });
});
var saveBtn = $("btn-report-detail-save");
if (saveBtn) saveBtn.addEventListener("click", function () { saveReportDetailChanges(type, r); });
var banBtn = $("btn-report-detail-ban");
if (banBtn) banBtn.addEventListener("click", function () { quickBanFromReport(r); });
var delBtn = $("btn-report-detail-delete");
if (delBtn) delBtn.addEventListener("click", function () { deleteReportFromDetail(type, r.id); });
}
function saveReportDetailChanges(type, r) {
var endpoint = type === "bug" ? "bug_report.php" : "player_report.php";
var status = $("report-detail-status").value;
var note = $("report-detail-note").value.trim();
var params = { action: "update_status", id: r.id, status: status, admin_note: note };
if (type === "player") {
var actionSel = $("report-detail-action");
if (actionSel) params.action_taken = actionSel.value;
}
var saveBtn = $("btn-report-detail-save");
if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = "Menyimpan..."; }
apiCall(endpoint, params).then(function (res) {
if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = "Simpan Perubahan"; }
var msg = $("report-detail-save-msg");
if (res.ok) {
if (msg) msg.textContent = res.message || "Tersimpan.";
loadAdminReportsList();
refreshReportBadges();
} else {
popup(res.error || "Gagal menyimpan perubahan.");
}
});
}
function quickBanFromReport(r) {
showOverlay("overlay-report-detail", false);
if (window.mpOpenAdminBan) {
Promise.resolve(window.mpOpenAdminBan()).then(function () {
var uEl = $("ban-username"); if (uEl) uEl.value = r.reported_username;
var rEl = $("ban-reason"); if (rEl) rEl.value = "Cheating / Eksploitasi bug";
var dEl = $("ban-description"); if (dEl) dEl.value = "Berdasarkan laporan pemain #" + r.id + ": " + r.description.slice(0, 220);
});
}
}
function deleteReportFromDetail(type, id) {
confirmDialog("Yakin ingin menghapus laporan ini beserta screenshot-nya? Tindakan ini tidak bisa dibatalkan.").then(function (yes) {
if (!yes) return;
var endpoint = type === "bug" ? "bug_report.php" : "player_report.php";
apiCall(endpoint, { action: "delete", id: id }).then(function (res) {
if (res.ok) {
showOverlay("overlay-report-detail", false);
loadAdminReportsList();
refreshReportBadges();
} else {
popup(res.error || "Gagal menghapus laporan.");
}
});
});
}
var lastBugNewCount = 0, lastPlayerNewCount = 0;
function updateReportBadgeCount(n, type) {
if (typeof n !== "number") return;
if (type === "bug") lastBugNewCount = n; else lastPlayerNewCount = n;
var total = lastBugNewCount + lastPlayerNewCount;
var el = $("report-badge-dot");
if (!el) return;
el.textContent = total > 99 ? "99+" : String(total);
el.classList.toggle("zero", !total);
}
function refreshReportBadges() {
if (!isStaffUser()) return;
apiCall("bug_report.php?action=list&status=baru").then(function (res) {
if (res.ok) updateReportBadgeCount(res.new_count || 0, "bug");
});
apiCall("player_report.php?action=list&status=baru").then(function (res) {
if (res.ok) updateReportBadgeCount(res.new_count || 0, "player");
});
}
function syncReportButtons() {
toggleHidden("btn-online-admin-reports", !isStaffUser());
toggleHidden("btn-menu-report", !window.onlineMode);
syncFloatingReportButton();
}
function syncFloatingReportButton() {
var btn = $("btn-float-report");
if (!btn) return;
var anyOverlayOpen = !!document.querySelector(".overlay:not(.hidden)");
var doorActive = !!document.querySelector(".door-transition:not(.hidden)");
btn.classList.toggle("hidden", anyOverlayOpen || doorActive);
}
var reportSyncTicks = 0;
function reportSyncTick() {
syncReportButtons();
reportSyncTicks++;
if (isStaffUser() && reportSyncTicks % 20 === 0) refreshReportBadges();
}
function bindClick(id, handler) {
var el = $(id);
if (el) el.addEventListener("click", handler);
}
function initReportsUI() {
bindClick("btn-online-report-center", function () { showOverlay("overlay-report-center", true); });
bindClick("btn-report-center-close", function () { showOverlay("overlay-report-center", false); });
bindClick("btn-open-report-bug", openReportBug);
bindClick("btn-open-report-player", function () { openReportPlayer(); });
bindClick("btn-open-my-reports", function () {
showOverlay("overlay-report-center", false);
showOverlay("overlay-my-reports", true);
loadMyReports();
});
bindClick("btn-my-reports-close", function () {
showOverlay("overlay-my-reports", false);
showOverlay("overlay-report-center", true);
});
bindClick("btn-bug-report-close", function () {
showOverlay("overlay-report-bug", false);
showOverlay("overlay-report-center", true);
});
bindClick("btn-bug-report-submit", submitBugReport);
bindClick("btn-player-report-close", function () {
showOverlay("overlay-report-player", false);
if (playerReportOpenedFromHub) showOverlay("overlay-report-center", true);
});
bindClick("btn-player-report-submit", submitPlayerReport);
bindClick("btn-online-admin-reports", openAdminReports);
bindClick("btn-admin-reports-close", function () { showOverlay("overlay-admin-reports", false); });
bindClick("btn-report-detail-close", function () { showOverlay("overlay-report-detail", false); });
bindClick("lightbox-close", function () { showOverlay("overlay-image-lightbox", false); });
var lightboxOverlay = $("overlay-image-lightbox");
if (lightboxOverlay) {
lightboxOverlay.addEventListener("click", function (e) {
if (e.target === lightboxOverlay) showOverlay("overlay-image-lightbox", false);
});
}
document.querySelectorAll("#bug-severity-select .severity-btn").forEach(function (b) {
b.addEventListener("click", function () {
document.querySelectorAll("#bug-severity-select .severity-btn").forEach(function (x) { x.classList.remove("selected"); });
b.classList.add("selected");
selectedSeverity = b.dataset.sev;
});
});
document.querySelectorAll("#admin-reports-type-tabs .gallery-tab").forEach(function (b) {
b.addEventListener("click", function () {
adminReportType = b.dataset.type;
adminReportStatus = "baru";
document.querySelectorAll("#admin-reports-type-tabs .gallery-tab").forEach(function (x) { x.classList.remove("active"); });
b.classList.add("active");
renderAdminStatusTabs();
loadAdminReportsList();
});
});
renderAdminStatusTabs();
var menuBtn = $("btn-menu");
if (menuBtn) menuBtn.addEventListener("click", function () { toggleHidden("btn-menu-report", !window.onlineMode); });
bindClick("btn-menu-report", function () {
var gm = $("game-menu"); if (gm) gm.classList.add("hidden");
var gmb = $("game-menu-backdrop"); if (gmb) gmb.classList.add("hidden");
var oppNameEl = $("opp-name-tag");
var oppName = (oppNameEl && oppNameEl.textContent) || "";
var roomCode = (window.mpRoom && window.mpRoom.code) || "";
openReportPlayer({ username: oppName, roomCode: roomCode });
});
bindClick("btn-float-report", function () { showOverlay("overlay-report-center", true); });
wireContextEditToggle("bug-context-display", "btn-bug-context-edit", "Bukan di sini? Ubah manual");
wireContextEditToggle("player-context-display", "btn-player-context-edit", "Ubah manual");
bugShotManager = createShotManager("bug");
playerShotManager = createShotManager("player");
syncReportButtons();
setTimeout(syncReportButtons, 500);
setTimeout(syncReportButtons, 1500);
setTimeout(function () { if (isStaffUser()) refreshReportBadges(); }, 1600);
setInterval(reportSyncTick, 3000);
setInterval(syncFloatingReportButton, 400);
}
if (document.readyState === "loading") {
document.addEventListener("DOMContentLoaded", initReportsUI);
} else {
initReportsUI();
}
})();
