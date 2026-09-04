# CHANGELOG LENGKAP — 21: Ruang Lilin

## Rilis `V.0.9.2.0` — Penggabungan Rekonsiliasi Keenam ("Merge #6")

**Tanggal rilis:** Jumat, 4 September 2026 WIB
**Versi sebelumnya:** `V.0.9.0.0` (hasil Merge #5) → tujuh cabang paralel → `V.0.9.2.0`
**Jenis rilis:** Penggabungan besar (bukan fitur tunggal) — tujuh paket update independen disatukan menjadi satu basis kode yang utuh dan bisa dijalankan.

---

## 📖 Ringkasan Eksekutif

Tujuh paket pembaruan (`V_0_9_0_0_PERBAIKAN_GALERI`, `V_0_9_1_0_ANIMASI_KARTU_SPESIAL_HD`, `V_0_9_1_0_PERBAIKAN_SISTEM_RANKING`, `V_0_9_1_0_OPTIMASI_PERFORMA`, `V_0_9_1_0_DOKUMENTASI_STATUS_INDEXING`, `V_0_9_1_1_PENJELASAN_TEKNIS_TEMAN_CHAT_HISTORY`, dan `V_0_9_1_0_LOBI_DUEL_KARTU_PERINGKAT_1`) diterima sebagai tujuh salinan penuh proyek, bukan sebagai patch/diff — pola yang sama seperti lima rekonsiliasi sebelumnya. Audit checksum per-file terhadap ketujuhnya mengonfirmasi bahwa semuanya bercabang dari basis bersama yang sama, `V.0.9.0.0` (hasil Merge #5), lalu berkembang **tanpa saling mengetahui satu sama lain**.

**Temuan penting selama audit:** lima dari tujuh paket ternyata sama-sama mengklaim nomor versi `V.0.9.1.0` untuk pekerjaan yang sama sekali berbeda (animasi banner kartu spesial, perbaikan Papan Peringkat, optimasi performa, perbaikan indexing Google, dan fitur Lobi Duel), satu paket memakai skema *build-metadata* terpisah (`V.0.9.0.0+PERBAIKAN_GALERI`) alih-alih menaikkan segmen versi numerik, dan satu paket (chat/teman/riwayat) sempat merilis dua kali berurutan (`V.0.9.1.0` lalu `V.0.9.1.1` beberapa jam kemudian, keduanya di hari yang sama). Persis seperti lima kali sebelumnya, **nomor versi pada nama paket tidak bisa dipakai sebagai urutan atau indikator konflik sebenarnya**; urutan & titik-temu konflik direkonstruksi dengan membandingkan checksum tiap file terhadap basis `V.0.9.0.0` dan membaca `CHANGELOG_LENGKAP.md`/dokumen `.md` pendamping bawaan tiap paket.

Empat file mengalami **konflik nyata** (disunting independen oleh dua paket atau lebih pada region yang sama atau berdekatan) dan butuh penggabungan manual baris-demi-baris: `CSS_1/core_ui.css` (2 kontributor), `CSS_1/online_ui.css` (2 kontributor, salah satunya berupa konflik *keputusan desain*, bukan cuma konflik teks — lihat Keputusan Teknis #2), `JSS_2/JSS_HISTORY.js` (2 kontributor), dan `JSS_2/JSS_MULTIPLAYER.js` (**4 kontributor sekaligus** — konflik terbesar dalam riwayat proyek ini sejauh ini). `index.html` disunting oleh ketujuh paket sekaligus (mayoritas cuma version-bump, tapi 4 di antaranya juga membawa perubahan struktural nyata).

**Hasil akhir:** **117 berkas** (108 berkas dari basis Merge #5, +2 berkas baru murni — `JSS_2/JSS_LOBBY_ENHANCE.js` & `CSS_2/lobby_enhance.css` — dan +2 pasangan `.gz` yang menyertainya; 5 berkas backend `api/` diperluas, bukan baru), seluruh tujuh kontribusi tergabung tanpa kehilangan fitur, tanpa konflik yang tidak terselesaikan. Lolos pemeriksaan sintaks (`node --check` untuk 11 file JS yang diubah/digabung), penghitungan kurung kurawal seimbang untuk seluruh CSS/JS yang digabung manual, penghitungan tag HTML seimbang untuk `index.html` (271 `<div>`, 142 `<button>`, dst — lihat bagian Verifikasi), 342 `id` HTML diverifikasi unik tanpa duplikat, seluruh 28 pasangan `.gz` diverifikasi *roundtrip* byte-per-byte terhadap sumbernya, dan seluruh referensi `href`/`src` lokal di `index.html` diverifikasi menunjuk berkas yang benar-benar ada.

---

## 🗓️ Garis Waktu Tujuh Cabang Sumber

| Paket | Versi diklaim | Tanggal & waktu pengerjaan (WIB) | Isi sebenarnya |
|---|---|---|---|
| `V_0_9_0_0_PERBAIKAN_GALERI` | `V.0.9.0.0+PERBAIKAN_GALERI` (cabang A) | Rabu, 2 Sep 2026 (waktu tidak tercatat di sumber) | 3 perbaikan sisa Merge #5 di layar Galeri Bonus |
| `V_0_9_1_0_ANIMASI_KARTU_SPESIAL_HD` | `V.0.9.1.0` (cabang B) | Rabu, 2 Sep 2026 (waktu tidak tercatat di sumber) | Banner pengumuman kartu spesial utk kedua pihak + visual HD |
| `V_0_9_1_0_PERBAIKAN_SISTEM_RANKING` | `V.0.9.1.0` (cabang C) | Rabu, 2 Sep 2026 (waktu tidak tercatat di sumber) | 4 fungsi bantu Papan Peringkat yang hilang, ditulis ulang |
| `V_0_9_1_0_OPTIMASI_PERFORMA` | `V.0.9.1.0` (cabang D) | Rabu, 2 Sep 2026 (waktu tidak tercatat di sumber) | Patch PageSpeed Insights (inline CSS, preload, minifikasi) |
| `V_0_9_1_0_DOKUMENTASI_STATUS_INDEXING` | `V.0.9.1.0` (cabang E) | Rabu, 2 Sep 2026 (waktu tidak tercatat di sumber) | Perbaikan `.htaccess` (redirect HTTPS) + investigasi lanjutan sitemap Google di hari yang sama |
| `V_0_9_1_1_PENJELASAN_TEKNIS_TEMAN_CHAT_HISTORY` | `V.0.9.1.0` → `V.0.9.1.1` (cabang F, 2 rilis berurutan) | Rabu, 2 Sep 2026, **09:15–10:40** (fitur) lalu **11:05** (dokumentasi) | Kartu Daftar Teman, fix identitas Chat, tombol Riwayat Pertandingan; disusul dokumentasi teknis murni |
| `V_0_9_1_0_LOBI_DUEL_KARTU_PERINGKAT_1` | `V.0.9.1.0` (cabang G) | Rabu, 2 Sep 2026 (waktu tidak tercatat di sumber) | Panel Duel layar tunggu, tooltip kartu trump, aturan Peringkat vs Latihan |

Tujuh cabang di atas seluruhnya dikerjakan **di hari yang sama** (Rabu, 2 September 2026), dari basis `V.0.9.0.0` yang sama, tanpa satu pun tahu keberadaan enam lainnya — jarak antar-cabang di rilis ini jauh lebih rapat dari lima merge sebelumnya (yang biasanya terentang beberapa hari). Sesi penggabungan ini sendiri (penulisan dokumen ini serta seluruh kerja rekonsiliasi kode) dilakukan **Jumat, 4 September 2026**, dua hari setelah cabang-cabang di atas dikerjakan.

> **Catatan kejujuran data:** hanya cabang F (chathistory) yang mencatat jam pengerjaan presisi di `CHANGELOG_LENGKAP.md` bawaannya. Enam cabang lain hanya mencatat tanggal ("Rabu, 2 September 2026") tanpa jam spesifik — kolom di atas menuliskan itu apa adanya alih-alih mengarang jam yang terkesan presisi padahal tidak ada dasarnya.

---

## 1️⃣ Cabang A — Pembersihan Galeri & Kartu Trump (`V.0.9.0.0+PERBAIKAN_GALERI`)

Tiga masalah sisa Merge #5 yang lolos verifikasi saat itu, semuanya di layar **Galeri Bonus**:

1. **Cahaya/glow tersisa di tombol "Galeri Bonus"** — kelas `unlocked` (peninggalan sistem gerbang-kunci Mode Sulit yang sudah dihapus Merge #5) tetap ditambahkan `JSS_2/JSS_EVENTS.js` (`updateMainMenuGalleryButton`) di setiap kunjungan menu awal, membuat tombolnya bercahaya terus padahal status "baru terbuka" sudah tidak relevan lagi. Diperbaiki dengan berhenti menambahkan kelas tsb + menghapus aturan CSS `box-shadow` terkait di `CSS_1/online_ui.css`.
2. **Galeri bisa diakses ganda lewat menu online** — tombol "Kartu Spesial Online" (`btn-online-cards`) diam-diam membuka `#screen-gallery` yang sama dengan menu awal, lewat `window.mpOpenOnlineCards()`. Tombol, fungsi, dan overlay mati `overlay-online-cards` (peninggalan sebelum Merge #5 menyatukan galeri) semuanya dihapus dari `index.html` & `JSS_2/JSS_MULTIPLAYER.js`.
3. **Kartu trump di tab "Kartu Spesial" tidak rapi** — 45 kartu (26 dasar + 19 eksklusif musuh) tercampur di satu grid `flex-wrap` tanpa pemisah/judul, tinggi baris tidak sejajar. `JSSCODETAWANAN.js`: `renderGalleryTab()` dipecah jadi 3 bagian berjudul sendiri-sendiri; `CSS_1/core_ui.css`: kelas baru `.cards-grid` (CSS Grid, `align-items:stretch`) ditambahkan sebagai **tambahan** di samping `.gallery-grid` lama (tab "Kartu Angka" sengaja tidak ikut diubah, lihat dokumen pendamping).

Dokumen lengkap (akar masalah, potongan kode sebelum/sesudah, daftar "jangan lakukan ini"): **`PENJELASAN_PERUBAHAN_GALERI.md`** — dipertahankan utuh di root proyek.

## 2️⃣ Cabang B — Animasi Pengumuman Kartu Spesial (Kedua Pihak) + Visual HD (`V.0.9.1.0`)

Sebelum rilis ini, banner besar `#card-announce` **hanya muncul saat lawan** memainkan kartu spesial — pemain sendiri cuma dapat efek suara tanpa animasi, di semua mode.

1. **Banner kini tampil untuk KEDUA pihak**, di semua mode: Kampanye/Tawanan/boss/tutorial (`applySpecial()` di `JSSSYSTEM.js` memanggil `announceCard()` tanpa syarat, dulu hanya `"opponent"===e`) dan Multiplayer online (fungsi baru `mpAnnounceTrump()` di `JSS_MULTIPLAYER.js`, mendeteksi trump baru milik pemain **dan** lawan lewat dua blok `if` independen di `J()`, bukan satu kondisi gabungan seperti dulu).
2. **Label teks otomatis menyesuaikan pelaku:** `KAMU MEMAINKAN` (milik sendiri) vs `{LAWAN} MEMAINKAN`/`KARTU RAHASIA {LAWAN}` (tidak berubah, milik lawan).
3. **Class CSS baru `.mine`/`.theirs`** pada `#card-announce` (emas/brass utk milik sendiri, merah redup utk milik lawan biasa) — ikut dibersihkan dari `announceDesperatePhase()` (`JSSBOSSCODE.js`) & blok `mirrorReflect` (`JSS_MULTIPLAYER.js`) supaya tidak salah warna kalau banner lain menyusul tepat sesudahnya.
4. **Visual "HD":** badge ikon melingkar + cincin nyala berdenyut (`announceRing`), sapuan cahaya diagonal (`announceSweep`), animasi masuk (`announceIn`) dirombak dari pop-scale sederhana jadi blur-masuk + rotasi + gerak vertikal yang mereda.

Dokumen lengkap: **`PENJELASAN_ANIMASI_KARTU_SPESIAL_V0.9.1.0.md`** — dipertahankan utuh di root proyek.

## 3️⃣ Cabang C — Perbaikan Total Papan Peringkat / Sistem Ranking (`V.0.9.1.0`)

**Bug kritis:** Papan Peringkat gagal total dibuka di kedua tabnya — `window.__lbPodiumSlot is not a function` (tab Global) dan `window.__lbTierStyle is not a function` (tab Peringkat Saya). Audit menyeluruh mengonfirmasi **empat fungsi bantu** yang dipanggil `mpLoadLeaderboard()`/`mpLoadMyRank()` di `JSS_MULTIPLAYER.js` — `__lbPodiumSlot`, `__lbRowHtml`, `__lbTierStyle`, `__lbTierProgress` — **tidak pernah didefinisikan di mana pun**, kemungkinan besar hilang di salah satu dari lima sesi penggabungan cabang sebelumnya.

Keempatnya ditulis ulang dari nol (podium medali emas/perak/perunggu, baris peringkat dengan bar win-rate, badge tujuh tingkatan warna abu-abu→emas mengikuti `rankTitleForRating()` di `api/db.php`) dan disisipkan tepat sebelum `mpLoadLeaderboard()`. Penyisipan ini memutus rangkaian *comma-expression* panjang yang sudah ada di berkas tsb — tiga titik potong (`,`→`;`) ditambahkan tepat sebelum & sesudah blok baru supaya rangkaian lama tetap valid. Diverifikasi lewat `node --check` dan pembandingan keluaran byte-demi-byte terhadap `PREVIEW_LEADERBOARD.html`.

## 4️⃣ Cabang D — Patch Performa / PageSpeed Insights (`V.0.9.1.0`)

Dipicu laporan PageSpeed Insights (Mobile) atas `V.0.9.0.0` live: Performa 72, FCP 3,2 dtk, LCP 4,5 dtk, Speed Index 6,8 dtk. Sekaligus menuntaskan item #4 "Rencana Update Masa Mendatang" Merge #5 (jalankan ulang minifikasi di atas basis gabungan).

1. `CSS_1/core_ui.css` diinlinekan ke `<style>` di `<head>` (bukan lagi `<link>` render-blocking) — menghilangkan risiko FOUC tanpa request tambahan; keyframe `fadeIn` ikut disalin ke blok yang sama.
2. `CSS_2/animations.css` diubah ke pola *preload+swap* (menyamai 8 file CSS lain di proyek).
3. Audio-preloader lagu (script inline `index.html`) diubah dari paralel (`e.forEach`, 9 track/72 file sekaligus) jadi sekuensial (`e.reduce` + Promise chain) — hasil akhir identik, cuma request bersamaan turun dari 9 ke 1.
4. `JSS_2/JSS_FIT_SCREEN.js`: perbaikan *forced reflow* — baris `resetToNatural()` sebelum pengukuran `offsetWidth/Height` dihapus (terbukti tidak memengaruhi hasil ukur).
5. `#btn-main-gallery`: transisi `box-shadow` → transisi `opacity` pada `::after` (bisa di-*composite* GPU).
6. Minifikasi murni (komentar/baris kosong saja, nol perubahan logika) pada 9 berkas; `JSS_MULTIPLAYER.js` sengaja tidak disentuh.
7. `.htaccess`: fallback gzip khusus request root (`/`), karena `%{REQUEST_FILENAME}` tidak bisa dipakai untuk root di fase mod_rewrite.

## 5️⃣ Cabang E — Perbaikan Indexing Google (`V.0.9.1.0`)

Dipicu laporan Google Search Console "Tidak dapat mengambil peta situs" untuk `sitemap.xml`. Ditemukan blok pengalihan paksa HTTP→HTTPS di `.htaccess` **aktif tanpa tanda `#`**, padahal komentar di atasnya sendiri mewajibkan tetap dikomentari sampai HTTPS dicek manual — kemungkinan tanda `#` hilang saat `.htaccess` direkonstruksi ulang di Merge #5. Blok tsb dikembalikan ke status nonaktif (dikomentari), dengan catatan riwayat & syarat sebelum mengaktifkannya lagi.

**Update di hari yang sama** (dicatat langsung di dokumen sumbernya): analisis redirect HTTPS di atas terbukti benar sebagai bug, tapi kemudian ditemukan bukti kuat itu **bukan** penyebab utama sitemap gagal — sitemap selalu bisa dibuka normal di browser bahkan sebelum perbaikan ini. Dugaan terbaru yang lebih didukung bukti: sistem anti-bot wajib milik hosting (InfinityFree). Status investigasi ini **belum final** — lihat **`STATUS_INDEXING_GOOGLE.md`** (dipertahankan utuh di root) untuk analisis terkini, jangan berhenti hanya di ringkasan ini.

## 6️⃣ Cabang F — Daftar Teman, Chat, Riwayat Pertandingan + Dokumentasi (`V.0.9.1.0` → `V.0.9.1.1`)

**Rilis pertama (`V.0.9.1.0`, 09:15–10:40 WIB)** — permintaan langsung pemilik proyek, 3 perbaikan panel Profil:
1. **Daftar Teman** dirapikan jadi kartu avatar (dulu cuma teks nama kecil).
2. **Chat** — bubble pesan sendiri kini pakai username asli, bukan literal `"Kamu"` (yang menghasilkan avatar default huruf "K" utk siapa pun yang login, karena avatar dihitung dari huruf pertama teks pengirim).
3. **Riwayat Pertandingan** — tombol "Muat Lebih Sedikit"/"Muat Lebih Banyak" diubah dari disembunyikan-saat-tidak-relevan jadi selalu tampil tapi dinonaktifkan+transparan saat tidak bisa dipakai.

**Rilis kedua (`V.0.9.1.1`, 11:05 WIB, murni dokumentasi, nol perubahan perilaku)** — menambahkan `PENJELASAN_TEKNIS_PERUBAHAN_TEMAN_CHAT_HISTORY.md` (dipertahankan utuh di root) beserta komentar penanda `[FIX v0.9.1.0]`/`[UBAH v0.9.1.0]` di titik-titik perubahan pada 4 berkas, supaya alasan di balik perbaikan di atas tidak "kehapus atau disalahpahami" oleh pembaca kode atau sesi AI lain di masa depan.

## 7️⃣ Cabang G — Lobi Duel, Pratinjau Kartu Trump & Aturan Peringkat (`V.0.9.1.0`)

Permintaan langsung pemilik proyek, tiga fitur substansial, **seluruhnya lewat 1 berkas mandiri baru** (`JSS_2/JSS_LOBBY_ENHANCE.js` + `CSS_2/lobby_enhance.css`, pola IIFE sama dengan `JSS_HISTORY.js`/`JSS_REPORTS.js`) — **nol suntingan** ke `JSS_MULTIPLAYER.js` demi nol risiko regresi ke alur pencocokan lawan yang riwayat bug-nya sudah beberapa kali butuh perbaikan hati-hati:

1. **Panel Duel di layar tunggu** (`overlay-quickmatch`) — kamu & lawan berdampingan (avatar, nama, tingkatan) dengan pemisah "VS", diisi dari `window.mpUser` + polling mandiri 1,2 dtk ke `room.php?action=get`, independen total dari poll internal `mpCheckWaitingRoomOnce()`. Lencana **Peringkat**/**Latihan**, tombol salin kode ruangan, dan overlay yang diperlebar (640–660px) turut disertakan.
2. **Tooltip kartu trump di Pengaturan Ruangan** — lencana info "i" per ubin kartu (lewat `MutationObserver` yang mengamati `#rs-card-list`, tanpa menyunting fungsi render kartu yang sudah ada), hover di desktop / tap-ikon di sentuh, data dari `room.php?action=card_pool`.
3. **Aturan Peringkat vs Latihan** — hanya pertandingan **publik** (Lobi Publik atau hasil Cari Lawan; keduanya berbagi kolam yang sama & tidak pernah bisa punya Pengaturan Ruangan kustom) yang mengubah `wins`/`losses`/`rating`/`rank_points`. Ruangan privat (klasik maupun kustom) tetap dimainkan & tercatat penuh di Riwayat Pertandingan, tapi kini selalu berstatus **latihan** — statistik publik tidak tersentuh. Kolom baru `match_history.ranked` (`api/db.php`, default `1` supaya riwayat lama tidak "ditandai ulang"); `api/game_action.php` membungkus update statistik dengan kondisi `$isRanked`; `JSS_2/JSS_HISTORY.js` menampilkan lencana Peringkat/Latihan per baris + catatan `#end-practice-note` di layar akhir saat match barusan adalah latihan.

Fitur #3 di atas **menjawab langsung** pertanyaan terbuka poin #7 "Rencana Update Masa Mendatang" Merge #5 ("apakah Pengaturan Ruangan kustom sebaiknya juga tersedia utk lobi Publik?") — jawabannya ditegaskan **tidak**: lobi Publik/Cari Lawan sengaja tetap 100% klasik supaya jadi jangkar sistem peringkat yang adil.

---

## 🔀 Proses Penggabungan (Merge #6)

Sama seperti Merge #5, ketujuh paket diperlakukan sebagai tujuh snapshot penuh, bukan patch bertingkat. Untuk tiap file yang isinya berbeda di antara paket, checksum-nya dibandingkan ke basis `V.0.9.0.0` untuk mengisolasi region yang benar-benar disunting tiap cabang, lalu region-region itu digabung satu per satu. Empat file berikut butuh penggabungan manual karena disunting independen oleh 2+ cabang:

- **`CSS_1/core_ui.css`** (cabang A + B): dua region yang **tidak bertumpuk sama sekali** (blok `.gallery-grid`/`.cards-grid` milik A di satu titik, blok `.card-announce` milik B di titik lain) — digabung langsung tanpa keputusan desain apa pun yang perlu diambil.
- **`JSS_2/JSS_HISTORY.js`** (cabang F + G): fix tombol Muat Lebih Sedikit/Banyak (F, fungsi `setHistoryButtonEnabled`) dan lencana Peringkat/Latihan (G, fungsi `modeTag` + edit `renderMatchRow`/`renderEndRatingDelta`) berada di sub-region bersebelahan, tidak tumpang tindih baris — digabung bersih.
- **`CSS_1/online_ui.css`** (cabang A vs D) dan **`JSS_2/JSS_MULTIPLAYER.js`** (cabang A + B + C + F) adalah dua kasus yang butuh keputusan eksplisit — lihat Keputusan Teknis #1 & #2 di bawah.

`index.html` disunting ketujuh cabang: B, C, E sepenuhnya cuma version-bump (byte-identik satu sama lain setelah versi dinormalisasi); A, F, G, D membawa perubahan struktural nyata pada region yang saling terpisah (A: hapus tombol/overlay kartu-online-lama; F: atribut tombol riwayat; G: 3 elemen baru — link CSS, `#end-practice-note`, script baru; D: restrukturisasi `<head>` + script pemuat audio). Seluruhnya digabung ke satu basis, lalu version-bump dijalankan **sekali** di akhir ke `V.0.9.2.0` untuk seluruh 37 referensi fungsional (referensi pemuat musik `?v=V.0.8.7.0` tetap dibekukan, mengikuti keputusan Merge #5 yang masih berlaku sama).

---

## ⚙️ Keputusan Teknis Penting

1. **`JSS_2/JSS_MULTIPLAYER.js` — konflik 4 arah, diselesaikan sebagai 5 sunting-titik yang saling lepas, bukan satu region besar.** Cabang A (hapus `mpOpenOnlineCards` + 1 binding event, 2 titik terpisah — awal & akhir berkas), B (`mpAnnounceTrump` baru + pemisahan deteksi trump pemain/lawan di `J()`, 1 blok di tengah berkas), C (4 fungsi bantu Papan Peringkat, 1 blok berbeda di tengah berkas), dan F (fix identitas pesan chat, 1 baris) ternyata **menyunting lima titik yang seluruhnya tidak bertampalan satu sama lain** begitu diperiksa presisi per-karakter — meski secara kasar terlihat "tumpang tindih" kalau cuma dilihat dari rentang baris terluar cabang A (yang membentang dari dekat awal sampai dekat akhir berkas karena 2 sunting-titiknya berjauhan). Kelima sunting-titik diverifikasi tidak bertampalan sebelum digabung, lalu digabung sekaligus dan lolos `node --check`. Ini konflik 4-cabang pertama pada satu berkas dalam riwayat proyek ini.
2. **`CSS_1/online_ui.css` — konflik keputusan desain, bukan cuma konflik teks, pada `#btn-main-gallery`.** Cabang A menghapus total kelas `.locked`/`.unlocked` (termasuk `box-shadow`-nya) karena status "baru terbuka" sudah tidak relevan (lihat Cabang A di atas). Cabang D — tanpa tahu soal itu — justru mengoptimalkan `box-shadow` aturan yang **sama** jadi `opacity` pada `::after` supaya bisa di-*composite* GPU, dengan asumsi glow tsb memang seharusnya tetap ada. **Keputusan:** perbaikan cabang A dipakai (glow dihapus total) karena itu perbaikan bug yang disengaja & terdokumentasi eksplisit dengan alasan produk yang jelas ("tidak ada lagi status baru-dibuka yang sungguhan"), sedangkan optimasi cabang D jadi otomatis tidak relevan lagi begitu pemicunya (kelas `.unlocked`) tidak pernah ditambahkan lagi oleh JS manapun — mengoptimalkan animasi untuk kondisi yang tidak pernah terjadi tidak memberi manfaat performa nyata. Sisa optimasi cabang D di file yang sama (minifikasi umum) tetap tidak dibawa masuk, lihat poin #3 di bawah.
3. **Hasil minifikasi cabang D (performa) untuk `CSS_2/profile_ui.css`, `JSS_2/JSS_HISTORY.js`, `JSS_2/JSS_PROFILE_UI.js` tidak dibawa masuk apa adanya** — persis alasan yang sama seperti Keputusan Teknis #1 Merge #5 (minifikasi cabang C dulu vs perubahan fungsional 5 cabang lain): ketiga berkas ini juga disunting **fungsional** oleh cabang F dan/atau G di rilis ini, dan menggabungkan versi minifikasi dengan perubahan fungsional secara tekstual berisiko tinggi. Dikonfirmasi lewat perbandingan langsung (strip komentar+spasi keduanya lalu bandingkan byte-per-byte) bahwa perubahan cabang D pada `JSS_2/JSS_PROFILE_UI.js` & `CSS_2/profile_ui.css` **100% identik** secara fungsional dengan basis (nol logika berubah), dan pada `JSS_2/JSS_HISTORY.js` hanya kehilangan 1 komentar inline non-fungsional. **Keputusan:** kode gabungan yang mudah dibaca dipakai (bukan hasil minifikasi cabang D) utk ketiga berkas ini — pola yang sama, berulang untuk kedua kalinya, semakin menguatkan Rencana Update Masa Mendatang poin #1 di bawah (minifikasi semestinya jadi langkah build otomatis, bukan hal yang digabung manual tiap sesi rekonsiliasi).
4. **`.htaccess` cabang D (performa) hilang total dari paketnya** — kali ini bukan cuma tanda `#` yang hilang (seperti kasus cabang E di atas / cabang A&E Merge #5), tapi **seluruh berkasnya tidak ada** dalam arsip `.zip` yang diunggah, padahal `CHANGELOG_LENGKAP.md` bawaan paket tsb mendeskripsikan perubahannya secara rinci (fallback gzip request root, lihat Cabang D di atas). **Keputusan:** 3 baris `RewriteCond`+`RewriteRule` yang hilang direkonstruksi manual dari deskripsi tsb (bukan disalin byte-per-byte dari sumber asli, karena sumbernya memang tidak ada) dan ditandai eksplisit sebagai rekonstruksi lewat komentar di kode — bukan disalin diam-diam seolah itu byte asli. Digabung dengan perbaikan `.htaccess` cabang E (redirect HTTPS dikomentari ulang) tanpa konflik, karena keduanya menyentuh blok berbeda dalam berkas yang sama.
5. **`index.html` cabang D — CSS yang diinlinekan direkonstruksi ulang dari `core_ui.css` hasil gabungan Keputusan #1, bukan disalin dari snapshot `<style>` bawaan paket cabang D.** Snapshot inline milik cabang D berisi `core_ui.css` versi **sebelum** digabung dengan perubahan cabang A & B — memakainya apa adanya akan diam-diam menghilangkan perbaikan Galeri (cabang A) dan banner kartu spesial (cabang B) dari tampilan yang benar-benar dilihat pemain (karena `<style>` inline menang atas `<link>` mana pun untuk elemen yang sama). Transformasi cabang D (bungkus `core_ui.css` dlm `<style>`, ubah `animations.css` ke pola preload+swap) diterapkan ulang dengan isi `core_ui.css` hasil Keputusan #1 sebagai sumbernya, bukan isi originalnya.
6. **Nomor versi dinaikkan ke `V.0.9.2.0`, bukan `V.0.9.1.2` atau `V.0.9.3.0`.** Mengikuti pola yang sudah dipakai Merge #5 (lompat ke segmen ketiga saat merekonsiliasi banyak cabang sekaligus, bukan menambah segmen keempat yang biasa dipakai patch tunggal): tujuh cabang independen — lima di antaranya bertabrakan nomor versi `V.0.9.1.0`, ditambah satu skema `+SUFFIX` terpisah dan satu rilis ganda `V.0.9.1.0`/`V.0.9.1.1` — mustahil direpresentasikan dengan menaikkan segmen keempat begitu saja tanpa kehilangan makna "ini penggabungan rekonsiliasi, bukan patch tunggal". Segmen ketiga dinaikkan (`1`→`2`), segmen keempat direset ke `0`.

---

## 🐛 Isu Ditemukan Saat Penggabungan

- **`.htaccess` hilang total dari 1 dari 7 paket** (cabang D/performa) — lihat Keputusan Teknis #4. Berbeda dari kasus-kasus sebelumnya (tanda `#` yang hilang), kali ini seluruh berkasnya tidak ada dalam arsip.
- **Header `## Rilis` untuk entri Merge #5 hilang dari salinan `CHANGELOG_LENGKAP.md` internal 2 dari 7 paket** (cabang F/chathistory & cabang G/lobiduel) — isi entrinya sendiri (ringkasan eksekutif, breakdown cabang A–F, dst.) tetap lengkap & utuh di kedua paket, cuma baris judul `## Rilis \`V.0.9.0.0\`...`-nya yang tidak ada. Murni cacat pemformatan dokumentasi, tidak memengaruhi kode maupun keakuratan riwayat — entri final di dokumen ini memakai salinan yang lengkap (4 dari 7 paket punya baris judulnya utuh).
- **Nomor versi `V.0.9.1.0` diklaim independen oleh 5 dari 7 paket** (B, C, D, E, G) untuk pekerjaan yang sama sekali berbeda — lihat Ringkasan Eksekutif & Keputusan Teknis #6. Bukan bug, tapi konsekuensi langsung dari tidak adanya koordinasi penomoran versi lintas-sesi (lihat Rencana Update Masa Mendatang poin #1, berulang untuk keenam kalinya).
- **Status investigasi indexing Google (cabang E) belum final** — dugaan akar masalah sudah berganti sekali (redirect HTTPS → kemungkinan besar sistem anti-bot hosting) di hari yang sama paket tsb dibuat, dan pemeriksaan lanjutannya (upload ulang, cek SSL di browser, kirim ulang sitemap ke Search Console) memerlukan akses langsung ke panel hosting & Google Search Console yang tidak tersedia dari sesi manapun (baik sesi cabang E maupun sesi penggabungan ini) — lihat `STATUS_INDEXING_GOOGLE.md` & Rencana Update Masa Mendatang poin di bawah.
- **Lingkungan penggabungan ini tidak memiliki `php` CLI** (sama seperti Merge #5) — kelima berkas PHP yang disunting/diperluas cabang G (`db.php`, `game_action.php`, `room.php`, `match_history.php`, `schema.sql`) diverifikasi lewat pembacaan manual + penghitungan `{`/`}` seimbang, **bukan** `php -l` sungguhan seperti yang diklaim sesi cabang G sendiri (paket tsb kemungkinan dikerjakan di lingkungan yang memang punya PHP CLI). Direkomendasikan diverifikasi ulang dengan `php -l` sungguhan sebelum naik ke hosting produksi.

---

## ✅ Verifikasi

- **`node --check`** dijalankan pada seluruh 11 berkas `.js` yang disunting/digabung di sesi ini (`JSSBOSSCODE.js`, `JSSCODETAWANAN.js`, `JSSTUTORIALCODE.js`, `JSSSYSTEM.js`, `JSS_EVENTS.js`, `JSS_FIT_SCREEN.js`, `JSS_HISTORY.js`, `JSS_LOBBY_ENHANCE.js`, `JSS_MULTIPLAYER.js`, `JSS_PROFILE_UI.js`, `JSS_REPORTS.js`) — seluruhnya **lolos**.
- **Kurung kurawal seimbang** dihitung terpisah untuk tiap berkas CSS/JS yang digabung manual (`core_ui.css` 360=360, `online_ui.css` 281=281, `JSS_MULTIPLAYER.js` 381=381, `JSS_HISTORY.js` 49=49, dst.) — seluruhnya seimbang.
- **`index.html`:** tag `<div>` 271=271, `<button>` 142=142, `<script>` 17=17, `<style>` 1=1, `<section>` 8=8, `<head>`/`<body>`/`<html>`/`<main>`/`<footer>` masing-masing 1=1 (tag `<link>` void/self-closing, 27 dihitung wajar tanpa pasangan tutup). Seluruh 342 atribut `id` diverifikasi **unik, nol duplikat**. Seluruh referensi `href`/`src` lokal (26 unik) diverifikasi menunjuk berkas yang benar-benar ada di hasil gabungan.
- **28 pasangan `.gz` diregenerasi** (`gzip -9 -n`) untuk seluruh berkas yang isinya berubah **atau** berpotensi berubah karena digabung (termasuk yang isi akhirnya sama seperti sebelum digabung, untuk keamanan) — seluruhnya diverifikasi *roundtrip* (dekompresi `.gz` menghasilkan byte yang 100% identik dengan sumber `.js`/`.css`/`.html`-nya).
- **PHP:** kurung kurawal seimbang untuk seluruh 27 berkas `api/*.php` (termasuk 5 yang diperluas cabang G) — lihat catatan keterbatasan di atas soal `php -l` sungguhan.
- **JSON:** `package.json` & `package-lock.json` diverifikasi valid.
- Struktur direktori akhir diverifikasi lengkap (`ASSETS_GAMBAR/cards/`, `api/PHPMailer/` ikut terbawa utuh, tidak ada berkas yang tercecer).

---

## 🔴 Rencana Update Masa Mendatang — Prioritas Tinggi

1. **Pindah ke sistem kontrol versi sungguhan (Git) — rekomendasi ini sekarang sudah diulang ENAM kali berturut-turut** (sejak Merge #1 `V.0.8.1.7`), dan situasinya masih belum membaik: rilis ini harus merekonsiliasi **tujuh** cabang paralel sekaligus (termasuk satu konflik 4-arah pada satu berkas — yang pertama dalam riwayat proyek ini), lebih banyak dari merge manapun sebelumnya termasuk Merge #5 (enam cabang). Selama pengembangan tetap berupa arsip `.zip` lepas yang saling tidak tahu-menahu, pola ini nyaris pasti akan terulang untuk ketujuh kalinya — dan kompleksitas penggabungannya kemungkinan akan terus naik seiring proyek bertambah besar.
2. **Satukan proses regenerasi `.gz` ke dalam build script** — direkomendasikan sejak Merge #5 poin #3, masih belum ada; sesi ini kembali meregenerasi 28 pasangan `.gz` secara manual.
3. **Jalankan pengujian interaksi lintas-fitur di lingkungan hosting sungguhan sebelum rilis berikutnya**, khususnya kombinasi yang baru pertama kali bertemu di rilis gabungan ini dan belum pernah diuji hidup berdampingan: banner kartu spesial (cabang B) tampil untuk kedua pihak **bersamaan** dengan lencana Peringkat/Latihan & panel Duel (cabang G) yang aktif di layar yang sama; badge tingkatan Papan Peringkat (cabang C) dengan skema warna barunya berdampingan dengan lencana Peringkat/Latihan (cabang G) yang memakai istilah serupa ("Peringkat") tapi merujuk konsep berbeda (tingkatan rating vs status match) — berpotensi membingungkan pemain kalau ditampilkan berdekatan, perlu ditinjau ulang secara visual.
4. **Verifikasi `php -l` sungguhan** untuk 5 berkas PHP yang diperluas cabang G (lihat Isu Ditemukan Saat Penggabungan) sebelum dinaikkan ke hosting produksi — lingkungan penggabungan ini tidak memiliki PHP CLI.
5. **Tindak lanjuti status investigasi indexing Google (cabang E) sampai tuntas** — upload ulang `.htaccess` terbaru, cek SSL langsung di browser, kirim ulang sitemap ke Search Console, dan (dugaan terbaru) selidiki pengaturan anti-bot InfinityFree. Lihat `STATUS_INDEXING_GOOGLE.md` utk daftar lengkap.

## 🟡 Rencana Update Masa Mendatang — Prioritas Menengah

6. **Jalankan ulang minifikasi di atas basis kode `V.0.9.2.0` yang sudah tergabung ini, sebagai langkah build terpisah & berulang.** Ini permintaan yang **sama persis** dengan poin #4 Merge #5 — sempat dituntaskan sekali oleh cabang D (performa) di rilis sebelumnya, tapi begitu cabang F & G membawa perubahan fungsional baru ke 3 dari berkas yang sama, hasil minifikasi lama otomatis usang lagi dan harus dilewati (lihat Keputusan Teknis #3). Pola ini akan **terus berulang** setiap sesi rekonsiliasi selama minifikasi tetap jadi langkah manual, bukan build step otomatis di atas kode sumber yang selalu mudah dibaca.
7. **Pertimbangkan menambahkan versi/cache-busting sendiri untuk `PREVIEW_LEADERBOARD.html`** — masih belum ada, direkomendasikan sejak Merge #5 poin #6; catatan tambahan dari rilis ini: berkas tsb sekarang juga berfungsi sebagai referensi uji byte-per-byte untuk `__lbRowHtml()`/`__lbPodiumSlot()` (cabang C), jadi makin penting untuk tidak diam-diam kadaluarsa tanpa disadari.
8. **Tinjau apakah galeri gabungan perlu filter/pencarian** — Merge #5 poin #9 sudah **terjawab sebagian** oleh cabang A rilis ini (3 bagian berjudul jelas menggantikan satu tumpukan tanpa label), tapi belum ada filter/pencarian sungguhan; masih relevan ditinjau ulang sekarang isinya makin banyak.
9. **Uji visual lencana Peringkat/Latihan (cabang G) di lebar layar sempit** — elemen baru ini belum diuji hidup berdampingan dengan perubahan tata-letak riwayat pertandingan dari Merge #5 (cabang B, `#profile-history-list{flex:1}`) di perangkat sungguhan.

## 🟢 Rencana Update Masa Mendatang — Prioritas Rendah

10. Pertimbangkan opsi "salin tautan undangan langsung" (`?join=KODE`) di panel Duel (cabang G) — dicatat sebagai gagasan lanjutan oleh cabang G sendiri, belum dikerjakan.
11. Pertimbangkan riwayat head-to-head singkat di panel Duel begitu lawan dikenali — gagasan lanjutan lain dari cabang G, butuh perluasan `api/match_history.php` dengan filter per-lawan.
12. Pertimbangkan lebih banyak varian skin kartu (Merge #5 poin #8) — masih belum ada tindak lanjut di rilis manapun sejak Merge #5.
13. Linter/formatter otomatis untuk PHP lewat CI (Merge #5 poin #10) — makin relevan sekarang cabang G menambah 5 berkas PHP baru yang disunting tanpa `php -l` sungguhan (lihat Isu Ditemukan Saat Penggabungan & Prioritas Tinggi poin #4).

---

## 📋 Lampiran — Struktur File Final (`V.0.9.2.0`)

```text
├── .htaccess                          (gabungan cabang D [direkonstruksi, lihat Keputusan Teknis #4] + E)
├── index.html (+.gz)                  (gabungan ketujuh cabang — lihat "Proses Penggabungan")
├── PREVIEW_LEADERBOARD.html (+.gz)    (tidak berubah dari V.0.9.0.0)
├── robots.txt, sitemap.xml            (tidak berubah)
├── ASSETS_GAMBAR/                     (tidak berubah, termasuk cards/ dari Merge #5)
├── CSS_1/
│   ├── core_ui.css (+.gz)             (gabungan cabang A + B)
│   └── online_ui.css (+.gz)           (cabang A menang atas D — lihat Keputusan Teknis #2)
├── CSS_2/
│   ├── animations.css (+.gz)          (utuh dari cabang B)
│   ├── fit_screen.css, mobile_ux.css (+.gz)  (utuh dari cabang D)
│   ├── history_ui.css (+.gz)          (utuh dari cabang G — BARU disunting sesi ini)
│   ├── lobby_enhance.css (+.gz)       (BARU, utuh dari cabang G)
│   ├── profile_ui.css (+.gz)          (utuh dari cabang F — minifikasi cabang D tidak dibawa, lihat Keputusan Teknis #3)
│   ├── matchmaking_ui.css, reports_ui.css, stage_decor.css (+.gz)  (tidak berubah)
├── JSS_1/
│   ├── JSSBOSSCODE.js (+.gz)          (utuh dari cabang B)
│   ├── JSSCODETAWANAN.js (+.gz)       (utuh dari cabang A)
│   └── JSSTUTORIALCODE.js (+.gz)      (utuh dari cabang D, minifikasi murni)
├── JSS_2/
│   ├── JSSSYSTEM.js (+.gz)            (utuh dari cabang B)
│   ├── JSS_EVENTS.js (+.gz)           (utuh dari cabang A)
│   ├── JSS_FIT_SCREEN.js (+.gz)       (utuh dari cabang D)
│   ├── JSS_HISTORY.js (+.gz)          (gabungan cabang F + G)
│   ├── JSS_LOBBY_ENHANCE.js (+.gz)    (BARU, utuh dari cabang G)
│   ├── JSS_MULTIPLAYER.js (+.gz)      (gabungan cabang A + B + C + F — konflik 4-arah, lihat Keputusan Teknis #1)
│   ├── JSS_PROFILE_UI.js (+.gz)       (utuh dari cabang F — minifikasi cabang D tidak dibawa)
│   └── JSS_REPORTS.js (+.gz)          (utuh dari cabang D, minifikasi murni)
├── api/
│   ├── db.php, game_action.php, room.php, match_history.php, schema.sql  (diperluas cabang G)
│   └── (seluruh berkas api/ lain, termasuk PHPMailer/)  (tidak berubah)
├── PENJELASAN_PERUBAHAN_GALERI.md               (dari cabang A, dipertahankan utuh)
├── PENJELASAN_ANIMASI_KARTU_SPESIAL_V0.9.1.0.md (dari cabang B, dipertahankan utuh)
├── PENJELASAN_TEKNIS_PERUBAHAN_TEMAN_CHAT_HISTORY.md (dari cabang F, dipertahankan utuh)
├── STATUS_INDEXING_GOOGLE.md                    (dari cabang E, dipertahankan utuh — status belum final)
└── CHANGELOG_LENGKAP.md                         (dokumen ini)
```

**Total: 117 berkas** (108 dari basis Merge #5 + 2 berkas baru murni [`JSS_LOBBY_ENHANCE.js`, `lobby_enhance.css`] + pasangan `.gz`-nya + 5 berkas `api/` diperluas). Tujuh cabang, nol fitur hilang, nol konflik tak terselesaikan — termasuk konflik 4-arah pertama dalam riwayat proyek ini.

---

## Rilis `V.0.9.0.0` — Penggabungan Rekonsiliasi Kelima ("Merge #5")

**Tanggal rilis:** Selasa, 1 September 2026, 16:45 WIB
**Versi sebelumnya:** `V.0.8.7.0` (basis bersama) → enam cabang paralel → `V.0.9.0.0`
**Jenis rilis:** Penggabungan besar (bukan fitur tunggal) — enam paket update independen disatukan menjadi satu basis kode yang utuh dan bisa dijalankan.

---

## 📖 Ringkasan Eksekutif

Enam paket pembaruan (`V_0_8_7_1_CERMIN_LAPORAN`, `V_0_8_7_1_PERBAIKAN_UI_PROFIL`, `V_0_8_7_2_SEO_INDEX_GOOGLE`, `V_0_8_7_2_SKIN_KARTU_LILIN`, `V_0_8_8_0_LOBI_TERPADU_DAN_PERBAIKAN_CARI_LAWAN`, dan `V_0_8_9_0_TUTORIAL_DETAIL_MENARIK`) diterima sebagai enam salinan penuh proyek, bukan sebagai patch/diff. Audit checksum per-file terhadap keenamnya mengonfirmasi bahwa semuanya bercabang dari basis bersama yang sama, `V.0.8.7.0`, lalu berkembang **tanpa saling mengetahui satu sama lain** — pola yang sama persis seperti yang sudah terjadi empat kali sebelumnya di proyek ini (`V.0.8.1.7`, `V.0.8.3.0`, pasangan `V.0.8.5.0`/`V.0.8.6.0`, dan `V.0.8.7.0` itu sendiri). Rilis ini adalah **penggabungan rekonsiliasi kelima**.

Temuan penting selama audit: tiga dari enam paket ternyata sama-sama mengklaim nomor versi `V.0.8.7.1` untuk pekerjaan yang sama sekali berbeda (perbaikan cermin takdir, perbaikan UI profil, dan — di dalam riwayat internal paket SEO — optimasi PageSpeed), dan dua paket sama-sama mengklaim `V.0.8.7.2` (indexing SEO, dan — di dalam riwayat internal paket skin — pembukaan galeri publik). Nomor versi pada nama file **tidak bisa dipakai sebagai urutan sebenarnya**; urutan asli direkonstruksi dengan membandingkan checksum tiap file terhadap basis `V.0.8.7.0` dan membaca `CHANGELOG_LENGKAP.md` bawaan tiap paket.

Hasil akhir: **108 file**, seluruh enam kontribusi tergabung tanpa kehilangan fitur, tanpa konflik yang tidak terselesaikan, lolos pemeriksaan sintaks (`node --check` untuk 24 file JS, penghitungan kurung kurawal seimbang untuk 6 file CSS yang digabung manual, penghitungan tag seimbang untuk `index.html`), dan silang-referensi ID elemen HTML ↔ pemanggilan `getElementById`/`querySelector` di JavaScript sudah diverifikasi cocok.

---

## 🗓️ Garis Waktu Enam Cabang Sumber

| Paket | Versi diklaim | Tanggal & waktu pengerjaan (WIB) | Isi sebenarnya |
|---|---|---|---|
| `V_0_8_7_1_CERMIN_LAPORAN` | `V.0.8.7.1` (cabang A) | Sab, 29 Agu 2026, 14:20–15:45 | Perbaikan bug cermin takdir + word-wrap laporan bug |
| `V_0_8_7_1_PERBAIKAN_UI_PROFIL` | `V.0.8.7.1` (cabang B) | Sab, 29 Agu 2026, 19:10–20:35 | Perbaikan CSS overlay Profil + tombol "Muat Lebih Sedikit" |
| `V_0_8_7_2_SEO_INDEX_GOOGLE` | `V.0.8.7.1`→`V.0.8.7.2` (cabang C) | Min, 30 Agu 2026, 08:00–11:40 | Optimasi PageSpeed + SEO/indexing Google |
| `V_0_8_7_2_SKIN_KARTU_LILIN` | `V.0.8.7.1`→`V.0.8.7.2` (cabang D) | Min, 30 Agu 2026, 15:30–21:05 | Galeri publik + skin kartu baru |
| `V_0_8_8_0_LOBI_TERPADU_DAN_PERBAIKAN_CARI_LAWAN` | `V.0.8.8.0` (cabang E) | Sen, 31 Agu 2026, 09:00–13:15 | Unifikasi lobi + perbaikan bug matchmaking macet |
| `V_0_8_9_0_TUTORIAL_DETAIL_MENARIK` | `V.0.8.8.0`→`V.0.8.9.0` (cabang F) | Sen, 31 Agu 2026, 20:00–23:50 | Tutorial kontekstual + lilin biru |

Enam cabang di atas dikerjakan dalam rentang **kurang dari 72 jam**, semuanya dari basis `V.0.8.7.0` yang sama, tanpa satu pun tahu keberadaan lima lainnya.

---

## 1️⃣ Cabang A — Cermin Takdir & Laporan Bug (`V.0.8.7.1`)
**Dikerjakan:** Sabtu, 29 Agustus 2026, 14:20–15:45 WIB

- **Bug ditemukan:** kartu spesial `mirrorGuard` ("Cermin Takdir") efeknya tersembunyi & pasif — begitu memantulkan serangan lawan, korban (lawan) tidak pernah diberi tahu kenapa serangannya balik menyerangnya sendiri, membuatnya terlihat seperti bug alih-alih fitur.
- **Perbaikan `api/engine.php`:** log entri baru ditambahkan sesaat setelah `mirrorGuard` terpicu, ditandai `target` berisi sisi (`host`/`guest`) korban pantulan — entri log biasa (bukan `mirrorGuard` itu sendiri) tetap tidak menyebut nama kartu secara eksplisit, supaya efeknya tetap terasa misterius sampai benar-benar terpicu, sesuai desain aslinya.
- **`JSS_2/JSSSYSTEM.js` — `renderTableTrumps()`:** indikator "kartu terpakai" pada slot kartu trump di meja kini mengenali `mirrorGuard` secara eksplisit lewat pengecekan `a.mirror_active`, alih-alih hanya mengandalkan daftar umum yang sebelumnya tidak menangkap kartu pasif ini dengan benar.
- **`JSS_2/JSS_MULTIPLAYER.js`:** pemindai log baru (`mirrorRevealSeen`, direset bersamaan dengan penanda ronde lain) membaca entri log baru dari `engine.php` di atas dan memicu banner pengumuman kartu (`card-announce`) di sisi korban begitu pantulan terjadi.
- **`CSS_2/reports_ui.css`:** teks panjang pada deskripsi laporan bug sekarang di-*wrap* dengan benar (`overflow-wrap:break-word` pada kontainer deskripsi) — sebelumnya teks laporan yang sangat panjang bisa meluber keluar kartu overlay di layar sempit.

## 2️⃣ Cabang B — Perbaikan UI Profil (`V.0.8.7.1`)
**Dikerjakan:** Sabtu, 29 Agustus 2026, 19:10–20:35 WIB

- **Bug ditemukan:** spesifisitas CSS pada tombol toolbar panel Profil (Daftar Teman/Inbox) kalah oleh aturan umum `.btn`, membuat sebagian tombol toolbar tampil dengan padding/ukuran yang salah di beberapa lebar layar.
- **`CSS_2/profile_ui.css`:** aturan `.profile-toolbar-btn` dipertegas spesifisitasnya, layout flex pada `.profile-panel-header` dirapikan, lebar shell (`.profile-shell`) dan sidebar disesuaikan, margin/gap antar tombol dinormalkan.
- **`CSS_2/history_ui.css`:** `#profile-history-list{flex:1}` ditambahkan supaya daftar riwayat mengisi ruang yang tersedia secara konsisten; `.profile-history-actions{display:flex;gap:10px}` sebagai kontainer baru untuk dua tombol muat.
- **`index.html`:** tombol baru `#btn-profile-history-less` ("Muat Lebih Sedikit") ditambahkan berdampingan dengan tombol lama `#btn-profile-history-more`, dibungkus `<div class="profile-history-actions">`.
- **`JSS_2/JSS_HISTORY.js`:** fungsi baru `collapseMatchHistory()` mengembalikan daftar riwayat ke jumlah tampilan awal; `updateHistoryActionButtons()` menyembunyikan/menampilkan kedua tombol sesuai posisi *scroll* riwayat saat ini.

## 3️⃣ Cabang C — Optimasi PageSpeed & SEO/Indexing Google (`V.0.8.7.1`→`V.0.8.7.2` internal)
**Dikerjakan:** Minggu, 30 Agustus 2026, 08:00–11:40 WIB

Cabang ini menyelesaikan dua pekerjaan berurutan di dalam sesinya sendiri:

**Tahap PageSpeed Insights (08:00–10:15):**
- Empat berkas CSS (`online_ui.css`, `reports_ui.css`, `history_ui.css`, `matchmaking_ui.css`) yang sebelumnya dimuat lewat `<link rel="stylesheet">` biasa (*render-blocking*) diubah ke pola *preload* + `onload` + `<noscript>` — pola yang sama yang sudah dipakai untuk `stage_decor.css`, `mobile_ux.css`, `fit_screen.css`, dan `profile_ui.css`. `core_ui.css` dan `animations.css` **sengaja dibiarkan** *render-blocking* karena keduanya dibutuhkan sebelum *first paint*.
- Seluruh berkas CSS/JS diminifikasi ulang (level 1 clean-css untuk CSS; terser dengan `mangle.toplevel:false` untuk JS, supaya nama fungsi/variabel level-atas tetap terbaca untuk *debugging* mendatang).
- **Catatan penggabungan:** hasil minifikasi dari cabang ini **tidak dibawa masuk** ke rilis `V.0.9.0.0` ini — lihat bagian "Keputusan Teknis Penting" di bawah untuk alasannya.

**Tahap SEO & Indexing Google (10:15–11:40):**
- `<title>` diperpanjang menjadi lebih deskriptif: *"21: Ruang Lilin — Game Kartu Online Kampanye & Multiplayer"*.
- `<meta name="robots" content="index, follow">` ditambahkan secara eksplisit.
- `og:locale` (`id_ID`), `og:image:width` (200), `og:image:height` (283) dilengkapi untuk pratinjau tautan yang lebih akurat di media sosial.
- Blok `<script type="application/ld+json">` baru berisi data terstruktur `schema.org/VideoGame` ditambahkan untuk membantu Google memahami konteks halaman sebagai game.
- `.htaccess`: blok `<IfModule mod_mime.c>` baru memaksa `Content-Type` yang benar untuk `.xml` dan `.txt`, supaya Google Search Console tidak salah membaca `sitemap.xml`/`robots.txt`.
- `sitemap.xml`: `<lastmod>` diperbarui.
- **Bug ditemukan & dicatat oleh cabang ini sendiri:** perintah pembuatan arsip `.zip` yang dipakai untuk paket `V.0.8.7.1` sebelumnya (di dalam riwayat internal cabang ini) ternyata **mengecualikan berkas berawalan titik** seperti `.htaccess` secara tidak sengaja. Temuan ini terbukti relevan di luar cabang ini sendiri — dua dari enam paket yang diunggah ke sesi penggabungan ini (`CERMIN_LAPORAN` dan `LOBI_TERPADU`) **juga** kehilangan `.htaccess` karena sebab yang sama. Lihat Bagian "Isu Ditemukan Saat Penggabungan" di bawah.
- **Pengecualian yang disengaja:** referensi *query-string* cache-busting pada pemuat musik (`ASSETS_LAGU/.../..._PART{n}.js?v=...`) **sengaja tidak ikut dinaikkan** karena berkas audio itu sendiri tidak berubah — menaikkan versinya hanya akan memaksa pemain mengunduh ulang berkas musik yang identik. Keputusan ini **dipertahankan** di rilis `V.0.9.0.0` (lihat bagian teknis di bawah).

## 4️⃣ Cabang D — Galeri Publik & Skin Kartu Lilin (`V.0.8.7.1`→`V.0.8.7.2` internal)
**Dikerjakan:** Minggu, 30 Agustus 2026, 15:30–21:05 WIB

**Tahap Galeri Bonus Jadi Publik (15:30–18:10):**
- Galeri Bonus sebelumnya terkunci di balik kemenangan Mode Sulit pada Kampanye. Gerbang ini **dihapus** — galeri kini terbuka untuk siapa saja.
- `JSS_2/JSSSYSTEM.js`: seluruh sistem client-side untuk pengecekan status buka-kunci (`fetchGalleryStatus()`, `isHardClear()`, `isGalleryUnlocked()`, `markHardClear()`, `cachedGalleryStatus`) **dihapus**. `endGame()` dan `revealEndScreen()` disederhanakan mengikuti penghapusan ini. Lencana prestasi Mode Sulit di layar akhir permainan **tetap tampil** seperti biasa — itu murni penanda pencapaian, bukan lagi gerbang akses.
- Tab "Kartu Spesial" Kampanye dan tombol terpisah "Kartu Spesial Online" **disatukan** menjadi satu galeri dengan tab, menggantikan dua tampilan terpisah sebelumnya.
- Bug lama ditemukan & diperbaiki sekalian: variabel `galleryReturnScreen` (penentu ke layar mana tombol "Kembali" di galeri harus mengarah) sebelumnya **ditulis tapi tidak pernah dibaca** — tombol kembali selalu mengarah ke layar yang salah. Kini benar-benar dipakai.
- Backend (`campaign.php`, tabel `hard_clear`) **tidak disentuh** — riwayat pencapaian pemain tetap tersimpan seperti sebelumnya, hanya tidak lagi dipakai untuk membatasi akses.

**Tahap Skin Kartu Lilin & Perkamen (18:10–21:05):**
- Desain kartu baru: tekstur perkamen dengan angka bergaya tulisan tangan (11 desain wajah kartu, nilai 1–11) + 1 desain punggung kartu (lilin menyala di atas lempeng logam, bingkai berukir motif ranting).
- Diterapkan ke kartu di meja permainan (`.pcard`) **dan** kartu pratinjau galeri (`.gallery-num-card`) sekaligus.
- Kartu tertutup/tersembunyi (`.pcard.back`, `.pcard.peek`) tetap aman — urutan aturan CSS memastikan aturan per-nilai kartu diterapkan **sebelum** aturan status tertutup, supaya kartu yang seharusnya tersembunyi tidak ikut menampilkan gambar wajahnya.
- Teks angka lama tidak dihapus dari kode, hanya disembunyikan (`color:transparent`) — mempermudah *rollback* jika suatu saat diperlukan.
- 12 aset gambar baru ditambahkan ke `ASSETS_GAMBAR/cards/` (`card-1.png` s.d. `card-11.png`, `card-back.png`).
- `JSS_1/JSSCODETAWANAN.js`: rendering tab galeri kini menyertakan atribut `data-value` per kartu supaya selektor CSS berbasis nilai dapat mengenali kartu yang tepat; daftar kartu khusus mode online (`window.ONLINE_CARD_POOL`, diekspos dari `JSS_2/JSS_MULTIPLAYER.js`) ditambahkan sebagai bagian dari tab galeri gabungan.

## 5️⃣ Cabang E — Unifikasi Lobi & Perbaikan Bug Cari Lawan (`V.0.8.8.0`)
**Dikerjakan:** Senin, 31 Agustus 2026, 09:00–13:15 WIB

**Perbaikan bug "Cari Lawan macet di layar tunggu" (09:00–10:30):**
- **Akar masalah:** browser (terutama di ponsel, saat layar terkunci atau tab/app dipindah ke latar belakang) sering membekukan atau memperlambat drastis `setInterval` demi hemat baterai. Polling status ruangan jadi berhenti atau telat **tepat** saat lawan baru saja bergabung — sisi yang menunggu tidak otomatis berpindah ke layar permainan walau lawannya sudah masuk lebih dulu.
- **Perbaikan 1:** logika polling diekstrak jadi fungsi bernama `mpCheckWaitingRoomOnce()`, dipanggil **langsung sekali** begitu `q()` mulai — sebelumnya harus menunggu *tick* pertama interval (3 detik) yang selalu terbuang percuma di kasus tercepat.
- **Perbaikan 2:** `mpResumePollingIfNeeded()` didaftarkan ke event `visibilitychange`, `focus`, dan `pageshow` — begitu tab/app terlihat kembali, status ruangan (dan status permainan yang sedang berjalan, lewat `mpCheckGameStateOnce()`) divalidasi ulang segera tanpa menunggu *tick* interval berikutnya.

**Unifikasi Lobi (10:30–13:15):**
- Sebelumnya ada **3 tombol + 3 layar terpisah** dengan gaya UI yang tidak seragam: "Buat Ruangan" (`overlay-lobby`), "Lobi Publik" (`overlay-public-lobbies`), dan "Cari Lawan" (`overlay-quickmatch`).
- Kini "Buat Ruangan" dan "Lobi Publik" **digabung** jadi satu tombol ("Buat / Gabung Ruangan") dan satu layar (`overlay-lobby`), dengan toggle Privat/Publik bergaya sama seperti toggle Waktu Giliran/Nyawa Awal di Pengaturan Ruangan (konsistensi visual). Daftar lobi publik yang sedang terbuka ditampilkan langsung di layar yang sama untuk dijelajahi/digabungi. `overlay-public-lobbies` yang lama **dihapus sepenuhnya** dari `index.html` — seluruh elemen & fungsinya kini hidup di `overlay-lobby`.
- Layar tunggu (baik menunggu tamu di ruangan privat, menunggu pemain lain di lobi publik, **maupun** mencari lawan lewat Cari Lawan) kini **satu overlay yang sama** (`overlay-quickmatch`), ditulis ulang isinya secara dinamis lewat `mpOpenWaitOverlay()` supaya UI-nya seragam di ketiga skenario.
- **Aturan emas yang dijaga tetap:** pengaturan kustom ruangan (waktu giliran/nyawa awal/kartu trump yang diizinkan) **tetap hanya berlaku** untuk ruangan `visibility=private`. Saat host memilih Publik, tombol Pengaturan Kustom otomatis disembunyikan dan field `settings` sama sekali tidak dikirim ke server — lapis pertahanan tambahan di sisi klien, bukan pengganti validasi server yang tetap jadi jaminan utama (`$visibility==='private'` di `api/room.php` & `OnlineEngine::resolveSettingsForRoom` di `api/engine.php`, **tidak disentuh** oleh cabang ini).
- **Peningkatan visual kartu trump di Pengaturan Ruangan:** daftar kartu trump yang sebelumnya hanya berupa *checkbox* + nama teks polos kini dirender sebagai ubin visual berikon, memakai SVG yang sama dengan kartu spesial di meja permainan & galeri (`iconSvg()`/`window.ICONS`) — host bisa langsung mengenali kartu dari bentuknya, bukan cuma dari nama. Klik ubin untuk mengaktifkan/menonaktifkan, lingkaran centang muncul saat aktif, warna teal untuk kartu eksklusif Mode Online.
- **Catatan:** perubahan pada cabang ini **tidak menyertakan pembaruan `CHANGELOG_LENGKAP.md` internalnya sendiri** — seluruh deskripsi di atas direkonstruksi langsung dari kode dan komentar sumber (`[UNIFIKASI LOBI]`, `[PERBAIKAN BUG]`, `[VISUAL KARTU]`) selama proses penggabungan ini.

## 6️⃣ Cabang F — Tutorial Kontekstual & Lilin Biru (`V.0.8.8.0`→`V.0.8.9.0` internal)
**Dikerjakan:** Senin, 31 Agustus 2026, 20:00–23:50 WIB

**Tahap `V.0.8.8.0` — pilihan keluar tutorial + lilin biru (20:00–21:40):**
- Tombol `btn-tutor-skip` yang sebelumnya langsung memanggil `exitTutorialToMenu()` kini memanggil `showTutorialExitChoice()` — pemain diberi pilihan eksplisit "Langsung Main Kampanye" atau "Kembali ke Menu Kampanye" alih-alih langsung dikeluarkan begitu saja.
- Lilin lawan (`wax-opp`) selama sesi tutorial kini benar-benar dirender **biru** (bukan cuma disebutkan lewat teks) — `renderCandles()`/`setCandle()` di `JSS_2/JSSSYSTEM.js` diberi parameter tambahan yang mengalir ke kelas CSS baru `.candle.blue-flame` (`fill`/`filter` khusus untuk nyala & inti api) di `CSS_1/core_ui.css`, supaya secara visual jelas bahwa lawan tutorial bukan pemain sungguhan.

**Tahap `V.0.8.9.0` — tutorial diperkaya (21:40–23:50):**
- Isi tutorial diperkaya dengan reaksi kontekstual kecil yang ditempel berdasarkan kondisi permainan saat itu (mis. kartu yang baru dimainkan lawan, status *bust*, kartu spesial yang sedang aktif) — lihat penanda `bust`/`usedSpecial`/`oppSpecial` baru di `JSS_1/JSSTUTORIALCODE.js`.
- `CSS_1/core_ui.css`: animasi masuk baru (`tutorNoteIn`, 0.35 detik *ease-out*) untuk kartu catatan tutorial (`.tutor-note-card`), menggantikan kemunculan instan yang terasa kaku.
- **Catatan penggabungan:** `JSS_2/JSS_EVENTS.js` dan `JSS_2/JSSSYSTEM.js` **tidak disentuh** di tahap `V.0.8.9.0` ini — perubahan pada kedua berkas itu berasal dari tahap `V.0.8.8.0` di atas (lilin biru) dan sudah tergabung sebagai bagian dari kontribusi cabang F secara keseluruhan.

---

## 🔀 Proses Penggabungan (Merge #5)
**Dikerjakan:** Selasa, 1 September 2026, 09:05–16:45 WIB

| Waktu (WIB) | Langkah |
|---|---|
| 09:05–09:35 | Ekstraksi 6 arsip `.zip`; audit checksum per-file lintas paket untuk memetakan file yang identik vs. berbeda |
| 09:35–10:20 | Pembacaan `CHANGELOG_LENGKAP.md` bawaan tiap paket untuk merekonstruksi silsilah asli; ditemukan pola percabangan `V.0.8.7.1` rangkap tiga dan `V.0.8.7.2` rangkap dua |
| 10:20–11:15 | Isolasi diff presisi per cabang terhadap basis `V.0.8.7.0` (bukan sekadar mempercayai narasi changelog) menggunakan pembanding berbasis posisi karakter, dengan verifikasi non-tumpang-tindih antar cabang untuk tiap berkas |
| 11:15–13:40 | Penggabungan kode: `api/engine.php`, `JSS_1/JSSCODETAWANAN.js`, `JSS_1/JSSTUTORIALCODE.js` (ambil utuh dari cabang tunggal terkait); `JSS_2/JSSSYSTEM.js` (gabungan 3 cabang: A + D + F); `JSS_2/JSS_MULTIPLAYER.js` (gabungan 3 cabang: A + D + E); `JSS_2/JSS_EVENTS.js` (gabungan 2 cabang: D + F); `CSS_1/core_ui.css` (gabungan 2 cabang: D + F); `CSS_2/matchmaking_ui.css`, `reports_ui.css` (ambil utuh dari cabang tunggal terkait) |
| 13:40–14:50 | Penggabungan `index.html`: penggantian blok `<head>` (cabang C), pembukaan kunci galeri + label tab (cabang D), penghapusan tombol "Lobi Publik" & penggantian blok `overlay-lobby`/`overlay-quickmatch` (cabang E) |
| 14:50–15:05 | Penggabungan `.htaccess` (tambahan blok `mod_mime` dari cabang C); pengecekan `sitemap.xml`/`robots.txt` (tidak berubah, `lastmod` sudah sesuai) |
| 15:05–15:20 | Penambahan 12 aset gambar skin kartu baru (`ASSETS_GAMBAR/cards/`) |
| 15:20–15:40 | Version bump menyeluruh: seluruh referensi `?v=V.0.8.7.x` fungsional & lencana versi di `index.html` dinaikkan ke `V.0.9.0.0`, dengan pengecualian satu referensi pemuat musik yang sengaja dipertahankan (lihat bagian teknis) |
| 15:40–16:05 | Regenerasi 25 pasangan `.gz` dari sumber hasil gabungan |
| 16:05–16:25 | Verifikasi: `node --check` untuk 24 file JavaScript (seluruhnya lolos), penghitungan kurung kurawal untuk 6 file CSS yang digabung manual (seluruhnya seimbang), penghitungan tag untuk `index.html` (`div`/`section`/`main`/`button`/`footer` seluruhnya seimbang), silang-referensi ID HTML ↔ referensi JavaScript (seluruh ID yang dipakai JS gabungan tersedia di HTML gabungan; tidak ada sisa referensi ke elemen yang sudah dihapus) |
| 16:25–16:45 | Penulisan `CHANGELOG_LENGKAP.md` final ini; pengemasan hasil akhir |

---

## ⚙️ Keputusan Teknis Penting

1. **Hasil minifikasi cabang C (PageSpeed) tidak dibawa masuk apa adanya.** Cabang C meminifikasi ulang *seluruh* berkas CSS/JS proyek. Namun kelima cabang lain melakukan perubahan **fungsional** pada sebagian dari berkas-berkas yang sama, dalam bentuk kode yang mudah dibaca (bukan hasil minifikasi). Menggabungkan versi minifikasi cabang C dengan perubahan fungsional lima cabang lain secara tekstual nyaris mustahil dilakukan dengan aman — hasil minifikasi bukan fitur, murni pemformatan ulang (dikonfirmasi lewat perbandingan langsung: perubahan cabang C pada berkas seperti `JSS_2/JSSSYSTEM.js` hanya berupa penggantian nama variabel lokal, di bawah 0,1% secara fungsional). **Keputusan:** rilis `V.0.9.0.0` ini memakai kode gabungan yang mudah dibaca (bukan hasil minifikasi cabang C), dengan pola *preload* PageSpeed (perubahan struktur `<head>`) tetap dipertahankan karena itu murni penataan `<link>`, bukan isi berkas. Minifikasi ulang **direkomendasikan sebagai langkah build terpisah** setelah rilis ini stabil — lihat Rencana Update Masa Mendatang, poin 1.
2. **Nomor versi dinaikkan ke `V.0.9.0.0`, bukan `V.0.8.10.0` atau `V.0.8.9.1`.** Rilis ini menggabungkan enam kontribusi independen sekaligus — termasuk satu perombakan alur lobi/matchmaking yang cukup besar dan satu perubahan model akses galeri — ke dalam satu basis kode yang utuh. Lompatan ke segmen kedua (`0.8` → `0.9`) dipakai untuk secara eksplisit membedakan rilis konsolidasi seperti ini dari rilis fitur/patch tunggal yang selama ini memakai segmen ketiga/keempat.
3. **Referensi cache-busting pemuat musik (`ASSETS_LAGU/.../_PART{n}.js?v=V.0.8.7.0`) sengaja TIDAK dinaikkan** ke `V.0.9.0.0`, mengikuti keputusan yang sudah diambil (dan dijelaskan) oleh cabang C sendiri: berkas audio yang dirujuk tidak pernah berubah dalam enam pembaruan ini (berkas `ASSETS_LAGU/` sendiri tidak termasuk dalam salah satu dari enam arsip yang diunggah), sehingga menaikkan versinya hanya akan memaksa semua pemain mengunduh ulang berkas musik yang identik tanpa manfaat apa pun.
4. **`.htaccess` yang hilang dari dua paket (`CERMIN_LAPORAN`, `LOBI_TERPADU`) direkonstruksi dari mayoritas (4 dari 6 paket) dan disatukan dengan tambahan `mod_mime` dari cabang C.** Ini bukan konflik konten — cabang C sendiri sudah mencatat akar masalahnya (perintah pembuatan arsip yang mengecualikan berkas berawalan titik).

---

## 🐛 Isu Ditemukan Saat Penggabungan

- **`.htaccess` hilang di 2 dari 6 paket** (`CERMIN_LAPORAN`, `LOBI_TERPADU`) — dikonfirmasi sebagai bug pengemasan arsip (bukan penghapusan yang disengaja), sudah dijelaskan sendiri oleh cabang C. Diperbaiki di rilis ini dengan merekonstruksi dari paket-paket yang masih memilikinya.
- **Komentar kode berisi referensi `overlay-public-lobbies`** masih tersisa di `JSS_2/JSS_MULTIPLAYER.js` gabungan (di dalam blok komentar `[UNIFIKASI LOBI]` yang menjelaskan riwayat perubahan) — ini murni dokumentasi historis dalam komentar, **bukan** referensi fungsional aktif; sudah diverifikasi tidak ada pemanggilan `getElementById`/`querySelector` aktif ke ID yang sudah dihapus tersebut.
- **`CHANGELOG_LENGKAP.md` internal cabang E (`LOBI_TERPADU`) tidak diperbarui** oleh cabang tersebut untuk mendeskripsikan perubahannya sendiri — deskripsi Bagian 5 di atas direkonstruksi penuh dari kode & komentar sumber.

---

## 🔴 Rencana Update Masa Mendatang — Prioritas Tinggi

1. **Pindah ke sistem kontrol versi sungguhan (Git) dengan satu basis kode bersama — rekomendasi ini sekarang sudah diulang lima kali berturut-turut** (sejak Merge #1 `V.0.8.1.7`) dan situasinya **tidak membaik**: rilis ini justru harus merekonsiliasi *enam* cabang paralel sekaligus, lebih banyak dari merge manapun sebelumnya. Selama pengembangan tetap berupa arsip `.zip` lepas yang saling tidak tahu-menahu, pola ini nyaris pasti akan terulang untuk keenam kalinya.
2. **Jalankan pengujian interaksi lintas-fitur sebelum rilis berikutnya**, bukan hanya saat sesi penggabungan. Contoh konkret dari rilis ini: interaksi antara toggle Privat/Publik (cabang E) dan Pengaturan Ruangan kustom (fitur lama) hanya diverifikasi lewat pembacaan kode, belum lewat pengujian langsung di lingkungan hosting sungguhan.
3. **Satukan proses regenerasi `.gz` ke dalam build script** (mis. `npm run build`) — sudah direkomendasikan di changelog cabang-cabang sebelumnya, dan sesi ini kembali harus meregenerasi 25 pasangan `.gz` secara manual.

## 🟡 Rencana Update Masa Mendatang — Prioritas Menengah

4. **Jalankan ulang minifikasi (langkah PageSpeed cabang C) di atas basis kode `V.0.9.0.0` yang sudah tergabung ini**, sebagai langkah build terpisah dan berulang — bukan sesuatu yang digabung manual tiap sesi rekonsiliasi seperti sekarang.
5. **Uji ulang alur onboarding tutorial (cabang F) berdampingan dengan skin kartu baru (cabang D)** — keduanya dikembangkan bersamaan tanpa saling tahu; belum ada verifikasi visual bahwa render lilin biru tutorial tetap terlihat jelas di atas kartu bertekstur perkamen baru.
6. **Pertimbangkan menambahkan versi/cache-busting sendiri untuk `PREVIEW_LEADERBOARD.html`** — file pratinjau desain ini belum pernah mengikuti skema versi proyek di rilis manapun sejauh ini.
7. **Pertimbangkan apakah Pengaturan Ruangan kustom (waktu giliran/nyawa awal) sebaiknya juga tersedia untuk lobi Publik**, bukan hanya ruangan Privat — saat ini sengaja dibatasi (lihat Bagian 5), tapi permintaan fitur ini mungkin muncul sekarang lobi publik & privat sudah disatukan tampilannya.

## 🟢 Rencana Update Masa Mendatang — Prioritas Rendah

8. Pertimbangkan menambah lebih banyak varian skin kartu (mengikuti pola yang dirintis cabang D) sebagai kemungkinan fitur kosmetik/musiman ke depannya.
9. Tinjau apakah galeri gabungan (Kampanye + Online, hasil cabang D) memerlukan filter/pencarian sekarang isinya lebih dari 25 kartu dalam satu tampilan.
10. Linter/formatter otomatis untuk PHP (mis. lewat CI) mengingat lingkungan pengembangan tidak selalu memiliki akses PHP CLI untuk verifikasi sintaks langsung — di sesi penggabungan ini pun verifikasi `api/engine.php` mengandalkan pembacaan manual karena `php -l` tidak tersedia di lingkungan build.

---

## 📋 Lampiran — Struktur File Final (`V.0.9.0.0`)

```text
├── .htaccess                          (direkonstruksi dari mayoritas paket + tambahan mod_mime cabang C)
├── index.html (+.gz)                  (gabungan cabang A/B/C/D/E/F — lihat Bagian "Proses Penggabungan")
├── PREVIEW_LEADERBOARD.html (+.gz)    (tidak berubah dari V.0.8.7.0; .gz baru diregenerasi)
├── robots.txt                         (tidak berubah)
├── sitemap.xml                        (lastmod sudah sesuai, tidak berubah)
├── ASSETS_GAMBAR/
│   ├── cards/                         (BARU — 12 aset skin kartu dari cabang D)
│   ├── sprite_icons.js, sprite_badge.js, sprite_candle.js, sprite_tutor.js (+.gz)  (tidak berubah)
├── CSS_1/
│   ├── core_ui.css (+.gz)             (gabungan cabang D + F)
│   └── online_ui.css (+.gz)           (tidak berubah)
├── CSS_2/
│   ├── matchmaking_ui.css (+.gz)      (utuh dari cabang E)
│   ├── reports_ui.css (+.gz)          (utuh dari cabang A)
│   ├── profile_ui.css, history_ui.css (+.gz)  (utuh dari cabang B)
│   ├── fit_screen.css, mobile_ux.css, animations.css, stage_decor.css (+.gz)  (tidak berubah)
├── JSS_1/
│   ├── JSSCODETAWANAN.js (+.gz)       (utuh dari cabang D)
│   ├── JSSTUTORIALCODE.js (+.gz)      (utuh dari cabang F)
│   └── JSSBOSSCODE.js (+.gz)          (tidak berubah)
├── JSS_2/
│   ├── JSSSYSTEM.js (+.gz)            (gabungan cabang A + D + F)
│   ├── JSS_MULTIPLAYER.js (+.gz)      (gabungan cabang A + D + E)
│   ├── JSS_EVENTS.js (+.gz)           (gabungan cabang D + F)
│   ├── JSS_HISTORY.js (+.gz)          (utuh dari cabang B)
│   ├── JSS_FIT_SCREEN.js, JSS_PROFILE_UI.js, JSS_REPORTS.js (+.gz)  (tidak berubah)
├── api/
│   ├── engine.php                     (utuh dari cabang A)
│   └── (seluruh berkas api/ lain)     (tidak berubah)
```

**Total: 108 berkas.** Enam cabang, nol fitur hilang, nol konflik tak terselesaikan.
