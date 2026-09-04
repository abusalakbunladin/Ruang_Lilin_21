# STATUS: Google Search Console — Sitemap Gagal Diambil

> **📌 BACA FILE INI DULU** sebelum mengubah `.htaccess`, `robots.txt`, `sitemap.xml`, atau mengutak-atik pengaturan Search Console untuk masalah "Tidak dapat mengambil peta situs". Ini sudah diselidiki dua tahap — baca dulu supaya tidak mengulang kerja yang sama, dan tidak salah paham menganggap masalahnya "sudah selesai" atau "belum disentuh sama sekali".

**Dibuat:** 2 September 2026, menyusul rilis `V.0.9.1.0`
**Status saat ini:** 🟡 Belum tuntas — kemungkinan besar ini keterbatasan platform hosting gratis, BUKAN bug di kode situs ini.
**Berkas terkait:** `.htaccess`, `CHANGELOG_LENGKAP.md` (bagian rilis `V.0.9.1.0`)

---

## Ringkasan Singkat

Sejak pertengahan Agustus 2026, Google Search Console menampilkan status **"Tidak dapat mengambil peta situs"** untuk `sitemap.xml` pada properti `http://gamekartuduasatu.freepage.cc/` — 0 halaman ditemukan, jenis file "Tidak diketahui".

Setelah dua tahap investigasi, kesimpulan saat ini: **penyebab paling mungkin adalah sistem anti-bot wajib milik InfinityFree** (penyedia hosting di balik `freepage.cc`), bukan kesalahan konfigurasi di kode situs ini. Ini **belum terkonfirmasi 100% lewat pengujian langsung** terhadap domain ini — kesimpulannya dibangun dari bukti tidak langsung yang sangat kuat (dokumentasi resmi InfinityFree + puluhan laporan komunitas bergejala identik). Lihat bagian "Belum Terverifikasi" di bawah sebelum menganggap ini kepastian mutlak.

---

## Tahap 1 (rilis `V.0.9.1.0`) — Bug Nyata, Tapi Ternyata Bukan Penyebab Utama

**Yang ditemukan:** di `.htaccess`, blok pengalihan paksa `http://` → `https://` (`RewriteCond`/`RewriteRule`/header HSTS) aktif tanpa tanda `#`, padahal instruksi tepat di atasnya sendiri mewajibkan blok itu tetap nonaktif sampai HTTPS dites manual di browser dulu — sebab kalau SSL ternyata belum siap, redirect paksa ini bisa membuat seluruh situs tak bisa diakses sama sekali.

**Dugaan penyebab bug:** kemungkinan tanda `#`-nya hilang saat `.htaccess` direkonstruksi ulang dari beberapa paket sumber di sesi penggabungan `V.0.9.0.0`.

**Perbaikan yang dilakukan:** 6 baris tersebut dikembalikan ke status nonaktif (dikomentari lagi). Catatan riwayat lengkap ada langsung di `.htaccess` — cari teks `[CATATAN V.0.9.1.0`.

**Kenapa ternyata ini BUKAN penyebab utama sitemap gagal:** pengguna menunjukkan bukti bahwa `sitemap.xml` SELALU bisa dibuka normal di browser (XML valid, gembok aman) — bahkan **sebelum** perbaikan di atas dilakukan. Kalau redirect/SSL memang biang keroknya, browser biasa juga akan ikut gagal. Karena browser selalu berhasil dari dulu, redirect HTTPS kemungkinan besar bukan (atau bukan satu-satunya) penyebab kegagalan di Google Search Console.

**Status perbaikan ini sekarang:** tetap dipertahankan (jangan di-revert). Ini tetap bug konfigurasi yang nyata dan berisiko kalau SSL benar-benar belum siap suatu saat — jadi tetap benar untuk sudah diperbaiki. Hanya saja: jangan berharap ini yang menyelesaikan masalah sitemap di Search Console.

---

## Tahap 2 — Dugaan yang Jauh Lebih Kuat: Sistem Anti-Bot InfinityFree

Diriset dari dokumentasi resmi InfinityFree ("Browser Security System — Features and Limitations", forum.infinityfree.com) dan puluhan thread forum berjudul serupa persis dengan kasus ini — misalnya "Google Search Console not detecting my sitemap ... Couldn't fetch", "Sitemap Could not be read", "Sitemaps being overrided from application/xml to text/html" — tersebar dari tahun 2022 sampai 2026.

**Cara kerja sistem ini, menurut dokumentasi resmi InfinityFree:**

- Semua akun hosting gratis InfinityFree **wajib** memakai sistem keamanan yang mengecek apakah pengunjung bisa menjalankan JavaScript dan menerima cookie, sebelum diizinkan mengakses konten situs yang sesungguhnya.
- Verifikasi ini otomatis dan nyaris tak terlihat pengunjung berbrowser normal — persis kenapa situs ini SELALU tampak normal kalau dibuka manual.
- Alat non-browser (cURL, skrip otomatis, REST API, alat validator/SEO checker) mendapat halaman tantangan JavaScript, bukan konten aslinya — biasanya berupa 403 Forbidden atau pesan "situs ini butuh Javascript untuk berjalan".
- Saat admin InfinityFree ditanya langsung soal ini di forum mereka (thread "Whitelist bingbot?"), jawabannya: alat semacam **sitemap checker kemungkinan besar termasuk yang terblokir** sistem ini — meski crawler Bing/Google yang sesungguhnya (beda dari alat bantu/checker) diklaim tetap bisa lolos karena mendukung JS.
- **Sistem ini resmi TIDAK BISA dimatikan di akun hosting gratis.** Satu-satunya cara resmi menghilangkannya: upgrade ke hosting premium (InfinityFree/iFastNet, mulai kira-kira $3.99/bulan untuk paket Super Premium; migrasi dari akun gratis bisa dilakukan tanpa biaya tambahan lewat tiket support).

