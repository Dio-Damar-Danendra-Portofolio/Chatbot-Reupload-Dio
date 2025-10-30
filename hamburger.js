document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
                
                document.querySelectorAll('.chat-list-item, .main-menu-item a, .profile-btn, .logout-btn').forEach(item => {
                    item.addEventListener('click', () => {
                        if (window.innerWidth <= 768) {
                            setTimeout(() => {
                                sidebar.classList.remove('open');
                            }, 50);
                        }
                    });
                });
            }
        });