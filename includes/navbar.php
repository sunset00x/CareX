<?php
if (count(get_included_files()) == 1) exit("Direct access not permitted.");
$user = currentUser();
$unreadNotifs = $user ? getUnreadNotificationCount($user['id']) : 0;
?>
<nav class="navbar navbar-expand-lg navbar-light main-navbar px-4 py-3 border-bottom">
    <div class="container-fluid p-0">
        <span class="navbar-brand fw-semibold text-secondary">
            <i class="bi bi-hospital text-primary me-2"></i>CarePlus Portal
        </span>

        <div class="d-flex align-items-center ms-auto">
            <?php if ($user): ?>
                <!-- Notifications Dropdown -->
                <div class="dropdown me-3">
                    <button class="btn btn-light position-relative rounded-circle p-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell text-secondary fs-5"></i>
                        <?php if ($unreadNotifs > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $unreadNotifs ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header fw-bold border-bottom d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <span class="badge bg-primary rounded-pill"><?= $unreadNotifs ?> New</span>
                        </li>
                        <?php
                        $db = Database::getConnection();
                        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                        $stmt->execute([$user['id']]);
                        $notifs = $stmt->fetchAll();
                        if (!empty($notifs)):
                            foreach ($notifs as $n):
                        ?>
                            <li>
                                <a class="dropdown-item py-2 border-bottom <?= $n['is_read'] ? '' : 'bg-light notification-item-unread' ?>" data-id="<?= $n['id'] ?>" href="#">
                                    <div class="fw-semibold small"><?= sanitize($n['title']) ?></div>
                                    <div class="text-muted small text-wrap"><?= sanitize($n['message']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= formatDate($n['created_at']) ?></div>
                                </a>
                            </li>
                        <?php endforeach; else: ?>
                            <li class="dropdown-item text-center text-muted py-3">No notifications</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- User Identity Dropdown -->
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" data-bs-toggle="dropdown">
                        <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($user['profile']) ?>" alt="User" width="36" height="36" class="rounded-circle me-2 object-fit-cover border">
                        <div class="d-none d-md-block text-start">
                            <div class="fw-bold fs-6 lh-1"><?= sanitize($user['name']) ?></div>
                            <small class="text-muted text-capitalize"><?= sanitize($user['role']) ?></small>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Logout</a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>