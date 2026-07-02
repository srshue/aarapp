<?php
// advances.php - View and manage employee advances
session_start();

if ( $localAccess
    || (isset($_SESSION['userid']))
    || $host == '192.168.0.6'
    || $host == '192.168.1.101'
    || $host == '192.168.4.26'
    || $host == '192.168.2.178'
    || $host == '192.168.1.4' ){
    $navbar = 1;
    $logindisplay = 0;
    $sortBy = "";
    $username = isset($_SESSION["username"]) ? $_SESSION["username"] : 'local';
    $Role = isset($_SESSION["Role"]) ? $_SESSION["Role"] : 'Developer';
    if (isset($_SESSION['userid'])) $userid = $_SESSION["userid"]; else $userid = 0;
    if (isset($_GET["filterName"])) $filterNameParam = $_GET["filterName"]; else  $filterNameParam = "";
    if (isset($_GET["mh"])) $mh = $_GET["mh"]; else  $mh = "";
    if (isset($_GET["bh"])) $bh = $_GET["bh"]; else  $bh = "";
    if (isset($_GET["ph"])) $ph = $_GET["ph"]; else  $ph = "";
    if (isset($_GET["stDt"])) $filterStDt = $_GET["stDt"]; else  $filterStDt = "";
    if (isset($_GET["edDt"])) $filterEdDt = $_GET["edDt"]; else  $filterEdDt = "";
    if (isset($_GET["sortBy"])) $sortBy = $_GET["sortBy"]; else  $sortBy = "";
}
else {
    header ('Location: login.php');
}

$conn = null;
if (file_exists(__DIR__ . '/connect_local.php')) {
    include_once __DIR__ . '/connect_local.php';
} elseif (file_exists(__DIR__ . '/connect.php')) {
    include_once __DIR__ . '/connect.php';
}

if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);

function safe_query($conn, $sql) {
    if (!$conn) return false;
    return @mysqli_query($conn, $sql);
}

$dbAvailable = false;
if (isset($conn) && $conn instanceof mysqli) {
    $dbAvailable = @mysqli_ping($conn) ? true : false;
}

// ensure data dir for file fallback
if (!$dbAvailable) {
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
}

// ensure Advances table exists
if ($dbAvailable) {
    $createAdv = "CREATE TABLE IF NOT EXISTS Advances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        EmpID VARCHAR(64) NOT NULL,
        employee_name VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(12,2) DEFAULT 0,
        note VARCHAR(512) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createAdv);
}

// load employee list
$employeeOptions = [];
if ($dbAvailable) {
    $resEmp = safe_query($conn, "SELECT EmpID AS id, Name AS name FROM Employees ORDER BY Name");
    if ($resEmp && mysqli_num_rows($resEmp) > 0) {
        while ($r = mysqli_fetch_assoc($resEmp)) {
            $employeeOptions[] = ['id' => $r['id'], 'name' => $r['name']];
        }
    }
}
if (empty($employeeOptions)) {
    $empFile = __DIR__ . '/data/employees.json';
    if (file_exists($empFile)) {
        $raw = file_get_contents($empFile);
        $list = json_decode($raw, true);
        if (is_array($list)) foreach ($list as $e) if (isset($e['id']) && isset($e['name'])) $employeeOptions[] = $e;
    }
}
if (empty($employeeOptions)) {
    $sample = ['Ravi', 'Suresh', 'Anita', 'Priya', 'Kumar', 'Deepa'];
    foreach ($sample as $i => $n) $employeeOptions[] = ['id' => 's' . $i, 'name' => $n];
}

// build a quick lookup: id -> name
$empNameMap = [];
foreach ($employeeOptions as $opt) $empNameMap[$opt['id']] = $opt['name'];

