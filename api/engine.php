<?php
// File: api/engine.php
// Proyek: 21: Ruang Lilin
// Fungsi: Mesin server-side multiplayer: state game, efek kartu, validasi ronde, dan konfirmasi lanjut.
// Online 1v1 engine for 21: Ruang Lilin (simplified from JS engine)
// Works purely on arrays that are JSON-encoded into `rooms.game_state`.

class OnlineEngine {
    // Daftar kode kartu spesial (trump) untuk Mode Online. Setiap kode id unik dipakai mesin game untuk mengeksekusi efek.
    const CARD_POOL = [
        ['id'=>'draw3', 'name'=>'Tarik Kartu 3', 'desc'=>'Tarik kartu bernilai 3 dari dek jika masih ada.', 'icon'=>'draw3', 'weight'=>3, 'num'=>3], // KODE draw3 — tarik kartu 3 dari dek
        ['id'=>'draw5', 'name'=>'Tarik Kartu 5', 'desc'=>'Tarik kartu bernilai 5 dari dek jika masih ada.', 'icon'=>'draw5', 'weight'=>3, 'num'=>5], // KODE draw5 — tarik kartu 5 dari dek
        ['id'=>'draw7', 'name'=>'Tarik Kartu 7', 'desc'=>'Tarik kartu bernilai 7 dari dek jika masih ada.', 'icon'=>'draw7', 'weight'=>2, 'num'=>7], // KODE draw7 — tarik kartu 7 dari dek
        ['id'=>'draw9', 'name'=>'Tarik Kartu 9', 'desc'=>'Tarik kartu bernilai 9 dari dek jika masih ada.', 'icon'=>'draw9', 'weight'=>2, 'num'=>9], // KODE draw9 — tarik kartu 9 dari dek
        ['id'=>'perfect', 'name'=>'Tarikan Sempurna', 'desc'=>'Tarik kartu terbaik yang tersisa di dek untuk dirimu.', 'icon'=>'perfect', 'weight'=>1], // KODE perfect — tarik kartu terbaik dari dek
        ['id'=>'destroy', 'name'=>'Hancurkan', 'desc'=>'Kembalikan kartu terbuka terakhir lawan ke dalam dek.', 'icon'=>'destroy', 'weight'=>2], // KODE destroy — hapus kartu terbuka terakhir lawan
        ['id'=>'return', 'name'=>'Tarik Ulang', 'desc'=>'Kembalikan kartu terbukamu sendiri yang terakhir ke dek.', 'icon'=>'undo', 'weight'=>2], // KODE return — kembalikan kartu terbuka sendiri ke dek
        ['id'=>'exchange', 'name'=>'Pertukaran', 'desc'=>'Tukar kartu terakhirmu dengan kartu terakhir lawan.', 'icon'=>'exchange', 'weight'=>1], // KODE exchange — tukar kartu terakhir dengan lawan
        ['id'=>'goFor17', 'name'=>'Incar 17', 'desc'=>'Ubah target ronde ini menjadi 17.', 'icon'=>'targetLow', 'weight'=>2], // KODE goFor17 — target ronde jadi 17
        ['id'=>'goFor24', 'name'=>'Incar 24', 'desc'=>'Ubah target ronde ini menjadi 24.', 'icon'=>'targetHigh', 'weight'=>2], // KODE goFor24 — target ronde jadi 24
        ['id'=>'goFor27', 'name'=>'Incar 27', 'desc'=>'Ubah target ronde ini menjadi 27.', 'icon'=>'targetHighest', 'weight'=>1], // KODE goFor27 — target ronde jadi 27
        ['id'=>'oneUp', 'name'=>'Naikkan Taruhan', 'desc'=>'Jika lawan kalah ronde ini, ia kehilangan 1 nyala tambahan.', 'icon'=>'up', 'weight'=>2], // KODE oneUp — lawan kalah kehilangan +1 nyala
        ['id'=>'desperation', 'name'=>'Keringanan', 'desc'=>'Jika kau kalah ronde ini, kerugian nyalamu berkurang 1 (tidak menghapus seluruh kerugian).', 'icon'=>'down', 'weight'=>1], // KODE desperation — kalah ronde ini kerugian nyala berkurang 1
        ['id'=>'shield', 'name'=>'Perisai', 'desc'=>'Menangkal 1 efek berbahaya berikutnya dari lawan: Hancurkan, Sita, Rebut, Naikkan Taruhan, atau Bungkam.', 'icon'=>'shield', 'weight'=>2], // KODE shield — tangkal 1 efek berbahaya dari lawan
        ['id'=>'loveEnemy', 'name'=>'Kasih Untuk Musuh', 'desc'=>'Lawan menerima kartu terbaik untuknya sendiri.', 'icon'=>'heart', 'weight'=>1], // KODE loveEnemy — lawan menerima kartu terbaik untuknya
        ['id'=>'trumpSwitch', 'name'=>'Tukar Takdir', 'desc'=>'Buang hingga 2 kartu spesial acakmu, lalu ambil 3 kartu spesial baru.', 'icon'=>'cycle', 'weight'=>1], // KODE trumpSwitch — buang 2 kartu spesial, ambil 3 baru
        ['id'=>'remove', 'name'=>'Sita', 'desc'=>'Buang satu kartu spesial acak dari tangan lawan.', 'icon'=>'remove', 'weight'=>2], // KODE remove — buang 1 kartu spesial lawan
        ['id'=>'silence', 'name'=>'Bungkam', 'desc'=>'Batalkan 1x pengambilan kartu lawan di giliran berikutnya. Tembus oleh Tarik Kartu 3/5/7/9 & Tarikan Sempurna.', 'icon'=>'silence', 'weight'=>2], // KODE silence — batalkan 1 kali pengambilan kartu lawan di giliran berikutnya, bisa ditembus draw3/5/7/9/perfect
        ['id'=>'heal', 'name'=>'Suntikan Lilin', 'desc'=>'Pulihkan 1 nyala (tidak bisa melebihi batas maksimal).', 'icon'=>'soul', 'weight'=>1], // KODE heal — pulihkan 1 nyala
        ['id'=>'snatch', 'name'=>'Rebut', 'desc'=>'Ambil satu kartu spesial acak dari tangan lawan untuk dirimu.', 'icon'=>'singleClaw', 'weight'=>1], // KODE snatch — ambil 1 kartu spesial lawan
        ['id'=>'mulligan', 'name'=>'Kocok Ulang', 'desc'=>'Kembalikan SEMUA kartu di meja -- milikmu dan lawan, terbuka & tertutup -- ke dek, lalu bagikan ulang kartu baru untuk kedua pihak.', 'icon'=>'shuffle', 'weight'=>1], // KODE mulligan — kocok ulang semua kartu KEDUA pihak

        // ============================================================
        // KARTU EKSKLUSIF MODE ONLINE (ditambahkan V.0.8.2.0)
        // 10 kartu di bawah ini SENGAJA hanya ada di sini (CARD_POOL milik
        // OnlineEngine), TIDAK pernah ditambahkan ke CARD_POOL versi JS
        // (JSS_2/JSSSYSTEM.js) yang dipakai Mode Kampanye. Karena Kampanye
        // 100% berjalan di client (tidak pernah memanggil engine.php ini),
        // memisahkan definisinya di sini sudah cukup untuk menjamin kartu
        // ini TIDAK PERNAH muncul di Kampanye -- murni khusus Mode Online
        // & Multiplayer. Field 'online'=>true ikut dikirim ke client supaya
        // tampilannya bisa diberi gaya visual berbeda (lihat online-special
        // di CSS & JS).
        ['id'=>'peekHidden', 'name'=>'Intip Rahasia', 'desc'=>'Lihat kartu tertutup lawan untuk sisa ronde ini.', 'icon'=>'eye', 'weight'=>2, 'online'=>true], // KODE peekHidden — intip kartu tertutup lawan sisa ronde ini
        ['id'=>'randomDraw', 'name'=>'Tarikan Acak', 'desc'=>'Kamu dan lawan SAMA-SAMA menarik 1 kartu acak dari dek secara bersamaan.', 'icon'=>'dice', 'weight'=>3, 'online'=>true], // KODE randomDraw — kedua pihak menarik 1 kartu acak masing-masing, bersamaan
        ['id'=>'sweepVisible', 'name'=>'Sapu Bersih', 'desc'=>'Kembalikan SEMUA kartu terbuka lawan ke dalam dek secara acak.', 'icon'=>'broom', 'weight'=>1, 'online'=>true], // KODE sweepVisible — semua kartu terbuka lawan kembali ke dek
        ['id'=>'lifeSwap', 'name'=>'Tukar Nasib', 'desc'=>'Nasib bergeser: pihak yang nyalanya lebih banyak kehilangan 1, pihak yang tertinggal mendapat 1. Tak berefek kalau sudah sama.', 'icon'=>'lifeSwap', 'weight'=>2, 'online'=>true], // KODE lifeSwap — geser 1 nyala dari pemimpin ke yang tertinggal (bukan tukar total lagi)
        ['id'=>'doubleHeal', 'name'=>'Berkah Ganda', 'desc'=>'Pulihkan 2 nyala kalau nyalamu sedang 3 ke bawah (kritis); kalau lebih, cuma pulih 1.', 'icon'=>'healPlus', 'weight'=>2, 'online'=>true], // KODE doubleHeal — +2 hanya saat nyala <=3, else +1
        ['id'=>'copyLast', 'name'=>'Tiru Gerakan', 'desc'=>'Salin efek kartu spesial terakhir yang dimainkan lawan ronde ini, lalu gunakan untuk dirimu.', 'icon'=>'copyIcon', 'weight'=>1, 'online'=>true], // KODE copyLast — tiru kartu spesial terakhir milik lawan ronde ini
        ['id'=>'lockTarget', 'name'=>'Segel Target', 'desc'=>'Kunci target ronde ini -- tak ada kartu, milikmu atau lawan, yang bisa mengubahnya lagi sampai ronde berakhir.', 'icon'=>'lockIcon', 'weight'=>1, 'online'=>true], // KODE lockTarget — kunci target ronde, tak bisa diubah kartu manapun
        ['id'=>'mirrorGuard', 'name'=>'Cermin Takdir', 'desc'=>'Efeknya tersembunyi & pasif -- begitu lawan main kartu berbahaya, langsung dipantulkan jadi serangan balik ke pemainnya sendiri. Kalau tak sempat terpakai, hilang di akhir ronde.', 'icon'=>'mirrorIcon', 'weight'=>2, 'online'=>true], // KODE mirrorGuard — counter tersembunyi, terpakai otomatis saat trigger, expired di akhir ronde jika tidak
        ['id'=>'freezeTurn', 'name'=>'Bekukan', 'desc'=>'Giliran lawan berikutnya, ia tidak bisa memainkan kartu spesial -- Ambil Kartu & Bertahan tetap normal.', 'icon'=>'freezeIcon', 'weight'=>2, 'online'=>true], // KODE freezeTurn — blokir hanya special 1 giliran, hit/stand tetap normal (bukan auto-stand lagi)

        // ============================================================
        // KARTU AMBUSH (mirrorGuard) + BISIKAN BALAS DENDAM -- ditambahkan
        // V.0.8.3.2. revengeWhisper SENGAJA masuk const SECRET (lihat di
        // bawah) sama seperti mirrorGuard -- aktivasinya tidak boleh bocor
        // ke log yang dibagikan ke kedua sisi. Juga masuk const UNIQUE_ONCE:
        // tiap pemain cuma bisa mendapatkannya SEKALI seumur pertandingan
        // (lihat drawSpecialInstance/drawAndTrackSpecial).
        // ============================================================
        ['id'=>'revengeWhisper', 'name'=>'Bisikan Balas Dendam', 'desc'=>'Efeknya tersembunyi & pasif -- begitu kau kalah ronde ini, nyalamu langsung dikunci di 1 dan lilinmu berubah hitam busuk: terkutuk, tak bisa disembuhkan lagi dengan cara apapun sisa pertandingan. Kartu langka -- setiap pemain cuma bisa mendapatkannya sekali seumur pertandingan. Kalau tak sempat terpakai, hilang di akhir ronde.', 'icon'=>'snuffOut', 'weight'=>1, 'online'=>true], // KODE revengeWhisper — counter tersembunyi & langka, terpicu saat pemain ini KALAH ronde (lihat maybeTriggerRevenge)

        // ============================================================
        // 4 KARTU TARUHAN (Wager) BARU -- ditambahkan V.0.8.4.0. Semuanya
        // 'harmful' (menaikkan taruhan/kerugian utk salah satu atau kedua
        // pihak) sehingga otomatis bisa ditangkal Perisai/Cermin Takdir
        // seperti kartu berbahaya lain -- lihat const HARMFUL di bawah.
        // ============================================================
        ['id'=>'doubleWager', 'name'=>'Taruhan Mendesak', 'desc'=>'Naikkan taruhan ronde ini untuk KEDUA pihak: +3 jika dimainkan di awal ronde (sebelum ada yang Ambil Kartu/Bertahan), +2 jika tepat di giliran kedua, +1 jika sudah lewat giliran kedua.', 'icon'=>'urgentWager', 'weight'=>1, 'online'=>true], // KODE doubleWager — besar efek menurun seiring turns_taken (lihat applySpecialEffect)
        ['id'=>'compoundWager', 'name'=>'Taruhan Berlipat', 'desc'=>'Naikkan taruhan ronde ini untuk KEDUA pihak sebesar jumlah kartu spesial yang SUDAH dimainkan LAWAN ronde ini (maksimal +3).', 'icon'=>'compoundWager', 'weight'=>1, 'online'=>true], // KODE compoundWager — besarnya = count(played_this_round lawan), dibatasi maks 3
        ['id'=>'recklessWager', 'name'=>'Taruhan Nekat', 'desc'=>'Naikkan taruhan ronde ini untuk KEDUA pihak sebesar 2, lalu pulihkan 1 nyala untuk dirimu sendiri sebagai kompensasi risiko.', 'icon'=>'recklessWager', 'weight'=>1, 'online'=>true], // KODE recklessWager — +2 taruhan kedua pihak, +1 nyala pemain sendiri (diblok jika cursed)
        ['id'=>'lopsidedWager', 'name'=>'Taruhan Berat Sebelah', 'desc'=>'Kalau kau kalah ronde ini, kerugianmu bertambah 1. Kalau lawan yang kalah, kerugiannya bertambah 2 -- taruhan sengaja timpang menguntungkanmu.', 'icon'=>'lopsidedWager', 'weight'=>1, 'online'=>true] // KODE lopsidedWager — bet_mod asimetris: +1 utk diri sendiri, +2 utk lawan
    ];

