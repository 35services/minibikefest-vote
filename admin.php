<?php
require_once __DIR__ . '/config.php';

// Auth
$secret = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($secret !== ADMIN_SECRET) {
    http_response_code(403);
    exit('Access denied. Append ?secret=YOUR_SECRET to the URL.');
}

$error = null;
$message = null;
$pdo = null;

try {
    $pdo = getDB();
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
}

// Handle actions
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_voting') {
        $current = isVotingOpen($pdo) ? '1' : '0';
        $new = $current === '1' ? '0' : '1';
        $pdo->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'voting_open'")->execute([$new]);
        $message = 'Voting is now ' . ($new === '1' ? 'OPEN' : 'CLOSED') . '.';

    } elseif ($action === 'delete_votes') {
        $pdo->exec("DELETE FROM votes");
        $message = 'All votes deleted.';

    } elseif ($action === 'sync_contestants') {
        syncContestants($pdo);
        $message = 'Contestants synced from contestants.md.';
    }
}

$votingOpen = $pdo ? isVotingOpen($pdo) : false;
$voteCounts = $pdo ? getVoteCounts($pdo) : [];
$totalVotes = array_sum($voteCounts);

// Load contestants sorted by vote count
$contestants = $pdo ? syncContestants($pdo) : [];
usort($contestants, fn($a, $b) => ($voteCounts[$b['id']] ?? 0) - ($voteCounts[$a['id']] ?? 0));

// Recent votes log
$recentVotes = [];
if ($pdo) {
    $recentVotes = $pdo->query("
        SELECT v.id, c.name, v.ip_address, v.voted_at
        FROM votes v
        JOIN contestants c ON c.id = v.contestant_id
        ORDER BY v.voted_at DESC
        LIMIT 100
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin &mdash; Mini Bike Fest</title>
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

        header {
            background: rgba(0,0,0,0.6);
            border-bottom: 3px solid var(--box-border);
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        header img {
            height: 40px;
            filter: brightness(0) invert(1);
        }
        header h1 {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px var(--shadow-light);
        }
        header span { font-size: 0.85rem; color: rgba(255,255,255,0.5); }

        main { max-width: 960px; margin: 0 auto; padding: 2rem 1rem; }

        .card {
            background: var(--box-bg);
            border: 3px solid var(--box-border);
            border-radius: 3px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.6);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card h2 {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.5);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .status-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 0.35rem 0.9rem;
            border-radius: 3px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .badge-open   { border: 2px solid rgba(100,220,100,0.6); color: #90ee90; }
        .badge-closed { border: 2px solid rgba(220,100,100,0.6); color: #ffaaaa; }

        .btn {
            display: inline-block;
            padding: 0.55rem 1.2rem;
            background: rgba(0,0,0,0.6);
            color: #fff;
            border: 2px solid var(--box-border);
            border-radius: 3px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, transform 0.1s, box-shadow 0.2s;
        }
        .btn:hover {
            background: var(--shadow-light);
            border-color: rgba(255,255,255,0.6);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.8);
        }
        .btn-danger { border-color: rgba(220,100,100,0.6); color: #ffaaaa; }
        .btn-danger:hover { background: rgba(180,50,50,0.8); border-color: rgba(255,150,150,0.8); color: #fff; }

        .message {
            background: rgba(0,80,0,0.5);
            border: 2px solid rgba(100,220,100,0.4);
            border-radius: 3px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            color: #90ee90;
            font-weight: 600;
        }
        .error-msg {
            background: rgba(80,0,0,0.5);
            border: 2px solid rgba(220,100,100,0.4);
            border-radius: 3px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            color: #ffaaaa;
        }

        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td {
            text-align: left;
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        th {
            font-weight: 700;
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.04); }

        .bar-track {
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
            min-width: 80px;
        }
        .bar-fill {
            height: 100%;
            background: var(--shadow-light);
            border-radius: 3px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 3px;
            padding: 1rem;
            text-align: center;
        }
        .stat-box .num {
            font-size: 2rem;
            font-weight: 900;
            text-shadow: 2px 2px 0px var(--shadow-light);
        }
        .stat-box .lbl { font-size: 0.75rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.05em; }

        .danger-zone { border-color: rgba(220,100,100,0.4); }
        .danger-zone h2 { color: rgba(255,150,150,0.6); }
        .danger-zone p { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body>

<header>
    <img src="assets/logo-bike-making.svg" alt="Mini Bike Fest">
    <h1>Admin</h1>
    <span>Best Bike of Show</span>
</header>

<main>

<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Status & Controls -->
<div class="card">
    <h2>Voting Status</h2>
    <div class="status-row">
        <span class="badge <?= $votingOpen ? 'badge-open' : 'badge-closed' ?>">
            <?= $votingOpen ? 'OPEN' : 'CLOSED' ?>
        </span>
        <form method="post" style="display:inline">
            <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">
            <input type="hidden" name="action" value="toggle_voting">
            <button class="btn btn-primary" type="submit">
                <?= $votingOpen ? 'Close Voting' : 'Open Voting' ?>
            </button>
        </form>
        <form method="post" style="display:inline">
            <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">
            <input type="hidden" name="action" value="sync_contestants">
            <button class="btn btn-neutral" type="submit">Sync Contestants from MD</button>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="card">
    <h2>Overview</h2>
    <div class="stat-grid">
        <div class="stat-box">
            <div class="num"><?= $totalVotes ?></div>
            <div class="lbl">Total Votes</div>
        </div>
        <div class="stat-box">
            <div class="num"><?= count($contestants) ?></div>
            <div class="lbl">Contestants</div>
        </div>
    </div>
</div>

<!-- Results Table -->
<?php if (!empty($contestants)): ?>
<div class="card">
    <h2>Results</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Contestant</th>
                <th>Votes</th>
                <th>%</th>
                <th style="width:120px"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($contestants as $i => $c):
            $count = $voteCounts[$c['id']] ?? 0;
            $pct = $totalVotes > 0 ? round($count / $totalVotes * 100) : 0;
        ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= $count ?></td>
                <td><?= $pct ?>%</td>
                <td>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Recent Votes Log -->
<?php if (!empty($recentVotes)): ?>
<div class="card">
    <h2>Recent Votes (last 100)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Contestant</th>
                <th>IP Address</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentVotes as $v): ?>
            <tr>
                <td><?= (int)$v['id'] ?></td>
                <td><?= htmlspecialchars($v['name']) ?></td>
                <td><?= htmlspecialchars($v['ip_address']) ?></td>
                <td><?= htmlspecialchars($v['voted_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Danger Zone -->
<div class="card danger-zone">
    <h2>Danger Zone</h2>
    <p style="margin-bottom:1rem; font-size:0.9rem;">
        Delete all votes before the real vote starts (e.g. after testing).
    </p>
    <form method="post" onsubmit="return confirm('Delete ALL votes? This cannot be undone.')">
        <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">
        <input type="hidden" name="action" value="delete_votes">
        <button class="btn btn-danger" type="submit">Delete All Votes</button>
    </form>
</div>

</main>
</body>
</html>
