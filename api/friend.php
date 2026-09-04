<?php
// File: api/friend.php
// Proyek: 21: Ruang Lilin
// Fungsi: Endpoint sistem pertemanan: request, accept, remove, list.
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'add') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $friendId = (int)($_POST['friend_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');

    if ($friendId <= 0 && $username !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if ($row) $friendId = (int)$row['id'];
    }

    if ($friendId <= 0) jsonResponse(['ok' => false, 'error' => 'Pilih lawan yang valid.']);
    if ($friendId === $uid) jsonResponse(['ok' => false, 'error' => 'Tidak bisa berteman dengan diri sendiri.']);

    $u1 = min($uid, $friendId);
    $u2 = max($uid, $friendId);

    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO friends (user_id_1, user_id_2, requester_id, status) VALUES (?, ?, ?, "pending")');
        $stmt->execute([$u1, $u2, $uid]);
        if ($stmt->rowCount() > 0) {
            jsonResponse(['ok' => true, 'status' => 'pending', 'message' => 'Permintaan pertemanan terkirim.']);
        } else {
            jsonResponse(['ok' => true, 'status' => 'exists', 'message' => 'Anda sudah terhubung atau sedang menunggu persetujuan.']);
        }
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal mengirim pertemanan.']);
    }
}

if ($action === 'accept') {
    requireAuth();
    $friendId = (int)($_POST['friend_id'] ?? 0);
    $uid = (int)$_SESSION['user_id'];
    if ($friendId <= 0) jsonResponse(['ok' => false, 'error' => 'Pilih teman yang valid.']);

    $u1 = min($uid, $friendId);
    $u2 = max($uid, $friendId);

    try {
        $stmt = $pdo->prepare('UPDATE friends SET status = "accepted" WHERE user_id_1 = ? AND user_id_2 = ? AND status = "pending" AND requester_id != ?');
        $stmt->execute([$u1, $u2, $uid]);
        jsonResponse(['ok' => $stmt->rowCount() > 0, 'status' => 'accepted']);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal menerima pertemanan.']);
    }
}

if ($action === 'remove') {
    requireAuth();
    $friendId = (int)($_POST['friend_id'] ?? 0);
    $uid = (int)$_SESSION['user_id'];
    if ($friendId <= 0) jsonResponse(['ok' => false, 'error' => 'Pilih teman yang valid.']);

    $u1 = min($uid, $friendId);
    $u2 = max($uid, $friendId);

    try {
        $stmt = $pdo->prepare('DELETE FROM friends WHERE user_id_1 = ? AND user_id_2 = ?');
        $stmt->execute([$u1, $u2]);
        jsonResponse(['ok' => $stmt->rowCount() > 0]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal menghapus pertemanan.']);
    }
}

if ($action === 'status') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $friendId = (int)($_POST['friend_id'] ?? $_GET['friend_id'] ?? 0);
    if ($friendId <= 0) jsonResponse(['ok' => false, 'error' => 'Pilih user yang valid.']);
    if ($friendId === $uid) jsonResponse(['ok' => true, 'status' => 'self']);

    $u1 = min($uid, $friendId);
    $u2 = max($uid, $friendId);

    try {
        $stmt = $pdo->prepare('SELECT status FROM friends WHERE user_id_1 = ? AND user_id_2 = ?');
        $stmt->execute([$u1, $u2]);
        $row = $stmt->fetch();
        jsonResponse(['ok' => true, 'status' => $row ? $row['status'] : 'none']);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal memeriksa status pertemanan.']);
    }
}

if ($action === 'list') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare('SELECT f.*, u1.username AS user1, u2.username AS user2 FROM friends f JOIN users u1 ON u1.id=f.user_id_1 JOIN users u2 ON u2.id=f.user_id_2 WHERE f.user_id_1 = ? OR f.user_id_2 = ? ORDER BY f.status ASC, f.updated_at DESC');
        $stmt->execute([$uid, $uid]);
        jsonResponse(['ok' => true, 'friends' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal memuat daftar teman.']);
    }
}

if ($action === 'profile') {
    requireAuth();
    $profileId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
    if ($profileId <= 0) jsonResponse(['ok' => false, 'error' => 'Pilih user yang valid.']);
    try {
        $stmt = $pdo->prepare('SELECT id, username, wins, losses, `rank`, rating, rank_points, hard_clear, campaign_completed, campaign_best_mode FROM users WHERE id = ?');
        $stmt->execute([$profileId]);
        $user = $stmt->fetch();
        if (!$user) jsonResponse(['ok' => false, 'error' => 'User tidak ditemukan.']);

        $stmt2 = $pdo->prepare('SELECT f.*, u1.username AS user1, u2.username AS user2 FROM friends f JOIN users u1 ON u1.id=f.user_id_1 JOIN users u2 ON u2.id=f.user_id_2 WHERE f.status="accepted" AND (f.user_id_1 = ? OR f.user_id_2 = ?)');
        $stmt2->execute([$profileId, $profileId]);
        $friendsRows = $stmt2->fetchAll();

        $friendsList = [];
        foreach ($friendsRows as $fr) {
            $fid = ($fr['user_id_1'] == $profileId) ? $fr['user_id_2'] : $fr['user_id_1'];
            $fname = ($fid == $fr['user_id_1']) ? $fr['user1'] : $fr['user2'];
            $friendsList[] = ['id' => (int)$fid, 'username' => $fname];
        }

        jsonResponse(['ok' => true, 'profile' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'wins' => (int)$user['wins'],
            'losses' => (int)$user['losses'],
            'rank' => rankTitleForRating($user['rating']),
            'rating' => (int)$user['rating'],
            'rank_points' => (int)$user['rank_points'],
            'hard_clear' => (bool)$user['hard_clear'],
            'campaign_completed' => (bool)$user['campaign_completed'],
            'campaign_best_mode' => $user['campaign_best_mode'],
            'friends_count' => count($friendsList),
            'friends' => $friendsList
        ]]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal memuat profil.']);
    }
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
