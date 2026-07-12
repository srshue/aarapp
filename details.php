<?php
// details.php - Function details for an event
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

// check if current user is privileged (Suresh, Anu, Rajesh)
$currUser = isset($_SESSION['username']) ? trim($_SESSION['username']) : '';
$isPrivileged = false;
$privilegedUsers = ['Suresh', 'Anu', 'Rajesh'];
foreach ($privilegedUsers as $p) {
    if (strcasecmp($currUser, $p) === 0) { $isPrivileged = true; break; }
}

// DJ Charges pricing table (shown as a reference next to each type in the dropdown)
$djChargesPrices = [
    'Basic' => 7500,
    'Extra base' => 11000,
    'Extra Dj console' => 13000,
    'Honey comb setup' => 15000
];

// get eventID from GET or POST
$eventID = 0;
if (isset($_GET['eventID'])) $eventID = intval($_GET['eventID']);
if (isset($_POST['eventID'])) $eventID = intval($_POST['eventID']);

// handle DJ Charges pricing update (privileged users only)
$djUpdateMsg = '';
$djUpdateError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dj_prices']) && $isPrivileged) {
    // Update pricing
    foreach ($djChargesPrices as $key => &$price) {
        $inputKey = 'dj_price_' . str_replace(' ', '_', strtolower($key));
        if (isset($_POST[$inputKey])) {
            $newPrice = floatval(str_replace(',', '', $_POST[$inputKey]));
            if ($newPrice > 0) {
                $price = $newPrice;
            }
        }
    }
    // Save to file or DB (for simplicity, using file storage)
    $storageDir = __DIR__ . '/data';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
    $filePath = $storageDir . '/dj_charges_config.json';
    if (file_put_contents($filePath, json_encode($djChargesPrices, JSON_PRETTY_PRINT))) {
        $djUpdateMsg = 'DJ Charges pricing updated successfully.';
        $djUpdateError = false;
    } else {
        $djUpdateMsg = 'Failed to save DJ Charges pricing.';
        $djUpdateError = true;
    }
}

// Load DJ Charges from file if exists (override defaults)
$storageDir = __DIR__ . '/data';
if (is_dir($storageDir)) {
    $filePath = $storageDir . '/dj_charges_config.json';
    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        $loaded = json_decode($raw, true);
        if (is_array($loaded)) {
            $djChargesPrices = $loaded;
        }
    }
}

// debug helper: force-set booking date for testing when ?force_set_date=1
if (isset($_GET['force_set_date']) && $_GET['force_set_date'] == '1' && isset($eventID) && $eventID > 0 && isset($conn) && $dbAvailable) {
    $newStart = '2026-06-10 16:15:12';
    $newEnd = '2026-06-10 21:15:12';
    $upd = @mysqli_prepare($conn, "UPDATE Bookings SET EventDate = ?, `end` = ? WHERE id = ? OR EventID = ?");
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'ssii', $newStart, $newEnd, $eventID, $eventID);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
    // also update payment due dates for this event (if table/columns exist)
    $newDue = '2026-06-10';
    $upd2 = @mysqli_prepare($conn, "UPDATE PaymentDueDates SET DueDate = ? WHERE eventid = ?");
    if ($upd2) {
        mysqli_stmt_bind_param($upd2, 'si', $newDue, $eventID);
        mysqli_stmt_execute($upd2);
        mysqli_stmt_close($upd2);
    }
    // attempt updating DueDateTime or DueDatetime column
    $upd3 = @mysqli_prepare($conn, "UPDATE PaymentDueDates SET DueDateTime = ? WHERE eventid = ?");
    if ($upd3) {
        mysqli_stmt_bind_param($upd3, 'si', $newStart, $eventID);
        mysqli_stmt_execute($upd3);
        mysqli_stmt_close($upd3);
    }
    // redirect back to details to reflect change
    header('Location: ' . $_SERVER['PHP_SELF'] . '?eventID=' . urlencode($eventID));
    exit;
}

// fields to capture
$fields = [
    'decor_amount' => 'Decor Amount',
    'real_flower' => 'Real flower',
    'banner' => 'Banner Printing',
    'outside_labour_1' => 'Outside labour 1',
    'outside_labour_2' => 'Outside labour 2',
    'dj_charges' => 'DJ Charges',
    'fixing' => 'Banner fixing',
    'removal' => 'Removal: 200 or 400',
    'aarna_labor' => 'AARNA labor',
    'balloon' => 'Baloon',
    'noble_bose' => 'Noble/bose',
    'miscellaneous' => 'Miscellaneous',
    'night_stay_person_1' => 'Night stay person 1',
    'night_stay_person_2' => 'Night stay person 2',
    'night_stay_electrician' => 'Night stay electrician',
    'watchman_stay' => 'Watchman stay',
    'wedding_pillars_person1' => 'Wedding pillars person1',
    'wedding_pillars_person2' => 'Wedding pillars person2',
    'wedding_pillars_person3' => 'Wedding pillars person3',
    'wedding_pillars_person4' => 'Wedding pillars person4',
    'real_flower_purchase' => 'Real flower purchase',
    'real_flower_fixing_pillar' => 'Real flower fixing for Wedding pillar',
    'WB' => 'Water Bottle',
    'WC' => 'Vessels',
    'GC' => 'Gas Cylinder',
    'NM' => 'Nadhaswaram',
    'B' => 'Booking',
    'LT' => 'Leaf Taking',
    'total_expenses' => 'Total expenses',
    'profit' => 'Profit',
    'incentives' => 'Incentives'
];

// try to load employee list from DB, else from data/employees.json, else sample
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

