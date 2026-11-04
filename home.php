
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Event-Pro</title>

    <style>
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b;
            --accent-text: #000000;
            --card-bg: rgba(30, 30, 32, 0.6);
        }

        * ,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont,
                         "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* --- ANIMATED BACKGROUND ORBS --- */

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.4;
            animation: float 15s infinite ease-in-out alternate;
            z-index: -1;
            pointer-events: none;
        }

        .orb-1 {
            width: 400px;
            height: 400px;
            background: var(--accent-color);
            top: -10%;
            left: -5%;
        }

        .orb-2 {
            width: 500px;
            height: 500px;
            background: #8d99ae;
            top: 40%;
            right: -10%;
            animation-delay: -4s;
        }

        .orb-3 {
            width: 350px;
            height: 350px;
            background: rgba(248, 243, 43, 0.4);
            bottom: -10%;
            left: 30%;
            animation-delay: -8s;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(50px, -80px) scale(1.1);
            }
        }

        /* --- TOP NAVIGATION BAR --- */

        nav {
            position: fixed;
            top: 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 50px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            z-index: 100;
        }

        .logo {
            font-size: 22px;
            font-weight: 700;
            color: var(--accent-color);
            text-decoration: none;
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .nav-btn {
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            transition: color 0.3s;
        }

        .nav-btn:hover {
            color: var(--accent-color);
        }

        .btn-register {
            background: var(--text-main);
            color: #000;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 600;
        }

        .btn-register:hover {
            background: var(--accent-color);
            color: var(--accent-text);
        }

        /* Mobile menu button: hidden on desktop */

        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            padding: 0;
            background: transparent;
            color: var(--text-main);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 12px;
            font-size: 24px;
            cursor: pointer;
        }

        /* --- HERO SECTION --- */

        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 100px 20px 50px;
        }

        .hero h1 {
            font-size: 64px;
            font-weight: 800;
            margin: 0 0 20px;
            letter-spacing: -1px;
            line-height: 1.15;
            background: linear-gradient(to right, #ffffff, #8d99ae);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 20px;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 0 40px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
        }

        .btn-primary {
            background: var(--accent-color);
            color: var(--accent-text);
            padding: 16px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s;
            border: 2px solid var(--accent-color);
        }

        .btn-primary:hover {
            background: transparent;
            color: var(--accent-color);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            color: var(--text-main);
            padding: 16px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        /* --- SECTION HEADERS --- */

        .section-title {
            text-align: center;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 50px;
            color: var(--text-main);
        }

        #about,
        #features {
            scroll-margin-top: 90px;
        }

        /* --- ABOUT SECTION --- */

        .about {
            padding: 100px 20px;
            max-width: 900px;
            margin: 0 auto;
        }

        .about-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 50px;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .about-card p {
            font-size: 18px;
            color: var(--text-muted);
            line-height: 1.8;
            margin-bottom: 0;
        }

        .about-card strong {
            color: var(--text-main);
        }

        /* --- FEATURES GRID --- */

        .features {
            padding: 100px 50px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 40px 30px;
            border-radius: 24px;
            text-align: center;
            transition: transform 0.3s, border-color 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: rgba(248, 243, 43, 0.3);
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 20px;
            display: inline-block;
        }

        .feature-card h3 {
            font-size: 20px;
            margin: 0 0 10px;
            color: var(--text-main);
        }

        .feature-card p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.6;
        }

        /* --- FOOTER --- */

        footer {
            text-align: center;
            padding: 40px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 50px;
            background: rgba(0, 0, 0, 0.3);
        }

        footer p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        footer strong {
            color: var(--accent-color);
            letter-spacing: 0.5px;
        }

        /* --- MOBILE RESPONSIVE DESIGN --- */

        @media (max-width: 768px) {

            nav {
                padding: 12px 18px;
            }

            .logo {
                font-size: 20px;
            }

            .menu-toggle {
                display: inline-flex;
            }

            /* Hidden menu that opens when hamburger is tapped */

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
                padding: 14px 18px 20px;
                background: rgba(10, 10, 10, 0.97);
                border-bottom: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 12px 26px rgba(0, 0, 0, 0.4);
            }

            .nav-links.is-open {
                display: flex;
            }

            .nav-btn {
                font-size: 15px;
                min-height: 44px;
                display: flex;
                align-items: center;
                padding: 10px 14px;
                border-radius: 12px;
            }

            .btn-register {
                justify-content: center;
                margin-top: 5px;
            }

            .hero {
                min-height: 100vh;
                min-height: 100svh;
                padding: 115px 20px 65px;
            }

            .hero h1 {
                font-size: clamp(32px, 9vw, 46px);
                line-height: 1.12;
            }

            .hero p {
                font-size: 16px;
                margin-bottom: 28px;
            }

            .hero-buttons {
                flex-wrap: wrap;
                justify-content: center;
                width: 100%;
            }

            .btn-primary,
            .btn-secondary {
                text-align: center;
            }

            .section-title {
                font-size: clamp(27px, 7vw, 34px);
                margin-bottom: 30px;
            }

            .features,
            .about {
                padding: 65px 18px;
            }

            .features-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 18px;
            }

            .feature-card {
                padding: 30px 22px;
            }

            .about-card {
                padding: 28px 20px;
            }

            .about-card p {
                font-size: 16px;
            }

            /* Smaller background effects for phones */

            .orb-1 {
                width: 230px;
                height: 230px;
            }

            .orb-2 {
                width: 240px;
                height: 240px;
            }

            .orb-3 {
                width: 200px;
                height: 200px;
            }
        }

        /* --- EXTRA SMALL PHONES --- */

        @media (max-width: 420px) {

            .hero-buttons {
                flex-direction: column;
                align-items: stretch;
                max-width: 320px;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
                padding: 13px 18px;
            }

            footer p {
                font-size: 12px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .orb {
                animation: none;
            }

            html {
                scroll-behavior: auto;
            }
        }
    </style>
</head>

<body>

    <!-- Animated background -->

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Navigation -->

    <nav>
        <a href="#" class="logo">Event-Pro</a>

        <button
            class="menu-toggle"
            type="button"
            aria-label="Open navigation menu"
            aria-controls="primary-navigation"
            aria-expanded="false"
        >☰</button>

        <div class="nav-links" id="primary-navigation">
            <a href="#about" class="nav-btn">About</a>
            <a href="#features" class="nav-btn">Features</a>
            <a href="pages/auth/login.php" class="nav-btn">Sign In</a>
            <a href="pages/auth/register.php" class="nav-btn btn-register">
                Get Started
            </a>
        </div>
    </nav>

    <!-- Hero section -->

    <section class="hero">
        <h1>Next-Gen Seminar Management.</h1>

        <p>
            Seamlessly organize events, track participants with smart QR codes,
            and manage your entire seminar workflow from a single,
            beautiful dashboard.
        </p>

        <div class="hero-buttons">
            <a href="pages/auth/register.php" class="btn-primary">
                Register Now
            </a>

            <a href="pages/auth/login.php" class="btn-secondary">
                Login to Portal
            </a>
        </div>
    </section>

    <!-- About section -->

    <section id="about" class="about">
        <h2 class="section-title">About the Platform</h2>

        <div class="about-card">
            <p>
                <strong>Event-Pro</strong> was engineered to eliminate
                the friction of modern event tracking. Gone are the days of
                manual paper sign-ins and messy spreadsheets. We provide a
                streamlined, secure, and beautiful interface for both
                administrators and attendees, bridging the gap between
                digital registration and physical, in-person verification.
            </p>
        </div>
    </section>

    <!-- Features section -->

    <section id="features" class="features">
        <h2 class="section-title">Powerful Features</h2>

        <div class="features-grid">

            <div class="feature-card">
                <div class="feature-icon">📷</div>
                <h3>Smart QR Scanning</h3>
                <p>
                    Generate and scan unique QR codes for instant,
                    touchless participant check-ins right at the door.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">⏱️</div>
                <h3>Automated Attendance</h3>
                <p>
                    Intelligent status tracking automatically updates
                    participants from Registered, to Checked-In, to Completed.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Brute-Force Protection</h3>
                <p>
                    Built-in security automatically locks out accounts
                    after 3 failed login attempts to protect user data.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Live Dashboards</h3>
                <p>
                    Monitor attendance, track registered users,
                    and manage upcoming events in real-time
                    from an elegant UI.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Capacity Limits</h3>
                <p>
                    Prevent overcrowding by setting dynamic participant
                    limits when creating new seminars.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔐</div>
                <h3>Role-Based Access</h3>
                <p>
                    Secure routing ensures attendees see their schedules,
                    while admins have full control over the ecosystem.
                </p>
            </div>

        </div>
    </section>

    <!-- Footer -->

    <footer>
        <p>
            Designed and Built by
            <strong>Prince Jameel Doble</strong>
        </p>

        <p style="margin-top: 8px; opacity: 0.5;">
            &copy; <?php echo date("Y"); ?> Event-Pro System.
            All rights reserved.
        </p>
    </footer>

    <!-- Mobile navigation JavaScript -->

    <script>
        const menuToggle = document.querySelector('.menu-toggle');
        const navigation = document.querySelector('#primary-navigation');

        function closeMobileMenu() {
            navigation.classList.remove('is-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Open navigation menu');
            menuToggle.textContent = '☰';
        }

        menuToggle.addEventListener('click', () => {
            const isOpen = navigation.classList.toggle('is-open');

            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute(
                'aria-label',
                isOpen ? 'Close navigation menu' : 'Open navigation menu'
            );

            menuToggle.textContent = isOpen ? '✕' : '☰';
        });

        navigation.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeMobileMenu();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeMobileMenu();
            }
        });
    </script>

</body>
</html>
