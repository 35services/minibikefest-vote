<?php
require_once __DIR__ . '/config.php';

$error = null;
$pdo = null;
try {
    $pdo = getDB();
} catch (Exception $e) {
    $error = 'Database connection failed.';
}

$votingOpen = $pdo ? isVotingOpen($pdo) : false;
$contestants = $pdo ? syncContestants($pdo) : [];
shuffle($contestants);

$alreadyVoted = !empty($_COOKIE[COOKIE_NAME]);
$justVoted = false;
$votedForId = null;

// Handle vote submission
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && $votingOpen && !$alreadyVoted) {
    $contestantId = filter_input(INPUT_POST, 'contestant_id', FILTER_VALIDATE_INT);
    if ($contestantId) {
        $stmt = $pdo->prepare("SELECT id FROM contestants WHERE id = ?");
        $stmt->execute([$contestantId]);
        if ($stmt->fetch()) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
                ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
                : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $cookieId = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO votes (contestant_id, ip_address, cookie_id) VALUES (?, ?, ?)");
            $stmt->execute([$contestantId, trim($ip), $cookieId]);
            setcookie(COOKIE_NAME, $cookieId, time() + COOKIE_LIFETIME, '/', '', false, true);
            $alreadyVoted = true;
            $justVoted = true;
            $votedForId = $contestantId;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Best Bike of Show &mdash; Minibikefest</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #111;
            color: #eee;
            min-height: 100vh;
        }

        header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2a1a00 100%);
            border-bottom: 3px solid #ff6b00;
            padding: 2rem 1rem;
            text-align: center;
        }

        header h1 {
            font-size: clamp(1.8rem, 5vw, 3rem);
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #ff6b00;
            text-transform: uppercase;
        }

        header p {
            margin-top: 0.5rem;
            color: #aaa;
            font-size: 1rem;
        }

        .status-badge {
            display: inline-block;
            margin-top: 0.75rem;
            padding: 0.3rem 0.9rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .status-open   { background: #1a4a1a; color: #4caf50; border: 1px solid #4caf50; }
        .status-closed { background: #4a1a1a; color: #f44336; border: 1px solid #f44336; }

        main {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .notice {
            background: #1e1e1e;
            border: 1px solid #333;
            border-left: 4px solid #ff6b00;
            border-radius: 6px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            font-size: 1.05rem;
        }
        .notice.success { border-left-color: #4caf50; }
        .notice.info    { border-left-color: #2196f3; }

        .notice strong { display: block; font-size: 1.2rem; margin-bottom: 0.3rem; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .card {
            background: #1e1e1e;
            border: 1px solid #2e2e2e;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            transition: border-color 0.15s, transform 0.1s;
        }

        .card:hover { border-color: #ff6b00; transform: translateY(-2px); }

        .card h2 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #fff;
        }

        .vote-btn {
            display: block;
            width: 100%;
            padding: 0.6rem 1rem;
            background: #ff6b00;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s;
        }
        .vote-btn:hover { background: #e05a00; }

        .error { color: #f44336; text-align: center; padding: 2rem; }

        footer {
            text-align: center;
            padding: 2rem;
            color: #444;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

<header>
    <h1>Best Bike of Show</h1>
    <p>Cast your vote for the most impressive machine</p>
    <span class="status-badge <?= $votingOpen ? 'status-open' : 'status-closed' ?>">
        <?= $votingOpen ? 'Voting Open' : 'Voting Closed' ?>
    </span>
</header>

<main>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>

<?php elseif ($justVoted): ?>
    <div class="notice success">
        <strong>Thanks for voting!</strong>
        The winner will be announced at the show.
    </div>

<?php elseif ($alreadyVoted && !$justVoted): ?>
    <div class="notice info">
        <strong>You've already voted.</strong>
        The winner will be announced at the show.
    </div>

<?php elseif (!$votingOpen): ?>
    <div class="notice">
        <strong>Voting is closed.</strong>
        The winner will be announced at the show.
    </div>

<?php elseif (empty($contestants)): ?>
    <div class="notice">No contestants added yet. Check back soon!</div>

<?php else: ?>
    <div class="notice">
        <strong>Pick your favourite!</strong>
        Choose one bike &mdash; you can only vote once.
    </div>
<?php endif; ?>

<?php if (!$error && $votingOpen && !$alreadyVoted && !empty($contestants)): ?>
    <div class="grid">
        <?php foreach ($contestants as $c): ?>
        <div class="card">
            <h2><?= htmlspecialchars($c['name']) ?></h2>
            <form method="post">
                <input type="hidden" name="contestant_id" value="<?= (int)$c['id'] ?>">
                <button class="vote-btn" type="submit">Vote &rarr;</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</main>

<footer>Minibikefest &mdash; Best Bike of Show</footer>

</body>
</html>
