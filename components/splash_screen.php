<style>
    /* Custom Web Splash Screen */
    #gb-splash-screen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #ffffff;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999999;
        /* Highest priority */
        transition: opacity 0.4s ease-out, visibility 0.4s ease-out;
        visibility: visible;
    }

    #gb-splash-screen.hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .gb-splash-center-logo {
        width: 100px;
        height: auto;
        object-fit: contain;
        animation: zoomInSplash 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }

    .gb-splash-bottom-text {
        position: absolute;
        bottom: 50px;
        text-align: center;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        animation: fadeInUpSplash 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        animation-delay: 0.2s;
        opacity: 0;
    }

    .gb-splash-bottom-text .from-text {
        color: #8a8d91;
        font-size: 15px;
        margin-bottom: 2px;
        display: block;
    }

    .gb-splash-bottom-text .brand-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .gb-splash-bottom-text .brand-logo-small {
        height: 24px;
        width: auto;
        object-fit: contain;
    }

    .gb-splash-bottom-text .brand-text {
        font-size: 22px;
        font-weight: 700;
        /* Blue gradient matching Facebook/Meta modern style */
        background: linear-gradient(90deg, #0064e0, #1877f2);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.5px;
    }

    /* Splash Animations */
    @keyframes zoomInSplash {
        0% {
            transform: scale(0.85);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    @keyframes fadeInUpSplash {
        0% {
            transform: translateY(15px);
            opacity: 0;
        }

        100% {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<div id="gb-splash-screen">
    <img src="assets/LogoGB.png" alt="GB Center Logo" class="gb-splash-center-logo">
    <div class="gb-splash-bottom-text">
        <span class="from-text">Powered by</span>
        <div class="brand-wrap">
            <img src="assets/LogoGB.png" alt="GB Logo Small" class="brand-logo-small">
            <span class="brand-text">The Medyas</span>
        </div>
    </div>
</div>

<script>
    // Splash screen logic
    document.addEventListener("DOMContentLoaded", function() {
        var splash = document.getElementById('gb-splash-screen');

        // Check if the user is running the PWA via standalone display mode
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

        // We show the splash screen:
        // 1. If it's a standalone PWA on mobile/desktop
        // 2. OR if it's explicitly their first time loading the site in this session

        if (!sessionStorage.getItem('gb_splash_shown') || isStandalone) {
            // Make sure it displays long enough for the animation (e.g. 1.8 seconds)
            setTimeout(function() {
                if (splash) {
                    splash.classList.add('hidden');
                    setTimeout(() => splash.remove(), 400); // Wait for transition to finish before removing
                    sessionStorage.setItem('gb_splash_shown', 'true');
                }
            }, 1800);
        } else {
            // Immediately remove if already shown recently and not standalone
            if (splash) {
                splash.style.display = 'none';
                splash.remove();
            }
        }
    });
</script>