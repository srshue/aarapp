<?php
// salaries.php - simple salary entry and display per employee (monthly salaries, defaults, "others" column)
session_start();

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

$dbAvailable = false;
if (isset($conn) && $conn instanceof mysqli) {
    $dbAvailable = @mysqli_ping($conn) ? true : false;
}

// determine selected period (year-month). prefer GET then POST then current month
$period = '';
if (isset($_GET['period'])) $period = trim($_GET['period']);
if ($period === '' && isset($_POST['period'])) $period = trim($_POST['period']);
if ($period === '') $period = date('Y-m'); // format YYYY-MM
// parse year and month
$parts = explode('-', $period);
$selYear = intval($parts[0] ?? date('Y'));
$selMonth = intval($parts[1] ?? date('n'));

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
    $sample = ['Ravi','Suresh','Anita','Priya','Kumar','Deepa'];
    foreach ($sample as $i => $n) $employeeOptions[] = ['id' => 's' . $i, 'name' => $n];
}

// prepare storage if DB available - create new tables for defaults and monthly salaries
if ($dbAvailable) {
    // new MonthlySalaries
    $createMonths = "CREATE TABLE IF NOT EXISTS MonthlySalaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        EmpID VARCHAR(64) NOT NULL,
        year INT NOT NULL,
        month INT NOT NULL,
        salary DECIMAL(12,2) DEFAULT 0,
        others DECIMAL(12,2) DEFAULT 0,
        advance DECIMAL(12,2) DEFAULT 0,
        deduction DECIMAL(12,2) DEFAULT 0,
        incentives DECIMAL(12,2) DEFAULT 0,
        final_amount DECIMAL(12,2) DEFAULT 0,
        saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_emp_period (EmpID, year, month),
        INDEX (EmpID), INDEX(year,month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createMonths);

    // SalaryDefaults
    $createDefaults = "CREATE TABLE IF NOT EXISTS SalaryDefaults (
        EmpID VARCHAR(64) PRIMARY KEY,
        salary DECIMAL(12,2) DEFAULT 0,
        others DECIMAL(12,2) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createDefaults);

    // keep legacy tables for compatibility
    $createSql = "CREATE TABLE IF NOT EXISTS Salaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        EmpID VARCHAR(64) NOT NULL,
        employee_name VARCHAR(255) DEFAULT NULL,
        salary DECIMAL(12,2) DEFAULT 0,
        advance DECIMAL(12,2) DEFAULT 0,
        deduction DECIMAL(12,2) DEFAULT 0,
        incentives DECIMAL(12,2) DEFAULT 0,
        final_amount DECIMAL(12,2) DEFAULT 0,
        saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY(EmpID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createSql);

    $createAdv = "CREATE TABLE IF NOT EXISTS Advances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        EmpID VARCHAR(64) NOT NULL,
        employee_name VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(12,2) DEFAULT 0,
        note VARCHAR(512) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createAdv);

    $createApp = "CREATE TABLE IF NOT EXISTS SalariesApproval (
        id INT AUTO_INCREMENT PRIMARY KEY,
        approved_by VARCHAR(255) DEFAULT NULL,
        approved_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createApp);
} else {
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
}

// determine current approval state (DB or file)
$approved = false;
$approvedBy = '';
$approvedAt = '';
if ($dbAvailable) {
    $resApp = safe_query($conn, "SELECT approved_by, approved_at FROM SalariesApproval ORDER BY approved_at DESC LIMIT 1");
    if ($resApp && mysqli_num_rows($resApp) > 0) {
        $r = mysqli_fetch_assoc($resApp);
        if (!empty($r['approved_at'])) {
            $approved = true;
            $approvedBy = $r['approved_by'];
            $approvedAt = $r['approved_at'];
        }
    }
} else {
    $metaFile = __DIR__ . '/data/salaries_meta.json';
    if (file_exists($metaFile)) {
        $raw = file_get_contents($metaFile);
        $meta = json_decode($raw, true);
        if (is_array($meta) && !empty($meta['approved_at'])) {
            $approved = true;
            $approvedBy = isset($meta['approved_by']) ? $meta['approved_by'] : '';
            $approvedAt = isset($meta['approved_at']) ? $meta['approved_at'] : '';
        }
    }
}

