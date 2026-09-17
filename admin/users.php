<?php
ob_start();
/**
 * Advanced Admin - User Management Console, Live Session Guard & Device Tracker
 * CarePlus Smart Hospital Management System
 */

// Include core database initialization before processing POST/GET exports
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// Helper to parse User Agent into readable browser & OS
function parseUserAgent($agentStr) {
    if (empty($agentStr)) return 'Unknown Device';
    $os = 'Unknown OS';
    $browser = 'Unknown Browser';

    if (preg_match('/windows nt/i', $agentStr)) $os = 'Windows PC';
    elseif (preg_match('/macintosh|mac os x/i', $agentStr)) $os = 'macOS';
    elseif (preg_match('/linux/i', $agentStr)) $os = 'Linux';
    elseif (preg_match('/android/i', $agentStr)) $os = 'Android Device';
    elseif (preg_match('/iphone|ipad/i', $agentStr)) $os = 'iOS Device';

    if (preg_match('/chrome/i', $agentStr)) $browser = 'Google Chrome';
    elseif (preg_match('/firefox/i', $agentStr)) $browser = 'Mozilla Firefox';
    elseif (preg_match('/safari/i', $agentStr)) $browser = 'Apple Safari';
    elseif (preg_match('/edg/i', $agentStr)) $browser = 'Microsoft Edge';

    return "{$browser} on {$os}";
}

