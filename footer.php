<?php
// footer.php
?>
        </div>
    </div>

    <script>
        // Keep submenu state based on current page
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.sidebar-menu a');
            
            links.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.closest('li').classList.add('active');
                    // Expand parent submenu if exists
                    let parent = link.closest('.submenu');
                    if (parent) {
                        parent.classList.add('show');
                        let parentLi = parent.closest('.has-submenu');
                        if (parentLi) {
                            parentLi.classList.add('active');
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>