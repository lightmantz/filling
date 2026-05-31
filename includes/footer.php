<?php
// Close the content wrapper and main content divs
?>
    </div> <!-- Close content-wrapper -->
    
    <!-- Footer -->
    <div class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <p>&copy; <?php echo date('Y'); ?> Filing Management System. All rights reserved.</p>
                <p class="footer-links">
                    <a href="<?php echo $base_url; ?>/help.php">Help</a> |
                    <a href="<?php echo $base_url; ?>/privacy.php">Privacy Policy</a> |
                    <a href="<?php echo $base_url; ?>/contact.php">Contact</a>
                </p>
            </div>
            <div class="footer-section">
                <p>Version 1.0 | Developed for Records Management</p>
                <p>Last Updated: <?php echo date('F d, Y'); ?></p>
            </div>
        </div>
    </div>
</div> <!-- Close main-content -->
</div> <!-- Close app-container -->

<!-- JavaScript -->
<script>
// Toggle sidebar on mobile
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle) {
    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });
}

// Handle submenu toggles
document.querySelectorAll('.has-submenu').forEach(function(element) {
    element.addEventListener('click', function(e) {
        e.preventDefault();
        const parent = this.parentElement;
        const submenu = parent.querySelector('.submenu');
        
        if (submenu) {
            submenu.classList.toggle('show');
            const icon = this.querySelector('.fa-chevron-down');
            if (icon) {
                icon.style.transform = submenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
            }
        }
    });
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active')) {
        if (!sidebar.contains(event.target) && event.target !== menuToggle && !menuToggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
});

// Set active menu item based on current URL
document.addEventListener('DOMContentLoaded', function() {
    const currentUrl = window.location.href;
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    
    menuLinks.forEach(link => {
        if (link.href === currentUrl) {
            link.classList.add('active');
            
            // Expand parent submenu if any
            const parentSubmenu = link.closest('.submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('show');
                const parentLink = parentSubmenu.closest('li').querySelector('.has-submenu');
                if (parentLink) {
                    const icon = parentLink.querySelector('.fa-chevron-down');
                    if (icon) {
                        icon.style.transform = 'rotate(180deg)';
                    }
                }
            }
        }
    });
});
</script>
</body>
</html>
<?php
// Close any open database connections if needed
if (isset($conn)) {
    $conn->close();
}
?>