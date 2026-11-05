
<?php
session_start();
require_once '../../config/db.php';

/* ADMIN ACCESS ONLY */
if (
    empty($_SESSION['username']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* PRESELECT EVENT FROM ADMIN DASHBOARD */
$target_event_id = filter_var(
    $_GET['event_id'] ?? null,
    FILTER_VALIDATE_INT
) ?: 0;

/* LOAD EVENTS */
$events = mysqli_query(
    $conn,
    "SELECT event_id, event_name, event_status
     FROM events
     ORDER BY event_date DESC"
);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Entrance Scanner | Event-Pro</title>

    <!-- EXISTING LOCAL QR LIBRARY -->
    <script src="../../assets/html5-qrcode.js"></script>

    <style>
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b;
            --accent-text: #000000;
            --danger-color: #ff453a;
            --success-color: #32d74b;
        }

        * {
            box-sizing: border-box;
        }

        html {
            width: 100%;
            min-height: 100%;
        }

        body {
            margin: 0;
            padding: 24px 16px;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
            position: relative;
        }

        /* ANIMATED BACKGROUND */

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .4;
            animation: float 10s infinite ease-in-out alternate;
            z-index: 0;
            pointer-events: none;
        }

        .orb-1 {
            width: 350px;
            height: 350px;
            background: #8d99ae;
            top: 10%;
            right: 20%;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            background: var(--accent-color);
            bottom: 10%;
            left: 15%;
            animation-delay: -3s;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(-30px, 40px) scale(1.1);
            }
        }

        /* SCANNER CARD */

        .scanner-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 460px;
            padding: 30px;
            text-align: center;
            background: rgba(30, 30, 32, .65);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,.5);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
        }

        h1 {
            color: var(--text-main);
            margin: 0 0 8px;
            font-size: 26px;
            font-weight: 600;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0 0 25px;
            line-height: 1.5;
        }

        /* EVENT SELECTION */

        select {
            width: 100%;
            padding: 14px 16px;
            margin-bottom: 20px;
            background: rgba(0,0,0,.4);
            color: var(--text-main);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
            font-size: 16px;
            outline: none;
        }

        select option {
            background: #1c1c1e;
            color: var(--text-main);
        }

        select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(248,243,43,.1);
        }

        /* SCANNER TABS */

        .tab-container {
            display: flex;
            gap: 6px;
            padding: 6px;
            margin-bottom: 20px;
            background: rgba(0,0,0,.4);
            border: 1px solid rgba(255,255,255,.05);
            border-radius: 12px;
        }

        .tab-btn {
            flex: 1;
            min-width: 0;
            min-height: 46px;
            padding: 12px 8px;
            color: var(--text-muted);
            background: transparent;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .tab-btn.active {
            background: var(--accent-color);
            color: var(--accent-text);
        }

        .tab-btn:disabled {
            opacity: .65;
            cursor: wait;
        }

        /* QR READER */

        #reader {
            display: block;
            width: 100%;
            min-height: 230px;
            background: rgba(0,0,0,.5);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            overflow: hidden;
        }

        #reader video {
            width: 100% !important;
            height: auto !important;
            max-height: 380px;
            object-fit: contain !important;
            border-radius: 12px;
        }

        #reader canvas {
            max-width: 100%;
        }

        #reader img {
            max-width: 100%;
            height: auto;
        }

        /* FILE UPLOAD AREA */

        #file-ui {
            display: none;
            padding: 20px 12px;
            margin-top: 15px;
            background: rgba(0,0,0,.2);
            border: 2px dashed rgba(255,255,255,.2);
            border-radius: 16px;
        }

        .file-description {
            margin: 0 0 15px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .btn-action {
            width: 100%;
            min-height: 48px;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            background: var(--accent-color);
            color: #000;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-action:disabled {
            opacity: .55;
            cursor: wait;
        }

        /* STATUS AND DIAGNOSTIC MESSAGES */

        .status-msg {
            margin: 15px 0 0;
            padding: 12px;
            border-radius: 10px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .status-msg.error {
            color: var(--danger-color);
            background: rgba(255,69,58,.09);
        }

        .status-msg.success {
            color: var(--success-color);
            background: rgba(50,215,75,.09);
        }

        .status-msg.info {
            color: var(--accent-color);
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            color: var(--accent-color);
        }

        /* MOBILE RESPONSIVENESS */

        @media (max-width: 600px) {
            body {
                padding: 20px 12px;
                align-items: flex-start;
            }

            .scanner-card {
                margin: 20px auto;
                padding: 25px 17px;
                border-radius: 20px;
            }

            h1 {
                font-size: 24px;
            }

            #reader {
                min-height: 200px;
            }

            #reader video {
                max-height: 340px;
            }

            .orb-1 {
                width: 220px;
                height: 220px;
            }

            .orb-2 {
                width: 200px;
                height: 200px;
            }
        }
    </style>
</head>

