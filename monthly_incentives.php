<?php
// monthly_incentives.php - Consolidated per-employee incentive totals for a selected month
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
    $username = isset($_SESSION["username"]) ? $_SESSION["username"] : 'local';
    $Role = isset($_SESSION["Role"]) ? $_SESSION["Role"] : 'Developer';
    if (isset($_SESSION['userid'])) $userid = $_SESSION["userid"]; else $userid = 0;
}
else {
    header ('Location: login.php');
} 

// include local or default DB connect if present
$conn = null;
if (file_exists(__DIR__ . '/connect_local.php')) {
    include_once __DIR__ . '/connect_local.php';
} elseif (file_exists(__DIR__ . '/connect.php')) {
    include_once __DIR__ . '/connect.php';
}

// disable mysqli exceptions in dev
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);

function safe_query($conn, $sql) {
    if (!$conn) return false;
    return @mysqli_query($conn, $sql);
}

// detect DB availability
$dbAvailable = false;
if (isset($conn) && $conn instanceof mysqli) {
    $dbAvailable = @mysqli_ping($conn) ? true : false;
}

// ── Selected year / month (defaults to current) ─────────────────────────────
$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');
$selYear  = isset($_GET['year'])  ? intval($_GET['year'])  : $currentYear;
$selMonth = isset($_GET['month']) ? intval($_GET['month']) : $currentMonth;
if ($selMonth < 1 || $selMonth > 12) $selMonth = $currentMonth;

$monthNames = [
    1=>'January', 2=>'February', 3=>'March', 4=>'April', 5=>'May', 6=>'June',
    7=>'July', 8=>'August', 9=>'September', 10=>'October', 11=>'November', 12=>'December'
];

// ── Fields that count toward incentives (mirrors details.php) ──────────────
// NOTE: 'miscellaneous' is intentionally excluded from incentive calculation.
// 'dj_charges' is a normal amount field and counts in full, same as the rest.
$incentiveKeys = [
    'real_flower','banner','outside_labour_1','outside_labour_2','fixing','removal','aarna_labor',
    'balloon','noble_bose','dj_charges',
    'night_stay_person_1','night_stay_person_2','night_stay_electrician','watchman_stay',
    'wedding_pillars_person1','wedding_pillars_person2','wedding_pillars_person3','wedding_pillars_person4',
    'real_flower_purchase','real_flower_fixing_pillar',
    'WB','WC','GC','NM','B','LT'
];

// ── load employee list (used to resolve id -> name in file-storage mode) ──
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
    $sample = ['Ravi','Suresh','Anita','Priya','Kumar','Deepa'];
    foreach ($sample as $i => $n) $employeeOptions[] = ['id' => 's' . $i, 'name' => $n];
}

function resolveEmpName($emp, $employeeOptions) {
    foreach ($employeeOptions as $eo) {
        if ((string)$eo['id'] === (string)$emp || (string)$eo['name'] === (string)$emp) return $eo['name'];
    }
    return $emp;
}

// ── figure out which column in a table actually holds the event date ──────
// (different deployments have used different column names historically)
// fetch all column names for a table, plus which one is the primary key (if any)
function getColumns($conn, $table) {
    $out = ['all' => [], 'primary' => null];
    $sql = "SELECT COLUMN_NAME, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = @mysqli_prepare($conn, $sql);
    if (!$stmt) return $out;
    mysqli_stmt_bind_param($stmt, 's', $table);
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return $out; }
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out['all'][] = $row['COLUMN_NAME'];
            if ($row['COLUMN_KEY'] === 'PRI' && $out['primary'] === null) $out['primary'] = $row['COLUMN_NAME'];
        }
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// pick the actual name (case-preserved) of the first candidate found among $existing
function pickColumn($existing, $candidates) {
    foreach ($candidates as $c) {
        foreach ($existing as $e) {
            if (strcasecmp($e, $c) === 0) return $e;
        }
    }
    return null;
}

// does a table exist at all in the current DB?
function tableExists($conn, $table) {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1";
    $stmt = @mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 's', $table);
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return false; }
    mysqli_stmt_store_result($stmt);
    $found = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