// ── handle POST actions ──────────────────────────────────────────────────────
$message = '';
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // ADD a new advance
    if ($action === 'add') {
        $empID  = isset($_POST['EmpID'])  ? trim($_POST['EmpID'])  : '';
        $amount = isset($_POST['amount']) ? floatval(str_replace(',', '', $_POST['amount'])) : 0;
        $note   = isset($_POST['note'])   ? trim($_POST['note'])   : '';
        $empName = isset($empNameMap[$empID]) ? $empNameMap[$empID] : $empID;

        if ($empID === '' || $amount <= 0) {
            $message = 'Please select an employee and enter a valid amount.';
            $messageIsError = true;
        } else {
            if ($dbAvailable) {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO Advances (EmpID, employee_name, amount, note) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssds', $empName, $empName, $amount, $note);
                    // use EmpID for the first column
                    mysqli_stmt_bind_param($stmt, 'ssds', $empID, $empName, $amount, $note);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $message = 'Advance added.';
                } else {
                    $message = 'DB error: ' . mysqli_error($conn);
                    $messageIsError = true;
                }
            } else {
                $advFile = __DIR__ . '/data/advances.json';
                $list = [];
                if (file_exists($advFile)) {
                    $raw = file_get_contents($advFile);
                    $list = json_decode($raw, true);
                    if (!is_array($list)) $list = [];
                }
                $list[] = [
                    'id'            => time() . rand(100, 999),
                    'EmpID'         => $empID,
                    'employee_name' => $empName,
                    'amount'        => $amount,
                    'note'          => $note,
                    'created_at'    => date('Y-m-d H:i:s')
                ];
                file_put_contents($advFile, json_encode($list, JSON_PRETTY_PRINT));
                $message = 'Advance saved to file.';
            }
        }
    }

    // DELETE an advance
    if ($action === 'delete') {
        $delID = isset($_POST['id']) ? $_POST['id'] : '';
        if ($delID !== '') {
            if ($dbAvailable) {
                $stmt = mysqli_prepare($conn, "DELETE FROM Advances WHERE id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $delID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $message = 'Record deleted.';
                }
            } else {
                $advFile = __DIR__ . '/data/advances.json';
                if (file_exists($advFile)) {
                    $raw  = file_get_contents($advFile);
                    $list = json_decode($raw, true);
                    if (is_array($list)) {
                        $list = array_values(array_filter($list, function($r) use ($delID) {
                            return (string)$r['id'] !== (string)$delID;
                        }));
                        file_put_contents($advFile, json_encode($list, JSON_PRETTY_PRINT));
                    }
                }
                $message = 'Record deleted.';
            }
        }
    }

    // EDIT / UPDATE an advance
    if ($action === 'update') {
        $editID = isset($_POST['id'])     ? $_POST['id']                                  : '';
        $empID  = isset($_POST['EmpID'])  ? trim($_POST['EmpID'])                         : '';
        $amount = isset($_POST['amount']) ? floatval(str_replace(',', '', $_POST['amount'])) : 0;
        $note   = isset($_POST['note'])   ? trim($_POST['note'])                           : '';
        $empName = isset($empNameMap[$empID]) ? $empNameMap[$empID] : $empID;

        if ($editID === '' || $empID === '' || $amount <= 0) {
            $message = 'Please select an employee and enter a valid amount.';
            $messageIsError = true;
        } else {
            if ($dbAvailable) {
                $stmt = mysqli_prepare($conn,
                    "UPDATE Advances SET EmpID=?, employee_name=?, amount=?, note=? WHERE id=?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssdsi', $empID, $empName, $amount, $note, $editID);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $message = 'Advance updated.';
                }
            } else {
                $advFile = __DIR__ . '/data/advances.json';
                if (file_exists($advFile)) {
                    $raw  = file_get_contents($advFile);
                    $list = json_decode($raw, true);
                    if (is_array($list)) {
                        foreach ($list as &$r) {
                            if ((string)$r['id'] === (string)$editID) {
                                $r['EmpID']         = $empID;
                                $r['employee_name'] = $empName;
                                $r['amount']        = $amount;
                                $r['note']          = $note;
                                break;
                            }
                        }
                        unset($r);
                        file_put_contents($advFile, json_encode($list, JSON_PRETTY_PRINT));
                    }
                }
                $message = 'Advance updated.';
            }
        }
    }
}

