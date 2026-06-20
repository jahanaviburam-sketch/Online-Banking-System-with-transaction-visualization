<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: index.html");
    exit();
}

$databaseName = $_SESSION['db_name'] ?? 'online_bankin_system';
$conn = openDatabaseConnection($databaseName);

$email = $_SESSION['email'];

$statement = $conn->prepare("SELECT * FROM users WHERE email = ?");

if (!$statement) {
    die("Unable to prepare dashboard query: " . $conn->error);
}

$statement->bind_param("s", $email);
$statement->execute();
$result = $statement->get_result();
$row = $result->fetch_assoc();

$statement->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        :root {
            --bg: #edf4f8;
            --panel: rgba(255, 255, 255, 0.88);
            --panel-strong: #ffffff;
            --ink: #14213d;
            --muted: #5c6b82;
            --brand: #0f4c81;
            --brand-deep: #0a2e4f;
            --line: rgba(15, 76, 129, 0.12);
            --shadow: 0 24px 60px rgba(17, 46, 81, 0.14);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(34, 160, 107, 0.18), transparent 28%),
                radial-gradient(circle at top right, rgba(15, 76, 129, 0.16), transparent 30%),
                linear-gradient(180deg, #f8fbfd 0%, var(--bg) 100%);
        }

        .page-shell {
            width: min(1120px, calc(100% - 32px));
            margin: 32px auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
        }

        .brand h1 {
            margin: 0;
            font-size: clamp(28px, 4vw, 40px);
            letter-spacing: -0.03em;
        }

        .brand p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 15px;
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            padding: 12px 18px;
            border-radius: 999px;
            background: var(--brand-deep);
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 16px 30px rgba(10, 46, 79, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .logout-link:hover {
            background: #07233b;
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(10, 46, 79, 0.24);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.45fr 0.95fr;
            gap: 24px;
        }

        .hero-card,
        .info-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }

        .hero-card {
            padding: 32px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, rgba(15, 76, 129, 0.98), rgba(9, 41, 69, 0.96));
            color: #ffffff;
        }

        .hero-card::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -80px;
            top: -80px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.18), transparent 70%);
        }

        .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .hero-card h2 {
            margin: 18px 0 10px;
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.08;
            letter-spacing: -0.03em;
        }

        .hero-card p {
            max-width: 520px;
            margin: 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 16px;
            line-height: 1.7;
        }

        .balance-panel {
            margin-top: 28px;
            padding: 24px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .balance-label {
            display: block;
            color: rgba(255, 255, 255, 0.72);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .balance-amount {
            margin-top: 10px;
            font-size: clamp(36px, 5vw, 52px);
            font-weight: 700;
            letter-spacing: -0.04em;
        }

        .card-stack {
            display: grid;
            gap: 24px;
        }

        .info-card {
            padding: 24px;
            background: var(--panel-strong);
        }

        .info-card h3 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .info-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .meta-list {
            display: grid;
            gap: 16px;
            margin-top: 18px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(20, 33, 61, 0.08);
        }

        .meta-row:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .meta-label {
            color: var(--muted);
            font-size: 14px;
        }

        .meta-value {
            font-weight: 600;
            color: var(--ink);
            text-align: right;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            margin-top: 18px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(34, 160, 107, 0.12);
            color: #116b48;
            font-weight: 600;
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .logout-link {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .page-shell {
                width: min(100% - 20px, 1120px);
                margin: 20px auto;
            }

            .hero-card,
            .info-card {
                border-radius: 22px;
                padding: 22px;
            }

            .balance-panel {
                padding: 18px;
            }

            .meta-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .meta-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <div class="topbar">
            <div class="brand">
                <h1>Customer Dashboard</h1>
                <p>Manage your account details and track your balance from one secure dashboard.</p>
            </div>
            <a class="logout-link" href="logout.php">Logout</a>
        </div>

        <section class="dashboard-grid">
            <article class="hero-card">
                <span class="eyebrow">Account Overview</span>
                <h2>Welcome back, <?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p>Your account is active and ready. Review your current balance and registered details below.</p>

                <div class="balance-panel">
                    <span class="balance-label">Available Balance</span>
                    <div class="balance-amount">Rs. <?php echo number_format((float) ($row['balance'] ?? 0), 2); ?></div>
                </div>
            </article>

            <div class="card-stack">
                <article class="info-card">
                    <h3>Profile Summary</h3>
                    <p>Your registered customer information is displayed here for quick verification.</p>

                    <div class="meta-list">
                        <div class="meta-row">
                            <span class="meta-label">Full Name</span>
                            <span class="meta-value"><?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Email Address</span>
                            <span class="meta-value"><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Account Number</span>
                            <span class="meta-value"><?php echo htmlspecialchars($row['account_number'] ?? 'Not available', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </article>

                <article class="info-card">
                    <h3>Account Status</h3>
                    <p>Your banking profile is connected successfully and set up for future features like transfers and transaction history.</p>
                    <div class="status-badge">Active and secured</div>
                </article>
            </div>
        </section>
    </main>
</body>
</html>
