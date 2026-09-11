<?php
// includes/footer.php
// Main footer template file with Theme Switcher, Sidebar toggle handlers, Legal Links, and Footer Code Injection
?>
    </div> <!-- End .wrapper -->
    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <div class="container">
            <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(get_setting($pdo, 'site_title', 'My Tickets Manager')); ?> - Open Source Ticket Management</small>
            
            <?php 
            $termsUrl = get_setting($pdo, 'terms_url', '');
            $privacyUrl = get_setting($pdo, 'privacy_url', '');
            
            if (!empty($termsUrl) || !empty($privacyUrl)): 
            ?>
                <div class="mt-2">
                    <small>
                        <?php if (!empty($termsUrl)): ?>
                            <a href="<?php echo htmlspecialchars($termsUrl); ?>" class="text-white-50 text-decoration-none me-3" target="_blank"><?php echo __('terms_of_service', 'Terms of Service'); ?></a>
                        <?php endif; ?>
                        
                        <?php if (!empty($privacyUrl)): ?>
                            <a href="<?php echo htmlspecialchars($privacyUrl); ?>" class="text-white-50 text-decoration-none" target="_blank"><?php echo __('privacy_policy', 'Privacy Policy'); ?></a>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </footer>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

    <!-- Bootstrap 5 Theme Switcher Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeLabel = document.getElementById('themeLabel');
            const themeIcon = document.getElementById('themeIcon');

            function getPreferredTheme() {
                const storedTheme = localStorage.getItem('theme');
                if (storedTheme) {
                    return storedTheme;
                }
                return 'auto';
            }

            function setTheme(theme) {
                if (theme === 'auto') {
                    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.setAttribute('data-bs-theme', systemDark ? 'dark' : 'light');
                    if (themeLabel) themeLabel.textContent = 'Auto';
                    if (themeIcon) themeIcon.className = 'fa-solid fa-circle-half-stroke me-1';
                } else {
                    document.documentElement.setAttribute('data-bs-theme', theme);
                    if (themeLabel) themeLabel.textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
                    if (themeIcon) {
                        themeIcon.className = theme === 'dark' ? 'fa-solid fa-moon me-1 text-primary' : 'fa-solid fa-sun me-1 text-warning';
                    }
                }
            }

            // Apply theme on load
            const currentTheme = getPreferredTheme();
            setTheme(currentTheme);

            // Listen for user clicks on theme options
            document.querySelectorAll('[data-bs-theme-value]').forEach(toggle => {
                toggle.addEventListener('click', () => {
                    const theme = toggle.getAttribute('data-bs-theme-value');
                    localStorage.setItem('theme', theme);
                    setTheme(theme);
                });
            });

            // System color scheme change listener
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (getPreferredTheme() === 'auto') {
                    setTheme('auto');
                }
            });
        });
    </script>

    <!-- Sidebar Collapse/Expand Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('sidebarToggle');
            const body = document.body;

            // Load saved state from localStorage
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        body.classList.toggle('sidebar-expanded');
                    } else {
                        body.classList.toggle('sidebar-collapsed');
                        localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed'));
                    }
                });
            }
        });
    </script>

<!-- Automatic Background Queue Runner with Throttle -->
<script>
(function() {
    const lastRun = localStorage.getItem('ai_queue_last_run');
    const now = Date.now();

    if (!lastRun || (now - lastRun) > 10000) {
        localStorage.setItem('ai_queue_last_run', now);
        fetch('/api/process_ai_queue.php', { 
            method: 'GET', 
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function(){});
    }
})();
</script>

<!-- Custom Footer Injection -->
<?php 
$footerInjection = get_setting($pdo, 'inject_footer', '');
if (!empty($footerInjection)) {
    echo $footerInjection . "\n";
}
?>
</body>
</html>