// helpers: privileged users who can edit defaults and override read-only defaults
$privilegedUsers = ['Suresh','Anu','Rajesh']; // case-insensitive
$currUser = isset($_SESSION['username']) ? trim($_SESSION['username']) : '';
$isPrivileged = false;
foreach ($privilegedUsers as $p) if (strcasecmp($currUser, $p) === 0) { $isPrivileged = true; break; }

// handle save and approve
$message = '';
$messageIsError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';
    // period posted should be used
    $postedPeriod = isset($_POST['period']) ? $_POST['period'] : $period;
    $pparts = explode('-', $postedPeriod);
    $postYear = intval($pparts[0] ?? date('Y'));
    $postMonth = intval($pparts[1] ?? date('n'));

    if ($action === 'approve') {
        if (!$isPrivileged) {
            $message = 'You are not authorized to approve.';
            $messageIsError = true;
        } else {
            // record approval (DB or file)
            if ($dbAvailable) {
                $ins = mysqli_prepare($conn, "INSERT INTO SalariesApproval (approved_by, approved_at) VALUES (?, NOW())");
                if ($ins) {
                    mysqli_stmt_bind_param($ins, 's', $currUser);
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);
                } else {
                    $message = 'Approval failed: ' . mysqli_error($conn);
                    $messageIsError = true;
                }
            } else {
                $metaFile = __DIR__ . '/data/salaries_meta.json';
                $meta = ['approved_by' => $currUser, 'approved_at' => date('c')];
                file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));
            }
            if (!$messageIsError) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?period=' . urlencode($postedPeriod));
                exit;
            }
        }
    } elseif ($action === 'save') {
        // prevent saving if already approved
        if ($approved) {
            $message = 'Salaries have been approved; further edits are not allowed.';
            $messageIsError = true;
        } else {
            $posted = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : [];
            // load advances totals for this period (advances are not per month here)
            $advTotals = [];
            if ($dbAvailable) {
                $advRes = safe_query($conn, "SELECT EmpID, SUM(amount) AS total_adv FROM Advances GROUP BY EmpID");
                if ($advRes) {
                    while ($ar = mysqli_fetch_assoc($advRes)) {
                        $advTotals[$ar['EmpID']] = $ar['total_adv'];
                    }
                }
            } else {
                $advFile = __DIR__ . '/data/advances.json';
                if (file_exists($advFile)) {
                    $raw = file_get_contents($advFile);
                    $alist = json_decode($raw, true);
                    if (is_array($alist)) {
                        foreach ($alist as $entry) {
                            if (!isset($entry['EmpID'])) continue;
                            $eid = $entry['EmpID']; $amt = isset($entry['amount']) ? floatval($entry['amount']) : 0;
                            if (!isset($advTotals[$eid])) $advTotals[$eid] = 0;
                            $advTotals[$eid] += $amt;
                        }
                    }
                }
            }

            if ($dbAvailable) {
                mysqli_begin_transaction($conn);
                $ok = true; $errorMsg = '';

                $upsertSql = "INSERT INTO MonthlySalaries (EmpID, year, month, salary, others, advance, deduction, incentives, final_amount, saved_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE salary = VALUES(salary), others = VALUES(others), advance = VALUES(advance),
                                deduction = VALUES(deduction), incentives = VALUES(incentives), final_amount = VALUES(final_amount), saved_at = NOW()";
                $upsert = mysqli_prepare($conn, $upsertSql);
                if (!$upsert) { $ok = false; $errorMsg = 'Prepare failed (upsert): ' . mysqli_error($conn); }

                // fetch defaults to enforce server-side read-only behavior for non-privileged users
                $defaults = [];
                $defRes = safe_query($conn, "SELECT EmpID, salary, others FROM SalaryDefaults");
                if ($defRes) {
                    while ($dr = mysqli_fetch_assoc($defRes)) $defaults[$dr['EmpID']] = $dr;
                }

                if ($ok) {
                    foreach ($posted as $empID => $r) {
                        $empID = trim($empID);
                        if ($empID === '') continue;
                        // posted values
                        $salaryPosted = isset($r['salary']) && $r['salary'] !== '' ? floatval(str_replace(',', '', $r['salary'])) : 0;
                        $othersPosted = isset($r['others']) && $r['others'] !== '' ? floatval(str_replace(',', '', $r['others'])) : 0;
                        $deduction = isset($r['deduction']) && $r['deduction'] !== '' ? floatval(str_replace(',', '', $r['deduction'])) : 0;
                        $incentives = isset($r['incentives']) && $r['incentives'] !== '' ? floatval(str_replace(',', '', $r['incentives'])) : 0;

                        // does monthly row exist already?
                        $stmt = mysqli_prepare($conn, "SELECT id FROM MonthlySalaries WHERE EmpID = ? AND year = ? AND month = ? LIMIT 1");
                        $exists = false;
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, 'sii', $empID, $postYear, $postMonth);
                            if (mysqli_stmt_execute($stmt)) {
                                mysqli_stmt_store_result($stmt);
                                $exists = (mysqli_stmt_num_rows($stmt) > 0);
                            } else {
                                $ok = false; $errorMsg = 'Exist-check failed: ' . mysqli_error($conn);
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            $ok = false; $errorMsg = 'Exist-check prepare failed: ' . mysqli_error($conn);
                        }
                        if (!$ok) break;

                        // server-side enforcement:
                        if (!$exists && !$isPrivileged) {
                            // force salary/others to defaults (do not allow non-privileged user to create a monthly salary changing defaults)
                            $salary = isset($defaults[$empID]) ? floatval($defaults[$empID]['salary']) : $salaryPosted;
                            $others = isset($defaults[$empID]) ? floatval($defaults[$empID]['others']) : $othersPosted;
                        } else {
                            // allow posted values (exists OR privileged)
                            $salary = $salaryPosted;
                            $others = $othersPosted;
                        }

                        $advanceRemaining = isset($advTotals[$empID]) ? floatval($advTotals[$empID]) : 0;
                        // final formula per request: final = salary - deduction + incentives + others
                        $final = $salary - $deduction + $incentives + $others;

                        // bind & execute
                        if (!mysqli_stmt_bind_param($upsert, 'siidddddd', $empID, $postYear, $postMonth, $salary, $others, $advanceRemaining, $deduction, $incentives, $final)) {
                            $ok = false; $errorMsg = 'Bind failed: ' . mysqli_error($conn); break;
                        }
                        if (!mysqli_stmt_execute($upsert)) {
                            $ok = false; $errorMsg = 'Execute failed: ' . mysqli_error($conn); break;
                        }
                    } // end foreach posted rows
                }

                if ($upsert) mysqli_stmt_close($upsert);

                if ($ok) {
                    mysqli_commit($conn);
                    // redirect to preserve period in URL so the month selection persists
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?period=' . urlencode($postedPeriod));
                    exit;
                } else {
                    mysqli_rollback($conn);
                    $message = 'Save failed: ' . ($errorMsg ? $errorMsg : 'unknown');
                    $messageIsError = true;
                }
            } else {
                // file fallback: save monthly salaries into data/monthly_salaries_{YYYY_MM}.json
                $storageDir = __DIR__ . '/data';
                if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
                $filePath = $storageDir . '/monthly_salaries_' . sprintf('%04d_%02d', $postYear, $postMonth) . '.json';
                $payload = ['period' => sprintf('%04d-%02d', $postYear, $postMonth), 'saved_at' => date('c'), 'data' => $posted];
                if (file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT))) {
                    // redirect so the period stays in the URL
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?period=' . urlencode($postedPeriod));
                    exit;
                } else {
                    $message = 'Failed to save to file.';
                    $messageIsError = true;
                }
            }
        }
    }
}

