<?php
// File: api/migrate.php
// Proyek: 21: Ruang Lilin
// Fungsi: Migrasi data localStorage ke akun server (hard_clear, campaign, dll)
// Catatan: TIDAK mempengaruhi rating. Rating hanya dipertahankan oleh permainan multiplayer.
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'import';

if ($action === 'import') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    
    $hard_clear = !empty($_POST['hard_clear']) ? 1 : 0;
    $campaign_completed = !empty($_POST['campaign_completed']) ? 1 : 0;
    $campaign_best_mode = $_POST['campaign_best_mode'] ?? null;
    
    // Validasi mode
    if ($campaign_best_mode && !in_array($campaign_best_mode, ['mudah', 'normal', 'sulit'])) {
        $campaign_best_mode = null;
    }
    
    try {
        // Cek data user saat ini
        $stmt = $pdo->prepare('SELECT hard_clear, campaign_completed, campaign_best_mode FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        
        $needsUpdate = false;
        $updates = [];
        
        // Hanya update jika data server masih kosong (tidak menimpa progress server)
        if ($hard_clear && empty($user['hard_clear'])) {
            $updates[] = 'hard_clear = 1';
            $needsUpdate = true;
            
            // Auto unlock galeri
            $stmt2 = $pdo->prepare('INSERT IGNORE INTO gallery_unlocks (user_id, unlock_key) VALUES (?, ?)');
            $stmt2->execute([$uid, 'hard_clear']);
            $stmt2->execute([$uid, 'gallery_numbers']);
            $stmt2->execute([$uid, 'gallery_trumps']);
            $stmt2->execute([$uid, 'gallery_history']);
            $stmt2->execute([$uid, 'gallery_future']);
        }
        
        if ($campaign_completed && empty($user['campaign_completed'])) {
            $updates[] = 'campaign_completed = 1';
            $needsUpdate = true;
        }
        
        if ($campaign_best_mode && empty($user['campaign_best_mode'])) {
            $modeRank = ['mudah' => 1, 'normal' => 2, 'sulit' => 3];
            $currentRank = $modeRank[$user['campaign_best_mode']] ?? 0;
            $newRank = $modeRank[$campaign_best_mode] ?? 0;
            if ($newRank > $currentRank) {
                $updates[] = 'campaign_best_mode = ' . $pdo->quote($campaign_best_mode);
                $needsUpdate = true;
            }
        }
        
        if ($needsUpdate) {
            $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $pdo->prepare($sql)->execute([$uid]);
        }
        
        jsonResponse([
            'ok' => true,
            'imported' => $needsUpdate,
            'message' => $needsUpdate ? 'Data berhasil diimpor ke akun.' : 'Tidak ada data baru untuk diimpor.'
        ]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal migrasi: ' . $e->getMessage()]);
    }
}

if ($action === 'clear_local') {
    jsonResponse(['ok' => true, 'message' => 'Local storage siap dibersihkan.']);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
