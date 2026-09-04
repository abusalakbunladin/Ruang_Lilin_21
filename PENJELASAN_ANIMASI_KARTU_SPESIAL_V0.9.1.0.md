# Penjelasan Perubahan — Animasi Pengumuman Kartu Spesial (`V.0.9.1.0`)

> Dokumen ini menjelaskan **secara teknis & lengkap** semua perubahan yang dibuat di rilis `V.0.9.1.0`, supaya siapa pun (termasuk kamu sendiri beberapa bulan lagi, developer lain, atau AI assistant lain yang dipakai mengedit proyek ini) **tidak salah paham dan tidak sengaja menghapus/membalikkan perubahan ini** karena mengira kodenya aneh atau salah.
>
> Ringkasan versi singkat ada di `CHANGELOG_LENGKAP.md` (bagian paling atas). Dokumen ini adalah versi **jauh lebih detail**, khusus untuk fitur ini saja, dan juga dikutip sebagai komentar langsung di dalam kode (`/* [V.0.9.1.0] ... */`) di setiap titik yang relevan.

---

## 1. Ringkasan Singkat (TL;DR)

Sebelum `V.0.9.1.0`, banner besar di tengah layar yang menampilkan ikon + nama kartu spesial (elemen `#card-announce`) **hanya muncul saat LAWAN yang memainkan kartu spesial**. Saat pemain sendiri memainkan kartu spesial, cuma efek suara yang terdengar — tidak ada animasi visual sama sekali.

`V.0.9.1.0` mengubah ini supaya banner **muncul untuk KEDUA belah pihak** (pemain maupun lawan), di semua mode (kampanye, tawanan, boss, tutorial, multiplayer online), plus merombak tampilan animasinya jadi lebih halus & "HD" secara keseluruhan.

**Ini perubahan yang disengaja, bukan bug.** Kalau suatu saat banner terasa "terlalu sering muncul" atau ingin dikembalikan ke perilaku lama (cuma tampil untuk lawan), itu keputusan desain yang sah — tapi lakukan dengan sadar, jangan karena mengira kode barunya adalah bug. Baca [Bagian 6](#6-hal-yang-jangan-diubah-sembarangan) sebelum mengubah apa pun di area ini.

---

## 2. Masalah Sebelumnya (Sebelum `V.0.9.1.0`)

Di kode lama, fungsi `applySpecial()` (mesin utama yang memproses efek kartu spesial, di `JSS_2/JSSSYSTEM.js`) punya baris seperti ini:

```js
sfxSpecialCard();
"opponent" === e && announceCard(a, e); // <- HANYA jalan kalau e === "opponent"
```

`e` di sini adalah **pelaku** (`"player"` atau `"opponent"`). Artinya: efek suara (`sfxSpecialCard()`) selalu berbunyi siapa pun yang main, tapi banner visual (`announceCard()`) cuma dipanggil kalau yang main adalah **lawan**.

Di mode multiplayer online (`JSS_2/JSS_MULTIPLAYER.js`), pola yang sama juga terjadi — bedanya di sana deteksinya lewat perbandingan state hasil polling server (bukan panggilan langsung), tapi hasilnya sama: hanya kartu milik **lawan** yang memicu banner.

---

## 3. Apa Saja yang Diubah — Rincian per Berkas

### 3.1 `JSS_2/JSSSYSTEM.js` — mesin kampanye/tawanan/boss/tutorial

**a) `applySpecial(e, a)`** — baris pemicu banner diubah dari:
```js
"opponent" === e && announceCard(a, e);
```
menjadi (tanpa syarat, jalan untuk kedua sisi):
```js
announceCard(a, e);
```
Karena SEMUA jalur memainkan kartu spesial di mode kampanye (klik kartu oleh pemain → `playSpecialCard()` → `applySpecial("player", ...)`; AI/boss main kartu → `applySpecial("opponent", ...)`; tombol "Pakai" di God Mode/dev tool → `applySpecial("player", ...)`) berujung ke fungsi ini, satu perubahan ini otomatis mencakup **semua** cara kartu spesial bisa dimainkan di mode kampanye — termasuk tutorial, yang memakai mesin (`S`, `applySpecial`) yang sama persis.

**b) `announceCard(e, a)`** — direvisi supaya tahu **siapa** yang main, bukan cuma **apa** yang dimainkan:

```js
function announceCard(kartu, pelaku) {
  const isMine = pelaku === "player";
  const isExclusive = !isMine && ENEMY_ONLY_IDS.has(kartu.id);

  // teks label:
  //   isMine       -> "KAMU MEMAINKAN"
  //   isExclusive  -> "KARTU RAHASIA {NAMA LAWAN}"
  //   selain itu   -> "{NAMA LAWAN} MEMAINKAN"

  // class yang ditempel ke #card-announce:
  //   isMine       -> tambah class "mine"
  //   !isMine      -> tambah class "theirs"
  //   isExclusive  -> tambah class TAMBAHAN "exclusive" (menimpa warna theirs)
}
```

