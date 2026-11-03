<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Event-Pro</title>
    <style>
        /* :root acts as a storage area for our color palette. 
           Change the color here at once */
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b; /* The signature Event-Pro Yellow */
            --accent-text: #000000;
            --card-bg: rgba(30, 30, 32, 0.6); /* Semi-transparent black for the glass cards */
        }

        html {
            scroll-behavior: smooth; 
        }

        body { 
            margin: 0; 
            /* Apple/macOS system fonts for a clean, modern look */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            overflow-x: hidden; /* Prevents the page from scrolling left/right if orbs float out of bounds */
            line-height: 1.6;
        }

        /* ---  ANIMATED BACKGROUND ORBS --- */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px); /* Blurs the circle into a soft glow */
            opacity: 0.4;
            animation: float 15s infinite ease-in-out alternate; /* Triggers the movement animation below */
            z-index: -1; /* Keeps the orbs pushed into the background behind the text */
        }

        /* Individual sizes, colors, and starting positions for each orb */
        .orb-1 { width: 400px; height: 400px; background: var(--accent-color); top: -10%; left: -5%; }
        .orb-2 { width: 500px; height: 500px; background: #8d99ae; top: 40%; right: -10%; animation-delay: -4s; }
        .orb-3 { width: 350px; height: 350px; background: rgba(248, 243, 43, 0.4); bottom: -10%; left: 30%; animation-delay: -8s; }

        /* The actual movement instructions: Start at 0, end slightly shifted */
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, -80px) scale(1.1); }
        }

        /* --- TOP NAVIGATION BAR --- */
        nav {
            position: fixed; /* Keeps the nav bar permanently at the top of the screen */
            top: 0;
            width: 100%;
            display: flex;
            justify-content: space-between; /* Pushes Logo to the left, links to the right */
            align-items: center;
            padding: 20px 50px;
            box-sizing: border-box;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(20px); /* The Frosted Glass effect over the background */
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            z-index: 100; /* Extremely high number ensures the nav is always on top of everything else */
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
            gap: 24px; /* Space between each nav link */
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

        /* --- HERO SECTION (Main Landing View) --- */
        .hero {
            height: 100vh; /* Takes up exactly 100% of the user's screen height */
            display: flex;
            flex-direction: column; /* Stacks items vertically */
            justify-content: center; /* Centers vertically */
            align-items: center; /* Centers horizontally */
            text-align: center;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 64px;
            font-weight: 800;
            margin: 0 0 20px 0;
            letter-spacing: -1px;
            /* Creates a cool metallic white-to-gray gradient on the text */
            background: linear-gradient(to right, #ffffff, #8d99ae);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 20px;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 0 40px 0;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
        }

        /* The yellow primary button */
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
            transform: translateY(-2px); /* Makes the button float up slightly */
        }

        /* The glass secondary button */
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
            border: 1px solid rgba(255,255,255,0.1);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
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

        /* --- ABOUT SECTION --- */
        .about {
            padding: 100px 20px;
            max-width: 900px;
            margin: 0 auto; /* Centers the whole block horizontally */
        }

        .about-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 50px;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); /* Big soft shadow underneath */
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

        /* CSS Grid automatically calculates how many cards fit on a row based on screen size */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Auto-wraps on mobile */
            gap: 30px;
        }

        .feature-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 40px 30px;
            border-radius: 24px;
            text-align: center;
            transition: transform 0.3s, border-color 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px); /* Floats up */
            border-color: rgba(248, 243, 43, 0.3); /* Border lights up yellow */
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 20px;
            display: inline-block;
        }

        .feature-card h3 {
            font-size: 20px;
            margin: 0 0 10px 0;
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
            border-top: 1px solid rgba(255,255,255,0.05);
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

        /* --- RESPONSIVE DESIGN (MOBILE PHONES) --- */
        /* These rules ONLY activate if the screen is smaller than 768px wide */
        @media (max-width: 768px) {
            .hero h1 { font-size: 42px; } /* Shrinks giant text */
            .hero p { font-size: 16px; }
            nav { padding: 15px 20px; }
            .nav-links { gap: 12px; }
            .nav-btn { font-size: 12px; }
            .features, .about { padding: 60px 20px; }
            .about-card { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <nav>
        <a href="#" class="logo">Event-Pro</a>
        <div class="nav-links">
            <a href="#about" class="nav-btn">About</a>
            <a href="#features" class="nav-btn">Features</a>
            
            <a href="pages/auth/login.php" class="nav-btn">Sign In</a>
            <a href="pages/auth/register.php" class="nav-btn btn-register">Get Started</a>
        </div>
    </nav>

    <section class="hero">
        <h1>Next-Gen Seminar Management.</h1>
        <p>Seamlessly organize events, track participants with smart QR codes, and manage your entire seminar workflow from a single, beautiful dashboard.</p>
        
        <div class="hero-buttons">
            <a href="pages/auth/register.php" class="btn-primary">Register Now</a>
            <a href="pages/auth/login.php" class="btn-secondary">Login to Portal</a>
        </div>
    </section>

    <section id="about" class="about">
        <h2 class="section-title">About the Platform</h2>
        <div class="about-card">
            <p><strong>Event-Pro</strong> was engineered to eliminate the friction of modern event tracking. Gone are the days of manual paper sign-ins and messy spreadsheets. We provide a streamlined, secure, and beautiful interface for both administrators and attendees, bridging the gap between digital registration and physical, in-person verification.</p>
        </div>
    </section>

    <section id="features" class="features">
        <h2 class="section-title">Powerful Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📷</div>
                <h3>Smart QR Scanning</h3>
                <p>Generate and scan unique QR codes for instant, touchless participant check-ins right at the door.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⏱️</div>
                <h3>Automated Attendance</h3>
                <p>Intelligent status tracking automatically updates participants from Registered, to Checked-In, to Completed.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Brute-Force Protection</h3>
                <p>Built-in security automatically locks out accounts after 3 failed login attempts to protect user data.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Live Dashboards</h3>
                <p>Monitor attendance, track registered users, and manage upcoming events in real-time from an elegant UI.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Capacity Limits</h3>
                <p>Prevent overcrowding by setting dynamic participant limits when creating new seminars.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔐</div>
                <h3>Role-Based Access</h3>
                <p>Secure routing ensures attendees see their schedules, while admins have full control over the ecosystem.</p>
            </div>
        </div>
    </section>

    <footer>
        <p>Designed and Built by <strong>Prince Jameel Doble</strong></p>
        <p style="margin-top: 8px; opacity: 0.5;">&copy; <?php echo date("Y"); ?> Event-Pro System. All rights reserved.</p>
    </footer>

</body>
</html>