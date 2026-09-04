<?php
/*
  File: api/leaderboard.php
  Proyek: 21: Ruang Lilin
  Fungsi: API untuk sistem ranking/leaderboard
*/
require_once 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get') {
  $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
  $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
  
  $stmt = $pdo->prepare("
    SELECT id, username, wins, losses, rating, rank_points,
           (wins + losses) as total_matches,
           CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END as win_rate
    FROM users
    WHERE banned = 0
    ORDER BY rating DESC, win_rate DESC, rank_points DESC, wins DESC
    LIMIT :limit OFFSET :offset
  ");
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($players as &$p) { $p['rank'] = rankTitleForRating($p['rating']); }
  unset($p);
  
  // Get current user's rank if logged in and verified
  $my_rank = null;
  if (isset($_SESSION['user_id'])) {
    // Calculate user's win rate for comparison
    $userWins = $_SESSION['wins'] ?? 0;
    $userLosses = $_SESSION['losses'] ?? 0;
    $userTotal = $userWins + $userLosses;
    $userWinRate = $userTotal > 0 ? round($userWins * 100.0 / $userTotal, 1) : 0;
    
    $stmt = $pdo->prepare("
      SELECT COUNT(*) + 1 as position
      FROM users
      WHERE banned = 0 AND (
        rating > :rating OR 
        (rating = :rating2 AND (CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END) > :win_rate) OR 
        (rating = :rating3 AND (CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END) = :win_rate2 AND rank_points > :rank_points) OR 
        (rating = :rating4 AND (CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END) = :win_rate3 AND rank_points = :rank_points2 AND wins > :wins)
      )
    ");
    $stmt->bindValue(':rating', $_SESSION['rating'] ?? 1000, PDO::PARAM_INT);
    $stmt->bindValue(':rating2', $_SESSION['rating'] ?? 1000, PDO::PARAM_INT);
    $stmt->bindValue(':rating3', $_SESSION['rating'] ?? 1000, PDO::PARAM_INT);
    $stmt->bindValue(':rating4', $_SESSION['rating'] ?? 1000, PDO::PARAM_INT);
    $stmt->bindValue(':win_rate', $userWinRate, PDO::PARAM_STR);
    $stmt->bindValue(':win_rate2', $userWinRate, PDO::PARAM_STR);
    $stmt->bindValue(':win_rate3', $userWinRate, PDO::PARAM_STR);
    $stmt->bindValue(':rank_points', $_SESSION['rank_points'] ?? 0, PDO::PARAM_INT);
    $stmt->bindValue(':rank_points2', $_SESSION['rank_points'] ?? 0, PDO::PARAM_INT);
    $stmt->bindValue(':wins', $userWins, PDO::PARAM_INT);
    $stmt->execute();
    $my_rank = $stmt->fetch(PDO::FETCH_ASSOC)['position'];
  }
  
  echo json_encode(['ok' => true, 'players' => $players, 'my_rank' => $my_rank]);
  exit;
}

if ($action === 'my_rank') {
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
  }
  
  $stmt = $pdo->prepare("
    SELECT id, username, wins, losses, rating, rank_points,
           (wins + losses) as total_matches,
           CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END as win_rate
    FROM users
    WHERE id = :id
  ");
  $stmt->bindValue(':id', $_SESSION['user_id'], PDO::PARAM_INT);
  $stmt->execute();
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
  }
  $user['rank'] = rankTitleForRating($user['rating']);
  
  // Get position
  $userTotal = $user['wins'] + $user['losses'];
  $userWinRate = $userTotal > 0 ? round($user['wins'] * 100.0 / $userTotal, 1) : 0;
  
  $stmt = $pdo->prepare("
    SELECT COUNT(*) + 1 as position
    FROM users
    WHERE banned = 0 AND (
      rating > :rating OR 
      (rating = :rating2 AND (CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END) > :win_rate) OR 
      (rating = :rating3 AND (CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END) = :win_rate2 AND rank_points > :rank_points) OR 
      (rating = :rating4 AND (CASE WHEN (wins + losses) > 0 THEN ROUND(wins * 100.0 / (wins + losses), 1) ELSE 0 END) = :win_rate3 AND rank_points = :rank_points2 AND wins > :wins)
    )
  ");
  $stmt->bindValue(':rating', $user['rating'], PDO::PARAM_INT);
  $stmt->bindValue(':rating2', $user['rating'], PDO::PARAM_INT);
  $stmt->bindValue(':rating3', $user['rating'], PDO::PARAM_INT);
  $stmt->bindValue(':rating4', $user['rating'], PDO::PARAM_INT);
  $stmt->bindValue(':win_rate', $userWinRate, PDO::PARAM_STR);
  $stmt->bindValue(':win_rate2', $userWinRate, PDO::PARAM_STR);
  $stmt->bindValue(':win_rate3', $userWinRate, PDO::PARAM_STR);
  $stmt->bindValue(':rank_points', $user['rank_points'], PDO::PARAM_INT);
  $stmt->bindValue(':rank_points2', $user['rank_points'], PDO::PARAM_INT);
  $stmt->bindValue(':wins', $user['wins'], PDO::PARAM_INT);
  $stmt->execute();
  $user['position'] = $stmt->fetch(PDO::FETCH_ASSOC)['position'];
  
  echo json_encode(['ok' => true, 'user' => $user]);
  exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);