Durasi tampil banner juga sedikit dibedakan: **1500 md** untuk kartu milik pemain sendiri, **1700 md** untuk kartu lawan (sedikit lebih lama karena informasi soal gerakan lawan lebih penting untuk sempat dibaca).

### 3.2 `JSS_2/JSS_MULTIPLAYER.js` — mode online

Ini bagian paling berbeda dari kampanye, karena **kartu milik pemain sendiri di mode online sama sekali tidak lewat `applySpecial()` secara lokal**. Saat pemain klik kartu spesial dalam kondisi `onlineMode`, yang terjadi adalah:

```
klik kartu (pemain) → playSpecialCard("player", uid)
                     → window.mpSendAction("special", uid)   // dikirim ke server, TIDAK panggil applySpecial() lokal
                     → server proses & simpan state
                     → client polling tiap ~2.8 detik (mpCheckGameStateOnce → J(state))
                     → J() membandingkan state lama vs baru → baru ketahuan ada kartu baru dimainkan
```

Karena itu, perbaikannya harus ditaruh di **fungsi `J(e)`**, tepatnya di blok yang membandingkan `playedThisRound` lama vs baru. Sebelumnya blok ini cuma mengecek sisi lawan; sekarang mengecek **kedua sisi secara independen**:

```js
// di dalam J(e), state lama disebut `e`, state sekarang `S`
if (S.player.playedThisRound.length > e.playerTrump) {   // kartu baru milik PEMAIN
  sfxSpecialCard();
  const kartu = S.player.playedThisRound[S.player.playedThisRound.length - 1];
  kartu && mpAnnounceTrump(kartu, true);   // true = "mine"
}
if (S.opponent.playedThisRound.length > e.oppTrump) {     // kartu baru milik LAWAN
  sfxSpecialCard();
  const kartu = S.opponent.playedThisRound[S.opponent.playedThisRound.length - 1];
  kartu && mpAnnounceTrump(kartu, false);  // false = "theirs"
}
```

Fungsi baru **`mpAnnounceTrump(kartu, mine)`** ditambahkan sebagai padanan `announceCard()` khusus untuk mode online (tidak bisa memakai fungsi yang sama persis karena sumber datanya beda — state lokal vs hasil sinkronisasi server — meski isinya sangat mirip).

**Bonus tambahan (perbaikan kecil, bukan inti permintaan):** class `mine`/`theirs` sekarang juga ikut dibersihkan di dua tempat lain yang memakai elemen `#card-announce` yang sama, supaya warnanya tidak "nyasar" kalau banner-banner itu tampil tepat setelah banner kartu spesial:
- `JSS_1/JSSBOSSCODE.js` → `announceDesperatePhase()` (banner fase putus asa Raja Iblis)
- `JSS_2/JSS_MULTIPLAYER.js` → banner pengungkapan **Cermin Takdir** (`mirrorReflect`)

### 3.3 `CSS_1/core_ui.css` — visual banner dirombak

| Class | Kapan dipakai | Warna aksen |
|---|---|---|
| `.mine` | Kartu milik **pemain** sendiri | Emas / brass (`--brass-bright`) — senada dengan `.trump-chip.mine` & `.name-tag.you` yang sudah ada |
| `.theirs` | Kartu **biasa** milik lawan | Merah redup (`--blood`) — senada dengan `.trump-chip.theirs` |
| `.exclusive` | Kartu **rahasia eksklusif** milik lawan | Merah terang (`--blood-bright`) — tidak diubah dari versi lama |
| `.online-card` | Kartu eksklusif mode online | Tosca (`--online-bright`) — tidak diubah dari versi lama |

Tambahan visual "HD":
- **Badge ikon melingkar** (`.announce-icon`) menggantikan ikon polos, dengan cincin nyala (`::after` + keyframe `announceRing`) yang berdenyut keluar tiap banner muncul.
- **Sapuan cahaya** (`.card-announce::after` + keyframe `announceSweep`) melintas diagonal sesaat setelah banner muncul.
- **Tekstur grain tipis** (`.card-announce::before`) di latar kartu — pola yang sama seperti yang sudah dipakai di `.start-wrap`, cuma dekorasi statis.
- Bayangan berlapis (`box-shadow` inset + outset), padding & border-radius sedikit diperbesar, teks nama kartu diberi `text-shadow`.

### 3.4 `CSS_2/animations.css`

