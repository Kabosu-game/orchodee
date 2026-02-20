// Script to load menu and highlight active page
(function() {
    "use strict";
    
    // Menu HTML content
    var menuHTML = '<!-- Navbar & Hero Start -->\n' +
        '<div class="container-fluid nav-bar px-0 px-lg-4 py-lg-0">\n' +
        '    <div class="container">\n' +
        '        <nav class="navbar navbar-expand-lg navbar-light">\n' +
        '            <a href="index.php" class="navbar-brand p-0">\n' +
        '                <img src="img/orchideelogo.png" alt="Orchidee Logo">\n' +
        '            </a>\n' +
        '            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">\n' +
        '                <span class="fa fa-bars"></span>\n' +
        '            </button>\n' +
        '            <div class="collapse navbar-collapse" id="navbarCollapse">\n' +
        '                <div class="navbar-nav mx-0 mx-lg-auto">\n' +
        '                    <a href="index.php" class="nav-item nav-link" data-page="home">Home</a>\n' +
        '                    <a href="courses.php" class="nav-item nav-link" data-page="courses">Courses</a>\n' +
        '                    <div class="nav-item dropdown">\n' +
        '                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" data-page="registration">Registration</a>\n' +
        '                        <div class="dropdown-menu">\n' +
        '                            <a href="registration-next-session.php" class="dropdown-item">Registration for the Next Session</a>\n' +
        '                            <a href="nclex-registration-form.php" class="dropdown-item">NCLEX Registration Form</a>\n' +
        '                        </div>\n' +
        '                    </div>\n' +
        '                    <a href="consultation.html" class="nav-item nav-link" data-page="consultation">Book a Consultation</a>\n' +
        '                    <a href="about.php" class="nav-item nav-link" data-page="about">About Us</a>\n' +
        '                    <a href="blog.html" class="nav-item nav-link" data-page="news">News</a>\n' +
        '                    <a href="contact.html" class="nav-item nav-link" data-page="contact">Contact</a>\n' +
        '                </div>\n' +
        '                <!-- Login/Register buttons for mobile -->\n' +
        '                <div class="d-flex d-xl-none flex-column px-3 py-2" style="gap: 0.5rem;">\n' +
        '                    <a href="login.php" class="btn btn-outline-primary w-100">\n' +
        '                        <i class="fa fa-sign-in-alt me-2"></i>Login\n' +
        '                    </a>\n' +
        '                    <a href="register.php" class="btn btn-primary w-100">\n' +
        '                        <i class="fa fa-user-plus me-2"></i>Register\n' +
        '                    </a>\n' +
        '                </div>\n' +
        '            </div>\n' +
        '            <div class="d-none d-xl-flex flex-shrink-0 ps-4 align-items-center">\n' +
        '                <a href="login.php" class="btn btn-outline-primary me-2">\n' +
        '                    <i class="fa fa-sign-in-alt me-2"></i>Login\n' +
        '                </a>\n' +
        '                <a href="register.php" class="btn btn-primary">\n' +
        '                    <i class="fa fa-user-plus me-2"></i>Register\n' +
        '                </a>\n' +
        '            </div>\n' +
        '        </nav>\n' +
        '    </div>\n' +
        '</div>\n' +
        '<!-- Navbar & Hero End -->';
    
    // Function to highlight active page
    function setActivePage() {
        // Determine active page based on filename
        var currentPage = window.location.pathname.split('/').pop() || 'index.php';
        if (currentPage === '') currentPage = 'index.php';
        
        var activePage = '';
        if (currentPage === 'index.html' || currentPage === 'index.php') {
            activePage = 'home';
        } else if (currentPage === 'consultation.html') {
            activePage = 'consultation';
        } else if (currentPage === 'about.html' || currentPage === 'about.php') {
            activePage = 'about';
        } else if (currentPage === 'blog.html') {
            activePage = 'news';
        } else if (currentPage === 'contact.html') {
            activePage = 'contact';
        }
        
        // Highlight active link
        if (activePage) {
            setTimeout(function() {
                var activeLink = document.querySelector('.nav-link[data-page="' + activePage + '"]');
                if (activeLink) {
                    activeLink.classList.add('active');
                }
            }, 100);
        }
    }
    
    // Function to insert menu with error handling
    function insertMenu() {
        try {
        var menuContainer = document.getElementById('menu-container');
        if (menuContainer) {
            menuContainer.innerHTML = menuHTML;
            setActivePage();
        } else {
                // Fallback: try to find topbar or create container
            var topbar = document.querySelector('.topbar');
            if (topbar) {
                topbar.insertAdjacentHTML('afterend', menuHTML);
                setActivePage();
            } else {
                    // Last resort: insert before body content
                    var body = document.body;
                    if (body && body.firstChild) {
                        var div = document.createElement('div');
                        div.id = 'menu-container';
                        div.innerHTML = menuHTML;
                        body.insertBefore(div, body.firstChild);
                        setActivePage();
                    } else {
                        console.warn('Unable to find menu container - menu may not display correctly');
                    }
                }
            }
        } catch (error) {
            console.error('Error loading menu:', error);
            // Fallback menu with Registration (same structure as main menu)
            var menuContainer = document.getElementById('menu-container');
            if (menuContainer) {
                menuContainer.innerHTML = '<div class="container-fluid nav-bar px-0 px-lg-4 py-lg-0"><div class="container"><nav class="navbar navbar-expand-lg navbar-light"><a href="index.php" class="navbar-brand p-0"><img src="img/orchideelogo.png" alt="Orchidee Logo"></a><button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse"><span class="fa fa-bars"></span></button><div class="collapse navbar-collapse" id="navbarCollapse"><div class="navbar-nav mx-0 mx-lg-auto"><a href="index.php" class="nav-item nav-link">Home</a><a href="courses.php" class="nav-item nav-link">Courses</a><div class="nav-item dropdown"><a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Registration</a><div class="dropdown-menu"><a href="registration-next-session.php" class="dropdown-item">Registration for the Next Session</a><a href="nclex-registration-form.php" class="dropdown-item">NCLEX Registration Form</a></div></div><a href="consultation.html" class="nav-item nav-link">Book a Consultation</a><a href="about.php" class="nav-item nav-link">About Us</a><a href="blog.php" class="nav-item nav-link">Blog</a><a href="contact.html" class="nav-item nav-link">Contact</a></div><div class="d-none d-xl-flex ps-4"><a href="login.php" class="btn btn-outline-primary me-2">Login</a><a href="register.php" class="btn btn-primary">Register</a></div></div></nav></div></div>';
            }
        }
    }
    
    // Wait for DOM to be ready with timeout fallback
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', insertMenu);
        // Fallback timeout in case DOMContentLoaded never fires
        setTimeout(function() {
            if (document.getElementById('menu-container') && !document.getElementById('menu-container').innerHTML.trim()) {
                insertMenu();
            }
        }, 1000);
    } else {
        // DOM already loaded
        insertMenu();
    }
})();
