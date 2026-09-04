# Penjelasan Teknis — Perbaikan Daftar Teman, Chat & Riwayat Pertandingan

> **Kenapa dokumen ini ada:** tiga perbaikan di bawah ini gampang disalahpahami
> sebagai "kode yang aneh" atau "typo" kalau dibaca sekilas tanpa konteks —
> apalagi `JSS_2/JSS_MULTIPLAYER.js` seluruhnya satu baris (hasil minifikasi
> lama) dan gampang sekali komentar singkat di dalamnya terlewat. Dokumen ini
> **melengkapi** (bukan menggantikan) entri `V.0.9.1.0` di `CHANGELOG_LENGKAP.md`
> — di sana ringkas & kronologis, di sini lengkap & per-topik, supaya kalau
> suatu saat ada yang mau mengubah salah satu dari tiga hal ini lagi (baik
> pemilik proyek sendiri, AI lain, atau sesi Claude yang berbeda), alasan
> di balik tiap keputusan tetap jelas dan tidak sengaja "dikembalikan" ke
> perilaku lama yang justru itu bug-nya.
>
> Berlaku utk rilis `V.0.9.1.0` (perubahan fungsional) + `V.0.9.1.1`
> (dokumen ini sendiri + komentar penanda di kode, tanpa perubahan perilaku).

## Daftar Isi