- `@keyframes announceIn` dirombak: dari sekadar pop-scale sederhana jadi ada tahap blur-masuk + rotasi kecil + gerak vertikal yang mereda, dan fade-out yang lebih halus di akhir. **Durasi tetap 1.45 detik**, tidak berubah.
- **2 keyframe baru**: `announceSweep` (sapuan cahaya) dan `announceRing` (cincin nyala di badge ikon).

### 3.5 `index.html`

35 referensi versi (`?v=V.0.9.0.0` di setiap tag `<script>`/`<link>` untuk cache-busting, + badge versi di pojok kanan-bawah layar) dinaikkan menjadi `V.0.9.1.0`.

### 3.6 File `.gz`

Proyek ini menyajikan file `.gz` statis sebagai fallback kompresi lewat `.htaccess` (lihat bagian `RewriteCond %{REQUEST_FILENAME}\.gz -f` di `.htaccess`). **Setiap kali salah satu dari keenam berkas di atas diedit, `.gz`-nya WAJIB digenerasi ulang** — kalau tidak, browser yang mendukung gzip akan tetap disodori isi LAMA lewat aturan rewrite tersebut, walau file sumbernya sudah benar. Semua `.gz` terkait sudah diregenerasi dan diverifikasi byte-per-byte di rilis ini.

---

## 4. Alur Kerja Lengkap (Cara Kerjanya)

### Mode Kampanye / Tawanan / Boss / Tutorial

```
Pemain klik kartu spesial
        │
        ▼
playSpecialCard("player", uid)   [JSS_1/JSSCODETAWANAN.js]
        │
        ▼
applySpecial("player", kartu)    [JSS_2/JSSSYSTEM.js]
        │
        ├──► sfxSpecialCard()            (bunyi, selalu)
        └──► announceCard(kartu,"player") (banner, SEKARANG selalu — dulu tidak)
                     │
                     ├─ set label "KAMU MEMAINKAN" + class "mine"
                     └─ tambah class "show" → CSS memicu animasi (announceIn + announceSweep + announceRing)
```

AI/boss memainkan kartu → jalur yang sama persis, hanya `applySpecial("opponent", kartu)` → `announceCard(kartu,"opponent")` → label `"{lawan} MEMAINKAN"` (atau `"KARTU RAHASIA {lawan}"` kalau kartunya eksklusif) + class `"theirs"`/`"exclusive"`.

### Mode Multiplayer Online

```
Pemain klik kartu spesial (onlineMode = true)
        │
        ▼
playSpecialCard("player", uid) → window.mpSendAction("special", uid)   [dikirim ke server, TIDAK panggil applySpecial lokal]
        │
        ▼
   ⏳ server memproses & menyimpan state baru
        │
        ▼
Client polling (mpCheckGameStateOnce, tiap ~2.8 detik) → J(state_baru)
        │
        ▼
J() membandingkan state_baru vs state_lama (mpPrevState):
        │
        ├─ S.player.playedThisRound bertambah?  → mpAnnounceTrump(kartu, true)   → label "KAMU MEMAINKAN" + class "mine"
        └─ S.opponent.playedThisRound bertambah? → mpAnnounceTrump(kartu, false)  → label "{lawan} MEMAINKAN" + class "theirs"
```

Kedua pengecekan ini **independen** (dicek dengan dua `if` terpisah, bukan satu kondisi gabungan) — supaya kalau suatu saat kebetulan pemain & lawan sama-sama baru main kartu di siklus polling yang sama, dua-duanya tetap dapat banner masing-masing, bukan cuma salah satu.

---

## 5. Sistem Warna Banner — Referensi Cepat

Class-class berikut ditempel ke `#card-announce` oleh JavaScript (bukan ditulis manual di HTML), lalu CSS di `CSS_1/core_ui.css` yang menentukan tampilannya:

```
.mine        → emas / brass       (kartu milikmu)
.theirs      → merah redup        (kartu biasa milik lawan)
.exclusive   → merah terang       (kartu rahasia eksklusif lawan — menimpa .theirs)
.online-card → tosca              (kartu eksklusif mode online — menimpa semua di atas)
```

Urutan "menimpa" di atas murni soal urutan deklarasi rule di dalam `CSS_1/core_ui.css` (rule yang ditulis lebih belakang menang kalau elemennya kebetulan punya lebih dari satu class sekaligus, misalnya kartu online eksklusif yang dimainkan lawan bisa saja punya class `theirs` DAN `online-card` bersamaan — dalam kasus itu warna tosca yang tampil). Kalau mau mengubah prioritas ini, pindahkan urutan rule-nya di CSS, bukan urutan `classList.add()` di JS (urutan `add()` tidak memengaruhi prioritas CSS).

---

## 6. Hal yang JANGAN Diubah Sembarangan

