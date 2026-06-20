<?php
session_start();
if(empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
session_regenerate_id(true);
require_once 'db.php';

if (!isset($_SESSION['email'])) {
    header("Location: index.html");
    exit();
}

$allowed_db = 'online_bankin_system';
$databaseName = $allowed_db;
$conn = openDatabaseConnection($databaseName);

$email = $_SESSION['email'];
$stmt = $conn->prepare("
SELECT MONTHNAME(date) as month, SUM(amount) as total
FROM transactions
WHERE email=? AND type='withdraw'
GROUP BY MONTH(date)
");
$stmt->bind_param("s", $email);
$stmt->execute();
$monthly = $stmt->get_result();
// ✅ USER DATA
$stmt1 = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt1->bind_param("s", $email);
$stmt1->execute();
$result1 = $stmt1->get_result();
$user = $result1->fetch_assoc();

$balance = $user['balance'] ?? 0;
$low_balance = false;

if ($balance < 500) {
    $low_balance = true;
}

// ✅ CHART DATA
$stmt2 = $conn->prepare("
SELECT 
    SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) AS deposit,
    SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END) AS withdraw_amt
FROM transactions
WHERE email=?
");
$stmt2->bind_param("s", $email);
$stmt2->execute();
$result2 = $stmt2->get_result();
$chart = $result2->fetch_assoc();

$total_deposit = $chart['deposit'] ?? 0;
$total_withdraw = $chart['withdraw_amt'] ?? 0;
// 🔥 Predictive Alert Logic

$stmt = $conn->prepare("
SELECT AVG(amount) as avg_spend 
FROM transactions 
WHERE email=? AND type='withdraw'
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

$avg_spend = $data['avg_spend'] ?? 0;

// condition for alert
$predict_alert = false;

if ($avg_spend > 0 && $balance < ($avg_spend * 2)) {
    $predict_alert = true;
}
// ✅ RECENT TRANSACTIONS
$stmt3 = $conn->prepare("
SELECT type, amount,category, date 
FROM transactions 
WHERE email=? 
ORDER BY date DESC 
LIMIT 5
");
$stmt3->bind_param("s", $email);
$stmt3->execute();
$transactions = $stmt3->get_result();
// ✅ CATEGORY CHART DATA
$stmt4 = $conn->prepare("
SELECT category, SUM(amount) as total 
FROM transactions 
WHERE email=? AND type='withdraw' 
GROUP BY category
");
$stmt4->bind_param("s", $email);
$stmt4->execute();
$result4 = $stmt4->get_result();

$categories = [];
$amounts = [];

while($row = $result4->fetch_assoc()) {
    $categories[] = $row['category'];
    $amounts[] = $row['total'];
} 
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0d1117, #1f2937);
    color: #fff;
}
.alert {
    background: #ffe6e6;
    color: #a94442;
    padding: 10px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 14px;
    text-align: center;
}
.container {
    margin-left: 220px;
    padding: 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card {
    background: rgba(22, 27, 34, 0.7);
    backdrop-filter: blur(10px);
    padding: 20px;
    border-radius: 16px;
    margin-top: 15px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.6);
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 12px 35px rgba(0,0,0,0.8);
}

.balance {
    font-size: 32px;
    font-weight: bold;
    background: linear-gradient(90deg, #58a6ff, #1cc88a);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.transaction-panel {
    margin-top: 20px;
    padding: 25px;
    border-radius: 15px;
    background: linear-gradient(135deg, #0f4c81, #0a2e4f);
    color: white;
    text-align: center;
}

.transaction-form input {
    width: 80%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: none;
}

.btn-group {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.deposit-btn {
    background: #1cc88a;
    padding: 10px 20px;
    border: none;
    color: white;
    border-radius: 8px;
}

.withdraw-btn {
    background: #e74a3b;
    padding: 10px 20px;
    border: none;
    color: white;
    border-radius: 8px;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.history-table th {
    font-size: 14px;
    padding: 8px;
}

.history-table td {
    font-size: 13px;
    padding: 8px;
}

.deposit { color: green; font-weight: bold; }
.withdraw { color: red; font-weight: bold; }

.logout {
    background: black;
    color: white;
    padding: 10px;
    text-decoration: none;
}

canvas {
    max-height: 200px;
    margin-top: 15px;
}
h2, h3 {
    margin: 5px 0;
}
.popup {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #ff4d4d;
    color: white;
    padding: 12px 18px;
    border-radius: 8px;
    font-size: 14px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateY(-20px);
    transition: all 0.5s ease;
    z-index: 1000;
}

.popup.show {
    opacity: 1;
    transform: translateY(0);
}
.sidebar {
    position: fixed;
    width: 220px;
    height: 100%;
    background: linear-gradient(180deg, #161b22, #0d1117);
    padding: 20px;
}

.sidebar a {
    display: block;
    color: #aaa;
    padding: 12px;
    border-radius: 8px;
    transition: 0.3s;
}

.sidebar a:hover {
    background: #58a6ff;
    color: white;
    transform: translateX(5px);
}
.stats {
    display: flex;
    gap: 15px;
}

.stats .card {
    text-align: center;
    font-size: 16px;
    font-weight: 500;
    background: linear-gradient(145deg, #161b22, #0d1117);
}
.card:hover {
    transform: translateY(-3px);
    transition: 0.3s;
}
body {
    background: #0d1117;
    color: white;
}
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.history-table tr:hover {
    background: rgba(88, 166, 255, 0.1);
    transition: 0.2s;
}
.chart-card {
    background: linear-gradient(145deg, #161b22, #0d1117);
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.6);
    transition: 0.3s;
}

.chart-card:hover {
    transform: translateY(-5px);
}

.chart-card h3 {
    margin-bottom: 10px;
    font-size: 16px;
    color: #58a6ff;
}
canvas {
    width: 100% !important;
    height: 250px !important;
}
.chart-card {
    height: 300px;
    position: relative;
}

canvas {
    width: 100% !important;
    height: 100% !important;
}
.custom-popup {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    visibility: hidden;
    opacity: 0;
    transition: 0.3s;
    z-index: 9999;
}

.custom-popup.show {
    visibility: visible;
    opacity: 1;
}

.popup-content {
    background: #161b22;
    padding: 25px 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.7);
    max-width: 350px;
}

.popup-icon {
    font-size: 30px;
    display: block;
    margin-bottom: 10px;
}

.popup-content p {
    font-size: 16px;
    margin-bottom: 15px;
    color: #fff;
}

.popup-content button {
    background: #58a6ff;
    border: none;
    padding: 8px 18px;
    border-radius: 6px;
    color: white;
    cursor: pointer;
}

.popup-content button:hover {
    background: #1f6feb;
}
</style>
</head>

<body>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>💳 Bank</h2>

    <a href="#" onclick="showSection('dashboardSection')">Dashboard</a>
    <a href="#" onclick="showSection('transactionSection')">Transactions</a>
    <a href="#" onclick="showSection('analyticsSection')">Analytics</a>
    <a href="#" onclick="showSection('profileSection')">Profile</a>

    <a href="logout.php">Logout</a>
</div>

<div class="container">

<!-- HEADER -->
<div class="header">
    <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
</div>

<!-- ✅ DASHBOARD SECTION -->
<div id="dashboardSection" class="section">

    <div class="card">
        <h3>Available Balance</h3>
        <div class="balance">
            Rs. <?php echo number_format((float)$balance, 2); ?>
        </div>
    </div>

    <?php if($low_balance): ?>
    <div class="alert">
        ⚠️ Low Balance! Please deposit money.
    </div>
    <?php endif; ?>

    <div class="stats">
        <div class="card">💰 Balance <br> ₹<?php echo $balance; ?></div>
        <div class="card">📈 Deposit <br> ₹<?php echo $total_deposit; ?></div>
        <div class="card">📉 Withdraw <br> ₹<?php echo $total_withdraw; ?></div>
    </div>
<div class="card">
<h3>Monthly Expense</h3>
<?php while($m=$monthly->fetch_assoc()): ?>
<p><?php echo $m['month']; ?> : ₹<?php echo $m['total']; ?></p>
<?php endwhile; ?>
</div>
    <!-- RECENT -->
    <div class="card">
        <h3>📊 Recent Transactions</h3>
        <table class="history-table">
            <tr>
                <th>Type</th>
                <th>Amount</th>
                <th>Category</th>
                <th>Date</th>
            </tr>

            <?php while($t = $transactions->fetch_assoc()): ?>
            <tr>
                <td class="<?php echo $t['type']; ?>">
                    <?php echo ucfirst($t['type']); ?>
                </td>
                <td>₹ <?php echo number_format($t['amount'], 2); ?></td>
                <td><?php echo $t['category']; ?></td>

                <td><?php echo date("d M Y, h:i A", strtotime($t['date'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

</div>

<!-- ✅ TRANSACTION SECTION -->
<div id="transactionSection" class="section" style="display:none;">
    <div class="transaction-panel">
        <h3>💳 Transactions</h3>

        <form action="transaction.php" method="POST" class="transaction-form">

    <input type="number" name="amount" placeholder="Enter amount" required>

    <select name="category" required>
        <option value="">Select Category</option>
        <option value="Food">Food</option>
        <option value="Shopping">Shopping</option>
        <option value="Bills">Bills</option>
        <option value="Travel">Travel</option>
        <option value="Other">Other</option>
    </select>

    <!-- ✅ ADD THIS LINE -->
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

    <div class="btn-group">
        <button type="submit" name="type" value="deposit">Deposit</button>
        <button type="submit" name="type" value="withdraw">Withdraw</button>
    </div>

</form>
        
    </div>
</div>

<!-- ✅ PROFILE SECTION -->
<div id="profileSection" class="section" style="display:none;">
    <div class="card">
        <h3>Profile</h3>
        <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
        <p>Account No: <?php echo htmlspecialchars($user['account_number']); ?></p>
    </div>
</div>

<!-- ✅ ANALYTICS SECTION -->
<div id="analyticsSection" class="section" style="display:none;">
    
    <div class="analytics-grid">

        <div class="chart-card">
            <h3>📊 Deposits vs Withdrawals</h3>
            <canvas id="barChart"></canvas>
        </div>

        <div class="chart-card">
            <h3>📈 Balance Trend</h3>
            <canvas id="lineChart"></canvas>
        </div>

        <div class="chart-card">
            <h3>🥧 Distribution</h3>
            <canvas id="pieChart"></canvas>
        </div>
<div class="chart-card">
    <h3>📊 Category-wise Spending</h3>
    <canvas id="categoryChart"></canvas>
</div>
    </div>

</div>
</div>
<div class="stats-grid">
    <div class="stat-box">💰 Balance <br> ₹<?php echo $balance; ?></div>
    <div class="stat-box">📥 Deposit <br> ₹<?php echo $total_deposit; ?></div>
    <div class="stat-box">📤 Withdraw <br> ₹<?php echo $total_withdraw; ?></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function loadCharts() {

    const deposit = <?php echo $total_deposit ?? 0; ?>;
    const withdraw = <?php echo $total_withdraw ?? 0; ?>;
    const balance = <?php echo $balance ?? 0; ?>;

    // BAR CHART
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: ['Deposit', 'Withdraw'],
            datasets: [{
                label: 'Amount',
                data: [deposit, withdraw],
                backgroundColor: ['#1cc88a', '#e74a3b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // PIE CHART
    new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: ['Deposit', 'Withdraw'],
            datasets: [{
                data: [deposit, withdraw],
                backgroundColor: ['#1cc88a', '#e74a3b']
            }]
        },
        options: {
            maintainAspectRatio: false
        }
    });

    // LINE CHART
    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: ['Start', 'Now'],
            datasets: [{
                label: 'Balance',
                data: [0, balance],
                borderColor: '#58a6ff',
                tension: 0.4
            }]
        },
        options: {
            maintainAspectRatio: false
        }
    });
}
// CATEGORY CHART
new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($categories); ?>,
        datasets: [{
            data: <?php echo json_encode($amounts); ?>,
            backgroundColor: [
                '#ff6384', '#36a2eb', '#ffce56',
                '#4bc0c0', '#9966ff'
            ]
        }]
    },
    options: {
        maintainAspectRatio: false
    }
});
</script>
<script>
function showSection(sectionId) {

    document.querySelectorAll('.section').forEach(sec => {
        sec.style.display = 'none';
    });

    document.getElementById(sectionId).style.display = 'block';

    // 🔥 LOAD CHARTS ONLY WHEN ANALYTICS OPENS
    if(sectionId === 'analyticsSection'){
        setTimeout(() => {
            loadCharts();
        }, 200);
    }
}
</script>
<script>
function showPopup() {
    document.getElementById("predictPopup").classList.add("show");
}

function closePopup() {
    document.getElementById("predictPopup").classList.remove("show");
}

window.onload = function () {

    const predictAlert = <?php echo $predict_alert ? 'true' : 'false'; ?>;

    if (predictAlert) {
        setTimeout(() => {
            showPopup();
        }, 1000);
    }
};
</script>
<div id="predictPopup" class="custom-popup">
    <div class="popup-content">
        <span class="popup-icon">⚠️</span>
        <p>Based on your spending, your balance may go low soon!</p>
        <button onclick="closePopup()">OK</button>
    </div>
</div>
<script>
window.onload = function () {

    // existing code (alerts, etc.)

    // ✅ ADD ANIMATION HERE
    document.querySelectorAll('.card').forEach((card, i) => {
        card.style.opacity = 0;
        card.style.transform = "translateY(20px)";

        setTimeout(() => {
            card.style.transition = "0.5s";
            card.style.opacity = 1;
            card.style.transform = "translateY(0)";
        }, i * 150);
    });

};
</script>
</body>
</html>