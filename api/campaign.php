<?php
// File: api/campaign.php
// Proyek: 21: Ruang Lilin
// Fungsi: Endpoint untuk menyimpan hasil kampanye, unlock galeri.
// Catatan: Kampanye TIDAK mempengaruhi rating. Hanya untuk unlock galeri dan riwayat.
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

if ($action === 'save_result') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $result = $_POST['result'] ?? '';
    $difficulty = $_POST['difficulty'] ?? '';
    $stage_reached = (int)($_POST['stage_reached'] ?? 1);
    $total_rounds = (int)($_POST['total_rounds'] ?? 0);
    
    if (!in_array($result, ['win', 'lose'])) jsonResponse(['ok' => false, 'error' => 'Result tidak valid.']);
    if (!in_array($difficulty, ['mudah', 'normal', 'sulit'])) jsonResponse(['ok' => false, 'error' => 'Difficulty tidak valid.']);
    if ($stage_reached < 1 || $stage_reached > 5) jsonResponse(['ok' => false, 'error' => 'Stage tidak valid.']);
    
    try {
        // Simpan riwayat
        $stmt = $pdo->prepare('INSERT INTO campaign_history (user_id, result, difficulty, stage_reached, total_rounds) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$uid, $result, $difficulty, $stage_reached, $total_rounds]);
        
        // Jika menang, update campaign_completed & best_mode
        if ($result === 'win') {
            // Tandai completed
            $pdo->prepare('UPDATE users SET campaign_completed = 1 WHERE id = ?')->execute([$uid]);
            
            // Update best mode (sulit > normal > mudah)
            $stmt = $pdo->prepare('SELECT campaign_best_mode FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
            $currentBest = $user['campaign_best_mode'] ?? null;
            $modeRank = ['mudah' => 1, 'normal' => 2, 'sulit' => 3];
            $currentRank = $modeRank[$currentBest] ?? 0;
            $newRank = $modeRank[$difficulty] ?? 0;
            if ($newRank > $currentRank) {
                $pdo->prepare('UPDATE users SET campaign_best_mode = ? WHERE id = ?')->execute([$difficulty, $uid]);
            }
            
            // Jika menang di mode sulit, unlock hard_clear
            if ($difficulty === 'sulit') {
                $pdo->prepare('UPDATE users SET hard_clear = 1 WHERE id = ?')->execute([$uid]);
                // Auto unlock galeri
                $stmt = $pdo->prepare('INSERT IGNORE INTO gallery_unlocks (user_id, unlock_key) VALUES (?, ?)');
                $stmt->execute([$uid, 'hard_clear']);
                $stmt->execute([$uid, 'gallery_numbers']);
                $stmt->execute([$uid, 'gallery_trumps']);
                $stmt->execute([$uid, 'gallery_history']);
                $stmt->execute([$uid, 'gallery_future']);
            } elseif ($difficulty === 'normal') {
                // Normal mode unlock beberapa galeri
                $stmt = $pdo->prepare('INSERT IGNORE INTO gallery_unlocks (user_id, unlock_key) VALUES (?, ?)');
                $stmt->execute([$uid, 'gallery_numbers']);
                $stmt->execute([$uid, 'gallery_history']);
            }
        }
        
        jsonResponse(['ok' => true]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal menyimpan hasil: ' . $e->getMessage()]);
    }
}

if ($action === 'gallery_status') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare('SELECT hard_clear, campaign_completed, campaign_best_mode FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        
        $stmt = $pdo->prepare('SELECT unlock_key FROM gallery_unlocks WHERE user_id = ?');
        $stmt->execute([$uid]);
        $unlocks = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        jsonResponse([
            'ok' => true,
            'hard_clear' => (bool)($user['hard_clear'] ?? false),
            'campaign_completed' => (bool)($user['campaign_completed'] ?? false),
            'campaign_best_mode' => $user['campaign_best_mode'] ?? null,
            'unlocks' => $unlocks
        ]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal mengambil status: ' . $e->getMessage()]);
    }
}

if ($action === 'unlock_gallery') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $unlock_key = trim($_POST['unlock_key'] ?? '');
    if (!$unlock_key) jsonResponse(['ok' => false, 'error' => 'Key diperlukan.']);
    
    try {
        $stmt = $pdo->prepare('SELECT hard_clear FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user['hard_clear']) {
            jsonResponse(['ok' => false, 'error' => 'Galeri belum terbuka. Selesaikan mode sulit untuk membuka galeri.']);
        }
        
        $stmt = $pdo->prepare('INSERT IGNORE INTO gallery_unlocks (user_id, unlock_key) VALUES (?, ?)');
        $stmt->execute([$uid, $unlock_key]);
        jsonResponse(['ok' => true]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal unlock: ' . $e->getMessage()]);
    }
}

if ($action === 'history') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $limit = min(50, (int)($_GET['limit'] ?? 20));
    try {
        $stmt = $pdo->prepare('SELECT result, difficulty, stage_reached, total_rounds, played_at FROM campaign_history WHERE user_id = ? ORDER BY played_at DESC LIMIT ?');
        $stmt->bindValue(1, $uid, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $history = $stmt->fetchAll();
        jsonResponse(['ok' => true, 'history' => $history]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal mengambil history: ' . $e->getMessage()]);
    }
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