Supaya perubahan di rilis ini tidak "hilang" tanpa sadar saat ada yang mengedit kode di masa depan (termasuk AI assistant lain):

1. **Jangan kembalikan syarat `"opponent"===e` (atau serupa)** sebelum `announceCard(a,e)` di `applySpecial()` (`JSSSYSTEM.js`). Itu akan mengembalikan bug lama: pemain tidak lihat animasi kartunya sendiri lagi.
2. **Jangan gabungkan kembali** blok deteksi trump pemain & lawan di fungsi `J()` (`JSS_MULTIPLAYER.js`) jadi satu kondisi `if` seperti dulu. Keduanya harus tetap dicek terpisah.
3. **Jangan hapus fungsi `mpAnnounceTrump()`** atau panggilannya di `JSS_MULTIPLAYER.js` — ini satu-satunya jalur banner untuk kartu di mode online, tidak ada penggantinya di file lain.
4. **Jangan hapus class `"mine"`/`"theirs"`** dari daftar `classList.remove(...)` di `JSSBOSSCODE.js` (`announceDesperatePhase`) maupun di blok `mirrorReflect` (`JSS_MULTIPLAYER.js`) — kalau dihapus, warna banner fase putus-asa Raja Iblis atau banner Cermin Takdir bisa salah warna kalau muncul tepat setelah banner kartu spesial.
5. **Setiap kali salah satu dari 6 file ini diedit lagi** (`index.html`, `CSS_1/core_ui.css`, `CSS_2/animations.css`, `JSS_2/JSSSYSTEM.js`, `JSS_2/JSS_MULTIPLAYER.js`, `JSS_1/JSSBOSSCODE.js`) — **regenerasi ulang `.gz`-nya** sebelum diunggah ke hosting (`gzip -9 -n -c namafile > namafile.gz`), atau perubahannya tidak akan pernah terlihat di production (lihat penjelasan `.htaccess` di bagian 3.6).
6. Struktur HTML `#card-announce` di `index.html` **tidak perlu** dan **tidak boleh** ditambah elemen baru untuk fitur ini — semua efek visual baru murni lewat CSS `::before`/`::after`, bukan elemen tambahan.

---

## 7. Kalau Ingin Menambah Varian Warna Baru di Masa Depan

Misalnya ingin menambah status baru (contoh: kartu dari event musiman dengan warna ungu). Pola yang sudah ada bisa dicontoh:

1. Di CSS (`CSS_1/core_ui.css`), tambah rule baru mengikuti pola `.card-announce.NAMASTATUS{border-color:...;box-shadow:...}` + `.card-announce.NAMASTATUS .announce-tag{color:...}` + `.card-announce.NAMASTATUS .announce-icon{background:...;border-color:...}`.
2. Di JS (`announceCard()` di `JSSSYSTEM.js` dan/atau `mpAnnounceTrump()` di `JSS_MULTIPLAYER.js`), tambahkan logika penentuan kapan class baru itu ditempel, dan pastikan class baru itu ikut masuk daftar `classList.remove(...)` supaya tidak nyangkut di pemakaian berikutnya.
3. Ingat naikkan versi (`V.0.9.x.0` atau sesuai konvensi) di `index.html` + regenerasi `.gz` + tambah entri baru di `CHANGELOG_LENGKAP.md`.

---

## 8. Referensi Cepat Berkas

| Berkas | Yang berubah |
|---|---|
| `JSS_2/JSSSYSTEM.js` | `announceCard()` dukung sisi pemain; `applySpecial()` panggil tanpa syarat |
| `JSS_2/JSS_MULTIPLAYER.js` | Fungsi baru `mpAnnounceTrump()`; deteksi trump pemain+lawan terpisah di `J()`; cleanup class di blok `mirrorReflect` |
| `JSS_1/JSSBOSSCODE.js` | Cleanup class `mine`/`theirs`/`online-card` di `announceDesperatePhase()` |
| `CSS_1/core_ui.css` | Restyle total `#card-announce`, badge ikon, class `.mine`/`.theirs` baru |
| `CSS_2/animations.css` | `@keyframes announceIn` dirombak + `announceSweep` & `announceRing` baru |
| `index.html` | Versi `V.0.9.0.0` → `V.0.9.1.0` (35 referensi) |
| `CHANGELOG_LENGKAP.md` | Entri rilis baru ditambahkan di paling atas |
| *(dokumen ini)* | Penjelasan detail supaya tidak disalahpahami/terhapus |

Semua perubahan kode juga sudah diberi komentar `/* [V.0.9.1.0] ... */` langsung di titik yang relevan di dalam file aslinya masing-masing (bukan cuma di dokumen ini) — jadi kalaupun dokumen ini suatu saat hilang, penjelasannya tetap menempel di kodenya.