// ==========================================
// CSV & PDF DATA EXPORT HANDLER
// ==========================================
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'print'])) {
    $searchRole = sanitize($_GET['role'] ?? '');
    $searchQuery = sanitize($_GET['q'] ?? '');

    $sql = "SELECT u.id, u.name, u.email, u.role, u.phone, u.status, u.last_seen, u.ip_address, u.created_at FROM users u WHERE 1=1";
    $params = [];

    if (!empty($searchRole)) {
        $sql .= " AND u.role = ?";
        $params[] = $searchRole;
    }
    if (!empty($searchQuery)) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $term = "%{$searchQuery}%";
        $params[] = $term; $params[] = $term; $params[] = $term;
    }
    $sql .= " ORDER BY u.created_at DESC";
    $stmtExp = $db->prepare($sql);
    $stmtExp->execute($params);
    $exportData = $stmtExp->fetchAll();

    if ($_GET['export'] === 'csv') {
        if (ob_get_length()) ob_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=careplus_users_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Full Name', 'Email Address', 'Role', 'Phone Number', 'Account Status', 'Last Seen', 'IP Address', 'Created Date']);
        foreach ($exportData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
}

// ==========================================
// FORM ACTION HANDLERS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Error. Operation cancelled.";
    } else {

        // 1. BULK CSV IMPORT ENGINE
        if ($action === 'import_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['csv_file']['tmp_name'];
                $fileName = $_FILES['csv_file']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($ext !== 'csv') {
                    $error = "Invalid file type. Please upload a standard CSV file.";
                } else {
                    $handle = fopen($fileTmp, 'r');
                    $header = fgetcsv($handle);
                    $importedCount = 0;
                    $skippedCount = 0;

                    $db->beginTransaction();
                    try {
                        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
                        $stmtInsUser = $db->prepare("INSERT INTO users (name, email, password, role, phone, status) VALUES (?, ?, ?, ?, ?, 'active')");
                        $stmtInsPat = $db->prepare("INSERT INTO patients (user_id, patient_id, date_of_birth, gender) VALUES (?, ?, '1995-01-01', 'male')");

                        while (($data = fgetcsv($handle)) !== false) {
                            if (count($data) >= 3) {
                                $name  = sanitize($data[0] ?? '');
                                $email = sanitize($data[1] ?? '');
                                $role  = strtolower(sanitize($data[2] ?? 'patient'));
                                $phone = sanitize($data[3] ?? '');
                                $rawPass = !empty($data[4]) ? $data[4] : 'Password123!';

                                if (empty($name) || empty($email)) continue;

                                $stmtCheck->execute([$email]);
                                if ($stmtCheck->fetch()) {
                                    $skippedCount++;
                                    continue;
                                }

                                $hashed = password_hash($rawPass, PASSWORD_BCRYPT);
                                $stmtInsUser->execute([$name, $email, $hashed, $role, $phone]);
                                $newUserId = $db->lastInsertId();

                                if ($role === 'patient') {
                                    $patCode = generatePatientID();
                                    $stmtInsPat->execute([$newUserId, $patCode]);
                                }
                                $importedCount++;
                            }
                        }
                        fclose($handle);
                        $db->commit();

                        logAudit($_SESSION['user_id'], "Bulk Imported {$importedCount} Users from CSV", 'Users');
                        setFlashMessage('success', "CSV Import Complete: {$importedCount} accounts created. {$skippedCount} duplicates skipped.");
                        header('Location: users.php');
                        exit();
                    } catch (\Exception $e) {
                        $db->rollBack();
                        $error = "CSV Processing Error: " . $e->getMessage();
                    }
                }
            } else {
                $error = "Please choose a valid CSV file to upload.";
            }
        }

        // 2. REMOTE SESSION INVALIDATION (FORCE LOGOUT)
        elseif ($action === 'force_logout') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0 && $userId !== $_SESSION['user_id']) {
                $stmtForce = $db->prepare("UPDATE users SET force_logout = 1 WHERE id = ?");
                $stmtForce->execute([$userId]);

                logAudit($_SESSION['user_id'], "Revoked Session / Force Logged Out User #{$userId}", 'Security', $userId);
                setFlashMessage('success', "Remote session invalidated! User #{$userId} will be logged out on their next action.");
                header('Location: users.php');
                exit();
            }
        }

        // 3. EDIT USER DETAILS
        elseif ($action === 'edit_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $name   = sanitize($_POST['name'] ?? '');
            $email  = sanitize($_POST['email'] ?? '');
            $phone  = sanitize($_POST['phone'] ?? '');
            $role   = sanitize($_POST['role'] ?? '');
            $status = sanitize($_POST['status'] ?? 'active');

            if ($userId > 0 && !empty($name) && !empty($email)) {
                try {
                    $stmtUpd = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                    $stmtUpd->execute([$name, $email, $phone, $role, $status, $userId]);

                    logAudit($_SESSION['user_id'], "Updated User #{$userId} Profile ({$name})", 'Users', $userId);
                    setFlashMessage('success', "User '{$name}' updated successfully.");
                    header('Location: users.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Error updating user: " . $e->getMessage();
                }
            }
        }

        // 4. TOGGLE ACCOUNT STATUS
        elseif ($action === 'toggle_status') {
            $userId    = (int)($_POST['user_id'] ?? 0);
            $newStatus = sanitize($_POST['status'] ?? 'active');

            if ($userId > 0 && $userId !== $_SESSION['user_id']) {
                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $userId]);

                logAudit($_SESSION['user_id'], "Set User #{$userId} Status to {$newStatus}", 'Users', $userId);
                setFlashMessage('success', "User account status changed to '{$newStatus}'.");
                header('Location: users.php');
                exit();
            }
        }

        // 5. OVERRIDE PASSWORD
        elseif ($action === 'reset_password') {
            $userId   = (int)($_POST['user_id'] ?? 0);
            $newPass  = $_POST['new_password'] ?? '';

            if ($userId > 0 && !empty($newPass)) {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                $stmtPass = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtPass->execute([$hashed, $userId]);

                logAudit($_SESSION['user_id'], "Reset Password for User #{$userId}", 'Users', $userId);
                setFlashMessage('success', "Password updated successfully for User #{$userId}.");
                header('Location: users.php');
                exit();
            }
        }

        // 6. PERMANENT DELETE USER ACCOUNT
        elseif ($action === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId > 0 && $userId !== $_SESSION['user_id']) {
                try {
                    $db->beginTransaction();
                    $stmtFetch = $db->prepare("SELECT name FROM users WHERE id = ?");
                    $stmtFetch->execute([$userId]);
                    $targetUser = $stmtFetch->fetch();

                    if ($targetUser) {
                        $stmtDel = $db->prepare("DELETE FROM users WHERE id = ?");
                        $stmtDel->execute([$userId]);

                        $db->commit();
                        logAudit($_SESSION['user_id'], "Permanently Deleted User Account '{$targetUser['name']}' (#{$userId})", 'Users');
                        setFlashMessage('success', "User account for '{$targetUser['name']}' has been permanently removed.");
                        header('Location: users.php');
                        exit();
                    }
                } catch (\Exception $e) {
                    $db->rollBack();
                    $error = "Deletion failed: " . $e->getMessage();
                }
            } else {
                $error = "Cannot delete your own active administrator session.";
            }
        }
    }
}