    // peekHidden, lockTarget, dan mirrorGuard efeknya bertahan sepanjang ronde
    // (persis seperti shield/goFor/oneUp), jadi ikut memakai salah satu dari 5
    // slot meja -- supaya tetap adil & konsisten dengan kartu persistent lama.
    const PERSISTENT = ['goFor17','goFor24','goFor27','oneUp','desperation','shield','peekHidden','lockTarget','mirrorGuard','revengeWhisper','doubleWager','compoundWager','recklessWager','lopsidedWager'];
    // sweepVisible, lifeSwap, dan freezeTurn merugikan lawan secara langsung,
    // jadi bisa ditangkal Perisai/Cermin Takdir sama seperti kartu berbahaya lama.
    // 4 kartu Taruhan (V.0.8.4.0) juga masuk sini -- semuanya menaikkan
    // taruhan/kerugian, jadi tunduk pada aturan tangkal yang sama.
    const HARMFUL = ['destroy','remove','oneUp','silence','snatch','sweepVisible','lifeSwap','freezeTurn','doubleWager','compoundWager','recklessWager','lopsidedWager'];
    const SELF_DRAW = ['draw3','draw5','draw7','draw9','perfect','randomDraw'];
    const HAND_CAP = 99;
    const TABLE_LIMIT = 5;
    const STARTING_LIFE = 6;
    // Kartu yang aktivasinya TIDAK BOLEH tercatat di log bersama (dibagikan
    // mentah ke kedua sisi) maupun muncul di played_this_round milik lawan --
    // lihat doSpecial() & stripSecrets(). V.0.8.3.2: awalnya cuma mirrorGuard
    // (perbaikan kebocoran), lalu revengeWhisper (V.0.8.3.2) ikut masuk sejak
    // dibuat krn sifatnya sama-sama pasif & tersembunyi.
    const SECRET = ['mirrorGuard','revengeWhisper'];
    // Kartu yang cuma boleh didapat SEKALI seumur pertandingan per pemain --
    // lihat drawSpecialInstance()/drawAndTrackSpecial() & freshPlayer()['unique_granted'].
    const UNIQUE_ONCE = ['revengeWhisper'];

    // ============================================================
    // [PENGATURAN RUANGAN] Pilihan yang boleh dikustomisasi host di ruangan
    // kode PRIVAT saja (lihat resolveSettingsForRoom di bawah). Daftar di
    // sini adalah whitelist -- room.php & sini sama-sama memvalidasi
    // terhadap daftar ini, jadi client tidak bisa mengirim nilai sembarang
    // (mis. waktu giliran 1 detik) meski memodifikasi request-nya sendiri.
    // ============================================================
    const TURN_SECONDS_OPTIONS = [30, 45, 60, 90, 120];
    const STARTING_LIFE_OPTIONS = [4, 6, 8, 10];

    // Nilai default = persis perilaku "klasik" dari sebelum fitur pengaturan
    // ini ada. Dipakai utk: ruangan PUBLIK (lobi publik & hasil Cari Lawan),
    // ruangan privat lama yg belum punya kolom settings, dan JSON settings
    // yang rusak/tidak valid.
    private static function defaultSettings() {
        return [
            'turn_seconds' => 60,
            'starting_life' => self::STARTING_LIFE,
            'card_pool_ids' => array_column(self::CARD_POOL, 'id'),
        ];
    }