// reload approval state (in case it changed)
if ($dbAvailable) {
    $resApp = safe_query($conn, "SELECT approved_by, approved_at FROM SalariesApproval ORDER BY approved_at DESC LIMIT 1");
    if ($resApp && mysqli_num_rows($resApp) > 0) {
        $r = mysqli_fetch_assoc($resApp);
        if (!empty($r['approved_at'])) {
            $approved = true; $approvedBy = $r['approved_by']; $approvedAt = $r['approved_at'];
        }
    }
} else {
    $metaFile = __DIR__ . '/data/salaries_meta.json';
    if (file_exists($metaFile)) {
        $raw = file_get_contents($metaFile);
        $meta = json_decode($raw, true);
        if (is_array($meta) && !empty($meta['approved_at'])) { $approved = true; $approvedBy = isset($meta['approved_by']) ? $meta['approved_by'] : ''; $approvedAt = isset($meta['approved_at']) ? $meta['approved_at'] : ''; }
    }
}

// load existing monthly data for selected period (or defaults)
$saved = []; // per-employee monthly row if present
$defaults = [];
$advTotals = [];
if ($dbAvailable) {
    $stmt = mysqli_prepare($conn, "SELECT EmpID, salary, others, deduction, incentives, final_amount FROM MonthlySalaries WHERE year = ? AND month = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $selYear, $selMonth);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $saved[$r['EmpID']] = $r;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }

    $defRes = safe_query($conn, "SELECT EmpID, salary, others FROM SalaryDefaults");
    if ($defRes) {
        while ($dr = mysqli_fetch_assoc($defRes)) $defaults[$dr['EmpID']] = $dr;
    }

    // load advances totals per employee (for display)
    $advRes = safe_query($conn, "SELECT EmpID, SUM(amount) AS total_adv FROM Advances GROUP BY EmpID");
    if ($advRes) {
        while ($ar = mysqli_fetch_assoc($advRes)) { $advTotals[$ar['EmpID']] = $ar['total_adv']; }
    }
} else {
    // file fallback for monthly file
    $filePath = __DIR__ . '/data/monthly_salaries_' . sprintf('%04d_%02d', $selYear, $selMonth) . '.json';
    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        $p = json_decode($raw, true);
        if (is_array($p) && isset($p['data'])) {
            foreach ($p['data'] as $eid => $r) {
                $saved[$eid] = [
                    'salary' => isset($r['salary']) ? $r['salary'] : 0,
                    'others' => isset($r['others']) ? $r['others'] : 0,
                    'deduction' => isset($r['deduction']) ? $r['deduction'] : 0,
                    'incentives' => isset($r['incentives']) ? $r['incentives'] : 0,
                    'final_amount' => isset($r['final']) ? $r['final'] : 0
                ];
            }
        }
    }
    // defaults file
    $defFile = __DIR__ . '/data/salary_defaults.json';
    if (file_exists($defFile)) {
        $raw = file_get_contents($defFile);
        $p = json_decode($raw, true);
        if (is_array($p)) foreach ($p as $d) if (isset($d['EmpID'])) $defaults[$d['EmpID']] = $d;
    }
    // advances file
    $advFile = __DIR__ . '/data/advances.json';
    if (file_exists($advFile)) {
        $raw = file_get_contents($advFile);
        $alist = json_decode($raw, true);
        if (is_array($alist)) {
            foreach ($alist as $entry) {
                if (!isset($entry['EmpID'])) continue;
                $eid = $entry['EmpID']; $amt = isset($entry['amount']) ? floatval($entry['amount']) : 0;
                if (!isset($advTotals[$eid])) $advTotals[$eid] = 0;
                $advTotals[$eid] += $amt;
            }
        }
    }
}