// ── find event IDs that fall within the selected month (DB mode) ──────────
// returns ['ids' => [...], 'debug' => [...]] so problems can be diagnosed on-page
function fetchEventIDsForMonthDB($conn, $year, $month) {
    $ids = [];
    $debug = [];

    foreach (['Bookings', 'Events'] as $table) {
        if (!tableExists($conn, $table)) {
            $debug[] = "Table `$table` not found in this database.";
            continue;
        }

        $cols = getColumns($conn, $table);
        if (empty($cols['all'])) {
            $debug[] = "Table `$table` exists but its columns could not be read.";
            continue;
        }

        $dateCol = pickColumn($cols['all'], ['EventDate','event_date','date','EventDateTime','BookingDate','Booking_Date','Event_Date','EventDateTimeLocal','EventDate1']);
        if (!$dateCol) {
            $debug[] = "Table `$table` exists but no recognizable date column was found on it. Columns present: " . implode(', ', $cols['all']);
            continue;
        }

        $hasEventID = pickColumn($cols['all'], ['EventID']) !== null;
        // pick a genuine row-identifier column: prefer the primary key, then common id names
        $idCol = $cols['primary'];
        if (!$idCol) $idCol = pickColumn($cols['all'], ['id','ID','BookingID','Booking_ID','bookingId']);

        if ($hasEventID && $idCol) {
            $idExpr = "COALESCE(`EventID`, `$idCol`)";
        } elseif ($hasEventID) {
            $idExpr = "`EventID`";
        } elseif ($idCol) {
            $idExpr = "`$idCol`";
        } else {
            $debug[] = "Table `$table`: found date column `$dateCol` but no EventID or usable id/primary-key column, so rows can't be identified. Columns present: " . implode(', ', $cols['all']);
            continue;
        }

        $sql = "SELECT $idExpr AS eid FROM `$table` WHERE `$dateCol` IS NOT NULL AND YEAR(`$dateCol`) = ? AND MONTH(`$dateCol`) = ?";
        $stmt = @mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $debug[] = "Query on `$table` failed to prepare: " . mysqli_error($conn);
            continue;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $year, $month);
        if (!mysqli_stmt_execute($stmt)) {
            $debug[] = "Query on `$table` failed to execute: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
            continue;
        }
        $res = mysqli_stmt_get_result($stmt);
        $count = 0;
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                if ($row['eid'] !== null) { $ids[(string)$row['eid']] = true; $count++; }
            }
        }
        mysqli_stmt_close($stmt);
        $debug[] = "Table `$table`: used date column `$dateCol` and id expression $idExpr → matched $count row(s).";
    }

    return ['ids' => array_keys($ids), 'debug' => $debug];
}

// ── find event IDs that fall within the selected month (file-storage mode) ─
function fetchEventIDsForMonthFile($year, $month) {
    $ids = [];
    $candidates = [__DIR__ . '/data/bookings.json', __DIR__ . '/data/events.json'];
    foreach ($candidates as $evFile) {
        if (!file_exists($evFile)) continue;
        $raw = file_get_contents($evFile);
        $list = json_decode($raw, true);
        if (!is_array($list)) continue;
        foreach ($list as $r) {
            $dateStr = isset($r['EventDate']) ? $r['EventDate'] : null;
            if (!$dateStr) continue;
            $ts = strtotime($dateStr);
            if ($ts === false) continue;
            if ((int)date('Y', $ts) === $year && (int)date('n', $ts) === $month) {
                $eid = isset($r['EventID']) ? $r['EventID'] : (isset($r['id']) ? $r['id'] : null);
                if ($eid !== null) $ids[(string)$eid] = true;
            }
        }
    }
    return array_keys($ids);
}

// ── compute consolidated per-employee incentive totals for the month ───────
$incentivePerEmployee = [];
$dbDebug = [];
if ($dbAvailable) {
    $fetchResult = fetchEventIDsForMonthDB($conn, $selYear, $selMonth);
    $eventIDs = $fetchResult['ids'];
    $dbDebug = $fetchResult['debug'];
} else {
    $eventIDs = fetchEventIDsForMonthFile($selYear, $selMonth);
}

if ($dbAvailable && !empty($eventIDs)) {
    $idsSafe = array_map('intval', $eventIDs);
    $idsCsv = implode(',', $idsSafe);
    $sql = "SELECT employee_name, field_key, SUM(amount) AS total FROM FunctionDetailLine WHERE eventID IN ($idsCsv) GROUP BY employee_name, field_key";
    $res = safe_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $fk = $row['field_key'];
            $empName = isset($row['employee_name']) ? trim($row['employee_name']) : '';
            $amt = floatval($row['total']);
            if ($amt <= 0) continue;
            if ($empName === '') continue; // no employee assigned to these rows, so they don't count toward anyone's incentive
            if (in_array($fk, $incentiveKeys, true)) {
                if (!isset($incentivePerEmployee[$empName])) $incentivePerEmployee[$empName] = 0;
                $incentivePerEmployee[$empName] += $amt;
            }
        }
    } elseif ($conn) {
        $dbDebug[] = "FunctionDetailLine query failed: " . mysqli_error($conn);
    }
} elseif (!$dbAvailable && !empty($eventIDs)) {
    $eventIdsSet = array_flip(array_map('strval', $eventIDs));
    $dataDir = __DIR__ . '/data';
    if (is_dir($dataDir)) {
        foreach (glob($dataDir . '/FunctionDetails_event_*.json') as $file) {
            if (!preg_match('/FunctionDetails_event_(.+)\.json$/', basename($file), $m)) continue;
            if (!isset($eventIdsSet[$m[1]])) continue;

            $raw = file_get_contents($file);
            $payload = json_decode($raw, true);
            if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) continue;
            $savedData = $payload['data'];

            foreach ($incentiveKeys as $ik) {
                if (!isset($savedData[$ik]) || !is_array($savedData[$ik])) continue;
                foreach ($savedData[$ik] as $r) {
                    if (!is_array($r)) continue;
                    $emp = isset($r['employee']) ? $r['employee'] : null;
                    $amt = isset($r['amount']) ? floatval($r['amount']) : 0;
                    if ($emp === null || $emp === '' || $amt <= 0) continue;
                    $empLabel = resolveEmpName($emp, $employeeOptions);
                    if (!isset($incentivePerEmployee[$empLabel])) $incentivePerEmployee[$empLabel] = 0;
                    $incentivePerEmployee[$empLabel] += $amt;
                }
            }
        }
    }
}