<body>

    <!-- BACKGROUND -->

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <!-- SCANNER -->

    <main class="scanner-card">

        <h1>Entrance Scanner</h1>

        <p class="subtitle">
            Fast-track verification for Event-Pro
        </p>

        <!-- EVENT DROPDOWN -->

        <select id="event_select">
            <option value="">
                -- Select Event to Scan For --
            </option>

            <?php while ($ev = mysqli_fetch_assoc($events)): ?>

                <?php
                    $eventId = (int)$ev['event_id'];

                    $selected = (
                        $eventId === $target_event_id
                    );

                    $statusLabel = (
                        $ev['event_status'] === 'Ended'
                    ) ? ' (Ended)' : '';
                ?>

                <option
                    value="<?= $eventId ?>"
                    <?= $selected ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars(
                        $ev['event_name'] . $statusLabel,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </option>

            <?php endwhile; ?>

        </select>

        <!-- MODE BUTTONS -->

        <div class="tab-container">

            <button
                type="button"
                class="tab-btn active"
                id="tab-cam"
            >
                Live Camera
            </button>

            <button
                type="button"
                class="tab-btn"
                id="tab-file"
            >
                Upload Image
            </button>

        </div>

        <!-- SHARED QR SCANNER -->

        <div id="reader"></div>

        <!-- FILE UPLOAD CONTROLS -->

        <div id="file-ui">

            <p class="file-description">
                Select the original downloaded
                Event-Pro QR ticket PNG for best results.
            </p>

            <input
                type="file"
                id="file-input"
                accept="image/png,image/jpeg,image/webp,image/*"
                hidden
            >

            <button
                type="button"
                class="btn-action"
                id="choose-file"
            >
                Select QR Image
            </button>

        </div>

        <!-- SCANNER STATUS -->

        <p
            id="scanner-status"
            class="status-msg info"
            role="status"
            aria-live="polite"
        >
            Preparing QR scanner...
        </p>

        <a
            href="admin.php"
            class="back-link"
        >
            ← Back to Dashboard
        </a>

    </main>

    <script>
        /*
         * EVENT-PRO QR SCANNER
         *
         * The backend verification file stays unchanged.
         * Both camera and image upload use handleSuccess().
         */

        const reader = document.getElementById('reader');

        const fileUi = document.getElementById('file-ui');

        const fileInput = document.getElementById('file-input');

        const chooseFileBtn = document.getElementById('choose-file');

        const cameraTab = document.getElementById('tab-cam');

        const fileTab = document.getElementById('tab-file');

        const scannerStatus = document.getElementById('scanner-status');

        const eventSelect = document.getElementById('event_select');

        let html5QrCode = null;

        let isCameraRunning = false;

        let isSwitching = false;

        let processingScan = false;

        let currentMode = 'camera';

        /*
         * SHOW AN ACCURATE STATUS MESSAGE
         */

        function showStatus(message, type = 'info') {
            scannerStatus.textContent = message;

            scannerStatus.className =
                'status-msg ' + type;
        }

        /*
         * ENABLE / DISABLE MODE BUTTONS
         */

        function setSwitching(value) {
            isSwitching = value;

            cameraTab.disabled = value;
            fileTab.disabled = value;
        }

        /*
         * CREATE QR LIBRARY INSTANCE
         */

        function initializeScanner() {

            if (typeof Html5Qrcode === 'undefined') {
                showStatus(
                    'QR scanning library did not load. ' +
                    'Check assets/html5-qrcode.js.',
                    'error'
                );

                return false;
            }

            try {
                const options = {
                    verbose: false
                };

                if (
                    typeof Html5QrcodeSupportedFormats !== 'undefined'
                ) {
                    options.formatsToSupport = [
                        Html5QrcodeSupportedFormats.QR_CODE
                    ];
                }

                html5QrCode = new Html5Qrcode(
                    'reader',
                    options
                );

                return true;

            } catch (error) {
                showStatus(
                    'Unable to initialize QR scanner: ' +
                    String(error),
                    'error'
                );

                return false;
            }
        }

        /*
         * STOP CAMERA BEFORE FILE SCANNING
         */

        async function stopCamera() {

            if (!html5QrCode) {
                return;
            }

            if (isCameraRunning) {

                try {
                    await html5QrCode.stop();
                } catch (error) {
                    console.error(
                        'Camera stop error:',
                        error
                    );
                }

                isCameraRunning = false;
            }

            try {
                html5QrCode.clear();
            } catch (error) {
                console.warn(
                    'Scanner clear message:',
                    error
                );
            }
        }

        /*
         * PROCESS SUCCESSFULLY DECODED QR
         */

        async function handleSuccess(decodedText) {

            if (processingScan) {
                return;
            }

            const eventId = eventSelect.value;

            if (!eventId) {
                showStatus(
                    'Please select a seminar before scanning.',
                    'error'
                );

                return;
            }

            processingScan = true;

            showStatus(
                'QR code recognized. Verifying attendance...',
                'success'
            );

            const targetUrl =
                'verify_qr.php?qr_data=' +
                encodeURIComponent(decodedText) +
                '&event_id=' +
                encodeURIComponent(eventId);

            if (isCameraRunning) {
                try {
                    await html5QrCode.stop();
                } catch (error) {
                    console.warn(
                        'Camera stopped during verification:',
                        error
                    );
                }

                isCameraRunning = false;
            }

            window.location.href = targetUrl;
        }

        /*
         * START THE CAMERA
         *
         * No fixed 200x200 qrbox:
         * the library scans the full camera frame.
         */

        async function startCamera() {

            if (!html5QrCode || processingScan) {
                return;
            }

            reader.style.display = 'block';
            fileUi.style.display = 'none';

            showStatus(
                'Starting your camera...',
                'info'
            );

            const config = {
                fps: 10,
                disableFlip: false
            };

            try {
                await html5QrCode.start(
                    { facingMode: 'environment' },
                    config,
                    handleSuccess,
                    function () {
                        /* Normal frames without a QR:
                           do not show an error for every frame. */
                    }
                );

                isCameraRunning = true;

                showStatus(
                    'Camera ready. Place the complete QR ' +
                    'code inside the camera view.',
                    'info'
                );

            } catch (error) {

                isCameraRunning = false;

                showStatus(
                    'Camera unavailable: ' +
                    String(error) +
                    '. You may use Upload Image instead.',
                    'error'
                );

                console.error(
                    'Event-Pro camera error:',
                    error
                );
            }
        }

        /*
         * SWITCH BETWEEN CAMERA AND UPLOAD
         */

        async function switchTab(mode) {

            if (
                isSwitching ||
                processingScan ||
                !html5QrCode
            ) {
                return;
            }

            setSwitching(true);

            try {

                await stopCamera();

                currentMode = mode;

                cameraTab.classList.toggle(
                    'active',
                    mode === 'camera'
                );

                fileTab.classList.toggle(
                    'active',
                    mode === 'file'
                );

                /*
                 * IMPORTANT FIX:
                 * Keep #reader visible in upload mode.
                 * The QR library renders and scans
                 * the selected image inside this element.
                 */

                reader.style.display = 'block';

                if (mode === 'file') {

                    fileUi.style.display = 'block';

                    reader.style.minHeight = '0';

                    showStatus(
                        'Upload the original QR image ' +
                        'to test detection.',
                        'info'
                    );

                } else {

                    fileUi.style.display = 'none';

                    reader.style.minHeight = '';

                    await startCamera();
                }

            } catch (error) {

                showStatus(
                    'Unable to switch scanner mode: ' +
                    String(error),
                    'error'
                );

                console.error(error);

            } finally {

                setSwitching(false);
            }
        }

        /*
         * QR IMAGE UPLOAD
         */

        async function scanUploadedFile(file) {

            if (
                !file ||
                !html5QrCode ||
                processingScan
            ) {
                return;
            }

            if (!eventSelect.value) {

                showStatus(
                    'Please select a seminar before ' +
                    'uploading a QR image.',
                    'error'
                );

                return;
            }

            chooseFileBtn.disabled = true;

            showStatus(
                'Reading the selected QR image...',
                'info'
            );

            try {

                /*
                 * The camera must be stopped.
                 * #reader must remain visible.
                 */

                await stopCamera();

                reader.style.display = 'block';

                const decodedText =
                    await html5QrCode.scanFile(file, true);

                await handleSuccess(decodedText);

            } catch (error) {

                /*
                 * Unlike the old scanner, this shows
                 * the real error instead of replacing
                 * every failure with "QR not detected".
                 */

                const details = String(error);

                console.error(
                    'Event-Pro image scanning error:',
                    error
                );

                showStatus(
                    'QR image could not be decoded. ' +
                    'Details: ' + details,
                    'error'
                );

            } finally {

                chooseFileBtn.disabled = false;

                /*
                 * Allow the same image to be selected
                 * again for testing.
                 */

                fileInput.value = '';
            }
        }

        /*
         * BUTTON EVENTS
         */

        cameraTab.addEventListener(
            'click',
            function () {
                switchTab('camera');
            }
        );

        fileTab.addEventListener(
            'click',
            function () {
                switchTab('file');
            }
        );

        chooseFileBtn.addEventListener(
            'click',
            function () {

                if (!eventSelect.value) {

                    showStatus(
                        'Please select a seminar first.',
                        'error'
                    );

                    return;
                }

                fileInput.click();
            }
        );

        fileInput.addEventListener(
            'change',
            function (event) {

                const file = event.target.files[0];

                if (file) {
                    scanUploadedFile(file);
                }
            }
        );

        /*
         * INITIAL STARTUP
         */

        document.addEventListener(
            'DOMContentLoaded',
            async function () {

                if (!initializeScanner()) {
                    return;
                }

                await switchTab('camera');
            }
        );

        /*
         * RELEASE CAMERA ON PAGE LEAVE
         */

        window.addEventListener(
            'pagehide',
            function () {

                if (isCameraRunning && html5QrCode) {
                    html5QrCode.stop().catch(
                        function () {}
                    );
                }
            }
        );
    </script>

</body>
</html>