// render page
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Salaries - <?php echo htmlspecialchars(sprintf('%04d-%02d', $selYear, $selMonth)); ?></title>
<style>
body{font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial; background:#f5f7fb; color:#222}
.container{max-width:1100px;margin:30px auto;background:#fff;padding:20px;border-radius:8px}
table{width:100%;border-collapse:collapse}
th,td{padding:8px;border-bottom:1px solid #efefef;text-align:left}
input[type=text]{padding:6px;border:1px solid #d5dce6;border-radius:6px;width:110px}
input[type=month]{padding:6px;border:1px solid #d5dce6;border-radius:6px}
.btn{background:#0563c1;color:#fff;padding:8px 12px;border-radius:6px;border:none;cursor:pointer}
.note{display:inline-block;padding:8px 12px;border-radius:6px;margin-bottom:12px}
.note.error{background:#fff0f0;color:#a62b2b;border:1px solid #ffd6d8}
.note.ok{background:#e9f7ef;color:#186a3b;border:1px solid #cfead5}
.readonly { background:#f3f4f6; }
.period-form { display:inline-block; margin-right:12px; }
</style>
</head>
<body>
<div class="container">
<h2 style="display:inline-block;">Salaries — <?php echo htmlspecialchars(sprintf('%04d-%02d', $selYear, $selMonth)); ?></h2>

<?php if ($message) { $cls = $messageIsError ? 'note error' : 'note ok'; echo '<div class="'.$cls.'">'.htmlspecialchars($message).'</div>'; } ?>
<?php if ($approved): ?>
    <div class="note">Approved by <?php echo htmlspecialchars($approvedBy); ?> on <?php echo htmlspecialchars($approvedAt); ?></div>
<?php endif; ?>

<!-- Separate GET form for period selection so the month appears in the URL and persists -->
<form method="get" class="period-form" style="margin-top:12px;">
    <label for="period">Period (year-month): </label>
    <input type="month" id="period" name="period" value="<?php echo htmlspecialchars(sprintf('%04d-%02d', $selYear, $selMonth)); ?>">
    <button type="submit" class="btn" style="margin-left:8px;">Load</button>
</form>

<!-- Main POST form for save/approve -->
<form method="post" style="margin-top:12px;">
<input type="hidden" name="period" value="<?php echo htmlspecialchars(sprintf('%04d-%02d', $selYear, $selMonth)); ?>">

<table>
<thead>
<tr><th>Employee</th><th>Salary</th><th>Others</th><th>Advance remaining</th><th>Deduction this month</th><th>Incentives</th><th>Final amount</th></tr>
</thead>
<tbody>
<?php foreach ($employeeOptions as $opt):
    $id = $opt['id']; $name = $opt['name'];
    $row = isset($saved[$id]) ? $saved[$id] : null;
    $isDefaultInUse = !isset($saved[$id]); // if no monthly row, default applies
    $salary = $row ? $row['salary'] : (isset($defaults[$id]) ? $defaults[$id]['salary'] : '');
    $others = $row ? $row['others'] : (isset($defaults[$id]) ? $defaults[$id]['others'] : '');
    $advanceRemaining = isset($advTotals[$id]) ? $advTotals[$id] : 0;
    $deduction = $row ? (isset($row['deduction']) ? $row['deduction'] : '') : '';
    $incentives = $row ? (isset($row['incentives']) ? $row['incentives'] : '') : '';
    $final = $row ? (isset($row['final_amount']) ? $row['final_amount'] : '') : '';
    // readonly salary/others if using default and user is not privileged
    $salaryReadonly = ($isDefaultInUse && !$isPrivileged) ? 'readonly' : '';
    $othersReadonly = ($isDefaultInUse && !$isPrivileged) ? 'readonly' : '';
    $salaryClass = ($salaryReadonly) ? 'readonly' : '';
    $othersClass = ($othersReadonly) ? 'readonly' : '';
    $readonlyAttr = $approved ? 'readonly' : ''; // if approved, disable saves
?>
<tr>
    <td>
        <?php echo htmlspecialchars($name); ?>
        <input type="hidden" name="rows[<?php echo htmlspecialchars($id); ?>][name]" value="<?php echo htmlspecialchars($name); ?>">
    </td>
    <td>
        <input type="text" class="num <?php echo $salaryClass; ?>" data-field="salary" name="rows[<?php echo htmlspecialchars($id); ?>][salary]" value="<?php echo htmlspecialchars($salary); ?>" <?php echo ($salaryReadonly ? 'readonly' : ''); ?> <?php echo $readonlyAttr; ?>>
    </td>
    <td>
        <input type="text" class="num <?php echo $othersClass; ?>" data-field="others" name="rows[<?php echo htmlspecialchars($id); ?>][others]" value="<?php echo htmlspecialchars($others); ?>" <?php echo ($othersReadonly ? 'readonly' : ''); ?> <?php echo $readonlyAttr; ?>>
    </td>
    <td>
        <input type="text" class="num" data-field="advance_remaining" name="rows[<?php echo htmlspecialchars($id); ?>][advance_remaining]" value="<?php echo htmlspecialchars(number_format($advanceRemaining,2)); ?>" readonly>
    </td>
    <td>
        <input type="text" class="num" data-field="deduction" name="rows[<?php echo htmlspecialchars($id); ?>][deduction]" value="<?php echo htmlspecialchars($deduction); ?>" <?php echo $readonlyAttr; ?>>
    </td>
    <td>
        <input type="text" class="num" data-field="incentives" name="rows[<?php echo htmlspecialchars($id); ?>][incentives]" value="<?php echo htmlspecialchars($incentives); ?>" <?php echo $readonlyAttr; ?>>
    </td>
    <td>
        <input type="text" class="num final" name="rows[<?php echo htmlspecialchars($id); ?>][final]" value="<?php echo htmlspecialchars($final); ?>" readonly>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<p style="margin-top:12px;">
    <button type="submit" name="action" value="save" class="btn" <?php if ($approved) echo 'disabled'; ?>>Save</button>
    <button type="button" class="btn" onclick="window.open('advances.php','Advances','width=800,height=600')" style="margin-left:8px;background:#8b5cf6;">Manage Advances</button>
    <?php if ($isPrivileged && !$approved): ?>
        <button type="submit" name="action" value="approve" class="btn" style="background:#0b8b3a;margin-left:8px;">Approve</button>
    <?php endif; ?>
</p>
</form>
</div>

<script>
function parseNum(v){ if (v === null || v === undefined) return 0; v = String(v).replace(/,/g, ''); var f = parseFloat(v); return isNaN(f) ? 0 : f; }
function fmt(v){ return (Math.round((v + Number.EPSILON) * 100) / 100).toFixed(2); }
window.addEventListener('DOMContentLoaded', function(){
    function recomputeRow(row){
        var salary = parseNum(row.querySelector('input[data-field="salary"]').value);
        var others = parseNum(row.querySelector('input[data-field="others"]').value);
        var deduction = parseNum(row.querySelector('input[data-field="deduction"]').value);
        var incentives = parseNum(row.querySelector('input[data-field="incentives"]').value);
        // requested final: salary - deduction + incentives + others
        var final = salary - deduction + incentives + others;
        var finInput = row.querySelector('input.final');
        if (finInput) finInput.value = fmt(final);
    }
    var rows = document.querySelectorAll('tbody tr');
    rows.forEach(function(r){
        r.querySelectorAll('input.num').forEach(function(i){
            i.addEventListener('input', function(){ recomputeRow(r); });
        });
        recomputeRow(r);
    });
});
</script>
</body>
</html>