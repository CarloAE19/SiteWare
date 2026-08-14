<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$code = isset($_GET['code']) ? (int) $_GET['code'] : (http_response_code() ?: 404);
if (!in_array($code, [400, 401, 403, 404, 500, 503])) {
    $code = 404;
}

http_response_code($code);

$errors = [
    400 => [
        'title' => 'Bad Request',
        'subtext' => 'The server was unable to understand your request due to invalid or malformed syntax.',
        'badge' => 'Error 400',
        'color' => '#d97706',
        'glow' => 'rgba(217, 119, 6, 0.15)',
        'icon' => 'bi-exclamation-octagon-fill'
    ],
    401 => [
        'title' => 'Unauthorized Access',
        'subtext' => 'You need an active session to view this protected resource.',
        'badge' => 'Authentication Required',
        'color' => '#ef4444',
        'glow' => 'rgba(239, 68, 68, 0.15)',
        'icon' => 'bi-shield-lock-fill'
    ],
    403 => [
        'title' => 'Access Restricted',
        'subtext' => 'You do not have permission or security clearance to access this destination.',
        'badge' => 'Forbidden 403',
        'color' => '#dc2626',
        'glow' => 'rgba(220, 38, 38, 0.15)',
        'icon' => 'bi-shield-slash-fill'
    ],
    404 => [
        'title' => 'Page Not Found',
        'subtext' => 'The page you are looking for might have been removed, moved, or is temporarily unavailable.',
        'badge' => 'Not Found 404',
        'color' => '#2563eb',
        'glow' => 'rgba(37, 99, 235, 0.15)',
        'icon' => 'bi-compass-fill'
    ],
    500 => [
        'title' => 'Internal Server Error',
        'subtext' => 'The server encountered an unexpected error and was unable to fulfill your request.',
        'badge' => 'Server Error 500',
        'color' => '#ef4444',
        'glow' => 'rgba(239, 68, 68, 0.15)',
        'icon' => 'bi-hdd-network-fill'
    ],
    503 => [
        'title' => 'Service Unavailable',
        'subtext' => 'The system is temporarily undergoing maintenance. Please check back shortly.',
        'badge' => 'Maintenance 503',
        'color' => '#f59e0b',
        'glow' => 'rgba(245, 158, 11, 0.15)',
        'icon' => 'bi-wrench-adjustable-circle-fill'
    ]
];