// ── load all advances ────────────────────────────────────────────────────────
$advances = [];
if ($dbAvailable) {
    $filterEmp = isset($_GET['filter_emp']) ? trim($_GET['filter_emp']) : '';
    $sql = "SELECT id, EmpID, employee_name, amount, note, created_at FROM Advances";
    if ($filterEmp !== '') $sql .= " WHERE EmpID = '" . mysqli_real_escape_string($conn, $filterEmp) . "'";
    $sql .= " ORDER BY created_at DESC";
    $res = safe_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $advances[] = $r;
    }
} else {
    $advFile = __DIR__ . '/data/advances.json';
    if (file_exists($advFile)) {
        $raw  = file_get_contents($advFile);
        $list = json_decode($raw, true);
        if (is_array($list)) {
            $filterEmp = isset($_GET['filter_emp']) ? trim($_GET['filter_emp']) : '';
            foreach ($list as $r) {
                if ($filterEmp !== '' && (string)$r['EmpID'] !== $filterEmp) continue;
                $advances[] = $r;
            }
            // sort newest first (by created_at or id)
            usort($advances, function($a, $b) {
                return strcmp(
                    isset($b['created_at']) ? $b['created_at'] : '',
                    isset($a['created_at']) ? $a['created_at'] : ''
                );
            });
        }
    }
}

// per-employee totals
$totals = [];
foreach ($advances as $r) {
    $eid = $r['EmpID'];
    if (!isset($totals[$eid])) $totals[$eid] = 0;
    $totals[$eid] += floatval($r['amount']);
}
$grandTotal = array_sum($totals);

// detect edit mode
$editRow = null;
if (isset($_GET['edit'])) {
    $editID = $_GET['edit'];
    foreach ($advances as $r) {
        if ((string)$r['id'] === (string)$editID) { $editRow = $r; break; }
    }
}

$filterEmp = isset($_GET['filter_emp']) ? trim($_GET['filter_emp']) : '';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Advances</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
       background: #f5f7fb; color: #222; margin: 0; }
