<?php
session_start();
require_once 'config.php';
require_once 'db_helper.php';
require_once 'currency_helper.php';

// Fetch active games
$db = Database::getInstance();
$pdo = $db->getConnection();
$games = $pdo->query("SELECT * FROM games WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

$loggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? null;
$userCurrency = $_SESSION['currency'] ?? 'PHP';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casino Lobby</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #0b1120; color: #e5e7eb; line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        
        .hero { max-width: 1100px; margin: 0 auto; padding: 40px 20px 10px; text-align: center; }
        .hero h1 { font-size: 32px; margin-bottom: 12px; color: #fff; }
        .hero p { color: #9ca3af; margin-bottom: 24px; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 12px 20px; border-radius: 10px; font-weight: 600; border: none; cursor: pointer; transition: transform 0.15s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: transparent; color: #fff; border: 1px solid #3b4252; }
        .btn:hover { transform: translateY(-1px); }
        
        .section { max-width: 1100px; margin: 0 auto; padding: 30px 20px 50px; }
        .section-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .section-title h2 { font-size: 20px; color: #fff; }
        
        .games-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .game-card { background: #111827; border: 1px solid #1f2937; border-radius: 14px; overflow: hidden; transition: transform 0.2s, border-color 0.2s; }
        .game-card:hover { transform: translateY(-3px); border-color: #2563eb; }
        .game-thumb { height: 140px; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; font-size: 42px; color: #fff; }
        .game-body { padding: 14px; }
        .game-name { font-weight: 700; color: #fff; margin-bottom: 6px; }
        .game-meta { color: #9ca3af; font-size: 13px; margin-bottom: 12px; }
        .pill { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #1f2937; color: #cbd5e1; font-size: 12px; margin-right: 6px; }
        .play-link { display: inline-block; padding: 10px 12px; background: #2563eb; color: #fff; border-radius: 10px; font-weight: 600; }
        .play-link:hover { background: #1d4ed8; }
        
        .footer { max-width: 1100px; margin: 0 auto 30px; padding: 0 20px; text-align: center; color: #6b7280; font-size: 13px; }
        
        @media (max-width: 640px) {
            .hero h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <header class="hero">
        <h1>Play Top Casino Games</h1>
        <p>Browse our live catalog. Log in or create an account to start playing.</p>
        <div class="actions">
            <?php if ($loggedIn): ?>
                <a class="btn btn-primary" href="casino.php">Enter Lobby</a>
                <a class="btn btn-secondary" href="profile.php">My Profile</a>
            <?php else: ?>
                <a class="btn btn-primary" href="login.php">Login</a>
                <a class="btn btn-secondary" href="register.php">Create Account</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="section">
        <div class="section-title">
            <h2>Featured Games</h2>
            <small style="color:#9ca3af;">Showing up to 20 active titles</small>
        </div>
        <div class="games-grid">
            <?php foreach ($games as $game): ?>
                <div class="game-card">
                    <div class="game-thumb">
                        <?php if (!empty($game['image']) && file_exists($game['image'])): ?>
                            <img src="<?php echo htmlspecialchars($game['image']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>" style="width:100%; height:100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($game['name'], 0, 2)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="game-body">
                        <div class="game-name"><?php echo htmlspecialchars($game['name']); ?></div>
                        <div class="game-meta">
                            <span class="pill"><?php echo htmlspecialchars($game['provider']); ?></span>
                            <span class="pill"><?php echo htmlspecialchars($game['category']); ?></span>
                        </div>
                        <?php if ($loggedIn): ?>
                            <a class="play-link" href="play_game.php?game_uid=<?php echo urlencode($game['game_uid']); ?>">Play now</a>
                        <?php else: ?>
                            <a class="play-link" href="login.php">Login to play</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($games)): ?>
                <p style="color:#9ca3af;">No active games available yet.</p>
            <?php endif; ?>
        </div>
    </main>

    <div class="footer">
        <p>Secure gaming. Fair play. 24/7 availability.</p>
    </div>
</body>
</html>