    // ⚠️ PENTING -- JANGAN HAPUS/LEWATI pengecekan $visibility di bawah ini.
    // Ini LAPIS KEDUA dari jaminan "matchmaking selalu klasik" (lapis
    // pertama ada di room.php, aksi 'create'). Kalau baris pengecekan
    // visibility ini dihapus/dilewati, ruangan publik/hasil matchmaking BISA
    // ikut memakai pengaturan kustom -- itu justru hal yang diminta untuk
    // TIDAK PERNAH terjadi. Lihat DOKUMENTASI_MATCHMAKING_DAN_PENGATURAN.md
    // bagian "Aturan Emas" utk detail lengkapnya.
    //
    // Titik masuk TUNGGAL utk membaca pengaturan sebuah room. Sengaja
    // mengecek $room['visibility'] DI SINI JUGA (bukan cuma percaya pada
    // room.php yg menulisnya) sebagai lapis pertahanan kedua: apa pun yang
    // tersimpan di kolom `settings`, kalau ruangannya bukan 'private',
    // selalu dianggap tidak ada -- menjamin Cari Lawan & Lobi Publik SELALU
    // main dengan aturan klasik, tidak peduli apa pun isi datanya.
    public static function resolveSettingsForRoom($room) {
        $visibility = $room['visibility'] ?? 'private';
        $raw = ($visibility === 'private') ? ($room['settings'] ?? null) : null;
        if (empty($raw)) return self::defaultSettings();
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return self::defaultSettings();
        $turnSeconds = (int)($decoded['turn_seconds'] ?? 60);
        $startingLife = (int)($decoded['starting_life'] ?? self::STARTING_LIFE);
        return [
            'turn_seconds' => in_array($turnSeconds, self::TURN_SECONDS_OPTIONS, true) ? $turnSeconds : 60,
            'starting_life' => in_array($startingLife, self::STARTING_LIFE_OPTIONS, true) ? $startingLife : self::STARTING_LIFE,
            'card_pool_ids' => self::sanitizeCardIds($decoded['enabled_cards'] ?? null),
        ];
    }

    // Saring daftar id kartu kiriman client supaya cuma berisi id yang benar-
    // benar ada di CARD_POOL. Kalau hasilnya kosong (tidak valid sama sekali,
    // atau host somehow mematikan semua kartu), fallback ke SEMUA kartu --
    // pool kosong akan membuat drawSpecialInstance() tidak bisa membagikan
    // kartu spesial sama sekali, jadi ini bukan cuma validasi tapi juga
    // jaring pengaman gameplay.
    private static function sanitizeCardIds($ids) {
        $allIds = array_column(self::CARD_POOL, 'id');
        if (!is_array($ids)) return $allIds;
        $clean = array_values(array_intersect($allIds, $ids));
        return empty($clean) ? $allIds : $clean;
    }

    // Kembalikan definisi kartu (nama/desc/icon/weight) yang boleh ditarik
    // utk state ini, hasil filter CARD_POOL penuh berdasar $state['card_pool_ids']
    // yang disimpan sekali di freshState(). Dipakai drawSpecialInstance().
    private static function effectiveCardPool($state) {
        if (empty($state['card_pool_ids']) || !is_array($state['card_pool_ids'])) return self::CARD_POOL;
        $ids = $state['card_pool_ids'];
        $out = array_values(array_filter(self::CARD_POOL, function($c) use ($ids) { return in_array($c['id'], $ids, true); }));
        return empty($out) ? self::CARD_POOL : $out;
    }

    public static function freshState($room) {
        $settings = self::resolveSettingsForRoom($room);
        $state = [
            'status' => 'playing',
            'room_code' => $room['room_code'],
            'host_id' => (int)$room['host_id'],
            'guest_id' => (int)$room['guest_id'],
            'host_name' => $room['host_name'] ?? 'Host',
            'guest_name' => $room['guest_name'] ?? 'Guest',
            'turn' => 'host',
            'round' => 0,
            'target' => 21,
            'target_locked' => false, // diset true oleh kartu online 'lockTarget' (Segel Target)
            // [PENGATURAN RUANGAN] turn_seconds & lives_cap disimpan di state
            // (bukan cuma dibaca sekali di sini) supaya startRound(), heal-cap,
            // dan checkTimeout() di ronde-ronde berikutnya tetap konsisten
            // memakai nilai yang sama sepanjang pertandingan ini.
            'turn_seconds' => $settings['turn_seconds'],
            'lives_cap' => $settings['starting_life'],
            'card_pool_ids' => $settings['card_pool_ids'],
            'lives' => ['host' => $settings['starting_life'], 'guest' => $settings['starting_life']],
            'deck' => [],
            'host' => self::freshPlayer(),
            'guest' => self::freshPlayer(),
            'history' => [],
            'awaiting_continue' => false,
            'round_over' => false,
            'finished' => false,
            'winner' => null,
            'logs' => [],
            'last_action_at' => time(),
            'turn_deadline' => time() + $settings['turn_seconds'],
            'continue_confirmations' => ['host' => false, 'guest' => false],
            'continue_deadline' => null,
            // Skor kepala-lawan-kepala antar dua pemain ini SELAMA masih di room yang
            // sama (bertahan lewat rematch). Nilainya dibawa manual saat rematch terjadi
            // di game_action.php -- freshState() sendiri selalu mulai dari 0-0.
            'session_score' => ['host' => 0, 'guest' => 0],
            'rematch_requested' => ['host' => false, 'guest' => false],
        ];
        self::startRound($state, true);
        return $state;
    }

    private static function freshPlayer() {
        return [
            'hidden' => null,
            'visible' => [],
            'specials' => [],
            'shield_active' => false,
            'no_loss' => false,
            'bet_mod' => 0,
            'silenced' => 0,
            'silenced_turn_based' => false,
            'played_this_round' => [],
            'table_slots_used' => 0,
            // Field-field di bawah khusus mendukung kartu online baru:
            'peek_active' => false,  // 'peekHidden' — kartu tertutup lawan ikut terkirim ke sisi ini (lihat stripSecrets)
            'mirror_active' => false, // 'mirrorGuard' — 1 efek berbahaya lawan berikutnya dipantulkan balik
            'frozen' => false,        // 'freezeTurn' — giliran berikutnya otomatis bertahan (lihat action())
            'revenge_active' => false, // 'revengeWhisper' — bersenjata sisa ronde ini, terpicu saat pemain ini KALAH ronde (lihat maybeTriggerRevenge). Direset tiap ronde baru, TIDAK permanen.
            'cursed' => false,         // dipicu revengeWhisper — PERMANEN sisa pertandingan, TIDAK direset per ronde: memblokir semua cara menaikkan nyala (lihat 'heal'/'doubleHeal'/'lifeSwap'/'recklessWager')
            'unique_granted' => [],    // daftar id kartu UNIQUE_ONCE yang sudah pernah didapat pemain ini — PERMANEN sisa pertandingan, mencegah draw ulang (lihat drawSpecialInstance)
        ];
    }

    private static function freshDeck() {
        $d = [1,2,3,4,5,6,7,8,9,10,11];
        shuffle($d);
        return $d;
    }

    private static function trumpsPerRound($round) {
        if ($round <= 1) return 1;
        if ($round === 2) return 2;
        return 3;
    }

    private static function uid(&$state) {
        if (!isset($state['_uid'])) $state['_uid'] = 1;
        return $state['_uid']++;
    }

    private static function drawSpecialInstance(&$state, $existing, $permanentlyExcluded = []) {
        $pool = self::effectiveCardPool($state);
        $existingIds = array_column($existing, 'id');
        $blockedIds = array_unique(array_merge($existingIds, $permanentlyExcluded));
        $choices = [];
        foreach ($pool as $c) {
            if (!in_array($c['id'], $blockedIds)) $choices[] = $c;
        }
        if (empty($choices)) {
            // Fallback kalau semua kartu non-unik di pool sudah ada di tangan:
            // boleh dobel kartu biasa lagi, TAPI kartu UNIQUE_ONCE yang sudah
            // pernah didapat pemain ini tetap wajib dikecualikan secara permanen.
            foreach ($pool as $c) {
                if (!in_array($c['id'], $permanentlyExcluded)) $choices[] = $c;
            }
        }
        if (empty($choices)) $choices = $pool; // fallback ekstrem, seharusnya tak pernah terjadi
        $total = array_sum(array_column($choices, 'weight'));
        $r = mt_rand(1, 1000000) / 1000000 * $total;
        foreach ($choices as $c) {
            $r -= $c['weight'];
            if ($r <= 0) {
                $copy = $c;
                $copy['uid'] = 'c' . self::uid($state);
                return $copy;
            }
        }
        $c = $choices[0];
        $copy = $c;
        $copy['uid'] = 'c' . self::uid($state);
        return $copy;
    }