.container { max-width: 1000px; margin: 30px auto; background: #fff;
             padding: 28px; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
h2 { margin: 0 0 20px; color: #0b2545; }
h3 { margin: 24px 0 12px; color: #0b2545; font-size: 1rem; }

.note { display: inline-block; padding: 8px 14px; border-radius: 6px; margin-bottom: 14px; font-size: .92rem; }
.note.error { background: #fff0f0; color: #a62b2b; border: 1px solid #ffd6d8; }
.note.ok    { background: #e9f7ef; color: #186a3b; border: 1px solid #cfead5; }

/* form */
.form-grid { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 20px;
             background: #f8fafd; border: 1px solid #e3e8f0; border-radius: 8px; padding: 16px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group label { font-size: .82rem; font-weight: 600; color: #444; }
select, input[type=text], textarea {
    padding: 7px 10px; border: 1px solid #d5dce6; border-radius: 6px;
    font-size: .93rem; background: #fff; color: #222;
}
select { min-width: 180px; }
input[type=text] { width: 140px; }
textarea { width: 260px; resize: vertical; min-height: 36px; }

.btn        { padding: 8px 14px; border-radius: 6px; border: none; cursor: pointer; font-size: .9rem; }
.btn-primary { background: #0563c1; color: #fff; }
.btn-primary:hover { background: #044fa3; }
.btn-edit   { background: #e6eefc; color: #0645ad; border: 1px solid #d7e4ff; }
.btn-edit:hover { background: #dfeeff; }
.btn-delete { background: #fff5f6; color: #a62b2b; border: 1px solid #ffd6d8; }
.btn-delete:hover { background: #ffecec; }
.btn-cancel { background: #6b7280; color: #fff; }
.btn-cancel:hover { background: #4b5563; }

/* filter bar */
.filter-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-bar label { font-size: .88rem; font-weight: 600; }
.filter-bar select { min-width: 160px; }

/* table */
table { width: 100%; border-collapse: collapse; font-size: .92rem; }
th    { background: #0b2545; color: #fff; padding: 9px 10px; text-align: left; }
td    { padding: 8px 10px; border-bottom: 1px solid #efefef; vertical-align: middle; }
tr:hover td { background: #f5f8ff; }
.amount-cell { text-align: right; font-variant-numeric: tabular-nums; }
.total-row td { background: #f0f4ff; font-weight: 700; }
.grand-row td { background: #0b2545; color: #fff; font-weight: 700; }
.empty-note { color: #777; font-style: italic; padding: 14px 0; }

/* summary cards */
.summary { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
.card { background: #f0f4ff; border: 1px solid #d7e4ff; border-radius: 8px;
        padding: 12px 18px; min-width: 160px; }
.card .card-name { font-size: .82rem; color: #555; margin-bottom: 4px; }
.card .card-amt  { font-size: 1.25rem; font-weight: 700; color: #0563c1; }

.back-link { color: #0563c1; text-decoration: none; font-size: .88rem; }
.back-link:hover { text-decoration: underline; }

@media (max-width: 640px) {
    .container { margin: 12px; padding: 16px; }
    .form-grid  { flex-direction: column; }
    input[type=text], textarea, select { width: 100%; }
}
</style>
</head>
<body>
<div class="container">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
    <h2>💸 Advances</h2>
    <a class="back-link" href="salaries.php">← Back to Salaries</a>
</div>

<?php if (!$dbAvailable): ?>
<div class="note error">⚠ Database unavailable — using file storage (<code>data/advances.json</code>).</div>
<?php endif; ?>

<?php if ($message):
    $cls = $messageIsError ? 'note error' : 'note ok';
    echo '<div class="' . $cls . '">' . htmlspecialchars($message) . '</div>';
endif; ?>

<!-- ── Summary cards ───────────────────────────────────────────────────────── -->
<?php if (!empty($totals)): ?>
<div class="summary">
<?php foreach ($totals as $eid => $tot):
    $ename = isset($empNameMap[$eid]) ? $empNameMap[$eid] : $eid;
?>
    <div class="card">
        <div class="card-name"><?php echo htmlspecialchars($ename); ?></div>
        <div class="card-amt">₹<?php echo number_format($tot, 2); ?></div>
    </div>
<?php endforeach; ?>
    <div class="card" style="background:#fff3e0;border-color:#ffd59e;">
        <div class="card-name">Grand Total</div>
        <div class="card-amt" style="color:#b45309;">₹<?php echo number_format($grandTotal, 2); ?></div>
    </div>
</div>
<?php endif; ?>

<!-- ── Add / Edit form ────────────────────────────────────────────────────── -->
<h3><?php echo $editRow ? '✏️ Edit Advance' : '➕ Add Advance'; ?></h3>
<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
    <input type="hidden" name="action" value="<?php echo $editRow ? 'update' : 'add'; ?>">
    <?php if ($editRow): ?>
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editRow['id']); ?>">
    <?php endif; ?>

    <div class="form-grid">
        <div class="form-group">
            <label for="EmpID">Employee</label>
            <select name="EmpID" id="EmpID" required>
                <option value="">-- select --</option>
                <?php foreach ($employeeOptions as $opt):
                    $sel = ($editRow && (string)$editRow['EmpID'] === (string)$opt['id']) ? 'selected' : '';
                ?>
                <option value="<?php echo htmlspecialchars($opt['id']); ?>" <?php echo $sel; ?>>
                    <?php echo htmlspecialchars($opt['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="amount">Amount (₹)</label>
            <input type="text" id="amount" name="amount" placeholder="0.00"
                   value="<?php echo $editRow ? htmlspecialchars(number_format((float)$editRow['amount'], 2)) : ''; ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="note">Note</label>
            <textarea id="note" name="note" placeholder="Optional note"><?php
                echo $editRow ? htmlspecialchars($editRow['note']) : '';
            ?></textarea>
        </div>

        <div class="form-group" style="flex-direction:row;gap:8px;align-items:flex-end;">
            <button type="submit" class="btn btn-primary">
                <?php echo $editRow ? 'Update' : 'Add Advance'; ?>
            </button>
            <?php if ($editRow): ?>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-cancel"
               style="text-decoration:none;display:inline-block;">Cancel</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- ── Filter bar ─────────────────────────────────────────────────────────── -->
<div class="filter-bar">
    <label for="filter_emp">Filter by employee:</label>
    <form method="get" style="display:contents;">
        <select name="filter_emp" id="filter_emp" onchange="this.form.submit()">
            <option value="">All employees</option>
            <?php foreach ($employeeOptions as $opt):
                $sel = ($filterEmp === (string)$opt['id']) ? 'selected' : '';
            ?>
            <option value="<?php echo htmlspecialchars($opt['id']); ?>" <?php echo $sel; ?>>
                <?php echo htmlspecialchars($opt['name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($filterEmp): ?>
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-cancel btn" style="text-decoration:none;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- ── Advances table ─────────────────────────────────────────────────────── -->
<h3>📋 Advance Records<?php echo $filterEmp && isset($empNameMap[$filterEmp]) ? ' — ' . htmlspecialchars($empNameMap[$filterEmp]) : ''; ?></h3>

<?php if (empty($advances)): ?>
    <p class="empty-note">No advance records found.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Employee</th>
            <th style="text-align:right;">Amount (₹)</th>
            <th>Note</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $prevEmp = null; $empSubTotal = 0; $counter = 1;
    // group by employee for subtotals
    // first, collect rows per employee in order
    $grouped = []; $order = [];
    foreach ($advances as $r) {
        $eid = $r['EmpID'];
        if (!isset($grouped[$eid])) { $grouped[$eid] = []; $order[] = $eid; }
        $grouped[$eid][] = $r;
    }
    foreach ($order as $eid):
        $rows  = $grouped[$eid];
        $ename = isset($empNameMap[$eid]) ? $empNameMap[$eid] : $eid;
        $empTotal = array_sum(array_column($rows, 'amount'));
        foreach ($rows as $r):
    ?>
        <tr>
            <td><?php echo $counter++; ?></td>
            <td><?php echo htmlspecialchars($ename); ?></td>
            <td class="amount-cell">₹<?php echo number_format((float)$r['amount'], 2); ?></td>
            <td><?php echo htmlspecialchars($r['note'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars(isset($r['created_at']) ? date('d M Y, H:i', strtotime($r['created_at'])) : ''); ?></td>
            <td>
                <a href="?edit=<?php echo urlencode($r['id']); ?><?php echo $filterEmp ? '&filter_emp='.urlencode($filterEmp) : ''; ?>"
                   class="btn btn-edit btn" style="text-decoration:none;margin-right:4px;">Edit</a>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Delete this advance record?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($r['id']); ?>">
                    <button type="submit" class="btn btn-delete">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2">Subtotal — <?php echo htmlspecialchars($ename); ?></td>
            <td class="amount-cell">₹<?php echo number_format($empTotal, 2); ?></td>
            <td colspan="3"></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="grand-row">
            <td colspan="2">Grand Total</td>
            <td class="amount-cell">₹<?php echo number_format($grandTotal, 2); ?></td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

</div><!-- /container -->

<script>
// allow only numeric input in amount field
document.getElementById('amount').addEventListener('input', function() {
    var v = this.value.replace(/[^0-9.,]/g, '');
    if (v !== this.value) this.value = v;
});
</script>

</body>
</html>
