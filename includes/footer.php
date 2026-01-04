    </div><!-- End of Main Content Container -->

    <!-- Include custom modal dialogs -->
    <?php include 'includes/modals.php'; ?>

    <!-- Clear All Data Button - Only show on commissioning form -->
    <?php if (basename($_SERVER['PHP_SELF']) === 'comissionamento.php'): ?>
        <div class="container mt-4 mb-3 text-center">
            <button id="clear-all-data-btn" class="btn btn-danger btn-lg">
                <i class="fas fa-trash-alt me-2"></i>Clear All Form Data
            </button>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer mt-5 py-4 bg-light border-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
                    <img src="assets/img/favicon.png" alt="Cleanwatts Logo" height="40" class="me-2">
                    <span class="text-muted fw-bold">Cleanwatts</span>
                </div>
                <div class="col-md-4 text-center">
                    <span class="text-muted small">Cleanwatts Portal — Reports &copy; <?php echo date('Y'); ?></span>
                </div>
                <div class="col-md-4 text-center text-md-end">
                    <small class="text-muted">Professional Solar Solutions</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery (required for some components) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Soft Delete System -->
    <script src="assets/js/soft_delete.js?v=1"></script>

    <!-- 🎯 PUNCH LIST WIDGET STYLES -->
    <link rel="stylesheet" href="assets/css/punch_list_dashboard.css?v=2">

    <!-- 🎯 PUNCH LIST WIDGET MANAGER -->
    <!-- Session keep-alive (site-wide) -->
    <script src="assets/js/session.js?v=1"></script>

    <!-- 🎯 PUNCH LIST WIDGET MANAGER -->
    <script src="assets/js/punch_list_dashboard.js?v=7"></script>

    <!-- Custom JS - Ordem importante para dependências -->
    <!-- Only load commission form scripts if we're on comissionamento.php -->
    <?php if (basename($_SERVER['PHP_SELF']) === 'comissionamento.php'): ?>
        <script src="assets/js/custom_modals.js?v=2"></script>
        <script src="assets/js/main.js?v=4"></script>
        <script src="assets/js/inverter_cards.js?v=1"></script>
        <script src="assets/js/module_table.js?v=2"></script>
        <script src="assets/js/string_functions.js?v=4"></script>
        <script src="assets/js/power_calculator.js?v=1"></script>
        <script src="assets/js/associated_equipment_dropdowns.js?v=5"></script>
        <script src="assets/js/associated_equipment_dropdown_fix.js?v=1"></script>
        <script src="assets/js/new_inverter_manager.js?v=4"></script>
        <script src="assets/js/clear_form_data.js?v=1"></script>
        <!-- 🔥 NEW: MPPT Manager - Gerencia MPPT String Measurements (SQL direto) -->
        <script src="assets/js/mppt_manager.js?v=1"></script>
        <!-- 🔥 CRITICAL: Real-time autosave for string measurements to SQL -->
        <script src="assets/js/string_autosave.js?v=2"></script>
        <!-- 🔥 CRITICAL: Autosave to SQL every 5 seconds (prevents data loss after cache clear) -->
        <script src="assets/js/autosave_sql.js?v=2"></script>
        <!-- Loading overlay controller (must come after main and string scripts to listen for events) -->
        <script src="assets/js/loading_overlay.js?v=1"></script>
    <?php endif; ?>

    <!-- Debug Script -->
    <script>
        console.log('Footer loaded.');
    </script>

    <!-- Force load string measurements draft on page ready -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[FOOTER] DOMContentLoaded fired - attempting to load string measurements...');

            // Try immediately and with multiple delays to ensure script is loaded
            const attemptLoad = (delayMs, attemptNum) => {
                setTimeout(() => {
                    if (typeof window.loadStringMeasurementsDraft === 'function') {
                        console.log('[FOOTER] ✅ loadStringMeasurementsDraft available at ' + delayMs + 'ms');
                        window.loadStringMeasurementsDraft();
                    } else if (attemptNum < 5) {
                        console.log('[FOOTER] ⏳ loadStringMeasurementsDraft not ready yet at ' + delayMs + 'ms, retrying...');
                    }
                }, delayMs);
            };

            // Try at multiple intervals
            attemptLoad(0, 1);
            attemptLoad(100, 2);
            attemptLoad(300, 3);
            attemptLoad(500, 4);
            attemptLoad(1000, 5);
        });

        // Also try on window load as fallback
        window.addEventListener('load', function() {
            console.log('[FOOTER] Window load fired - attempting to load string measurements...');
            setTimeout(() => {
                if (typeof window.loadStringMeasurementsDraft === 'function') {
                    console.log('[FOOTER] ✅ Final attempt: calling loadStringMeasurementsDraft');
                    window.loadStringMeasurementsDraft();
                }
            }, 200);
        });
    </script>
    </body>

    </html>