// create storage table if DB available; otherwise ensure data dir exists for file storage
if ($dbAvailable) {
    $createSql = "CREATE TABLE IF NOT EXISTS FunctionDetails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        eventID INT NOT NULL,
        data LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createSql);

    // normalized table for reporting: one row per event-field-employee
    $createSql2 = "CREATE TABLE IF NOT EXISTS FunctionDetailLine (
        id INT AUTO_INCREMENT PRIMARY KEY,
        eventID INT NOT NULL,
        field_key VARCHAR(120) NOT NULL,
        employeeID VARCHAR(64) DEFAULT NULL,
        employee_name VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(12,2) DEFAULT 0,
        saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(eventID),
        INDEX(field_key),
        INDEX(employeeID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    safe_query($conn, $createSql2);
} else {
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
}

// handle save
$message = '';
$messageIsError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_dj_prices'])) {
    $postedEmployees = isset($_POST['employees']) ? $_POST['employees'] : [];
    $postedAmounts = isset($_POST['amounts']) ? $_POST['amounts'] : [];
    $postedDJTypes = isset($_POST['dj_type_selector']) ? $_POST['dj_type_selector'] : [];

    $save = [];
    foreach ($fields as $key => $label) {
        $rows = [];

        if ($key === 'dj_charges') {
            // Type + employee dropdowns, but the amount is always whatever the user typed
            // (no auto-fill from the pricing table).
            $empArr = isset($postedEmployees[$key]) && is_array($postedEmployees[$key]) ? $postedEmployees[$key] : [];
            $amtArr = isset($postedAmounts[$key]) && is_array($postedAmounts[$key]) ? $postedAmounts[$key] : [];
            $typeArr = isset($postedDJTypes) && is_array($postedDJTypes) ? array_values($postedDJTypes) : [];

            $count = max(count($empArr), count($amtArr), count($typeArr), 1);
            for ($i = 0; $i < $count; $i++) {
                $djType = isset($typeArr[$i]) && $typeArr[$i] !== '' ? htmlspecialchars($typeArr[$i]) : null;
                $emp = isset($empArr[$i]) && $empArr[$i] !== '' ? htmlspecialchars($empArr[$i]) : null;
                $amtVal = isset($amtArr[$i]) && $amtArr[$i] !== '' ? floatval(str_replace(',', '', $amtArr[$i])) : 0;
                // skip rows with no positive amount
                if ($amtVal <= 0) continue;
                $rows[] = ['dj_type' => $djType, 'employee' => $emp, 'amount' => $amtVal];
            }
        } else {
            $empArr = isset($postedEmployees[$key]) && is_array($postedEmployees[$key]) ? $postedEmployees[$key] : [];
            $amtArr = isset($postedAmounts[$key]) && is_array($postedAmounts[$key]) ? $postedAmounts[$key] : [];

            $count = max(count($empArr), count($amtArr), 1);
            for ($i = 0; $i < $count; $i++) {
                $emp = isset($empArr[$i]) && $empArr[$i] !== '' ? htmlspecialchars($empArr[$i]) : null;
                $amtVal = isset($amtArr[$i]) && $amtArr[$i] !== '' ? floatval(str_replace(',', '', $amtArr[$i])) : 0;
                // skip rows with no positive amount
                if ($amtVal <= 0) continue;
                $rows[] = ['employee' => $emp, 'amount' => $amtVal];
            }
        }

        // support legacy single-value saved format
        if (empty($rows) && isset($postedEmployees[$key]) && !is_array($postedEmployees[$key]) && isset($postedAmounts[$key])) {
            $legacyAmt = floatval(str_replace(',', '', $postedAmounts[$key]));
            if ($legacyAmt > 0) {
                $rows[] = ['employee' => htmlspecialchars($postedEmployees[$key]), 'amount' => $legacyAmt];
            }
        }

        $save[$key] = $rows;
    }

    // auto-compute total_expenses as sum of all expense fields (excludes decor_amount, dj_charges, total_expenses, profit, incentives)
    $expenseKeys = [
        'real_flower','banner','outside_labour_1','outside_labour_2','fixing','removal','aarna_labor',
        'balloon','noble_bose','miscellaneous',
        'night_stay_person_1','night_stay_person_2','night_stay_electrician','watchman_stay',
        'wedding_pillars_person1','wedding_pillars_person2','wedding_pillars_person3','wedding_pillars_person4',
        'real_flower_purchase','real_flower_fixing_pillar',
        'WB','WC','GC','NM','B','LT'
    ];
    $computedTotal = 0;
    foreach ($expenseKeys as $ek) {
        if (isset($save[$ek]) && is_array($save[$ek])) {
            foreach ($save[$ek] as $r) {
                $computedTotal += isset($r['amount']) ? floatval($r['amount']) : 0;
            }
        }
    }
    $save['total_expenses'] = [['employee' => null, 'amount' => $computedTotal]];

    // auto-compute profit = decor_amount - total_expenses (DJ charges NOT deducted)
    $decorAmt = 0;
    if (isset($save['decor_amount']) && is_array($save['decor_amount'])) {
        foreach ($save['decor_amount'] as $r) $decorAmt += isset($r['amount']) ? floatval($r['amount']) : 0;
    }
    $computedProfit = $decorAmt - $computedTotal;
    $save['profit'] = [['employee' => null, 'amount' => $computedProfit]];

    // capture per-field notes (stored separately from the row data so it doesn't
    // interfere with the normalized FunctionDetailLine insert loop below)
    $postedNotes = isset($_POST['notes']) && is_array($_POST['notes']) ? $_POST['notes'] : [];
    $fieldNotes = [];
    foreach ($fields as $key => $label) {
        if (isset($postedNotes[$key])) {
            $noteVal = trim($postedNotes[$key]);
            if ($noteVal !== '') $fieldNotes[$key] = $noteVal;
        }
    }
    $save['__field_notes'] = $fieldNotes;

    // validate event date: do not allow save if event date is more than 2 days past
    $preventSave = false;
    if ($eventID > 0) {
        $eventDateStr = null;
        $endDateStr = null;
        $candidates = ['event_date','EventDate','date','EventDateTime','BookingDate','Booking_Date','Event_Date','EventDateTimeLocal','EventDate1'];

        // exempt certain users from the date cutoff validation
        $skipValidationForUser = true;
        $exemptUsers = ['Suresh', 'Anu'];
        if (isset($_SESSION['username'])) {
            $currUser = trim($_SESSION['username']);
            foreach ($exemptUsers as $eu) {
                if (strcasecmp($currUser, $eu) === 0) { $skipValidationForUser = true; break; }
            }
        }

        if (!$skipValidationForUser) {
            if ($dbAvailable) {
                // Query Bookings explicitly using EventID first (Booking rows often use EventID)
                $stmt = @mysqli_prepare($conn, "SELECT EventDate, `end` FROM Bookings WHERE EventID = ? LIMIT 1");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $eventID);
                    if (mysqli_stmt_execute($stmt)) {
                        $res = mysqli_stmt_get_result($stmt);
                        if ($res && mysqli_num_rows($res) > 0) {
                            $row = mysqli_fetch_assoc($res);
                            $eventDateStr = isset($row['EventDate']) ? $row['EventDate'] : null;
                            $endDateStr = isset($row['end']) ? $row['end'] : null;
                        }
                    }
                    mysqli_stmt_close($stmt);
                }

                // fallback: try selecting by id if EventID lookup failed
                if ($eventDateStr === null && $endDateStr === null) {
                    $stmt = @mysqli_prepare($conn, "SELECT EventDate, `end` FROM Bookings WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'i', $eventID);
                        if (mysqli_stmt_execute($stmt)) {
                            $res = mysqli_stmt_get_result($stmt);
                            if ($res && mysqli_num_rows($res) > 0) {
                                $row = mysqli_fetch_assoc($res);
                                $eventDateStr = isset($row['EventDate']) ? $row['EventDate'] : null;
                                $endDateStr = isset($row['end']) ? $row['end'] : null;
                            }
                        }
                        mysqli_stmt_close($stmt);
                    }
                }

                // if still not found, try Events table as a last resort
                if ($eventDateStr === null && $endDateStr === null) {
                    $stmt = @mysqli_prepare($conn, "SELECT EventDate, `end` FROM Events WHERE EventID = ? OR id = ? LIMIT 1");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'ii', $eventID, $eventID);
                        if (mysqli_stmt_execute($stmt)) {
                            $res = mysqli_stmt_get_result($stmt);
                            if ($res && mysqli_num_rows($res) > 0) {
                                $row = mysqli_fetch_assoc($res);
                                $eventDateStr = isset($row['EventDate']) ? $row['EventDate'] : null;
                                $endDateStr = isset($row['end']) ? $row['end'] : null;
                            }
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
            } else {
                // file-based fallback
                $evFileB = __DIR__ . '/data/bookings.json';
                if (file_exists($evFileB)) {
                    $raw = file_get_contents($evFileB);
                    $list = json_decode($raw, true);
                    if (is_array($list)) {
                        foreach ($list as $r) {
                            if ((isset($r['id']) && $r['id'] == $eventID) || (isset($r['EventID']) && $r['EventID'] == $eventID)) {
                                if (isset($r['EventDate'])) $eventDateStr = $r['EventDate'];
                                if (isset($r['end'])) $endDateStr = $r['end'];
                                break;
                            }
                        }
                    }
                }

                if ($eventDateStr === null) {
                    $evFile = __DIR__ . '/data/events.json';
                    if (file_exists($evFile)) {
                        $raw = file_get_contents($evFile);
                        $list = json_decode($raw, true);
                        if (is_array($list)) {
                            foreach ($list as $r) {
                                if ((isset($r['id']) && $r['id'] == $eventID) || (isset($r['EventID']) && $r['EventID'] == $eventID)) {
                                    if (isset($r['EventDate'])) $eventDateStr = $r['EventDate'];
                                    if (isset($r['end'])) $endDateStr = $r['end'];
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // Now perform validation using the function end datetime if available, otherwise event start
            if ($endDateStr) {
                $endTs = strtotime($endDateStr);
                if ($endTs !== false) {
                    $cutoff = $endTs + (2 * 24 * 60 * 60); // end + 2 days
                    if (time() > $cutoff) {
                        $preventSave = true;
                        $message = 'Save blocked: function end date/time (' . htmlspecialchars($endDateStr) . ') is more than two days past.';
                    }
                }
            } elseif ($eventDateStr) {
                $startTs = strtotime($eventDateStr);
                if ($startTs !== false) {
                    $cutoff = $startTs + (2 * 24 * 60 * 60);
                    if (time() > $cutoff) {
                        $preventSave = true;
                        $message = 'Save blocked: event start date/time (' . htmlspecialchars($eventDateStr) . ') is more than two days past.';
                    }
                }
            }
        } // end if not skipValidationForUser
    }

    if ($preventSave) {
        // skip DB/file save and show message
        $messageIsError = true;
    } else {
        if ($dbAvailable) {
            // Save JSON payload and normalized rows in a transaction
            mysqli_begin_transaction($conn);
            $json = json_encode($save);
            $ok = true;

            $stmt = mysqli_prepare($conn, "INSERT INTO FunctionDetails (eventID, data) VALUES (?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $eventID, $json);
                if (!mysqli_stmt_execute($stmt)) {
                    $ok = false;
                    $errorMsg = mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $ok = false;
                $errorMsg = mysqli_error($conn);
            }

            if ($ok) {
                // remove existing normalized rows for this event and insert fresh ones
                $del = mysqli_prepare($conn, "DELETE FROM FunctionDetailLine WHERE eventID = ?");
                if ($del) {
                    mysqli_stmt_bind_param($del, 'i', $eventID);
                    mysqli_stmt_execute($del);
                    mysqli_stmt_close($del);
                }

                // prepare two insert statements: one that includes employeeID, and one that omits it (so DB will store NULL)
                $insWithEmp = mysqli_prepare($conn, "INSERT INTO FunctionDetailLine (eventID, field_key, employeeID, employee_name, amount, saved_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $insNoEmp = mysqli_prepare($conn, "INSERT INTO FunctionDetailLine (eventID, field_key, employee_name, amount, saved_at) VALUES (?, ?, ?, ?, NOW())");
                if ($insWithEmp || $insNoEmp) {
                    foreach ($save as $fieldKey => $rows) {
                        if ($fieldKey === '__field_notes') continue;
                        if (!is_array($rows)) continue;
                        foreach ($rows as $r) {
                            $emp = isset($r['employee']) ? $r['employee'] : null;
                            $amt = isset($r['amount']) ? $r['amount'] : 0;
                            // try to resolve employee name from options
                            $empName = '';
                            foreach ($employeeOptions as $eo) {
                                if ((string)$eo['id'] === (string)$emp || (string)$eo['name'] === (string)$emp) { $empName = $eo['name']; break; }
                            }

                            if ($emp === null || $emp === '') {
                                // use statement that omits employeeID so DB will store NULL
                                if ($insNoEmp) {
                                    mysqli_stmt_bind_param($insNoEmp, 'issd', $eventID, $fieldKey, $empName, $amt);
                                    if (!mysqli_stmt_execute($insNoEmp)) { $ok = false; $errorMsg = mysqli_error($conn); break 2; }
                                } else {
                                    $ok = false; $errorMsg = mysqli_error($conn); break 2;
                                }
                            } else {
                                if ($insWithEmp) {
                                    mysqli_stmt_bind_param($insWithEmp, 'isssd', $eventID, $fieldKey, $emp, $empName, $amt);
                                    if (!mysqli_stmt_execute($insWithEmp)) { $ok = false; $errorMsg = mysqli_error($conn); break 2; }
                                } else {
                                    $ok = false; $errorMsg = mysqli_error($conn); break 2;
                                }
                            }
                        }
                    }
                    if ($insWithEmp) mysqli_stmt_close($insWithEmp);
                    if ($insNoEmp) mysqli_stmt_close($insNoEmp);
                } else {
                    $ok = false;
                    $errorMsg = mysqli_error($conn);
                }
            }

            if ($ok) {
                mysqli_commit($conn);
                $message = 'Saved successfully.';
                $messageIsError = false;
            } else {
                mysqli_rollback($conn);
                $message = 'Save failed: ' . (isset($errorMsg) ? $errorMsg : 'unknown');
                $messageIsError = true;
            }
        } else {
            $storageDir = __DIR__ . '/data';
            if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
            $filePath = $storageDir . '/FunctionDetails_event_' . ($eventID ? $eventID : 'noid') . '.json';
            $payload = ['eventID' => $eventID, 'data' => $save, 'saved_at' => date('c')];
            if (file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT))) {
                $message = 'Saved to local file: ' . $filePath;
                $messageIsError = false;
            } else {
                $message = 'Failed to save local file.';
                $messageIsError = true;
            }
        }
    }
}

// load latest data for this event if exists
$savedData = null;
if ($eventID > 0) {
    if ($dbAvailable) {
        $stmt = mysqli_prepare($conn, "SELECT data FROM FunctionDetails WHERE eventID = ? ORDER BY created_at DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $eventID);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $d);
            if (mysqli_stmt_fetch($stmt)) $savedData = json_decode($d, true);
        }
    } else {
        $filePath = __DIR__ . '/data/FunctionDetails_event_' . ($eventID ? $eventID : 'noid') . '.json';
        if (file_exists($filePath)) {
            $raw = file_get_contents($filePath);
            $payload = json_decode($raw, true);
            if (is_array($payload) && isset($payload['data'])) $savedData = $payload['data'];
        }
    }
}

// pull saved per-field notes (if any) for display
$fieldNotes = [];
if (is_array($savedData) && isset($savedData['__field_notes']) && is_array($savedData['__field_notes'])) {
    $fieldNotes = $savedData['__field_notes'];
}

// compute per-employee totals across all expense fields for the Incentives display
$incentiveKeys = [
    'real_flower','banner','outside_labour_1','outside_labour_2','fixing','removal','aarna_labor',
    'balloon','noble_bose','dj_charges',
    'night_stay_person_1','night_stay_person_2','night_stay_electrician','watchman_stay',
    'wedding_pillars_person1','wedding_pillars_person2','wedding_pillars_person3','wedding_pillars_person4',
    'real_flower_purchase','real_flower_fixing_pillar',
    'WB','WC','GC','NM','B','LT'
];
// NOTE: 'miscellaneous' is intentionally excluded from incentive calculation.
// 'dj_charges' is now a normal amount field, counted in full against whichever employee is selected on that row.
$incentivePerEmployee = []; // empID/name => total
if (is_array($savedData)) {
    foreach ($incentiveKeys as $ik) {
        if (!isset($savedData[$ik]) || !is_array($savedData[$ik])) continue;
        foreach ($savedData[$ik] as $r) {
            if (!is_array($r)) continue;
            $emp = isset($r['employee']) ? $r['employee'] : null;
            $amt = isset($r['amount'])   ? floatval($r['amount']) : 0;
            if ($emp === null || $emp === '' || $amt <= 0) continue;
            // resolve display name
            $empLabel = $emp;
            foreach ($employeeOptions as $eo) {
                if ((string)$eo['id'] === (string)$emp || (string)$eo['name'] === (string)$emp) {
                    $empLabel = $eo['name']; break;
                }
            }
            if (!isset($incentivePerEmployee[$empLabel])) $incentivePerEmployee[$empLabel] = 0;
            $incentivePerEmployee[$empLabel] += $amt;
        }
    }
    // NOTE: DJ Charges is included above in $incentiveKeys, so it's counted here too,
    // attributed in full to whichever employee was selected on that row.
}
// keep only employees with total > 0
$incentivePerEmployee = array_filter($incentivePerEmployee, function($v){ return $v > 0; });

// render form
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Function Details - Event <?php echo htmlspecialchars($eventID); ?></title>
<style>
/* Page layout */
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial; background: #f5f7fb; color: #222; margin:0; padding:0; }
.container { max-width:1100px; margin:30px auto; background:#fff; padding:22px; border-radius:10px; box-shadow:0 10px 30px rgba(12,20,30,0.06); }
h2 { margin-top:0; font-weight:600; color:#0b2545; }
.note { color: #186a3b; background:#e9f7ef; padding:8px 12px; border-radius:6px; display:inline-block; margin-bottom:12px; }
.note.error { color: #a62b2b; background:#fff0f0; padding:8px 12px; border-radius:6px; display:inline-block; margin-bottom:12px; border:1px solid #ffcccc; }

/* Back link */
.top-actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
.back-link { text-decoration:none; color:#0645ad; background:#eef6ff; padding:8px 12px; border-radius:8px; border:1px solid #d9e9ff; font-weight:600; }
.back-link:hover { background:#dfeeff; }

/* Form layout */
table { width:100%; border-collapse: collapse; }
td { vertical-align: top; padding:10px 8px; }
label { display:block; font-weight:600; margin-bottom:6px; color:#0b2545; }
.amount { width:120px; padding:8px 10px; border-radius:6px; border:1px solid #d5dce6; }
.select { min-width:220px; padding:8px 10px; border-radius:6px; border:1px solid #d5dce6; background:#fff; }

/* Rows UI */
.rows-container { display:block; gap:8px; }
.row-entry { display:flex; gap:10px; align-items:center; margin-bottom:8px; }
.row-entry .select { min-width:240px; }
.add-row-btn, .remove-row-btn { background:#e6eefc; color:#0645ad; border:1px solid #d7e4ff; padding:6px 10px; border-radius:6px; cursor:pointer; }
.remove-row-btn { background:#fff5f6; color:#a62b2b; border:1px solid #ffd6d8; }
.add-row-btn:hover { background:#dfeeff; }
.remove-row-btn:hover { background:#ffecec; }

/* Buttons */
button[type=submit] { background:#0563c1; color:#fff; border:none; border-radius:8px; cursor:pointer; padding:10px 16px; }
button[type=button].cancel { background:#6b7280; }

/* DJ Charges modal */
.dj-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
.dj-modal-overlay.active { display: flex; align-items: center; justify-content: center; }
.dj-modal { background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); padding: 30px; max-width: 500px; width: 90%; }
.dj-modal h3 { margin-top: 0; color: #0b2545; }
.dj-modal-field { margin-bottom: 16px; }
.dj-modal-field label { margin-bottom: 6px; }
.dj-modal input[type=text] { width: 100%; padding: 8px 10px; border: 1px solid #d5dce6; border-radius: 6px; }
.dj-modal-buttons { display: flex; gap: 10px; margin-top: 20px; }
.dj-modal-buttons button { flex: 1; padding: 10px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; }
.dj-modal-buttons .save { background: #0563c1; color: #fff; }
.dj-modal-buttons .cancel { background: #6b7280; color: #fff; }

/* Edit button for DJ charges */
.dj-edit-btn { background: #8b5cf6; color: #fff; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; margin-left: 8px; }
.dj-edit-btn:hover { background: #7c3aed; }

/* DJ row wrapper */
.dj-row-wrapper { display: flex; gap: 10px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
.dj-row-wrapper .select { flex: 1; min-width: 180px; }

/* Field notes */
.notes-icon-btn { background:none; border:none; cursor:pointer; font-size:.95rem; margin-left:6px; padding:0 2px; vertical-align:middle; opacity:0.75; }
.notes-icon-btn:hover { opacity:1; }
.notes-block { margin-top:4px; }
.notes-display { display:none; font-style:italic; color:#666; font-size:.85rem; white-space:pre-wrap; cursor:default; }
.notes-display.has-note { display:block; }
.notes-textarea { display:none; width:100%; max-width:360px; min-height:50px; margin-top:4px; padding:6px 8px; border:1px solid #d5dce6; border-radius:6px; font-size:.85rem; font-family:inherit; resize:vertical; }
.notes-textarea.editing { display:block; }

@media (max-width: 760px) {
    .row-entry { flex-direction:column; align-items:stretch; }
    .row-entry .select, .row-entry .amount { width:100%; }
    .dj-row-wrapper { flex-direction: column; }
    .dj-row-wrapper .select { width: 100%; }
    .container { margin:14px; padding:14px; }
}
</style>
</head>
<body>
<div class="container">
<h2>Function Details for Event <?php echo htmlspecialchars($eventID); ?></h2>
<div class="top-actions">
    <a href="listBookingsv2.php" class="back-link">← Back to bookings</a>
</div>
<?php if ($message) {
    $cls = (isset($messageIsError) && $messageIsError) ? 'note error' : 'note';
    echo '<div class="' . $cls . '">' . htmlspecialchars($message) . '</div>';
} ?>
<?php if ($djUpdateMsg) {
    $cls = $djUpdateError ? 'note error' : 'note';
    echo '<div class="' . $cls . '">' . htmlspecialchars($djUpdateMsg) . '</div>';
} ?>

<!-- DJ Charges Pricing Modal (only for privileged users) -->
<?php if ($isPrivileged): ?>
<div class="dj-modal-overlay" id="djModalOverlay">
    <div class="dj-modal">
        <h3>Update DJ Charges Pricing</h3>
        <form method="post">
            <input type="hidden" name="update_dj_prices" value="1">
            <?php foreach ($djChargesPrices as $type => $price): ?>
                <div class="dj-modal-field">
                    <label><?php echo htmlspecialchars($type); ?> (₹)</label>
                    <input type="text" name="dj_price_<?php echo str_replace(' ', '_', strtolower($type)); ?>" value="<?php echo number_format($price, 0); ?>" required>
                </div>
            <?php endforeach; ?>
            <div class="dj-modal-buttons">
                <button type="submit" class="save">Save Pricing</button>
                <button type="button" class="cancel" onclick="closeDJModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="eventID" value="<?php echo htmlspecialchars($eventID); ?>">
<table border="0" cellpadding="6" cellspacing="0">
<?php foreach ($fields as $key => $label):
    // determine existing rows for this field from savedData (support legacy and new formats)
    $entries = [];
    if (is_array($savedData) && isset($savedData[$key])) {
        $val = $savedData[$key];
        // legacy format where 'employees' => [...], 'amount' => X
        if (isset($val['employees']) && isset($val['amount'])) {
            // employees might be array or single
            if (is_array($val['employees'])) {
                foreach ($val['employees'] as $ee) $entries[] = ['employee' => $ee, 'amount' => $val['amount']];
            } else {
                $entries[] = ['employee' => $val['employees'], 'amount' => $val['amount']];
            }
        } elseif (is_array($val)) {
            // new format: array of rows
            foreach ($val as $r) {
                if (is_array($r)) {
                    // accept rows that may be amount-only (employee null). Use array_key_exists so null values are preserved.
                    $employeeVal = array_key_exists('employee', $r) ? $r['employee'] : (array_key_exists('employees', $r) ? $r['employees'] : null);
                    $amountVal = array_key_exists('amount', $r) ? $r['amount'] : (array_key_exists('amounts', $r) ? $r['amounts'] : 0);
                    $entries[] = ['employee' => $employeeVal, 'amount' => $amountVal, 'dj_type' => isset($r['dj_type']) ? $r['dj_type'] : null];
                }
            }
        }
    }
    if (empty($entries)) $entries[] = ['employee' => null, 'amount' => '', 'dj_type' => null];
?>
<tr>
    <td style="vertical-align:top; width:260px;">
        <?php $noteVal = isset($fieldNotes[$key]) ? $fieldNotes[$key] : ''; ?>
        <label><?php echo htmlspecialchars($label); ?><?php if ($key === 'dj_charges' && $isPrivileged): ?> <button type="button" class="dj-edit-btn" onclick="openDJModal()">⚙ Edit Pricing</button><?php endif; ?>
            <button type="button" class="notes-icon-btn" title="Add/Edit note" onclick="toggleNoteEdit('<?php echo $key; ?>')">📝</button>
        </label>
        <div class="notes-block">
            <span class="notes-display<?php echo $noteVal !== '' ? ' has-note' : ''; ?>" id="notes-display-<?php echo $key; ?>"><?php echo htmlspecialchars($noteVal); ?></span>
            <textarea class="notes-textarea" id="notes-textarea-<?php echo $key; ?>" placeholder="Add a note..." onblur="saveNoteEdit('<?php echo $key; ?>')"><?php echo htmlspecialchars($noteVal); ?></textarea>
            <input type="hidden" name="notes[<?php echo $key; ?>]" id="notes-input-<?php echo $key; ?>" value="<?php echo htmlspecialchars($noteVal); ?>">
        </div>
    </td>
    <td>
        <div class="rows-container" data-field="<?php echo htmlspecialchars($key); ?>">
            <?php foreach ($entries as $idx => $entry): ?>
            <div class="row-entry" data-index="<?php echo $idx; ?>">
                <?php if ($key === 'total_expenses'): ?>
                    <!-- auto-calculated read-only total -->
                    <input class="amount" type="text" id="total_expenses_display"
                           name="amounts[total_expenses][]"
                           value="<?php echo htmlspecialchars($entry['amount']); ?>"
                           readonly
                           style="background:#f0f4ff;color:#0b2545;font-weight:700;cursor:default;border-color:#c7d7f5;">
                <?php elseif ($key === 'profit'): ?>
                    <!-- auto-calculated read-only: decor_amount - total_expenses -->
                    <input class="amount" type="text" id="profit_display"
                           name="amounts[profit][]"
                           value="<?php echo htmlspecialchars($entry['amount']); ?>"
                           readonly
                           style="background:#f0fff4;color:#186a3b;font-weight:700;cursor:default;border-color:#a3d9b1;">
                <?php elseif ($key === 'incentives'): ?>
                    <!-- read-only per-employee incentive breakdown; skip duplicate rows after first -->
                    <?php if ($idx === 0): ?>
                    <div style="min-width:400px;">
                    <?php if (empty($incentivePerEmployee)): ?>
                        <span style="color:#888;font-style:italic;font-size:.9rem;">No incentives recorded yet — amounts will appear once fields above are saved.</span>
                    <?php else: ?>
                        <table style="border-collapse:collapse;width:100%;font-size:.92rem;">
                            <thead>
                                <tr>
                                    <th style="text-align:left;padding:5px 10px;background:#f0f4ff;color:#0b2545;border-bottom:2px solid #c7d7f5;">Employee</th>
                                    <th style="text-align:right;padding:5px 10px;background:#f0f4ff;color:#0b2545;border-bottom:2px solid #c7d7f5;">Total Incentive</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($incentivePerEmployee as $empLabel => $empTotal): ?>
                                <tr>
                                    <td style="padding:5px 10px;border-bottom:1px solid #efefef;"><?php echo htmlspecialchars($empLabel); ?></td>
                                    <td style="padding:5px 10px;border-bottom:1px solid #efefef;text-align:right;font-variant-numeric:tabular-nums;">
                                        ₹<?php echo number_format($empTotal, 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f0f4ff;">
                                    <td style="padding:6px 10px;font-weight:700;color:#0b2545;">Total Incentives</td>
                                    <td style="padding:6px 10px;font-weight:700;color:#0b2545;text-align:right;font-variant-numeric:tabular-nums;">
                                        ₹<?php echo number_format(array_sum($incentivePerEmployee), 2); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php elseif ($key === 'dj_charges'): ?>
                    <!-- DJ Charges: type dropdown (reference only) + employee dropdown + manually-entered amount -->
                    <div class="dj-row-wrapper">
                        <select name="dj_type_selector[<?php echo $idx; ?>]" class="select dj-type-select">
                            <option value="">--type--</option>
                            <?php foreach ($djChargesPrices as $type => $price): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ((string)(isset($entry['dj_type']) ? $entry['dj_type'] : '') === (string)$type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?> - ₹<?php echo number_format($price); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="employees[<?php echo $key; ?>][]" class="select">
                            <option value="">--employee--</option>
                            <?php foreach ($employeeOptions as $opt):
                                $val = $opt['id'];
                                $text = $opt['name'];
                                $selEmp = ((string)$val === (string)(isset($entry['employee']) ? $entry['employee'] : '') || (string)$text === (string)(isset($entry['employee']) ? $entry['employee'] : '')) ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $selEmp; ?>><?php echo htmlspecialchars($text); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="amount dj-amount-display" type="text" name="amounts[<?php echo $key; ?>][]" value="<?php echo htmlspecialchars($entry['amount']); ?>" placeholder="Amount">
                        <button type="button" class="remove-row-btn" onclick="removeRow(this)">-</button>
                    </div>
                <?php elseif (in_array($key, ['decor_amount','real_flower','real_flower_purchase','real_flower_fixing_pillar','banner','outside_labour_1','outside_labour_2'])): ?>
                    <!-- amount-only field -->
                    <input class="amount" type="text" name="amounts[<?php echo $key; ?>][]" value="<?php echo htmlspecialchars($entry['amount']); ?>" placeholder="Amount">
                    <button type="button" class="remove-row-btn" onclick="removeRow(this)">-</button>
                <?php else: ?>
                    <select name="employees[<?php echo $key; ?>][]" class="select single-select">
                        <option value="" <?php echo ($entry['employee'] === null || $entry['employee'] === '') ? 'selected' : ''; ?>>--select--</option>
                        <?php foreach ($employeeOptions as $opt):
                            $val = $opt['id'];
                            $text = $opt['name'];
                            $sel = ((string)$val === (string)$entry['employee'] || (string)$text === (string)$entry['employee']) ? 'selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($text); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="amount" type="text" name="amounts[<?php echo $key; ?>][]" value="<?php echo htmlspecialchars($entry['amount']); ?>" placeholder="Amount">
                    <button type="button" class="remove-row-btn" onclick="removeRow(this)">-</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!in_array($key, ['decor_amount','real_flower','real_flower_purchase','real_flower_fixing_pillar','total_expenses','profit','incentives','banner','outside_labour_1','outside_labour_2','dj_charges'])): ?>
            <div style="margin-top:6px;"><button type="button" class="add-row-btn" onclick="addRow(this)">+ Add</button></div>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</table>

<p>
<button type="submit">Save</button>
<button type="button" class="cancel" onclick="window.location='listBookingsv2.php'">Cancel</button>
</p>
</form>
</div>

<script>
// DJ Charges pricing map (reference only — no longer used to auto-fill the amount)
var djChargesPrices = <?php echo json_encode($djChargesPrices); ?>;

function openDJModal() {
    document.getElementById('djModalOverlay').classList.add('active');
}

function closeDJModal() {
    document.getElementById('djModalOverlay').classList.remove('active');
}

// ── Per-field notes: icon toggles edit mode; on save shows italic read-only text ──
function toggleNoteEdit(key) {
    var textarea = document.getElementById('notes-textarea-' + key);
    var display = document.getElementById('notes-display-' + key);
    if (!textarea) return;
    if (textarea.classList.contains('editing')) {
        saveNoteEdit(key);
    } else {
        textarea.classList.add('editing');
        if (display) display.classList.remove('has-note');
        textarea.focus();
    }
}

function saveNoteEdit(key) {
    var textarea = document.getElementById('notes-textarea-' + key);
    var display = document.getElementById('notes-display-' + key);
    var input = document.getElementById('notes-input-' + key);
    if (!textarea) return;
    var val = textarea.value.trim();
    textarea.value = val;
    if (input) input.value = val;
    textarea.classList.remove('editing');
    if (display) {
        display.textContent = val;
        if (val !== '') {
            display.classList.add('has-note');
        } else {
            display.classList.remove('has-note');
        }
    }
}

// dynamic add/remove rows per field
window.addEventListener('DOMContentLoaded', function(){
    var employeeOptions = <?php echo json_encode($employeeOptions); ?>;

    function makeSelect(field, selected) {
        var sel = document.createElement('select');
        sel.name = 'employees['+field+'][]';
        sel.className = 'select single-select';
        // add a placeholder option as the default
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = '--select--';
        if (selected === null || selected === undefined || selected === '') ph.selected = true;
        sel.appendChild(ph);
        employeeOptions.forEach(function(opt){
            var o = document.createElement('option');
            o.value = opt.id;
            o.textContent = opt.name;
            if (String(opt.id) === String(selected) || String(opt.name) === String(selected)) o.selected = true;
            sel.appendChild(o);
        });
        return sel;
    }

    window.addRow = function(btn){
        var container = btn.closest('.rows-container');
        var field = container.getAttribute('data-field');
        var entry = document.createElement('div');
        entry.className = 'row-entry';

        if (['decor_amount','real_flower','real_flower_purchase','real_flower_fixing_pillar','total_expenses','profit','incentives','banner','outside_labour_1','outside_labour_2'].indexOf(field) !== -1) {
            var amt = document.createElement('input');
            amt.type = 'text'; amt.name = 'amounts['+field+'][]'; amt.className = 'amount'; amt.placeholder = 'Amount';
            var rem = document.createElement('button'); rem.type = 'button'; rem.className = 'remove-row-btn'; rem.textContent = '-'; rem.onclick = function(){ removeRow(rem); };
            entry.appendChild(amt);
            entry.appendChild(rem);
        } else if (field === 'dj_charges') {
            // DJ Charges: type dropdown (reference only) + employee dropdown + manually-entered amount
            var wrapper = document.createElement('div');
            wrapper.className = 'dj-row-wrapper';

            var typeSel = document.createElement('select');
            typeSel.className = 'select dj-type-select';
            var typePh = document.createElement('option');
            typePh.value = ''; typePh.textContent = '--type--';
            typeSel.appendChild(typePh);
            Object.keys(djChargesPrices).forEach(function(type) {
                var o = document.createElement('option');
                o.value = type;
                o.textContent = type + ' - ₹' + djChargesPrices[type].toLocaleString();
                typeSel.appendChild(o);
            });

            var empSel = document.createElement('select');
            empSel.name = 'employees[dj_charges][]';
            empSel.className = 'select';
            var empPh = document.createElement('option');
            empPh.value = ''; empPh.textContent = '--employee--';
            empSel.appendChild(empPh);
            employeeOptions.forEach(function(opt) {
                var o = document.createElement('option');
                o.value = opt.id;
                o.textContent = opt.name;
                empSel.appendChild(o);
            });

            var amt = document.createElement('input');
            amt.type = 'text'; amt.name = 'amounts[dj_charges][]'; amt.className = 'amount dj-amount-display'; amt.placeholder = 'Amount';

            var rem = document.createElement('button');
            rem.type = 'button'; rem.className = 'remove-row-btn'; rem.textContent = '-'; rem.onclick = function(){ removeRow(rem); };

            wrapper.appendChild(typeSel);
            wrapper.appendChild(empSel);
            wrapper.appendChild(amt);
            wrapper.appendChild(rem);
            entry.appendChild(wrapper);
        } else {
            var sel = makeSelect(field, null);
            var amt = document.createElement('input');
            amt.type = 'text'; amt.name = 'amounts['+field+'][]'; amt.className = 'amount'; amt.placeholder = 'Amount';
            var rem = document.createElement('button'); rem.type = 'button'; rem.className = 'remove-row-btn'; rem.textContent = '-'; rem.onclick = function(){ removeRow(rem); };

            entry.appendChild(sel);
            entry.appendChild(amt);
            entry.appendChild(rem);
        }

        // insert before the add button container
        btn.parentNode.parentNode.insertBefore(entry, btn.parentNode);
    };

    window.removeRow = function(btn){
        var container = btn.closest('.rows-container');
        var entries = container.querySelectorAll('.row-entry');
        if (entries.length <= 1) {
            // clear values instead of removing last
            var sel = entries[0].querySelector('select'); if (sel) sel.selectedIndex = 0;
            var amt = entries[0].querySelector('input.amount'); if (amt) amt.value = '';
            return;
        }
        btn.closest('.row-entry').remove();
    };

    // ── Live auto-sum for Total Expenses ──────────────────────────────────────
    var EXPENSE_KEYS = [
        'real_flower','banner','outside_labour_1','outside_labour_2','fixing','removal','aarna_labor',
        'balloon','noble_bose','miscellaneous',
        'night_stay_person_1','night_stay_person_2','night_stay_electrician','watchman_stay',
        'wedding_pillars_person1','wedding_pillars_person2','wedding_pillars_person3','wedding_pillars_person4',
        'real_flower_purchase','real_flower_fixing_pillar',
        'WB','WC','GC','NM','B','LT'
    ];

    function parseAmt(v) {
        var f = parseFloat(String(v).replace(/,/g, ''));
        return isNaN(f) ? 0 : f;
    }

    function recomputeTotal() {
        var total = 0;
        EXPENSE_KEYS.forEach(function(key) {
            var container = document.querySelector('.rows-container[data-field="' + key + '"]');
            if (!container) return;
            container.querySelectorAll('input.amount').forEach(function(inp) {
                total += parseAmt(inp.value);
            });
        });
        var display = document.getElementById('total_expenses_display');
        if (display) display.value = total.toFixed(2);

        // profit = decor_amount - total_expenses (DJ charges NOT included)
        var decorInput = document.querySelector('.rows-container[data-field="decor_amount"] input.amount');
        var decor = decorInput ? parseAmt(decorInput.value) : 0;
        var profitDisplay = document.getElementById('profit_display');
        if (profitDisplay) profitDisplay.value = (decor - total).toFixed(2);
    }

    // attach listeners to all existing amount inputs in expense rows + decor_amount
    function attachListeners() {
        EXPENSE_KEYS.concat(['decor_amount']).forEach(function(key) {
            var container = document.querySelector('.rows-container[data-field="' + key + '"]');
            if (!container) return;
            container.querySelectorAll('input.amount').forEach(function(inp) {
                inp.removeEventListener('input', recomputeTotal);
                inp.addEventListener('input', recomputeTotal);
            });
        });
    }

    // patch addRow / removeRow to re-attach after DOM changes
    var _origAddRow = window.addRow;
    window.addRow = function(btn) {
        _origAddRow(btn);
        attachListeners();
        recomputeTotal();
    };
    var _origRemoveRow = window.removeRow;
    window.removeRow = function(btn) {
        _origRemoveRow(btn);
        recomputeTotal();
    };

    attachListeners();
    recomputeTotal();

});
</script>

</body>
</html>