// keep only positive totals, sort highest first
$incentivePerEmployee = array_filter($incentivePerEmployee, function($v){ return $v > 0; });
arsort($incentivePerEmployee);
$grandTotal = array_sum($incentivePerEmployee);
$eventCountForMonth = count($eventIDs);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Consolidated Incentives - <?php echo htmlspecialchars($monthNames[$selMonth] . ' ' . $selYear); ?></title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial; background: #f5f7fb; color: #222; margin:0; padding:0; }
.container { max-width:800px; margin:30px auto; background:#fff; padding:22px; border-radius:10px; box-shadow:0 10px 30px rgba(12,20,30,0.06); }
h2 { margin-top:0; font-weight:600; color:#0b2545; }
.top-actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.back-link { text-decoration:none; color:#0645ad; background:#eef6ff; padding:8px 12px; border-radius:8px; border:1px solid #d9e9ff; font-weight:600; }
.back-link:hover { background:#dfeeff; }

.filter-form { display:flex; gap:12px; align-items:center; margin-bottom:20px; flex-wrap:wrap; }
.filter-form label { font-weight:600; color:#0b2545; margin-right:4px; }
.filter-form select { padding:8px 10px; border-radius:6px; border:1px solid #d5dce6; background:#fff; font-size:.95rem; }
.event-count { color:#666; font-size:.85rem; margin-bottom:14px; }

table.incentives { border-collapse:collapse; width:100%; font-size:.95rem; }
table.incentives thead th { text-align:left; padding:10px 12px; background:#f0f4ff; color:#0b2545; border-bottom:2px solid #c7d7f5; }
table.incentives thead th.amt { text-align:right; }
table.incentives tbody td { padding:9px 12px; border-bottom:1px solid #efefef; }
table.incentives tbody td.amt { text-align:right; font-variant-numeric:tabular-nums; }
table.incentives tfoot td { padding:11px 12px; font-weight:700; color:#0b2545; background:#f0f4ff; }
table.incentives tfoot td.amt { text-align:right; font-variant-numeric:tabular-nums; }

.empty-msg { color:#888; font-style:italic; padding:16px 0; }

@media (max-width: 620px) {
    .container { margin:14px; padding:14px; }
    .filter-form { flex-direction:column; align-items:stretch; }
}
</style>
</head>
<body>
<div class="container">
<h2>Consolidated Incentives</h2>
<div class="top-actions">
    <a href="listBookingsv2.php" class="back-link">← Back to bookings</a>
</div>

<form method="get" class="filter-form" id="monthForm">
    <label for="month">Month</label>
    <select name="month" id="month" onchange="document.getElementById('monthForm').submit()">
        <?php foreach ($monthNames as $num => $name): ?>
            <option value="<?php echo $num; ?>" <?php echo ($num === $selMonth) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
        <?php endforeach; ?>
    </select>

    <label for="year">Year</label>
    <select name="year" id="year" onchange="document.getElementById('monthForm').submit()">
        <?php for ($y = $currentYear + 1; $y >= $currentYear - 4; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo ($y === $selYear) ? 'selected' : ''; ?>><?php echo $y; ?></option>
        <?php endfor; ?>
    </select>
</form>

<div class="event-count">
    <?php echo $eventCountForMonth; ?> event<?php echo $eventCountForMonth === 1 ? '' : 's'; ?> found for <?php echo htmlspecialchars($monthNames[$selMonth] . ' ' . $selYear); ?>.
</div>

<?php if (empty($incentivePerEmployee)): ?>
    <div class="empty-msg">No incentives recorded for <?php echo htmlspecialchars($monthNames[$selMonth] . ' ' . $selYear); ?>.</div>
<?php else: ?>
    <table class="incentives">
        <thead>
            <tr>
                <th>Employee</th>
                <th class="amt">Total Incentive</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($incentivePerEmployee as $empLabel => $empTotal): ?>
                <tr>
                    <td><?php echo htmlspecialchars($empLabel); ?></td>
                    <td class="amt">₹<?php echo number_format($empTotal, 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>Grand Total</td>
                <td class="amt">₹<?php echo number_format($grandTotal, 2); ?></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

</div>
</body>
</html>