**Kenapa ini cocok dengan gejala yang terlihat di kasus kita:**

- "Jenis: Tidak diketahui" di Search Console → GSC kemungkinan menerima halaman tantangan (HTML+JS), bukan XML asli, sehingga tidak bisa mengenali jenis filenya sama sekali.
- Masalah bertahan konsisten 11+ hari, bukan sekali gagal lalu normal → cocok dengan pemblokiran struktural di level hosting, bukan gangguan sesaat.
- Browser pengguna SELALU berhasil membuka sitemap, kapan pun dicoba → cocok dengan sistem yang meloloskan browser asli tapi menantang alat otomatis non-browser.

### ⚠️ Belum Terverifikasi Langsung

Sesi ini belum berhasil menguji sendiri memakai alat non-browser terhadap domain `gamekartuduasatu.freepage.cc` (butuh akses jaringan keluar yang belum tersedia saat catatan ini ditulis). Kesimpulan di atas dibangun dari bukti tidak langsung yang sangat kuat (dokumentasi resmi + puluhan laporan gejala identik), **bukan** hasil pengujian langsung terhadap domain ini. Kalau suatu saat ada akses `curl`/alat serupa, cara memverifikasi: minta konten `https://gamekartuduasatu.freepage.cc/sitemap.xml` tanpa menjalankan JavaScript — kalau hasilnya bukan XML murni (misalnya berisi tag `<script>` atau redirect ke halaman lain), hipotesis ini terkonfirmasi.

---

## ⚠️ Supaya Tidak Mengulang Kerja yang Sama

- **Jangan** habiskan waktu mengubah `.htaccess` / `robots.txt` / `sitemap.xml` lagi untuk masalah "tidak dapat mengambil peta situs" ini — ketiganya sudah diperiksa dan secara teknis sudah benar. Masalahnya (kemungkinan besar) ada di lapisan InfinityFree, di DEPAN server, sebelum file-file ini bahkan sempat diproses.
- **Jangan** aktifkan lagi blok redirect HTTPS di `.htaccess` tanpa mengetes manual dulu (buka `https://gamekartuduasatu.freepage.cc/` langsung di browser, pastikan tidak ada peringatan sertifikat sama sekali). Ini isu terpisah dari masalah sitemap, tapi risikonya tetap nyata kalau diaktifkan sembarangan.
- **Jangan** langsung simpulkan situs ini gagal total diindeks Google hanya dari status merah di menu Peta Situs — itu baru soal fitur sitemap-checker-nya Search Console. Indexing halaman biasa lewat jalur lain (crawl langsung / Inspeksi URL / backlink dari situs lain) berpotensi tetap berjalan terpisah dari masalah ini.

## Pilihan yang Belum Dieksekusi

Belum ada keputusan yang diambil untuk tiga poin ini — murni pencatatan opsi, menunggu keputusan pemilik proyek:

1. **Coba dulu, gratis & cepat:** di Search Console, pakai **Inspeksi URL** untuk `https://gamekartuduasatu.freepage.cc/` (bukan sitemap-nya), lalu klik **Minta Pengindeksan**. Ini lewat jalur crawler utama Google, bukan alat "checker", jadi peluangnya lebih baik lolos dari sistem anti-bot InfinityFree.
2. **Terima kondisinya:** biarkan status Peta Situs tetap merah selama masih di hosting gratis ini — largely kosmetik di panel Search Console, bukan vonis final soal keterlihatan di hasil pencarian.
3. **Upgrade ke InfinityFree Premium** atau **pindah hosting** ke penyedia yang tidak menerapkan pemblokiran semacam ini — satu-satunya cara terjamin (menurut InfinityFree sendiri) untuk menghapus sistem ini sepenuhnya.

## Sumber

- InfinityFree — [Browser Security System - Features and Limitations](https://forum.infinityfree.com/t/browser-security-system-features-and-limitations/49353)
- InfinityFree Forum — [Whitelist bingbot?](https://forum.infinityfree.com/t/whitelist-bingbot/110932)
- InfinityFree Forum — [Is it possible to skip IF's Javascript check for major search engines?](https://forum.infinityfree.com/t/is-it-possible-to-skip-ifs-javascript-check-for-major-search-engines/103286)
- InfinityFree Forum — [Does InfinityFree block google bots from crawling the hosted sites?](https://forum.infinityfree.com/t/does-infinityfree-block-google-bots-from-crawling-the-hosted-sites/101767)
- Cari kata kunci `sitemap couldn't fetch` atau `Google Search Console` langsung di forum.infinityfree.com untuk melihat puluhan laporan serupa lainnya (2022–2026).
