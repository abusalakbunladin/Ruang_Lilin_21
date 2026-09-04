# PENJELASAN PERUBAHAN — Galeri Bonus & Kartu Trump

**Versi:** `V.0.9.0.0` → `V.0.9.0.0+PERBAIKAN_GALERI`
**Tanggal:** Rabu, 2 September 2026 WIB
**Konteks:** Tiga masalah spesifik ditemukan pada layar **Galeri Bonus** setelah rilis `V.0.9.0.0` (Merge #5). Ketiganya berakar dari sisa-sisa fitur lama yang tidak ikut dibersihkan tuntas saat penggabungan enam cabang di Merge #5 — bukan bug baru, tapi "puing" dari fitur yang sudah seharusnya hilang tapi kodenya masih tertinggal.

Dokumen ini ada supaya perubahan di sini **tidak terhapus balik tanpa sadar** (misalnya saat merge/refactor berikutnya) dan **tidak disalahpahami** sebagai bug atau kode yang belum selesai. Tiap file yang disebut di sini juga punya komentar `[PERBAIKAN GALERI]` langsung di lokasi perubahannya — dokumen ini adalah peta besarnya.

---

## Ringkasan Singkat

| # | Masalah | Penyebab | Status |
|---|---------|----------|--------|
| 1 | Tombol "Galeri Bonus" terus bercahaya (glow keemasan) | Sisa kelas `unlocked` dari sistem gerbang-kunci lama yang gerbangnya sudah dihapus | **Diperbaiki** |
| 2 | Galeri bisa diakses lewat tombol "Kartu Spesial Online" di menu online | Tombol itu diam-diam membuka layar Galeri yang sama — jalur akses ganda | **Diperbaiki** |
| 3 | Kartu-kartu trump di Galeri tidak rapi (bercampur, tinggi tidak sejajar) | Satu grid flex-wrap tanpa pemisah untuk 45 kartu berbeda jenis | **Diperbaiki** |

---

## Masalah 1 — Cahaya di Tombol "Galeri Bonus"

### Gejala
Tombol "Galeri Bonus" di menu awal selalu tampak bercahaya (ada `box-shadow` keemasan di sekelilingnya), padahal galeri sudah terbuka untuk siapa saja sejak lama — tidak ada lagi status "baru saja terbuka" yang sebenarnya berlaku.

### Akar Masalah
Sebelum galeri dibuka untuk semua orang, tombol ini terkunci di balik syarat "menang Mode Sulit". Begitu syarat itu terpenuhi, kode menambahkan kelas `unlocked` ke tombol untuk menandai "baru saja terbuka", dan kelas itu memicu aturan CSS:

```css
#btn-main-gallery.unlocked{
  opacity:1!important;
  border-color:var(--brass-bright);
  color:var(--brass-bright);
  box-shadow:0 0 18px rgba(176,138,78,.25); /* <- ini "cahaya"-nya */
}
```

Saat Merge #5 menghapus gerbang kunci itu (galeri dibuka untuk semua orang), fungsi `updateMainMenuGalleryButton()` di `JSS_2/JSS_EVENTS.js` tetap dipanggil **setiap kali menu awal ditampilkan** (lewat `MutationObserver` yang mengamati `#screen-main`), dan tetap menambahkan kelas `unlocked` setiap kali — jadi cahayanya menyala terus-menerus, bukan cuma sekali sebagai perayaan.

### Perbaikan
- **`JSS_2/JSS_EVENTS.js`** — fungsi `t()` (alias `window.updateMainMenuGalleryButton`) tidak lagi memanggil `classList.add("unlocked")`. Sekarang cuma memastikan kelas `locked`/`unlocked` **berdua-duanya tidak ada**, tombol aktif, dan teksnya benar.
- **`CSS_1/online_ui.css`** — aturan `#btn-main-gallery{transition:...}`, `#btn-main-gallery.locked{...}`, dan `#btn-main-gallery.unlocked{...}` (termasuk `box-shadow` di atas) **dihapus total**. Sudah tidak ada gunanya karena tidak ada lagi status lock/unlock yang sungguhan.

### ⚠️ Jangan
- Jangan tambahkan lagi `classList.add("unlocked")` di `t()` — itu akan menyalakan cahayanya lagi.
- Jangan tambahkan lagi aturan CSS `#btn-main-gallery.unlocked{box-shadow:...}` — itu artinya menghidupkan kembali fitur yang sudah sengaja dimatikan.
- Kelas `.locked` yang dipakai di tempat **lain** (misalnya `.special-card.locked` untuk kartu trump di meja permainan) **tidak berhubungan** dengan ini dan tidak disentuh sama sekali — jangan bingung keduanya.

---

## Masalah 2 — Galeri Bisa Diakses dari Menu Online

### Gejala
Menu **Mode Multiplayer Online** punya tombol "Kartu Spesial Online" yang, kalau diklik, ternyata membuka layar **Galeri Bonus** yang persis sama dengan yang bisa diakses dari menu awal — bukan tampilan terpisah seperti namanya menyiratkan.

### Akar Masalah
Sebelum galeri disatukan di Merge #5, "Kartu Spesial Online" adalah fitur **berdiri sendiri**: tombolnya membuka overlay terpisah (`overlay-online-cards`) berisi grid kartu online-only sendiri. Saat Merge #5 menyatukan tampilan "Kartu Spesial" Kampanye dan Online ke satu Galeri dengan tab, fungsi `window.mpOpenOnlineCards()` di `JSS_2/JSS_MULTIPLAYER.js` **diarahkan ulang** untuk membuka `#screen-gallery` (tab "trumps") alih-alih overlay lamanya — tapi:

1. Tombol pemicunya (`btn-online-cards`, teks "Kartu Spesial Online") **tetap ada** di menu online, sehingga Galeri jadi punya jalur akses tambahan yang tidak semestinya ada di sana.
2. Overlay lama `overlay-online-cards` **tidak pernah dihapus** — jadi ada markup mati (tidak pernah terbuka lewat jalur manapun) yang cuma nongkrong di HTML.

### Perbaikan
- **`index.html`** — tombol `btn-online-cards` di `#screen-online` **dihapus**. Overlay mati `overlay-online-cards` (lengkap dengan grid `online-cards-grid` dan tombol tutupnya) **dihapus**. Kalimat di overlay "Cara Bermain" yang tadinya merujuk tombol ini diperbarui agar mengarah ke Galeri Bonus.
- **`JSS_2/JSS_MULTIPLAYER.js`** — fungsi `window.mpOpenOnlineCards` **dihapus** (sudah tidak ada pemanggilnya). Dua *binding* event (`btn-online-cards` klik, `btn-online-cards-close` klik) **dihapus**.

Sekarang Galeri **hanya** bisa dicapai lewat:
- `#btn-main-gallery` ("Galeri Bonus" di menu awal), atau
- `#btn-gallery` ("Lihat Galeri Bonus" di layar akhir Kampanye, setelah menang Mode Sulit).

### ⚠️ Jangan
- Jangan tambahkan lagi tombol apa pun di `#screen-online` yang membuka `#screen-gallery` — itu mengulang duplikasi yang baru saja dibersihkan.
- Kalau suatu saat memang ingin pemain di Mode Online bisa "melihat" kartu spesial dari dalam menu online, arahkan mereka ke Galeri Bonus yang sudah ada (misalnya lewat teks/link biasa), **jangan** bikin ulang jalur/tombol/overlay terpisah untuk itu.
- 14 kartu online-only (`window.ONLINE_CARD_POOL`) **tetap ditampilkan** — cuma sekarang khusus di dalam Galeri (tab "Kartu Spesial", bagian "Kartu Khusus Mode Online / Multiplayer"), bukan dihapus datanya.

---

## Masalah 3 — Kartu Trump di Galeri Tidak Rapi

### Gejala
Di tab "Kartu Spesial" pada Galeri, 26 kartu dasar dan 19 kartu eksklusif musuh (total 45 kartu) tercampur jadi satu tumpukan tanpa judul pemisah. Karena memakai `display:flex;flex-wrap:wrap` biasa dan tinggi tiap kartu berbeda-beda (tergantung panjang teks deskripsinya), baris-barisnya tidak sejajar rapi — beberapa kartu "menjorok" lebih tinggi dari tetangganya di baris yang sama.

### Akar Masalah
`renderGalleryTab("trumps")` di `JSS_1/JSSCODETAWANAN.js` menggabungkan kartu dasar (`CARD_POOL`) dan kartu eksklusif musuh (`ENEMY_EXCLUSIVE`, di-dedup lewat `Set`) ke **satu** `<div class="gallery-grid">` tanpa judul apa pun di antaranya — beda dengan bagian kartu online yang sudah lebih dulu punya `<h3>` sendiri. Dan `.gallery-grid` sendiri cuma `flex-wrap`, yang tidak menjamin kartu dalam satu baris punya tinggi sama.

### Perbaikan
- **`JSS_1/JSSCODETAWANAN.js`** — cabang `"trumps"` di `renderGalleryTab()` ditulis ulang jadi **tiga bagian terpisah**, masing-masing dengan judul (`<h3 class="gallery-section-title">`), deskripsi singkat (`<p class="gallery-section-desc">`), dan grid sendiri:
  1. **Kartu Spesial Dasar** — isi `CARD_POOL` (26 kartu, bisa didapat siapa saja).
  2. **Kartu Eksklusif Musuh** — isi `ENEMY_EXCLUSIVE` yang di-dedup (19 kartu unik, cuma dimainkan lawan).
  3. **Kartu Khusus Mode Online / Multiplayer** — isi `window.ONLINE_CARD_POOL` (14 kartu; bagian ini logikanya tidak berubah, cuma judul/deskripsinya kini pakai kelas yang sama dengan dua bagian di atas, bukan gaya `style="..."` inline seperti sebelumnya).
- **`CSS_1/core_ui.css`** — kelas baru `.gallery-grid.cards-grid` ditambahkan: mengubah grid dari `flex` ke **CSS Grid** (`grid-template-columns:repeat(auto-fill,minmax(148px,1fr))`) dengan `align-items:stretch`, supaya kartu dalam satu baris **otomatis sejajar tingginya**. Kelas baru `.gallery-section-title` dan `.gallery-section-desc` juga ditambahkan untuk judul/deskripsi tiap bagian (menggantikan gaya inline `style="margin-top:22px;"` dkk. yang tadinya cuma dipakai bagian online).

### Kenapa Tab "Kartu Angka" Tidak Ikut Diubah
Tab "Kartu Angka" (11 kartu angka kecil, ukuran seragam 42×58px) **tetap** memakai `.gallery-grid` polos (flex-wrap), **tanpa** kelas `.cards-grid`. Kartu di sana semuanya sama ukuran, jadi tidak pernah punya masalah baris-tidak-sejajar — memaksanya masuk grid selebar minimal 148px justru akan membuang-buang ruang kosong di sekitar kartu kecilnya. Kelas `.cards-grid` sengaja dibuat sebagai **tambahan**, bukan pengganti `.gallery-grid`, persis supaya kedua kebutuhan ini bisa hidup berdampingan.

### ⚠️ Jangan
- Jangan gabungkan tiga grid di tab "Kartu Spesial" jadi satu lagi — itu mengulang masalah "kartu bercampur tanpa label" yang baru diperbaiki.
- Jangan tempelkan kelas `.cards-grid` ke grid tab "Kartu Angka" — lihat penjelasan di atas.
- Kalau menambah kartu baru ke `CARD_POOL` atau `ENEMY_EXCLUSIVE` di `JSS_2/JSSSYSTEM.js`, **tidak perlu** mengubah apa pun di sini — ketiga grid otomatis menyesuaikan jumlah kartunya (dihitung lewat `.length`, tidak ada angka yang di-*hardcode*).

---

## Daftar Lengkap File yang Diubah

| File | Perubahan |
|---|---|
| `index.html` | Hapus tombol `btn-online-cards` & overlay `overlay-online-cards`; perbarui teks "Cara Bermain"; naikkan versi di seluruh `?v=` & lencana versi |
| `CSS_1/core_ui.css` | Tambah `.cards-grid`, `.gallery-section-title`, `.gallery-section-desc` |
| `CSS_1/online_ui.css` | Hapus aturan `#btn-main-gallery.locked`/`.unlocked` (termasuk `box-shadow`) |
| `JSS_1/JSSCODETAWANAN.js` | Tulis ulang cabang `"trumps"` di `renderGalleryTab()` jadi 3 bagian |
| `JSS_2/JSS_EVENTS.js` | Fungsi `t()`/`updateMainMenuGalleryButton` tidak lagi menambah kelas `unlocked` |
| `JSS_2/JSS_MULTIPLAYER.js` | Hapus fungsi `mpOpenOnlineCards` & 2 *binding* event terkait |
| `CHANGELOG_LENGKAP.md` | Entri rilis `V.0.9.0.0+PERBAIKAN_GALERI` ditambahkan di paling atas |

Semua file di atas (kecuali dua `.md`) juga punya pasangan `.gz`-nya yang sudah diregenerasi ulang.

---

## Cara Memverifikasi Ulang

Kalau nanti ada perubahan lain di sekitar area ini dan ingin memastikan tidak ada yang rusak, jalankan urutan ini (semuanya tanpa perlu server PHP/MySQL — murni sisi client):

1. **Sintaks JS:** `node --check JSS_1/JSSCODETAWANAN.js JSS_2/JSS_EVENTS.js JSS_2/JSS_MULTIPLAYER.js`
2. **Kurung kurawal CSS seimbang:** hitung jumlah `{` vs `}` di `CSS_1/core_ui.css` dan `CSS_1/online_ui.css` — harus sama persis.
3. **Tag HTML seimbang:** hitung `<div`/`</div>`, `<section`/`</section>`, `<main`/`</main>`, `<button`/`</button>`, `<footer`/`</footer>` di `index.html` (abaikan dulu isi komentar `<!-- -->` saat menghitung, supaya teks penjelasan di dalam komentar tidak ikut terhitung sebagai tag sungguhan).
4. **Referensi ID menggantung:** kumpulkan semua `id="..."` di `index.html` (di luar komentar), lalu cocokkan dengan semua `getElementById("...")`/`querySelector('#...')` di seluruh file `JSS_1/*.js` dan `JSS_2/*.js` — harus nol referensi JS yang menunjuk ID yang tidak ada.
5. **Uji hidup (opsional tapi dianjurkan):** jalankan server statis lokal (`python3 -m http.server`) lalu buka `index.html` di browser (atau lewat Playwright/Puppeteer headless) — cek tombol "Galeri Bonus" tidak ber-`box-shadow`, tab "Kartu Spesial" di Galeri menampilkan 3 judul bagian, dan menu online tidak lagi punya tombol "Kartu Spesial Online".

---

## Penomoran Versi

Format `V.0.9.0.0+PERBAIKAN_GALERI` mengikuti pola *build metadata* (`versi-dasar+keterangan`): `V.0.9.0.0` adalah basis dari Merge #5 yang tidak diubah strukturnya, `+PERBAIKAN_GALERI` menandai patch ini di atasnya. Semua referensi `?v=V.0.9.0.0` fungsional & lencana versi di `index.html` dinaikkan bersamaan, **kecuali** satu referensi pemuat musik yang sengaja dibekukan di `V.0.8.7.0` sejak Merge #5 (lihat `CHANGELOG_LENGKAP.md`, bagian Merge #5, poin Keputusan Teknis — alasannya masih berlaku sama di sini: berkas musiknya sendiri tidak pernah berubah, jadi *cache-bust* di situ tidak diperlukan).