1. [Daftar Teman — kartu avatar + akses profil](#1-daftar-teman--kartu-avatar--akses-profil)
2. [Chat — nama asli, bukan `"Kamu"`/avatar default "K"](#2-chat--nama-asli-bukan-kamuavatar-default-k)
3. [Riwayat Pertandingan — tombol disabled + transparan](#3-riwayat-pertandingan--tombol-disabled--transparan)
4. [Peta File Lengkap](#4-peta-file-lengkap)
5. [Checklist Verifikasi](#5-checklist-verifikasi)
6. [Riwayat Dokumen Ini](#6-riwayat-dokumen-ini)

Ringkasan super-cepat:

| # | Perubahan | File utama yang disentuh | Ada perubahan backend/DB? |
|---|---|---|---|
| 1 | Daftar Teman jadi kartu avatar, seluruh kartu bisa diklik utk lihat profil | `JSS_2/JSS_PROFILE_UI.js`, `CSS_2/profile_ui.css` | Tidak |
| 2 | Chat pakai username asli, bukan `"Kamu"` literal | `JSS_2/JSS_MULTIPLAYER.js` | Tidak |
| 3 | Tombol Muat Lebih Sedikit/Banyak: disabled+transparan, bukan hilang | `JSS_2/JSS_HISTORY.js`, `index.html` | Tidak |

---

## 1) Daftar Teman — kartu avatar + akses profil

### Masalah sebelumnya
Baris teman cuma teks polos: nama (kecil, bisa diklik tapi tidak kelihatan
jelas) — status "Teman" — 3 tombol. Fungsi `window.mpViewFriendProfile(id, nama)`
utk menampilkan profil teman **sudah ada sejak `V.0.9.0.0`** (didefinisikan di
`JSS_2/JSS_MULTIPLAYER.js`), tapi cuma terpasang di `<span class="friend-name">`
tanpa penanda visual selain kursor pointer — gampang tidak disadari user
bahwa nama itu bisa diklik.

### Apa yang diubah
**`JSS_2/JSS_PROFILE_UI.js`, fungsi `renderFriendsList()`.** Struktur output
tiap baris berubah dari:

```html
<!-- SEBELUM -->
<div class="friend-row" data-uid="42">
  <span class="friend-name" onclick="window.mpViewFriendProfile(42,'Budi')">Budi</span>
  <span class="friend-status">Teman</span>
  <div class="friend-actions"> ... tombol Chat/Tantang/Hapus ... </div>
</div>
```

menjadi:

```html
<!-- SESUDAH -->
<div class="friend-row" data-uid="42">
  <div class="friend-info" onclick="window.mpViewFriendProfile(42,'Budi')" title="Lihat profil Budi">
    <div class="friend-avatar">B</div>
    <div class="friend-name-wrap">
      <span class="friend-name">Budi</span>
      <span class="friend-status">Teman</span>
    </div>
  </div>
  <div class="friend-actions"> ... tombol Chat/Tantang/Hapus, TIDAK berubah ... </div>
</div>
```

Poin penting dari struktur ini:

- **`.friend-info` dan `.friend-actions` adalah SAUDARA (sibling), bukan
  bersarang.** Ini disengaja — kalau tombol Chat/Tantang/Hapus ada *di dalam*
  elemen yang listen klik utk buka profil, klik tombol itu akan ikut memicu
  buka profil juga (event bubbling), dan butuh `event.stopPropagation()` di
  tiap tombol utk mencegahnya. Dengan membuat keduanya saudara, masalah itu
  tidak pernah muncul sama sekali — jadi kalau nanti mau menambah tombol baru
  di `.friend-actions`, **tidak perlu** menambahkan `stopPropagation`.
- Huruf avatar dihitung pakai `initialOf()` — fungsi yang **sudah ada** di
  file yang sama (dipakai jg utk avatar Inbox), bukan fungsi baru. Kalau mau
  ubah cara avatar dihitung (misal jadi 2 huruf inisial), ubah di situ, jangan
  bikin fungsi duplikat.
- Atribut `title="Lihat profil ..."` cuma tooltip native browser tambahan,
  tidak mempengaruhi fungsi.

**`CSS_2/profile_ui.css`**, blok `#profile-friends-list .friend-row` dkk.
`.friend-row` yang sebelumnya CSS Grid 3-kolom (nama | status | tombol)
diganti flexbox: kartu dengan background (`var(--panel-2)`), border, radius,
jarak antar-kartu — dan border-nya menyala pelan (`rgba(176,138,78,.4)`) saat
kartu di-hover. `.friend-info` dapat highlight background + garis bawah nama
saat area itu spesifik di-hover, supaya jelas *bagian mana* yang bisa diklik
(beda dari sekadar hover di kartu secara umum). Avatar (`.friend-avatar`)
memakai gradasi radial emas yang **sama persis** dengan `.profile-avatar` yang
sudah ada di `CSS_1/online_ui.css` (variabel `--brass-bright` / `--brass`),
cuma ukurannya 38px (bukan 56px) supaya muat di baris teman.

Semua selector CSS baru pakai prefix `#profile-friends-list .friend-row`
(ID + class), **bukan** cuma `.friend-row` polos. Ini disengaja: `.friend-row`
versi asli (grid 3-kolom) masih ada & tidak dihapus dari `CSS_1/online_ui.css`
— override di `profile_ui.css` menang karena spesifisitas selector-nya lebih
tinggi (ID+class > class saja), **bukan** cuma karena urutan file dimuat.
Kalau nanti ada yang mau menambah style baru utk elemen ini, tetap pakai
prefix `#profile-friends-list` supaya jaminan menangnya tidak bergantung
urutan `<link>` di `index.html`.

Breakpoint mobile (`<860px`, sudah ada sejak awal) disesuaikan dari
`grid-template-areas` ke `flex-direction:column` — hasil visualnya sama
persis dgn sebelumnya (info penuh di atas, tombol aksi penuh di bawah).

### Yang TIDAK diubah
`api/friend.php` tidak disentuh. Endpoint `action=list` & `action=profile`
yang sudah ada sejak proyek ini mulai sudah cukup lengkap (rating, menang/
kalah, daftar teman-dari-teman) utk ditampilkan `mpViewFriendProfile`.

### Cara tes manual
1. Login, buka Profil → Daftar Teman (harus ada minimal 1 teman berstatus
   diterima).
2. Pastikan tiap baris tampil sbg kartu dgn avatar bulat berinisial.
3. Klik area avatar/nama (BUKAN tombol) → overlay profil teman tsb harus
   terbuka.
4. Klik tombol Chat/Tantang/Hapus → harus jalan seperti biasa, **tanpa**
   ikut membuka overlay profil.
5. Perkecil lebar browser ke <860px (atau buka di HP) → kartu harus tetap
   rapi, nama tidak terpotong, tombol aksi jadi 1 baris penuh di bawah info.

### ⚠️ Kalau mau ubah lagi, jangan lakukan ini
- **Jangan** pindahkan `onclick="window.mpViewFriendProfile(...)"` ke
  `.friend-row` (elemen terluar) — itu akan membuat klik tombol aksi ikut
  membuka profil (karena tombolnya jadi anak dari elemen yang listen klik).
- **Jangan** hapus prefix `#profile-friends-list` dari selector CSS baru di
  atas kecuali kamu juga menghapus/mengubah `.friend-row` versi grid lama di
  `CSS_1/online_ui.css` — kalau tidak, hasilnya bisa tidak konsisten
  tergantung browser.

---

## 2) Chat — nama asli, bukan `"Kamu"`/avatar default "K"

### Akar masalahnya (root cause)
Di `JSS_2/JSS_MULTIPLAYER.js` ada **tiga** tempat yang perlu menampilkan
"nama pengguna yang sedang login, atau fallback kalau belum login". Dua di
antaranya (label lawan main saat rematch, dan judul panel "Peringkat Saya")
konsisten pakai pola:

```js
window.mpUser ? window.mpUser.username : "Kamu"
```

— pakai username asli kalau ada sesi, `"Kamu"` cuma fallback. Tapi di fungsi
pembangun baris chat (dipanggil dari poller pesan), pola yang sama **tidak**
diikuti. Kode aslinya (sebelum `V.0.9.1.0`):

```js
// SEBELUM — bug: tidak pernah mengecek window.mpUser.username sama sekali
const n = e.from_user_id == window.mpUser.id,
      a = n ? "Kamu" : e.from_username || "Lawan",
      // ...
```

Variabel `a` di atas dipakai utk **dua hal**: (1) label nama pengirim (untuk
pesan lawan — pesan sendiri sengaja tidak menampilkan label ini, lihat bagian
"Yang TIDAK diubah" di bawah), dan (2) huruf avatar bubble, lewat fungsi
`oe()` yang mengambil huruf pertama lalu `.toUpperCase()`. Karena `a` utk
pesan sendiri **selalu** literal `"Kamu"`, huruf pertamanya **selalu** `"K"`
— avatar bubble pesan sendiri jadi selalu bertuliskan "K", siapa pun yang
login, tidak pernah mencerminkan username sungguhan.

### Perbaikannya
```js
// SESUDAH
const n = e.from_user_id == window.mpUser.id,
      a = n
        ? (window.mpUser && window.mpUser.username ? window.mpUser.username : "Kamu")
        : e.from_username || "Lawan",
      // ...
```

Sekarang polanya sama persis dgn dua tempat lain di file yang sama:
username asli dipakai kalau ada, `"Kamu"` cuma fallback utk kondisi yang
seharusnya tidak pernah terjadi dalam praktik (overlay chat cuma bisa dibuka
dari dalam akun yang sudah login).

### Yang TIDAK diubah (dan kenapa)
Label nama di atas bubble pesan (`.chat-meta-name`) **tetap sengaja**
disembunyikan khusus utk pesan milik sendiri — ini bukan bug, ini perilaku
lama yang tidak disentuh. Pola serupa lumrah di aplikasi chat lain
(WhatsApp/Telegram jg tidak menuliskan ulang nama sendiri di atas bubble
sendiri, karena posisi bubble di sisi kanan sudah menunjukkan itu punya
siapa). Yang diperbaiki di `V.0.9.1.0` murni **komputasi identitas** di
baliknya (variabel `a`, dipakai buat avatar), bukan menambah label baru yang
sebelumnya tidak ada.

> **Kalau ternyata yang dimaksud pemilik proyek justru "nama juga harus
> tampil sebagai teks di atas tiap bubble milik sendiri"** (bukan cuma
> avatarnya) — itu perubahan kecil tambahan: hapus kondisi `n?"":...` yang
> membungkus `<span class="chat-meta-name">` di fungsi yang sama, supaya
> label itu ikut dirender jg utk pesan sendiri. **Belum dikerjakan** di
> `V.0.9.1.0` karena permintaan aslinya paling cocok dibaca sebagai
> perbaikan avatar/identitas, bukan penambahan label baru.

### Cara tes manual
1. Login sbg 2 akun berbeda (2 browser/tab berbeda), berteman satu sama
   lain, buka Chat dari Daftar Teman.
2. Kirim pesan dari akun A → di layar A, avatar bubble pesan itu harus
   menampilkan huruf pertama username akun A (bukan selalu "K").
3. Di layar B, pesan yang sama muncul sbg pesan "lawan" — avatar & nama
   harus tetap menampilkan username akun A dengan benar (jalur ini memang
   tidak diubah, cuma dicek supaya tidak ada regresi).

### ⚠️ Kalau mau ubah lagi, jangan lakukan ini
- **Jangan** kembalikan variabel `a` ke literal `"Kamu"` tanpa pengecekan
  `window.mpUser.username` — itu persis bug yang baru diperbaiki.
- Kalau menambah tempat baru yang butuh "nama user saat ini, dgn fallback",
  **pakai pola `window.mpUser && window.mpUser.username ? window.mpUser.username : "Kamu"`**
  yang sekarang konsisten di 3 tempat, supaya tidak muncul inkonsistensi baru
  yang serupa.

---

## 3) Riwayat Pertandingan — tombol disabled + transparan

### Masalah sebelumnya
`updateHistoryActionButtons()` di `JSS_2/JSS_HISTORY.js` (awalnya dibuat di
rilis `V.0.8.7.1`) memakai `classList.toggle("hidden", ...)` — tombol yang
tidak relevan (tidak ada halaman berikutnya utk "Muat Lebih Banyak", atau
belum ada halaman kedua utk "Muat Lebih Sedikit") hilang total dari layout,
bikin posisi tombol yang tersisa berpindah/"meloncat".

### Perbaikannya
```js
// SESUDAH — fungsi baru, dipakai menggantikan classList.toggle("hidden",...)
function setHistoryButtonEnabled(btn, enabled) {
  if (!btn) return;
  btn.classList.remove("hidden"); // selalu tampil
  btn.disabled = !enabled;        // browser otomatis blokir klik/tap
}

function updateHistoryActionButtons(hasMore) {
  setHistoryButtonEnabled($("btn-profile-history-more"), !!hasMore);
  setHistoryButtonEnabled($("btn-profile-history-less"), historyOffset > HISTORY_PAGE_SIZE);
}
```

Dipakai jg di cabang `reset` pada `loadMatchHistory()` (dua tombol dibuat
disabled dulu selagi memuat halaman pertama, bukan disembunyikan).
`collapseMatchHistory()` (fungsi di balik tombol "Lebih Sedikit") **tidak
disentuh sama sekali** — ia sudah memanggil `updateHistoryActionButtons()`
di akhir, jadi otomatis ikut memakai perilaku baru tanpa perubahan tambahan.

**Tidak ada CSS baru yang ditambahkan utk ini.** Aturan
`.btn:disabled{opacity:.35;cursor:not-allowed}` di `CSS_1/core_ui.css`
**sudah ada sejak awal proyek** dan persis memenuhi permintaan "transparan &
tidak bisa dipencet" — atribut `disabled` bawaan HTML sudah otomatis
memblokir event klik/tap oleh browser, jadi `pointer-events:none` pun tidak
perlu ditambahkan.

`index.html` cuma diubah supaya kedua tombol tidak lagi mulai dgn class
`hidden` sbg kondisi awal (diganti atribut `disabled` langsung di markup),
supaya sebelum JS selesai memuat pun tombolnya sudah dalam keadaan aman
(nonaktif+transparan), bukan aktif-tapi-belum-berfungsi.

### Cara tes manual
1. Buka Profil → History pada akun yang punya banyak riwayat pertandingan
   (>15, lebih dari 1 halaman).
2. Saat pertama dibuka (baru 1 halaman termuat): tombol "Muat Lebih Sedikit"
   harus terlihat **transparan & tidak bisa diklik**; "Muat Lebih Banyak"
   harus **aktif normal**.
3. Klik "Muat Lebih Banyak" sampai data habis → tombol itu harus berubah
   jadi transparan & tidak bisa diklik, sementara "Muat Lebih Sedikit"
   berubah jadi aktif.
4. Klik "Muat Lebih Sedikit" sampai kembali ke halaman pertama → kondisi
   balik seperti langkah 2.
5. Pastikan **posisi kedua tombol tidak pernah berpindah/hilang** di semua
   langkah di atas — cuma tampilan aktif/nonaktifnya yang berubah.

### ⚠️ Kalau mau ubah lagi, jangan lakukan ini
- **Jangan** kembalikan ke `classList.toggle("hidden", ...)` utk dua tombol
  ini — itu persis perilaku "meloncat" yang baru diperbaiki.
- Kalau butuh tombol lain di proyek ini berperilaku serupa (selalu tampil,
  disabled+transparan saat tidak relevan), pakai ulang pola
  `setHistoryButtonEnabled()` sbg referensi — jangan bikin CSS baru, cukup
  set `btn.disabled`, karena `.btn:disabled` global sudah menangani
  tampilannya.

---

## 4) Peta File Lengkap

| File | Apa yang berubah | Ada komentar penanda `[FIX v0.9.1.0]` / `[UBAH v0.9.1.0]`? |
|---|---|---|
| `index.html` | 35 referensi `?v=` + lencana versi → `V.0.9.1.0`/`.1`; 2 tombol history: `hidden` → `disabled` | Tidak perlu (cuma atribut, sudah jelas dari konteks) |
| `CSS_2/profile_ui.css` | Blok `.friend-row`/`.friend-info`/`.friend-avatar`/`.friend-name-wrap` baru, `.friend-name`/`.friend-status` di-scope ulang | Ya, di komentar atas blok tsb |
| `JSS_2/JSS_PROFILE_UI.js` | `renderFriendsList()`: markup avatar + blok info yang diklik | Ya, di header file & tepat di atas fungsi |
| `JSS_2/JSS_MULTIPLAYER.js` | 1 statement: identitas pesan chat milik sendiri | Ya, komentar blok `/*...*/` tepat sebelum kode yang diubah |
| `JSS_2/JSS_HISTORY.js` | `setHistoryButtonEnabled()` baru, dipakai di 2 tempat | Ya, di komentar atas fungsi |
| `CHANGELOG_LENGKAP.md` | Entri rilis `V.0.9.1.0` & `V.0.9.1.1` | — (dokumen changelog itu sendiri) |
| `PENJELASAN_TEKNIS_PERUBAHAN_TEMAN_CHAT_HISTORY.md` | Dokumen ini | — |

Referensi cache-busting pemuat musik (`ASSETS_LAGU/.../_PART{n}.js?v=V.0.8.7.0`)
**tetap tidak ikut naik**, mengikuti keputusan yang sudah didokumentasikan
sejak `V.0.9.0.0` — berkas audio yang dirujuk tidak berubah di rilis ini.

## 5) Checklist Verifikasi

Sudah dilakukan sebelum rilis:

- [x] `node --check` pada tiap file JS yang diubah — semua lolos.
- [x] Penghitungan kurung kurawal `CSS_2/profile_ui.css` — seimbang.
- [x] Penghitungan tag `index.html` (`div`/`section`/`main`/`button`/`footer`/
      `aside`/`nav`/`h2`/`h3`) — seimbang.
- [x] Silang-referensi: setiap class baru yang dipakai JS/HTML (`.friend-info`,
      `.friend-avatar`, `.friend-name-wrap`) punya definisi CSS; tidak ada yang
      dipakai tanpa gaya atau didefinisikan tanpa dipakai.
- [x] `diff` isi folder terhadap zip rilis sebelumnya — cuma file yang memang
      dimaksud untuk berubah yang berbeda, tidak ada file lain yang ketiban
      tersentuh tanpa sengaja.
- [x] Pasangan `.gz` dari tiap file yang berubah diregenerasi ulang (gzip -9,
      tanpa nama berkas/timestamp di header, mengikuti gaya `.gz` yang sudah
      ada di proyek ini) dan diverifikasi round-trip (`gunzip` hasilnya harus
      identik byte-demi-byte dgn sumbernya).
- [x] Daftar path di dalam zip dicek sama persis dgn zip rilis sebelumnya
      (tidak ada file hilang/nyasar, termasuk `.htaccess` yang memang harus
      selalu ikut — lihat riwayat masalah ini di `CHANGELOG_LENGKAP.md`).

## 6) Riwayat Dokumen Ini

| Versi proyek | Perubahan pada dokumen ini |
|---|---|
| `V.0.9.1.0` | Dokumen ini belum ada. |
| `V.0.9.1.1` | Dokumen ini dibuat pertama kali, sekaligus menambahkan komentar penanda `[FIX v0.9.1.0]`/`[UBAH v0.9.1.0]` di tiap file kode yang relevan (lihat tabel Bagian 4). Tidak ada perubahan perilaku aplikasi di versi ini — murni dokumentasi + komentar. |