    // Wrapper drawSpecialInstance() yang SELALU dipakai di titik-titik pembagian
    // kartu spesial ke pemain (startRound/trumpSwitch) -- otomatis mencatat ke
    // $p['unique_granted'] kalau kartu yang ditarik termasuk UNIQUE_ONCE, supaya
    // pemain yang sama tidak pernah bisa menariknya lagi seumur pertandingan ini.
    private static function drawAndTrackSpecial(&$state, &$p) {
        $card = self::drawSpecialInstance($state, $p['specials'], $p['unique_granted']);
        if (in_array($card['id'], self::UNIQUE_ONCE, true) && !in_array($card['id'], $p['unique_granted'], true)) {
            $p['unique_granted'][] = $card['id'];
        }
        return $card;
    }

    public static function startRound(&$state, $isFirst = false) {
        $state['round']++;
        $state['deck'] = self::freshDeck();
        $state['target'] = 21;
        $state['target_locked'] = false;
        $state['history'] = [];
        $state['awaiting_continue'] = false;
        $state['round_over'] = false;
        $state['continue_confirmations'] = ['host' => false, 'guest' => false];
        $state['continue_deadline'] = null;
        foreach (['host','guest'] as $side) {
            $p = &$state[$side];
            $p['hidden'] = null;
            $p['visible'] = [];
            $p['shield_active'] = false;
            $p['no_loss'] = false;
            $p['bet_mod'] = 0;
            $p['silenced'] = 0;
            $p['silenced_turn_based'] = false;
            $p['played_this_round'] = [];
            $p['table_slots_used'] = 0;
            $p['peek_active'] = false;
            $p['mirror_active'] = false;
            $p['frozen'] = false;
            $p['revenge_active'] = false;
            // SENGAJA tidak direset di sini: 'cursed' (kutukan revengeWhisper)
            // dan 'unique_granted' (riwayat kartu unik yang pernah didapat)
            // harus bertahan SEPANJANG pertandingan, bukan cuma 1 ronde.
        }
        // add specials: 1 di ronde 1, 2 di ronde 2, 3 di ronde 3+
        $n = self::trumpsPerRound($state['round']);
        foreach (['host','guest'] as $side) {
            $p = &$state[$side];
            for ($i = 0; $i < $n; $i++) {
                if (count($p['specials']) < self::HAND_CAP) {
                    $p['specials'][] = self::drawAndTrackSpecial($state, $p);
                }
            }
        }
        $state['turn'] = ($state['round'] % 2 === 1) ? 'host' : 'guest';
        // turns_taken: dihitung ulang tiap ronde, naik 1 setiap satu aksi Ambil
        // Kartu/Bertahan selesai (lihat afterAction()). Memainkan kartu spesial
        // TIDAK menaikkan ini. Dipakai 'doubleWager' (Taruhan Mendesak) untuk
        // menentukan besar efeknya.
        $state['turns_taken'] = 0;

        $state['host']['hidden'] = array_pop($state['deck']);
        $state['guest']['hidden'] = array_pop($state['deck']);
        $hostStart = array_pop($state['deck']);
        $guestStart = array_pop($state['deck']);
        if ($hostStart !== null) $state['host']['visible'][] = $hostStart;
        if ($guestStart !== null) $state['guest']['visible'][] = $guestStart;
        $state['turn_deadline'] = time() + ($state['turn_seconds'] ?? 60);
        // Detail tambahan: sebutkan nilai kartu TERBUKA yang dibagikan (menyamakan level
        // detail dengan Mode Kampanye, lihat log dealRound() di JSSSYSTEM.js). Kartu
        // TERTUTUP ('hidden') sengaja tidak pernah disebut di sini -- itu tetap rahasia
        // masing-masing pemain sampai ronde berakhir, tidak boleh bocor lewat Riwayat Kejadian.
        if ($hostStart !== null && $guestStart !== null) {
            self::log($state, 'sys', 'Ronde ' . $state['round'] . ' dimulai. Kartu awal dibagikan: ' . $state['host_name'] . ' dapat ' . $hostStart . ', ' . $state['guest_name'] . ' dapat ' . $guestStart . '.', 'round_start');
        } else {
            self::log($state, 'sys', 'Ronde ' . $state['round'] . ' dimulai. Kartu awal dibagikan.', 'round_start');
        }
    }

    public static function action(&$state, $actor, $action, $payload = []) {
        if ($state['finished']) return ['ok'=>false, 'error'=>'Permainan sudah selesai.'];
        if ($action === 'surrender') return self::doSurrender($state, $actor);
        if ($state['awaiting_continue']) {
            if ($action === 'continue') {
                return self::doContinue($state, $actor);
            }
            return ['ok'=>false, 'error'=>'Menunggu konfirmasi lanjut dari kedua pemain.'];
        }
        if ($action === 'continue') {
            return ['ok'=>true];
        }
        if ($state['turn'] !== $actor) return ['ok'=>false, 'error'=>'Bukan giliranmu.'];

        // Kartu online 'freezeTurn' (Bekukan) -- direvisi supaya tidak terlalu kuat:
        // dulu memaksa auto-bertahan (menghilangkan SEMUA pilihan giliran itu),
        // sekarang HANYA memblokir permainan kartu spesial. Ambil Kartu & Bertahan
        // tetap berjalan seperti biasa, dan status beku otomatis habis begitu
        // giliran ini benar-benar berakhir (yaitu saat hit/stand dipanggil).
        if ($action === 'special' && !empty($state[$actor]['frozen'])) {
            return ['ok'=>false, 'error'=>'Kamu sedang beku akibat kartu Bekukan -- tidak bisa memainkan kartu spesial giliran ini. Ambil Kartu atau Bertahan tetap bisa.'];
        }

        if ($action === 'hit') {
            $state[$actor]['frozen'] = false;
            return self::doHit($state, $actor);
        }
        if ($action === 'stand') {
            $state[$actor]['frozen'] = false;
            return self::doStand($state, $actor);
        }
        if ($action === 'special') {
            $uid = $payload['uid'] ?? '';
            return self::doSpecial($state, $actor, $uid);
        }
        return ['ok'=>false, 'error'=>'Aksi tidak dikenal.'];
    }