// ==========================================
// DATA QUERYING & KPI COUNTS
// ==========================================
$searchRole  = sanitize($_GET['role'] ?? '');
$searchQuery = sanitize($_GET['q'] ?? '');

$sql = "SELECT u.*, 
          p.patient_id as patient_code, p.gender, p.blood_group,
          d.doctor_id as doctor_code, d.specialization, dept.name as dept_name
        FROM users u
        LEFT JOIN patients p ON p.user_id = u.id
        LEFT JOIN doctors d ON d.user_id = u.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE 1=1";

$params = [];

if (!empty($searchRole)) {
    $sql .= " AND u.role = ?";
    $params[] = $searchRole;
}

if (!empty($searchQuery)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

$sql .= " ORDER BY u.created_at DESC";

$stmtUsers = $db->prepare($sql);
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll();

$kpiCounts = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$pageTitle = "Users & Access Control";
?>

<style>
.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.status-online {
    background-color: #22c55e;
    box-shadow: 0 0 8px #22c55e;
}
.status-offline {
    background-color: #94a3b8;
}
</style>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <!-- Title & Action Bar -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-0">System User Directory & Access Control</h2>
                <p class="text-muted mb-0">Manage hospital staff accounts, track live sessions, inspect login devices, and revoke access.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#importCsvModal">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import CSV
                </button>
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle fw-bold px-3" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-1"></i> Export Data
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="users.php?export=csv&role=<?= $searchRole ?>&q=<?= $searchQuery ?>"><i class="bi bi-filetype-csv me-2 text-success"></i>Download CSV Spreadsheet</a></li>
                        <li><a class="dropdown-item" href="javascript:window.print()"><i class="bi bi-printer me-2 text-primary"></i>Print PDF Directory</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Metric Quick Filter Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-2">
                <a href="users.php" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= empty($searchRole) ? 'border-primary border-2' : '' ?>">
                        <small class="text-muted fw-bold">ALL USERS</small>
                        <h4 class="fw-bold mb-0 text-dark"><?= array_sum($kpiCounts) ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=admin" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'admin' ? 'border-danger border-2' : '' ?>">
                        <small class="text-danger fw-bold">ADMINS</small>
                        <h4 class="fw-bold mb-0 text-danger"><?= $kpiCounts['admin'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=doctor" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'doctor' ? 'border-primary border-2' : '' ?>">
                        <small class="text-primary fw-bold">DOCTORS</small>
                        <h4 class="fw-bold mb-0 text-primary"><?= $kpiCounts['doctor'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=patient" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'patient' ? 'border-success border-2' : '' ?>">
                        <small class="text-success fw-bold">PATIENTS</small>
                        <h4 class="fw-bold mb-0 text-success"><?= $kpiCounts['patient'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=laboratory" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'laboratory' ? 'border-warning border-2' : '' ?>">
                        <small class="text-warning fw-bold">LAB TECHS</small>
                        <h4 class="fw-bold mb-0 text-warning"><?= $kpiCounts['laboratory'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=billing" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'billing' ? 'border-info border-2' : '' ?>">
                        <small class="text-info fw-bold">BILLING</small>
                        <h4 class="fw-bold mb-0 text-info"><?= $kpiCounts['billing'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
        </div>

        <!-- Directory Filter Bar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 btn-print-hide">
            <div class="card-body p-3">
                <form method="GET" action="users.php" class="row g-2">
                    <?php if (!empty($searchRole)): ?>
                        <input type="hidden" name="role" value="<?= sanitize($searchRole) ?>">
                    <?php endif; ?>
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-start-0" placeholder="Search user by name, email, or phone number..." value="<?= sanitize($searchQuery) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main User Accounts Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Live Status</th>
                                <th>User Profile</th>
                                <th>Email</th>
                                <th>System Role</th>
                                <th>Phone</th>
                                <th>Account Status</th>
                                <th class="text-end pe-4 btn-print-hide">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): foreach ($users as $u): 
                                // Calculate online status (active in last 5 minutes)
                                $isOnline = false;
                                if (!empty($u['last_seen'])) {
                                    $timeDiff = time() - strtotime($u['last_seen']);
                                    if ($timeDiff <= 300) $isOnline = true; // 5 mins
                                }
                            ?>
                                <tr>
                                    <td class="text-center ps-3">
                                        <span class="status-indicator <?= $isOnline ? 'status-online' : 'status-offline' ?>" title="<?= $isOnline ? 'Online Now' : 'Offline' ?>"></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($u['profile_image'] ?? 'default.png') ?>" class="rounded-circle border me-3 object-fit-cover" width="42" height="42">
                                            <div>
                                                <div class="fw-bold text-dark mb-0"><?= sanitize($u['name']) ?></div>
                                                <small class="text-muted">
                                                    <?php if ($u['role'] === 'patient' && !empty($u['patient_code'])): ?>
                                                        ID: <code><?= sanitize($u['patient_code']) ?></code>
                                                    <?php elseif ($u['role'] === 'doctor' && !empty($u['doctor_code'])): ?>
                                                        Specialist: <?= sanitize($u['specialization'] ?? 'General') ?>
                                                    <?php else: ?>
                                                        Created: <?= formatDate($u['created_at']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-semibold text-secondary"><?= sanitize($u['email']) ?></td>
                                    <td>
                                        <?php
                                        $roleBadge = match($u['role']) {
                                            'admin' => 'bg-danger',
                                            'doctor' => 'bg-primary',
                                            'patient' => 'bg-success',
                                            'laboratory' => 'bg-warning text-dark',
                                            'billing' => 'bg-info text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $roleBadge ?> text-capitalize px-3 py-2 rounded-pill"><?= sanitize($u['role']) ?></span>
                                    </td>
                                    <td><?= sanitize($u['phone'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>-subtle text-<?= $u['status'] === 'active' ? 'success' : 'danger' ?> fw-bold border border-<?= $u['status'] === 'active' ? 'success' : 'danger' ?> px-3 py-1">
                                            <?= ucfirst(sanitize($u['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 btn-print-hide">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
                                                Manage
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <!-- Edit Modal Trigger -->
                                                <li>
                                                    <button type="button" class="dropdown-item" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editModal"
                                                            data-id="<?= $u['id'] ?>"
                                                            data-name="<?= sanitize($u['name']) ?>"
                                                            data-email="<?= sanitize($u['email']) ?>"
                                                            data-phone="<?= sanitize($u['phone'] ?? '') ?>"
                                                            data-role="<?= sanitize($u['role']) ?>"
                                                            data-status="<?= sanitize($u['status']) ?>">
                                                        <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Profile Details
                                                    </button>
                                                </li>

                                                <!-- Deep Profile, Device & Audit Stream Viewer -->
                                                <li>
                                                    <button type="button" class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#viewModal<?= $u['id'] ?>">
                                                        <i class="bi bi-shield-check me-2 text-info"></i>View Profile & Device Tracker
                                                    </button>
                                                </li>

                                                <!-- Reset Password Trigger -->
                                                <li>
                                                    <button type="button" class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#passModal"
                                                            data-id="<?= $u['id'] ?>"
                                                            data-name="<?= sanitize($u['name']) ?>">
                                                        <i class="bi bi-key me-2 text-warning"></i>Override Password
                                                    </button>
                                                </li>

                                                <li><hr class="dropdown-divider"></li>

                                                <!-- Force Remote Logout Session Invalidation -->
                                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                                    <li>
                                                        <form action="users.php" method="POST" onsubmit="return confirm('Force logout session for <?= sanitize($u['name']) ?>?');">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="force_logout">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-left me-2"></i>Force Logout / Revoke Session</button>
                                                        </form>
                                                    </li>

                                                    <!-- Suspend / Activate Account -->
                                                    <li>
                                                        <form action="users.php" method="POST">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <?php if ($u['status'] === 'active'): ?>
                                                                <button type="submit" name="status" value="suspended" class="dropdown-item text-warning"><i class="bi bi-slash-circle me-2"></i>Suspend Account</button>
                                                            <?php else: ?>
                                                                <button type="submit" name="status" value="active" class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i>Reactivate Account</button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </li>

                                                    <!-- Delete Profile Trigger -->
                                                    <li>
                                                        <button type="button" class="dropdown-item text-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteModal"
                                                                data-id="<?= $u['id'] ?>"
                                                                data-name="<?= sanitize($u['name']) ?>">
                                                            <i class="bi bi-trash3 me-2"></i>Delete Profile Permanently
                                                        </button>
                                                    </li>
                                                <?php else: ?>
                                                    <li><span class="dropdown-item text-muted disabled">Current Logged Admin</span></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>

                                        <!-- MODAL: DEEP PROFILE, DEVICE TRACKER & AUDIT -->
                                        <div class="modal fade text-start" id="viewModal<?= $u['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-light border-0">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="bi bi-person-bounding-box text-primary me-2"></i>User Dossier & Security Log: <?= sanitize($u['name']) ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="p-3 bg-light rounded-4 mb-4 border d-flex align-items-center">
                                                            <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($u['profile_image'] ?? 'default.png') ?>" class="rounded-circle me-3 object-fit-cover border" width="60" height="60">
                                                            <div class="w-100">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <h5 class="fw-bold mb-0"><?= sanitize($u['name']) ?></h5>
                                                                    <span class="badge bg-<?= $isOnline ? 'success' : 'secondary' ?>"><?= $isOnline ? 'Active Session' : 'Offline' ?></span>
                                                                </div>
                                                                <small class="text-muted"><?= sanitize($u['email']) ?> | Phone: <?= sanitize($u['phone'] ?: 'N/A') ?></small>
                                                            </div>
                                                        </div>

                                                        <div class="row g-2 mb-4">
                                                            <div class="col-md-4">
                                                                <div class="p-3 bg-light rounded border">
                                                                    <small class="text-muted d-block fw-semibold">LAST SEEN ACTIVE</small>
                                                                    <strong class="text-dark"><?= !empty($u['last_seen']) ? formatDate($u['last_seen']) . ' ' . formatTime($u['last_seen']) : 'Never' ?></strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="p-3 bg-light rounded border">
                                                                    <small class="text-muted d-block fw-semibold">IP ADDRESS</small>
                                                                    <code><?= sanitize($u['ip_address'] ?: 'Not Recorded') ?></code>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="p-3 bg-light rounded border">
                                                                    <small class="text-muted d-block fw-semibold">CLIENT DEVICE</small>
                                                                    <small class="fw-bold text-dark"><?= parseUserAgent($u['user_agent'] ?? '') ?></small>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <ul class="nav nav-tabs mb-3" id="userTab<?= $u['id'] ?>" role="tablist">
                                                            <li class="nav-item" role="presentation">
                                                                <button class="nav-link active fw-bold" id="appts-tab-<?= $u['id'] ?>" data-bs-toggle="tab" data-bs-target="#appts-<?= $u['id'] ?>" type="button">Appointments</button>
                                                            </li>
                                                            <li class="nav-item" role="presentation">
                                                                <button class="nav-link fw-bold" id="logs-tab-<?= $u['id'] ?>" data-bs-toggle="tab" data-bs-target="#logs-<?= $u['id'] ?>" type="button">Audit Activity Trail</button>
                                                            </li>
                                                        </ul>

                                                        <div class="tab-content" id="userTabContent<?= $u['id'] ?>">
                                                            <div class="tab-pane fade show active" id="appts-<?= $u['id'] ?>" role="tabpanel">
                                                                <?php
                                                                if ($u['role'] === 'patient') {
                                                                    $stmtAppt = $db->prepare("SELECT a.*, u_doc.name as doctor_name FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id JOIN users u_doc ON d.user_id = u_doc.id WHERE p.user_id = ? ORDER BY a.appointment_date DESC");
                                                                    $stmtAppt->execute([$u['id']]);
                                                                } elseif ($u['role'] === 'doctor') {
                                                                    $stmtAppt = $db->prepare("SELECT a.*, u_pat.name as patient_name FROM appointments a JOIN doctors d ON a.doctor_id = d.id JOIN patients p ON a.patient_id = p.id JOIN users u_pat ON p.user_id = u_pat.id WHERE d.user_id = ? ORDER BY a.appointment_date DESC");
                                                                    $stmtAppt->execute([$u['id']]);
                                                                } else {
                                                                    $stmtAppt = null;
                                                                }
                                                                $userAppts = $stmtAppt ? $stmtAppt->fetchAll() : [];
                                                                ?>

                                                                <?php if (!empty($userAppts)): ?>
                                                                    <table class="table table-bordered table-sm align-middle">
                                                                        <thead class="table-light">
                                                                            <tr><th>Appt #</th><th><?= $u['role'] === 'patient' ? 'Doctor' : 'Patient' ?></th><th>Date</th><th>Status</th></tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($userAppts as $ap): ?>
                                                                                <tr>
                                                                                    <td class="fw-bold text-primary"><?= sanitize($ap['appointment_number']) ?></td>
                                                                                    <td><?= sanitize($u['role'] === 'patient' ? $ap['doctor_name'] : $ap['patient_name']) ?></td>
                                                                                    <td><?= formatDate($ap['appointment_date']) ?></td>
                                                                                    <td><span class="badge bg-secondary"><?= sanitize($ap['status']) ?></span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                <?php else: ?>
                                                                    <div class="alert alert-light text-center border py-3 text-muted">No appointments found.</div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="tab-pane fade" id="logs-<?= $u['id'] ?>" role="tabpanel">
                                                                <?php
                                                                $stmtAudit = $db->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
                                                                $stmtAudit->execute([$u['id']]);
                                                                $userLogs = $stmtAudit->fetchAll();
                                                                ?>

                                                                <?php if (!empty($userLogs)): ?>
                                                                    <table class="table table-sm align-middle">
                                                                        <thead class="table-light">
                                                                            <tr><th>Timestamp</th><th>Action Taken</th><th>Module</th><th>IP Address</th></tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($userLogs as $lg): ?>
                                                                                <tr>
                                                                                    <td><small class="text-muted"><?= formatDate($lg['created_at']) ?> <?= formatTime($lg['created_at']) ?></small></td>
                                                                                    <td class="fw-semibold"><?= sanitize($lg['action']) ?></td>
                                                                                    <td><span class="badge bg-light text-dark border"><?= sanitize($lg['module']) ?></span></td>
                                                                                    <td><code><?= sanitize($lg['ip_address']) ?></code></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                <?php else: ?>
                                                                    <div class="alert alert-light text-center border py-3 text-muted">No audit log activity recorded for this user.</div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No user accounts found matching query parameters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: BULK CSV IMPORT -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>Bulk Import Users via CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="import_csv">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload CSV File *</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>

                    <div class="p-3 bg-light rounded border text-muted small">
                        <strong>Expected CSV Header Format:</strong><br>
                        <code>name, email, role, phone, password</code><br><br>
                        <em>Example Row:</em><br>
                        <code>John Doe, john@example.com, doctor, +977-9800000000, Pass123!</code>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDIT USER DETAILS -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Edit System User Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="patient">Patient</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="billing">Billing</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Status *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: OVERRIDE USER PASSWORD -->
<div class="modal fade" id="passModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Override User Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="pass_user_id">

                    <p class="text-muted mb-3">Target Account: <strong id="pass_target_name" class="text-dark"></strong></p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password *</label>
                        <input type="password" name="new_password" class="form-control form-control-lg" placeholder="••••••••" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold px-4">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: PERMANENT DELETE PROFILE CONFIRMATION -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Permanent Account Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">

                    <p class="mb-2 fs-5">Are you sure you want to delete profile: <strong id="delete_target_name" class="text-danger"></strong>?</p>
                    <p class="text-muted small mb-0">Warning: This operation is permanent and will cascade-delete linked profile metrics and records for this user.</p>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">Confirm Permanent Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Populate Edit Modal
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_user_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_name').value    = btn.getAttribute('data-name');
            document.getElementById('edit_email').value   = btn.getAttribute('data-email');
            document.getElementById('edit_phone').value   = btn.getAttribute('data-phone');
            document.getElementById('edit_role').value    = btn.getAttribute('data-role');
            document.getElementById('edit_status').value  = btn.getAttribute('data-status');
        });
    }

    // Populate Password Override Modal
    const passModal = document.getElementById('passModal');
    if (passModal) {
        passModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('pass_user_id').value     = btn.getAttribute('data-id');
            document.getElementById('pass_target_name').textContent = btn.getAttribute('data-name');
        });
    }

    // Populate Delete Confirmation Modal
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('delete_user_id').value     = btn.getAttribute('data-id');
            document.getElementById('delete_target_name').textContent = btn.getAttribute('data-name');
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>