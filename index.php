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
    <title>Best Bike of Show &mdash; Mini Bike Fest</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple-1: #0D030A;
            --purple-2: #12030F;
            --purple-3: #1B041B;
            --purple-4: #230A26;
            --purple-5: #2B102F;
            --shadow-light: rgba(139, 95, 191, 0.8);
            --shadow-dark: rgba(45, 27, 78, 0.6);
            --box-bg: rgba(0, 0, 0, 0.8);
            --box-border: rgba(255, 255, 255, 0.3);
        }

        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #fff;
            min-height: 100vh;
            background: linear-gradient(90deg, var(--purple-1), var(--purple-2), var(--purple-3), var(--purple-4), var(--purple-5));
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            body { animation: none; background-position: 50% 50%; }
        }

        /* Fixed top-right logo */
        .logo-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 150px;
            z-index: 100;
            filter: brightness(0) invert(1);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 2;
        }

        /* Main logo */
        .logo-main {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo-main img {
            max-width: 600px;
            width: 90%;
            filter: brightness(0) invert(1);
        }

        h1 {
            font-size: 3em;
            letter-spacing: 3px;
            text-transform: uppercase;
            text-shadow: 3px 3px 0px var(--shadow-light), 6px 6px 0px var(--shadow-dark);
            margin-bottom: 0.5rem;
        }
        h2 {
            font-size: 2em;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px var(--shadow-light), 4px 4px 0px var(--shadow-dark);
        }
        h3 {
            font-size: 1.5em;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px var(--shadow-light);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            h1 { font-size: 2em; }
            h2 { font-size: 1.5em; }
            .logo-fixed { width: 100px; top: 10px; right: 10px; }
        }

        .box {
            background: var(--box-bg);
            border: 3px solid var(--box-border);
            border-radius: 3px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.6);
            padding: 30px;
            margin-bottom: 40px;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.9rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border: 2px solid var(--box-border);
            margin-top: 0.75rem;
        }
        .status-open   { border-color: rgba(100, 220, 100, 0.6); color: #90ee90; }
        .status-closed { border-color: rgba(220, 100, 100, 0.6); color: #ffaaaa; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        .card {
            background: var(--box-bg);
            border: 3px solid var(--box-border);
            border-radius: 3px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.6);
            padding: 1.5rem;
            transition: border-color 0.2s, transform 0.15s, box-shadow 0.2s;
        }
        .card:hover {
            border-color: rgba(255, 255, 255, 0.7);
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.8);
        }
        .card h2 {
            font-size: 1.1rem;
            letter-spacing: 1px;
            text-shadow: 1px 1px 0px var(--shadow-light);
            margin-bottom: 1rem;
        }

        .vote-btn {
            display: block;
            width: 100%;
            padding: 0.65rem 1rem;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border: 2px solid var(--box-border);
            border-radius: 3px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, transform 0.1s, box-shadow 0.2s;
        }
        .vote-btn:hover {
            background: var(--shadow-light);
            border-color: rgba(255, 255, 255, 0.6);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.8);
        }

        .notice {
            font-size: 1.2em;
            line-height: 1.6;
        }
        .notice strong {
            display: block;
            font-size: 1.4rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px var(--shadow-light), 4px 4px 0px var(--shadow-dark);
            margin-bottom: 0.5rem;
        }

        .error { text-align: center; padding: 2rem; color: #ffaaaa; }
    </style>
</head>
<body>

<img class="logo-fixed"
     src="assets/logo-bike-making.svg"
     alt="Mini Bike Fest">

<div class="container">

    <div class="logo-main">
        <img src="assets/mini-bike-fest-logo.svg"
             alt="Mini Bike Fest">
    </div>

    <div class="box" style="text-align:center">
        <h2>Best Bike of Show</h2>
        <p style="margin-top:0.5rem; color:rgba(255,255,255,0.7); font-size:1.1em;">
            Cast your vote for the most impressive machine
        </p>
        <span class="status-badge <?= $votingOpen ? 'status-open' : 'status-closed' ?>">
            <?= $votingOpen ? 'Voting Open' : 'Voting Closed' ?>
        </span>
    </div>

    <?php if ($error): ?>
        <div class="box"><p class="error"><?= htmlspecialchars($error) ?></p></div>

    <?php elseif ($justVoted): ?>
        <div class="box notice">
            <strong>Thanks for voting!</strong>
            The winner will be announced at the show.
        </div>

    <?php elseif ($alreadyVoted): ?>
        <div class="box notice">
            <strong>You've already voted.</strong>
            The winner will be announced at the show.
        </div>

    <?php elseif (!$votingOpen): ?>
        <div class="box notice">
            <strong>Voting is closed.</strong>
            The winner will be announced at the show.
        </div>

    <?php elseif (empty($contestants)): ?>
        <div class="box notice">No contestants added yet. Check back soon!</div>

    <?php else: ?>
        <div class="box notice">
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

</div>
</body>
</html>
