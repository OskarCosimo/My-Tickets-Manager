<?php
// includes/footer.php
// Main footer template file with JavaScript sidebar toggle handler
?>
    </div> <!-- End .wrapper -->
    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <div class="container">
            <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(get_setting($pdo, 'site_title', 'My Tickets Manager')); ?> - Open Source Ticket Management</small>
        </div>
    </footer>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar Collapse/Expand Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('sidebarToggle');
            const body = document.body;

            // Load saved state from localStorage (desktop default)
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
</body>
</html>