    private static function doHit(&$state, $actor) {
        $p = &$state[$actor];
        if ($p['silenced'] > 0) {
            $p['silenced']--;
            self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' dibungkam — satu kali pengambilan kartu dibatalkan.');
            return self::doStand($state, $actor);
        }
        if (empty($state['deck'])) {
            self::log($state, 'sys', 'Dek kosong, ' . ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' terpaksa bertahan.');
            return self::doStand($state, $actor);
        }
        $v = array_pop($state['deck']);
        $p['visible'][] = $v;
        self::log($state, $actor, ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' mengambil kartu ' . $v . '.');
        return self::afterAction($state, $actor, 'hit');
    }

    private static function doStand(&$state, $actor) {
        self::log($state, $actor, ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' bertahan.');
        return self::afterAction($state, $actor, 'stand');
    }

    private static function doSpecial(&$state, $actor, $cardUid) {
        $p = &$state[$actor];
        $idx = -1;
        foreach ($p['specials'] as $i => $c) {
            if ($c['uid'] === $cardUid) { $idx = $i; break; }
        }
        if ($idx < 0) return ['ok'=>false, 'error'=>'Kartu spesial tidak ditemukan.'];
        $card = $p['specials'][$idx];
        $isPersistent = in_array($card['id'], self::PERSISTENT, true);
        if ($isPersistent && $p['table_slots_used'] >= self::TABLE_LIMIT) {
            return ['ok'=>false, 'error'=>'Meja penuh.'];
        }
        if ($card['id'] === 'mulligan') {
            $mOpp = self::opponent($actor);
            $actorTotal = count($p['visible']) + ($p['hidden'] !== null ? 1 : 0);
            $oppTotal = count($state[$mOpp]['visible']) + ($state[$mOpp]['hidden'] !== null ? 1 : 0);
            // Semua kartu yang dikembalikan (milik kedua pihak) otomatis mengisi ulang
            // dek, jadi tidak pernah kekurangan -- cukup pastikan ada sesuatu untuk dikocok.
            if ($actorTotal + $oppTotal === 0) {
                return ['ok'=>false, 'error'=>'Kocok ulang gagal — tidak ada kartu di meja.'];
            }
        }
        // Semua kartu spesial dikeluarkan dari tangan saat dimainkan.
        // Kartu aktif (persistent) ditambahkan ke slot meja (maks 5).
        // Kartu instant tidak memakan slot, tetapi tiap kartu hanya sekali pakai.
        array_splice($p['specials'], $idx, 1);
        $p['played_this_round'][] = ['id'=>$card['id'], 'name'=>$card['name'], 'icon'=>$card['icon'], 'badge'=>($card['badge'] ?? ''), 'desc'=>$card['desc'], 'blocked'=>false, 'exclusive'=>false, 'online'=>($card['online'] ?? false)];
        // Kartu rahasia (mis. mirrorGuard/Cermin Takdir, revengeWhisper/Bisikan
        // Balas Dendam) SENGAJA TIDAK dicatat di log "memainkan kartu spesial"
        // -- log ini dibagikan mentah ke kedua sisi, jadi mencatatnya di sini
        // akan langsung membongkar kartu tersembunyi itu ke lawan.
        if (!in_array($card['id'], self::SECRET, true)) {
            self::log($state, $actor, ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' memainkan kartu spesial: ' . $card['name'] . '.');
        }

        if (!empty($state['history'])) {
            $state['history'] = [];
            self::log($state, 'sys', 'Keadaan berubah; kedua pihak perlu bertahan lagi.');
        }

        $blocked = false;
        $reflected = false;
        $opp = self::opponent($actor);
        if (in_array($card['id'], self::HARMFUL, true)) {
            if (!empty($state[$opp]['shield_active'])) {
                $state[$opp]['shield_active'] = false;
                $blocked = true;
                self::log($state, 'sys', ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' menangkal efek dengan Perisai!');
            } elseif (!empty($state[$opp]['mirror_active'])) {
                // Kartu online 'mirrorGuard' (Cermin Takdir): dicek HANYA kalau
                // korban tidak (lagi) punya Perisai aktif -- Perisai tetap jadi
                // pertahanan lapis pertama seperti sebelumnya, Cermin lapis kedua.
                $state[$opp]['mirror_active'] = false;
                $reflected = true;
                // 'target' => $actor (V.0.8.7.1): $actor di sini adalah korban pantulan
                // (pemain yang kartu berbahayanya baru saja dibalikkan), BUKAN pemilik
                // Cermin Takdir -- lihat renderMirrorReveal() di JSS_MULTIPLAYER.js.
                self::log($state, 'sys', ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' memantulkan efek itu balik dengan Cermin Takdir!', 'mirrorReflect', $actor);
            }
        }
        if ($blocked) {
            $p['played_this_round'][count($p['played_this_round'])-1]['blocked'] = true;
            return ['ok'=>true];
        }

        if ($isPersistent) {
            $p['table_slots_used']++;
        }

        // Kartu Tarik Kartu 3/5/7/9 & Tarikan Sempurna adalah kemampuan kartu spesial,
        // BUKAN pengambilan kartu biasa (tombol Ambil Kartu) -- jadi harus tetap tembus
        // Bungkam, sama seperti perilaku di mode kampanye. Tidak ada pengecekan silenced
        // di sini secara sengaja.
        if ($reflected) {
            // Tandai chip di meja supaya kedua pemain tahu efeknya terpantul,
            // lalu jalankan effect dengan actor/opp DITUKAR -- caster asli kini
            // jadi korban dari kartunya sendiri.
            $p['played_this_round'][count($p['played_this_round'])-1]['badge'] = '↩ Terpantul';
            self::applySpecialEffect($state, $opp, $card);
        } else {
            self::applySpecialEffect($state, $actor, $card);
        }
        return ['ok'=>true];
    }

    private static function applySpecialEffect(&$state, $actor, $card) {
        $p = &$state[$actor];
        $opp = self::opponent($actor);
        $o = &$state[$opp];
        switch ($card['id']) {
            case 'draw3': case 'draw5': case 'draw7': case 'draw9':
                $n = $card['num'];
                $i = array_search($n, $state['deck'], true);
                if ($i !== false) {
                    array_splice($state['deck'], $i, 1);
                    $p['visible'][] = $n;
                    self::log($state, 'sys', 'Kartu bernilai ' . $n . ' ditarik dari dek.');
                } else {
                    self::log($state, 'sys', 'Kartu bernilai ' . $n . ' sudah tidak ada di dek.');
                }
                break;
            case 'perfect':
                $cur = self::trueTotal($state, $actor);
                $v = self::bestValueFor($cur, $state['deck'], $state['target']);
                if ($v !== null) {
                    $i = array_search($v, $state['deck'], true);
                    if ($i !== false) array_splice($state['deck'], $i, 1);
                    $p['visible'][] = $v;
                    self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' menarik kartu terbaik: ' . $v . '.');
                } else {
                    self::log($state, 'sys', 'Dek kosong, tidak ada kartu yang bisa ditarik.');
                }
                break;
            case 'destroy':
                if (!empty($o['visible'])) {
                    $v = array_pop($o['visible']);
                    self::insertCardRandom($state['deck'], $v);
                    self::log($state, 'sys', 'Kartu terbuka ' . ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' bernilai ' . $v . ' dikembalikan ke dek secara acak.');
                } else {
                    self::log($state, 'sys', 'Tidak ada kartu terbuka lawan untuk dihancurkan.');
                }
                break;
            case 'return':
                if (!empty($p['visible'])) {
                    $v = array_pop($p['visible']);
                    self::insertCardRandom($state['deck'], $v);
                    self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' mengembalikan kartu bernilai ' . $v . ' ke dek secara acak.');
                } else {
                    self::log($state, 'sys', 'Tidak ada kartu sendiri untuk ditarik ulang.');
                }
                break;
            case 'exchange':
                if (!empty($p['visible']) && !empty($o['visible'])) {
                    $tmp = array_pop($p['visible']);
                    $p['visible'][] = array_pop($o['visible']);
                    $o['visible'][] = $tmp;
                    self::log($state, 'sys', 'Kartu terakhir ditukar antara kedua pihak.');
                } else {
                    self::log($state, 'sys', 'Pertukaran gagal.');
                }
                break;
            case 'goFor17':
                if (!empty($state['target_locked'])) { self::log($state, 'sys', 'Target sudah dikunci Segel Target -- tidak bisa diubah lagi.'); break; }
                $state['target'] = 17; self::log($state, 'sys', 'Target ronde berubah menjadi 17.'); break;
            case 'goFor24':
                if (!empty($state['target_locked'])) { self::log($state, 'sys', 'Target sudah dikunci Segel Target -- tidak bisa diubah lagi.'); break; }
                $state['target'] = 24; self::log($state, 'sys', 'Target ronde berubah menjadi 24.'); break;
            case 'goFor27':
                if (!empty($state['target_locked'])) { self::log($state, 'sys', 'Target sudah dikunci Segel Target -- tidak bisa diubah lagi.'); break; }
                $state['target'] = 27; self::log($state, 'sys', 'Target ronde berubah menjadi 27.'); break;
            case 'oneUp': $o['bet_mod']++; self::log($state, 'sys', 'Jika lawan kalah ronde ini, kerugiannya bertambah.'); break;
            case 'desperation': $p['no_loss'] = true; self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' akan kehilangan nyala lebih sedikit jika kalah ronde ini.'); break;
            case 'shield': $p['shield_active'] = true; self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' kini terlindungi Perisai.'); break;
            case 'loveEnemy':
                if ($o['silenced'] > 0) {
                    $o['silenced']--;
                    self::log($state, 'sys', ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' dibungkam, pemberian kartu dibatalkan.');
                    break;
                }
                $cur = self::trueTotal($state, $opp);
                $v = self::bestValueFor($cur, $state['deck'], $state['target']);
                if ($v !== null) {
                    $i = array_search($v, $state['deck'], true);
                    if ($i !== false) array_splice($state['deck'], $i, 1);
                    $o['visible'][] = $v;
                    self::log($state, 'sys', ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' menerima kartu ' . $v . '.');
                } else {
                    self::log($state, 'sys', 'Dek kosong.');
                }
                break;
            case 'trumpSwitch':
                $toDiscard = min(2, count($p['specials']));
                for ($i=0; $i<$toDiscard; $i++) {
                    $idx = array_rand($p['specials']);
                    array_splice($p['specials'], $idx, 1);
                }
                for ($i=0; $i<3; $i++) {
                    if (count($p['specials']) < self::HAND_CAP + 3) $p['specials'][] = self::drawAndTrackSpecial($state, $p);
                }
                self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' mengocok ulang kartu spesialnya.');
                break;
            case 'remove':
                if (!empty($o['specials'])) {
                    $idx = array_rand($o['specials']);
                    $seized = array_splice($o['specials'], $idx, 1)[0];
                    self::log($state, 'sys', 'Kartu spesial ' . ($seized['name'] ?? '') . ' milik lawan disita.');
                } else {
                    self::log($state, 'sys', 'Lawan tidak punya kartu spesial untuk disita.');
                }
                break;
            case 'silence':
                $o['silenced'] = 1;
                $o['silenced_turn_based'] = true;
                self::log($state, 'sys', ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' dibungkam — satu kali pengambilan kartu berikutnya dibatalkan.');
                break;
            case 'heal':
                if (!empty($p['cursed'])) {
                    self::log($state, 'sys', 'Lilin ' . ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' sudah terkutuk -- Suntikan Lilin tidak berefek.');
                    break;
                }
                $state['lives'][$actor] = min($state['lives_cap'] ?? self::STARTING_LIFE, $state['lives'][$actor] + 1);
                self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' memulihkan 1 nyala.');
                break;
            case 'snatch':
                $eligible = array_values(array_filter($o['specials'], function($c){
                    return ($c['id'] ?? '') !== 'snatch' && ($c['id'] ?? '') !== 'remove';
                }));
                if (!empty($eligible)) {
                    $pick = $eligible[array_rand($eligible)];
                    $idx = null;
                    foreach ($o['specials'] as $i => $c) {
                        if (($c['uid'] ?? null) === ($pick['uid'] ?? null)) { $idx = $i; break; }
                    }
                    if ($idx !== null) {
                        $stolen = array_splice($o['specials'], $idx, 1)[0];
                        if (count($p['specials']) < self::HAND_CAP + 3) $p['specials'][] = $stolen;
                        self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' merebut kartu spesial ' . ($stolen['name'] ?? '') . ' dari lawan.');
                    }
                } else {
                    self::log($state, 'sys', 'Lawan tidak punya kartu spesial yang bisa direbut.');
                }
                break;
            // ============================================================
            // EFEK 10 KARTU EKSKLUSIF MODE ONLINE (V.0.8.2.0)
            // ============================================================
            case 'peekHidden':
                $p['peek_active'] = true;
                self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' kini bisa melihat kartu tertutup lawan untuk sisa ronde ini.');
                break;
            case 'randomDraw':
                // Direvisi: dulu cuma actor yang menarik, sekarang KEDUA pemain
                // sama-sama menarik 1 kartu acak masing-masing, bersamaan --
                // jadi bukan keuntungan sepihak lagi, murni acak untuk berdua.
                $drawnActor = null; $drawnOpp = null;
                if (!empty($state['deck'])) {
                    $idx = array_rand($state['deck']);
                    $drawnActor = $state['deck'][$idx];
                    array_splice($state['deck'], $idx, 1);
                    $p['visible'][] = $drawnActor;
                }
                if (!empty($state['deck'])) {
                    $idx2 = array_rand($state['deck']);
                    $drawnOpp = $state['deck'][$idx2];
                    array_splice($state['deck'], $idx2, 1);
                    $o['visible'][] = $drawnOpp;
                }
                $actorName = $actor === 'host' ? $state['host_name'] : $state['guest_name'];
                $oppName = $opp === 'host' ? $state['host_name'] : $state['guest_name'];
                if ($drawnActor !== null && $drawnOpp !== null) {
                    self::log($state, 'sys', 'Tarikan Acak: ' . $actorName . ' menarik ' . $drawnActor . ', ' . $oppName . ' ikut menarik ' . $drawnOpp . ' -- bersamaan.');
                } elseif ($drawnActor !== null) {
                    self::log($state, 'sys', 'Dek nyaris habis -- cuma ' . $actorName . ' yang kebagian kartu acak (' . $drawnActor . ').');
                } else {
                    self::log($state, 'sys', 'Dek kosong, Tarikan Acak tidak menghasilkan apa-apa untuk siapa pun.');
                }
                break;
            case 'sweepVisible':
                if (!empty($o['visible'])) {
                    $swept = count($o['visible']);
                    foreach ($o['visible'] as $v) {
                        self::insertCardRandom($state['deck'], $v);
                    }
                    $o['visible'] = [];
                    self::log($state, 'sys', $swept . ' kartu terbuka milik ' . ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' disapu kembali ke dalam dek.');
                } else {
                    self::log($state, 'sys', 'Lawan tidak punya kartu terbuka untuk disapu.');
                }
                break;
            case 'lifeSwap':
                // Direvisi: dulu tukar TOTAL nyala (terlalu swingy/OP), sekarang
                // cuma menggeser 1 nyala dari pihak yang unggul ke pihak yang
                // tertinggal -- searah "menyamakan nasib", bukan membalik total.
                if ($state['lives']['host'] === $state['lives']['guest']) {
                    self::log($state, 'sys', 'Nyala kedua pihak sudah sama -- Tukar Nasib tidak berefek.');
                    break;
                }
                $leader = $state['lives']['host'] > $state['lives']['guest'] ? 'host' : 'guest';
                $trailer = self::opponent($leader);
                $state['lives'][$leader] = max(0, $state['lives'][$leader] - 1);
                if (!empty($state[$trailer]['cursed'])) {
                    self::log($state, 'sys', 'Nasib bergeser: ' . ($leader === 'host' ? $state['host_name'] : $state['guest_name']) . ' kehilangan 1 nyala, tapi lilin ' . ($trailer === 'host' ? $state['host_name'] : $state['guest_name']) . ' sudah terkutuk sehingga tidak menerima nyala tambahan.');
                } else {
                    $state['lives'][$trailer] = min($state['lives_cap'] ?? self::STARTING_LIFE, $state['lives'][$trailer] + 1);
                    self::log($state, 'sys', 'Nasib bergeser: ' . ($leader === 'host' ? $state['host_name'] : $state['guest_name']) . ' kehilangan 1 nyala, ' . ($trailer === 'host' ? $state['host_name'] : $state['guest_name']) . ' mendapat 1 nyala.');
                }
                break;
            case 'doubleHeal':
                if (!empty($p['cursed'])) {
                    self::log($state, 'sys', 'Lilin ' . ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' sudah terkutuk -- Berkah Ganda tidak berefek.');
                    break;
                }
                // Direvisi: dulu selalu +2 (terlalu kuat), sekarang +2 cuma saat
                // kondisi kritis (nyala <=3), kalau sudah cukup sehat cuma +1 --
                // jadi kartu comeback, bukan heal murah kapan saja.
                $before = $state['lives'][$actor];
                $amount = $before <= 3 ? 2 : 1;
                $state['lives'][$actor] = min($state['lives_cap'] ?? self::STARTING_LIFE, $before + $amount);
                $gained = $state['lives'][$actor] - $before;
                $note = $amount === 2 ? ' (kondisi kritis, efek penuh)' : ' (nyala masih cukup tinggi, efek berkurang jadi 1)';
                self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' memulihkan ' . $gained . ' nyala' . $note . '.');
                break;
            case 'copyLast':
                $lastOpp = !empty($o['played_this_round']) ? $o['played_this_round'][count($o['played_this_round']) - 1] : null;
                if ($lastOpp === null || empty($lastOpp['id']) || $lastOpp['id'] === 'copyLast') {
                    self::log($state, 'sys', 'Tidak ada gerakan lawan ronde ini yang bisa ditiru.');
                    break;
                }
                $copiedDef = null;
                foreach (self::CARD_POOL as $c) {
                    if ($c['id'] === $lastOpp['id']) { $copiedDef = $c; break; }
                }
                if ($copiedDef === null) {
                    self::log($state, 'sys', 'Gerakan lawan tidak dikenali, tidak bisa ditiru.');
                    break;
                }
                self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' meniru kartu ' . $copiedDef['name'] . ' milik lawan.');
                // Rekursi 1 lapis saja (dijamin aman oleh guard id==='copyLast' di atas).
                self::applySpecialEffect($state, $actor, $copiedDef);
                break;
            case 'lockTarget':
                $state['target_locked'] = true;
                self::log($state, 'sys', 'Target ronde ini disegel -- tidak bisa diubah lagi oleh siapa pun sampai ronde berakhir.');
                break;
            case 'mirrorGuard':
                $p['mirror_active'] = true;
                // Sengaja TIDAK log di sini -- Cermin Takdir harus tetap tersembunyi
                // & pasif sampai efeknya sendiri terpicu (lihat log "memantulkan
                // efek..." di doSpecial saat trigger benar-benar terjadi).
                break;
            case 'revengeWhisper':
                $p['revenge_active'] = true;
                // Sengaja TIDAK log di sini juga -- Bisikan Balas Dendam harus
                // tetap tersembunyi & pasif sampai pemain ini benar-benar kalah
                // ronde (lihat maybeTriggerRevenge() yang dipanggil dari
                // resolveRound() untuk log & efek kutukannya).
                break;
            case 'freezeTurn':
                // Direvisi: dulu memaksa auto-bertahan (menghapus semua pilihan,
                // termasuk hit/stand) -- terlalu OP. Sekarang HANYA memblokir
                // pemakaian kartu spesial di giliran berikutnya; Ambil Kartu &
                // Bertahan tetap berjalan normal (lihat pengecekan di action()).
                $o['frozen'] = true;
                self::log($state, 'sys', ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' akan beku pada gilirannya berikutnya -- tidak bisa pakai kartu spesial (Ambil Kartu & Bertahan tetap normal).');
                break;
            case 'mulligan':
                $myReturned = $p['visible'];
                if ($p['hidden'] !== null) array_unshift($myReturned, $p['hidden']);
                $oppReturned = $o['visible'];
                if ($o['hidden'] !== null) array_unshift($oppReturned, $o['hidden']);
                $myCount = count($myReturned);
                $oppCount = count($oppReturned);
                $totalCount = $myCount + $oppCount;
                if ($totalCount > 0) {
                    $p['visible'] = []; $p['hidden'] = null;
                    $o['visible'] = []; $o['hidden'] = null;
                    $state['deck'] = array_merge($state['deck'], $myReturned, $oppReturned);
                    shuffle($state['deck']);
                    $p['hidden'] = array_pop($state['deck']);
                    $o['hidden'] = array_pop($state['deck']);
                    for ($i = 1; $i < $myCount; $i++) { $p['visible'][] = array_pop($state['deck']); }
                    for ($i = 1; $i < $oppCount; $i++) { $o['visible'][] = array_pop($state['deck']); }
                    self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' mengocok ulang seluruh kartu di meja -- milik kedua pihak.');
                } else {
                    self::log($state, 'sys', 'Kocok ulang gagal &mdash; tidak ada kartu di meja.');
                }
                break;
            case 'doubleWager':
                // Bertingkat menurut seberapa jauh ronde sudah berjalan (lihat
                // afterAction() untuk penghitungan turns_taken): 0 = belum ada
                // Ambil Kartu/Bertahan sama sekali ("awal ronde"), 1 = persis 1
                // aksi sudah selesai (sekarang giliran kedua), 2+ = sudah lewat
                // giliran kedua.
                $turnsTaken = $state['turns_taken'] ?? 0;
                if ($turnsTaken === 0) {
                    $wagerAmt = 3;
                } elseif ($turnsTaken === 1) {
                    $wagerAmt = 2;
                } else {
                    $wagerAmt = 1;
                }
                $p['bet_mod'] = ($p['bet_mod'] ?? 0) + $wagerAmt;
                $o['bet_mod'] = ($o['bet_mod'] ?? 0) + $wagerAmt;
                self::log($state, 'sys', 'Taruhan Mendesak -- taruhan ronde ini naik ' . $wagerAmt . ' untuk KEDUA pihak sekaligus.');
                break;
            case 'compoundWager':
                $cwAmt = min(count($o['played_this_round']), 3);
                $p['bet_mod'] = ($p['bet_mod'] ?? 0) + $cwAmt;
                $o['bet_mod'] = ($o['bet_mod'] ?? 0) + $cwAmt;
                self::log($state, 'sys', 'Taruhan Berlipat -- taruhan ronde ini naik ' . $cwAmt . ' untuk KEDUA pihak, mengikuti ' . $cwAmt . ' kartu spesial yang sudah dimainkan lawan ronde ini.');
                break;
            case 'recklessWager':
                $p['bet_mod'] = ($p['bet_mod'] ?? 0) + 2;
                $o['bet_mod'] = ($o['bet_mod'] ?? 0) + 2;
                // Ditambahkan saat penggabungan V.0.8.5.0 -- 'recklessWager' (V.0.8.4.0) dan
                // 'cursed' dari revengeWhisper (V.0.8.3.2) dikembangkan terpisah tanpa saling
                // tahu. Bagian taruhan tetap naik seperti biasa (bukan efek nyala), tapi porsi
                // pemulihan-nyala WAJIB ikut diblokir kalau actor terkutuk -- kalau tidak, klaim
                // "tak bisa disembuhkan dengan cara apapun" di kartu revengeWhisper jadi tidak
                // benar lagi. Pola if/else persis sama seperti guard trailer di case 'lifeSwap'.
                if (!empty($p['cursed'])) {
                    self::log($state, 'sys', 'Taruhan Nekat -- taruhan ronde ini naik 2 untuk KEDUA pihak; lilin ' . ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' sudah terkutuk sehingga tidak mendapat pemulihan nyala kompensasi.');
                } else {
                    $state['lives'][$actor] = min($state['lives_cap'] ?? self::STARTING_LIFE, $state['lives'][$actor] + 1);
                    self::log($state, 'sys', 'Taruhan Nekat -- taruhan ronde ini naik 2 untuk KEDUA pihak; ' . ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' memulihkan 1 nyala sebagai kompensasi risiko.');
                }
                break;
            case 'lopsidedWager':
                $p['bet_mod'] = ($p['bet_mod'] ?? 0) + 1;
                $o['bet_mod'] = ($o['bet_mod'] ?? 0) + 2;
                self::log($state, 'sys', 'Taruhan Berat Sebelah -- taruhan ronde ini sengaja ditimpangkan: ' . ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' rugi +1 kalau kalah, ' . ($opp === 'host' ? $state['host_name'] : $state['guest_name']) . ' rugi +2 kalau kalah.');
                break;
        }
    }

    private static function afterAction(&$state, $actor, $actionType) {
        $state['turns_taken'] = ($state['turns_taken'] ?? 0) + 1;
        $state['history'][] = ['actor'=>$actor, 'action'=>$actionType];
        $n = count($state['history']);
        if ($n >= 2 && $state['history'][$n-1]['action'] === 'stand' && $state['history'][$n-2]['action'] === 'stand') {
            $state['round_over'] = true;
            $state['awaiting_continue'] = true;
            $state['continue_confirmations'] = ['host' => false, 'guest' => false];
            $state['continue_deadline'] = time() + 10;
            self::resolveRound($state);
            return ['ok'=>true, 'resolved'=>true];
        }
        if (!empty($state[$actor]['silenced_turn_based'])) {
            $state[$actor]['silenced'] = 0;
            $state[$actor]['silenced_turn_based'] = false;
        }
        $state['turn'] = ($actor === 'host') ? 'guest' : 'host';
        $state['turn_deadline'] = time() + ($state['turn_seconds'] ?? 60);
        return ['ok'=>true];
    }

    // Dipanggil dari resolveRound() setiap kali ronde berakhir dan salah satu
    // pihak KALAH. Kalau pihak yang kalah itu sempat memainkan revengeWhisper
    // (Bisikan Balas Dendam) ronde ini dan belum sempat terpicu, efeknya
    // meledak sekarang: nyalanya dikunci ke 1 dan ditandai 'cursed' PERMANEN
    // sisa pertandingan (lihat guard 'cursed' di 'heal'/'doubleHeal'/'lifeSwap'/
    // 'recklessWager'). Sengaja BARU di-log di sini, bukan saat kartu
    // dimainkan -- itulah yang membuat kartu ini betul-betul "tersembunyi".
    private static function maybeTriggerRevenge(&$state, $loser) {
        if (empty($state[$loser]['revenge_active'])) return;
        $state[$loser]['revenge_active'] = false;
        $state['lives'][$loser] = 1;
        $state[$loser]['cursed'] = true;
        $name = $loser === 'host' ? $state['host_name'] : $state['guest_name'];
        self::log($state, 'sys', 'Bisikan Balas Dendam ' . $name . ' menyala -- nyalanya dikunci di 1 dan lilinnya kini hitam busuk, terkutuk tak akan pernah pulih lagi sisa pertandingan.');
    }

    private static function resolveRound(&$state) {
        $hTotal = self::trueTotal($state, 'host');
        $gTotal = self::trueTotal($state, 'guest');
        $target = $state['target'];
        $hBust = $hTotal > $target;
        $gBust = $gTotal > $target;
        $doubleBust = $hBust && $gBust;

        if ($hBust && $gBust) $winner = ($hTotal === $gTotal) ? 'draw' : (($hTotal < $gTotal) ? 'host' : 'guest');
        else if ($hBust) $winner = 'guest';
        else if ($gBust) $winner = 'host';
        else $winner = ($hTotal === $gTotal) ? 'draw' : ((abs($target - $hTotal) < abs($target - $gTotal)) ? 'host' : 'guest');

        $hLoss = 0;
        $gLoss = 0;
        $hLifeBefore = $state['lives']['host'];
        $gLifeBefore = $state['lives']['guest'];

        if ($winner === 'host') {
            $gLoss = max(1, 1 + ($state['guest']['bet_mod'] ?? 0));
            if (!empty($state['guest']['no_loss'])) $gLoss = max(0, $gLoss - 1);
            $state['lives']['guest'] = max(0, $state['lives']['guest'] - $gLoss);
            self::log($state, 'host', $state['host_name'] . ' menang ronde ini.');
            self::maybeTriggerRevenge($state, 'guest');
        } elseif ($winner === 'guest') {
            $hLoss = max(1, 1 + ($state['host']['bet_mod'] ?? 0));
            if (!empty($state['host']['no_loss'])) $hLoss = max(0, $hLoss - 1);
            $state['lives']['host'] = max(0, $state['lives']['host'] - $hLoss);
            self::log($state, 'guest', $state['guest_name'] . ' menang ronde ini.');
            self::maybeTriggerRevenge($state, 'host');
        } else {
            self::log($state, 'sys', 'Ronde berakhir seri.');
        }

        // Simpan detail reveal agar client bisa menampilkan kartu tersembunyi dan perhitungan akurat
        $state['reveal'] = [
            'host_hidden' => $state['host']['hidden'],
            'guest_hidden' => $state['guest']['hidden'],
            'host_total' => $hTotal,
            'guest_total' => $gTotal,
            'target' => $target,
            'host_bust' => $hBust,
            'guest_bust' => $gBust,
            'double_bust' => $doubleBust,
            'winner' => $winner,
            'host_life_before' => $hLifeBefore,
            'guest_life_before' => $gLifeBefore,
            'host_life_after' => $state['lives']['host'],
            'guest_life_after' => $state['lives']['guest'],
            // Dihitung dari selisih before/after (bukan $hLoss/$gLoss mentah),
            // supaya tetap akurat kalau revengeWhisper baru saja override nyala
            // akhir jadi 1 (lihat maybeTriggerRevenge di atas).
            'host_life_change' => $hLifeBefore - $state['lives']['host'],
            'guest_life_change' => $gLifeBefore - $state['lives']['guest'],
        ];

        $state['last_winner'] = $winner;
        if ($state['lives']['host'] <= 0 || $state['lives']['guest'] <= 0) {
            $state['finished'] = true;
            $state['winner'] = ($state['lives']['host'] > 0) ? 'host' : 'guest';
        }
    }

    private static function allConfirmed($state) {
        return !empty($state['continue_confirmations']['host']) && !empty($state['continue_confirmations']['guest']);
    }

    private static function doContinue(&$state, $actor = null) {
        if (!$state['awaiting_continue']) return ['ok'=>true];
        if ($actor !== null) {
            $state['continue_confirmations'][$actor] = true;
        }
        if (!self::allConfirmed($state) && ($state['continue_deadline'] === null || time() <= $state['continue_deadline'])) {
            return ['ok'=>true, 'waiting'=>true, 'confirmations'=>$state['continue_confirmations'], 'deadline'=>$state['continue_deadline']];
        }
        if ($state['finished']) {
            self::log($state, 'sys', 'Permainan selesai. Pemenang: ' . ($state['winner'] === 'host' ? $state['host_name'] : $state['guest_name']));
            $state['awaiting_continue'] = false;
            return ['ok'=>true, 'finished'=>true];
        }
        self::startRound($state);
        return ['ok'=>true];
    }

    private static function trueTotal($state, $side) {
        $p = $state[$side];
        return $p['hidden'] + array_sum($p['visible']);
    }

    private static function bestValueFor($current, $deck, $target) {
        if (empty($deck)) return null;
        $candidates = array_filter($deck, function($v) use ($current, $target) { return $current + $v <= $target; });
        if (!empty($candidates)) return max($candidates);
        return min($deck);
    }

    // Sisipkan kartu ke posisi acak di dalam dek (bukan selalu ke ujung array,
    // supaya tidak otomatis jadi kartu berikutnya yang ditarik oleh array_pop).
    private static function insertCardRandom(&$deck, $v) {
        $idx = mt_rand(0, count($deck));
        array_splice($deck, $idx, 0, [$v]);
    }

    private static function opponent($actor) { return $actor === 'host' ? 'guest' : 'host'; }

    private static function doSurrender(&$state, $actor) {
        $opp = self::opponent($actor);
        self::log($state, 'sys', ($actor === 'host' ? $state['host_name'] : $state['guest_name']) . ' menyerah.');
        $state['finished'] = true;
        $state['winner'] = $opp;
        $state['lives'][$actor] = 0;
        return ['ok'=>true, 'surrender'=>true];
    }

    public static function checkTimeout(&$state) {
        if (empty($state) || !empty($state['finished']) || !empty($state['awaiting_continue']) || empty($state['turn'])) return false;
        if (empty($state['turn_deadline'])) {
            $state['turn_deadline'] = time() + ($state['turn_seconds'] ?? 60);
            return true;
        }
        if (time() <= $state['turn_deadline']) return false;
        $turn = $state['turn'];
        self::log($state, 'sys', 'Waktu habis untuk ' . ($turn === 'host' ? $state['host_name'] : $state['guest_name']) . '; otomatis bertahan.');
        self::action($state, $turn, 'stand');
        return true;
    }

    public static function checkContinueTimeout(&$state) {
        if (empty($state) || !empty($state['finished']) || empty($state['awaiting_continue']) || empty($state['continue_deadline'])) return false;
        if (time() <= $state['continue_deadline'] && !self::allConfirmed($state)) return false;
        self::doContinue($state, null);
        return true;
    }

    private static function log(&$state, $actor, $msg, $kind = null, $target = null) {
        // PERBAIKAN: setiap entri kini menyimpan nomor ronde saat kejadian itu terjadi
        // ('round'), bukan cuma actor/pesan. Sebelumnya klien (JSS_MULTIPLAYER.js) melabeli
        // SEMUA entri yang di-replay dengan ronde SAAT INI (S.round global) -- begitu ronde
        // maju dan panel di-render ulang, entri dari ronde-ronde lama ikut salah tertulis
        // sebagai ronde terbaru. Dengan field ini, klien bisa memakai ronde asli tiap entri.
        // 'kind' opsional menandai entri sebagai penanda visual (mis. 'round_start' untuk
        // pembatas ronde baru di panel Riwayat Kejadian) -- null berarti entri biasa.
        // 'target' opsional (V.0.8.7.1) menandai SISI ('host'/'guest') yang perlu bereaksi
        // khusus ke entri ini di client -- dipakai 'mirrorReflect' supaya cuma korban
        // pantulan (bukan pemilik Cermin Takdir) yang melihat popup pengungkapan kartu.
        // null berarti tidak ada sisi spesifik yang dituju.
        $entry = ['t' => time(), 'actor' => $actor, 'msg' => $msg, 'round' => $state['round'] ?? 0];
        if ($kind !== null) { $entry['kind'] = $kind; }
        if ($target !== null) { $entry['target'] = $target; }
        $state['logs'][] = $entry;
    }

    public static function visibleTotal($state, $side) {
        return array_sum($state[$side]['visible']);
    }

    public static function stripSecrets($state, $forSide) {
        $out = $state;
        $opp = ($forSide === 'host') ? 'guest' : 'host';

        // Saat ronde belum selesai, sembunyikan kartu tertutup lawan dan detail special-nya --
        // KECUALI kalau sisi yang minta ($forSide) sedang punya 'peek_active' aktif dari
        // kartu online 'peekHidden' (Intip Rahasia), maka nilai aslinya tetap dikirim.
        if (empty($out['round_over']) && empty($out['finished'])) {
            if (empty($state[$forSide]['peek_active'])) {
                $out[$opp]['hidden'] = null;
            }
            if (isset($out['reveal'])) unset($out['reveal']);
        } else {
            // Jika ronde selesai atau permainan usai, bongkar kartu tertutup dan reveal object.
            $out['host']['hidden'] = $state['host']['hidden'];
            $out['guest']['hidden'] = $state['guest']['hidden'];
        }

        // Keep count of opponent specials, but hide UIDs/ids so they cannot be guessed
        $count = count($out[$opp]['specials']);
        $out[$opp]['specials'] = $count;

        // Kartu rahasia lawan (mis. mirrorGuard/Cermin Takdir, revengeWhisper/
        // Bisikan Balas Dendam) harus benar-benar tersembunyi SELAMA belum
        // terpicu -- jangan dikirim di 'played_this_round' milik lawan, supaya
        // tidak muncul sebagai trump-chip biasa di meja kita. table_slots_used
        // (angka slot terpakai) TIDAK ikut disentuh, jadi lawan tetap tahu ada
        // slot yang terpakai tanpa tahu kartu apa itu.
        // PENGECUALIAN (V.0.8.7.1): begitu mirrorGuard SUDAH terpicu --
        // mirror_active balik jadi false padahal entrinya masih ada di
        // played_this_round, satu-satunya cara itu terjadi dalam ronde yang
        // sama -- kartunya boleh terlihat lawan. Jebakannya sudah tidak
        // relevan lagi utk disembunyikan, dan di sinilah lawan akhirnya tahu
        // kartu apa yang barusan memantulkan serangannya (chip yang sama
        // otomatis tampil "tidak aktif" lewat renderTableTrumps() di
        // JSSSYSTEM.js karena mirror_active sudah false). revengeWhisper TIDAK
        // ikut pengecualian ini -- trigger-nya (kalah ronde) tidak butuh
        // pengungkapan ke lawan seperti mirrorGuard, jadi tetap disembunyikan
        // selamanya seperti sebelumnya.
        if (!empty($out[$opp]['played_this_round'])) {
            $mirrorTriggered = empty($state[$opp]['mirror_active']);
            $out[$opp]['played_this_round'] = array_values(array_filter(
                $out[$opp]['played_this_round'],
                function ($entry) use ($mirrorTriggered) {
                    if (!in_array($entry['id'], self::SECRET, true)) return true;
                    return $entry['id'] === 'mirrorGuard' && $mirrorTriggered;
                }
            ));
        }
        return $out;
    }
}
