</div> <!-- Close main content area -->

<!-- Advanced Dynamic Global Footer -->
<footer class="bg-dark text-white pt-5 pb-4 mt-auto border-top border-secondary">
    <div class="container">
        <div class="row g-4">
            <!-- Brand & Info Column -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary text-white rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                        <i class="bi bi-heart-pulse-fill fs-5"></i>
                    </div>
                    <h4 class="fw-bold mb-0 text-white"><?= sanitize($hospName ?? 'CareX Hospital') ?></h4>
                </div>
                <p class="text-secondary small mb-3">
                    Comprehensive healthcare management platform providing digital appointments, specialist doctor directory, laboratory testing, and emergency admissions.
                </p>
                <div class="d-flex gap-2">
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>

            <!-- Navigation Links -->
            <div class="col-lg-2 col-md-6">
                <h6 class="fw-bold text-uppercase text-light mb-3">Navigation</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="<?= BASE_URL ?>index.php#home" class="text-secondary text-decoration-none hover-white">Home</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>index.php#departments" class="text-secondary text-decoration-none hover-white">Departments</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>index.php#doctors" class="text-secondary text-decoration-none hover-white">Specialists</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>login.php" class="text-secondary text-decoration-none hover-white">Portal Login</a></li>
                </ul>
            </div>

            <!-- Specialized Services -->
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold text-uppercase text-light mb-3">Clinical Care</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2 text-secondary"><i class="bi bi-check2 text-primary me-2"></i>Cardiology & Critical Care</li>
                    <li class="mb-2 text-secondary"><i class="bi bi-check2 text-primary me-2"></i>Neurology & Surgery</li>
                    <li class="mb-2 text-secondary"><i class="bi bi-check2 text-primary me-2"></i>Pathology & Diagnostics</li>
                    <li class="mb-2 text-secondary"><i class="bi bi-check2 text-primary me-2"></i>Online Slot Reservations</li>
                </ul>
            </div>

            <!-- Emergency & Contact Info -->
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold text-uppercase text-light mb-3">Emergency Line</h6>
                <div class="p-3 bg-secondary bg-opacity-10 rounded border border-secondary mb-3">
                    <small class="text-danger fw-bold d-block text-uppercase"><i class="bi bi-telephone-inbound me-1"></i>24/7 Hotline</small>
                    <h5 class="fw-bold text-white mb-0"><?= sanitize($sysSettings['emergency_phone'] ?? '+977-9800000000') ?></h5>
                </div>
                <p class="text-secondary small mb-1"><i class="bi bi-geo-alt me-2 text-primary"></i><?= sanitize($sysSettings['address'] ?? 'Biratnagar-4, Koshi Province, Nepal') ?></p>
                <p class="text-secondary small"><i class="bi bi-envelope me-2 text-primary"></i><?= sanitize($sysSettings['email'] ?? 'info@CareX.test') ?></p>
            </div>
        </div>

        <hr class="my-4 border-secondary">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="small text-secondary mb-0">&copy; <?= date('Y') ?> <strong><?= sanitize($hospName ?? 'CareX Hospital') ?></strong>. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <span class="badge bg-success-subtle text-success border border-success me-2">System Status: Operational</span>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>