$errorData = $errors[$code] ?? $errors[404];
$isLoggedIn = isset($_SESSION['user_id']);
$homeUrl = $isLoggedIn ? '/CIMS/dashboard' : '/CIMS/login';
$homeText = $isLoggedIn ? 'Go to Dashboard' : 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($code . ' — ' . $errorData['title']) ?> | SiteWare</title>
    <link rel="icon" type="image/png" href="/CIMS/assets/LogoGB.png">

    <!-- Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --theme-color:
                <?= $errorData['color'] ?>
            ;
            --theme-glow:
                <?= $errorData['glow'] ?>
            ;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-border: #e2e8f0;
            --bg-gradient: radial-gradient(at 0% 0%, #f1f5f9 0px, transparent 50%),
                radial-gradient(at 100% 0%, #e2e8f0 0px, transparent 50%),
                radial-gradient(at 50% 100%, #eff6ff 0px, transparent 50%),
                #f8fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: var(--text-dark);
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient blur shapes */
        .bg-mesh-blur {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            z-index: 0;
            pointer-events: none;
            opacity: 0.65;
        }

        .mesh-1 {
            width: 420px;
            height: 420px;
            top: -120px;
            right: -100px;
            background: rgba(37, 99, 235, 0.12);
        }

        .mesh-2 {
            width: 360px;
            height: 360px;
            bottom: -90px;
            left: -90px;
            background: rgba(220, 38, 38, 0.08);
        }

        .error-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 540px;
        }

        .error-card {
            background: var(--card-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 2.5rem 2.25rem 2.25rem;
            box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.08),
                0 1px 3px rgba(0, 0, 0, 0.04);
            text-align: center;
        }

        /* Error Hero Display */
        .error-hero {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .error-number {
            font-size: 5.5rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, var(--theme-color), #0f172a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .error-icon-float {
            position: absolute;
            bottom: 2px;
            right: -18px;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--theme-color);
            box-shadow: 0 8px 18px -2px var(--theme-glow), 0 2px 6px rgba(0, 0, 0, 0.06);
            border: 2px solid #ffffff;
        }

        .error-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.65rem;
        }

        .error-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2rem;
            max-width: 430px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Action Buttons */
        .cta-container {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-modern-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #0f172a;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.925rem;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid transparent;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
            transition: all 0.2s ease;
            min-width: 160px;
        }

        .btn-modern-primary:hover {
            background: #1e293b;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.18);
        }

        .btn-modern-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #ffffff;
            color: #334155;
            font-weight: 600;
            font-size: 0.925rem;
            padding: 12px 22px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-modern-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .footer-note {
            margin-top: 1.5rem;
            font-size: 0.825rem;
            color: #64748b;
            line-height: 1.5;
        }

        .footer-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        /* Mobile & Tablet Responsiveness */
        @media (max-width: 576px) {
            body {
                padding: max(1rem, env(safe-area-inset-top)) max(0.875rem, env(safe-area-inset-right)) max(1rem, env(safe-area-inset-bottom)) max(0.875rem, env(safe-area-inset-left));
            }

            .error-card {
                padding: 2rem 1.25rem 1.75rem;
                border-radius: 22px;
            }

            .error-number {
                font-size: 4rem;
            }

            .error-icon-float {
                width: 34px;
                height: 34px;
                font-size: 1.1rem;
                right: -12px;
                bottom: 0;
                border-radius: 10px;
            }

            .error-title {
                font-size: 1.35rem;
            }

            .error-desc {
                font-size: 0.875rem;
                margin-bottom: 1.5rem;
            }

            .cta-container {
                flex-direction: column-reverse;
                gap: 10px;
            }

            .btn-modern-primary,
            .btn-modern-secondary {
                width: 100%;
                padding: 12px 18px;
                font-size: 0.9rem;
            }

            .btn-modern-primary:active,
            .btn-modern-secondary:active {
                transform: scale(0.98);
            }

            .footer-note {
                font-size: 0.775rem;
                margin-top: 1.25rem;
            }
        }
    </style>
</head>

<body>

    <!-- Ambient blur accents -->
    <div class="bg-mesh-blur mesh-1"></div>
    <div class="bg-mesh-blur mesh-2"></div>

    <div class="error-wrapper">
        <div class="error-card">

            <!-- Error Code Hero -->
            <div>
                <div class="error-hero">
                    <div class="error-number"><?= htmlspecialchars((string) $code) ?></div>
                    <div class="error-icon-float">
                        <i class="bi <?= $errorData['icon'] ?>"></i>
                    </div>
                </div>
            </div>

            <!-- Title & Description -->
            <h1 class="error-title"><?= htmlspecialchars($errorData['title']) ?></h1>
            <p class="error-desc"><?= htmlspecialchars($errorData['subtext']) ?></p>

            <!-- Action Buttons -->
            <div class="cta-container">
                <button type="button" class="btn-modern-secondary"
                    onclick="window.history.length > 1 ? window.history.back() : window.location.href='<?= $homeUrl ?>'">
                    <i class="bi bi-arrow-left"></i>
                    <span>Go Back</span>
                </button>
                <a href="<?= $homeUrl ?>" class="btn-modern-primary">
                    <i class="bi bi-house-door-fill"></i>
                    <span><?= htmlspecialchars($homeText) ?></span>
                </a>
            </div>
        </div>

        <div class="text-center footer-note">
            <span class="fw-semibold">Copyright &copy; <?= date('Y') ?> Genetian Builders &amp; Enterprises Inc.</span>
            <span class="d-inline-block ms-1">| Powered by <a href="/CIMS/about" class="footer-link">The
                    Medyas</a></span>
        </div>
    </div>

</body>

</html>