/* ==========================================================
 * GB INVENTORY - SPA ROUTER & SIDEBAR
 * Handles page transitions with animated nano-progress bar,
 * double-click protection, active indicators, and timeout guards.
 * ========================================================== */

document.addEventListener("DOMContentLoaded", () => {
    let isNavigating = false;
    let progressInterval = null;
    let pillTimer = null;

    function getProgressBar() {
        let bar = document.getElementById('cims-top-progress-bar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'cims-top-progress-bar';
            document.body.appendChild(bar);
        }
        return bar;
    }

    function getRouteLoadingPill() {
        let pill = document.getElementById('cims-route-loading-pill');
        if (!pill) {
            pill = document.createElement('div');
            pill.id = 'cims-route-loading-pill';
            pill.className = 'cims-route-loading-pill';
            pill.innerHTML = '<span class="spinner-border spinner-border-sm text-warning" role="status" style="width: 13px; height: 13px; border-width: 2px;"></span><span id="cims-route-pill-text">Loading...</span>';
            document.body.appendChild(pill);
        }
        return pill;
    }

    function showRouteLoadingPill(label) {
        clearTimeout(pillTimer);
        const pill = getRouteLoadingPill();
        const textSpan = document.getElementById('cims-route-pill-text');
        if (textSpan) {
            textSpan.textContent = label || 'Loading page...';
        }
        // Show after 180ms to avoid flashing on instant cached hits
        pillTimer = setTimeout(() => {
            if (isNavigating) {
                pill.classList.add('show');
            }
        }, 180);
    }

    function hideRouteLoadingPill() {
        clearTimeout(pillTimer);
        const pill = document.getElementById('cims-route-loading-pill');
        if (pill) {
            pill.classList.remove('show');
        }
    }

    function startProgressBar() {
        const bar = getProgressBar();
        clearInterval(progressInterval);
        bar.style.transition = 'width 0.25s ease, opacity 0.2s ease';
        bar.classList.add('active');
        bar.style.width = '20%';

        let currentWidth = 20;
        progressInterval = setInterval(() => {
            if (currentWidth < 85) {
                currentWidth += (85 - currentWidth) * 0.18;
                bar.style.width = `${Math.round(currentWidth)}%`;
            }
        }, 280);
    }

    function finishProgressBar() {
        const bar = getProgressBar();
        clearInterval(progressInterval);
        bar.style.width = '100%';
        setTimeout(() => {
            bar.classList.remove('active');
            setTimeout(() => {
                bar.style.transition = 'none';
                bar.style.width = '0%';
            }, 300);
        }, 220);
    }

    function resetProgressBar() {
        const bar = getProgressBar();
        clearInterval(progressInterval);
        bar.classList.remove('active');
        bar.style.width = '0%';
    }

    function getHumanRouteLabel(url, link) {
        const clean = (url || '').split('?')[0].replace(/^\/+/, '').replace(/\.php$/, '').toLowerCase();
        const routeLabels = {
            'dashboard': 'Loading Dashboard...',
            'po': 'Loading Purchase Orders...',
            'requisitions': 'Loading Requisitions...',
            'withdrawals': 'Loading Withdrawals...',
            'suppliers': 'Loading Suppliers...',
            'index': 'Loading Materials Inventory...',
            'analytics': 'Loading Analytics & AI...',
            'physical_count': 'Loading Physical Count...',
            'projects': 'Loading Projects...',
            'users': 'Loading Users...',
            'units': 'Loading Units...',
            'categories': 'Loading Categories...',
            'profile': 'Loading Profile...',
            'audit': 'Loading Audit Trail...',
            'about': 'Loading About...'
        };

        if (routeLabels[clean]) return routeLabels[clean];

        const text = (link.innerText || '').trim().replace(/\s+/g, ' ');
        if (text && text.length > 2 && text.length < 30) {
            return `Loading ${text}...`;
        }
        return 'Loading page...';
    }

    document.body.addEventListener('click', async (e) => {
        
        // --- Sidebar Open/Close Toggle ---
        const toggleBtn = e.target.closest('#sidebarCollapse');
        if (toggleBtn) {
            e.preventDefault();
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            if (sidebar) sidebar.classList.toggle('active');
            if (content) content.classList.toggle('active');
            return; 
        }

        // --- Sidebar Mobile "X" Close Button ---
        const closeBtn = e.target.closest('#sidebarClose');
        if (closeBtn) {
            e.preventDefault();
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.remove('active');
            return; 
        }

        // --- Single Page Application (SPA) Router ---
        const link = e.target.closest('a');
        if (!link) return;

        const url = link.getAttribute('href');
        if (!url || url === '#' || url.startsWith('javascript:') || url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//') || url.startsWith('mailto:') || url.startsWith('tel:')) return;
        if (url.includes('logout') || link.target === '_blank' || link.hasAttribute('download') || link.getAttribute('data-bs-toggle')) return;

        // Check if destination is a static file or download
        if (/\.(pdf|png|jpe?g|gif|webp|svg|csv|xlsx?|zip)(\?.*)?$/i.test(url)) return;

        // Allowed navigation locations: sidebar, top navbar, footer, shortcut buttons, or recognized CIMS routes
        const isAppShell = !!(link.closest('#sidebar') || link.closest('.top-navbar') || link.closest('.app-footer') || link.closest('.shortcut-btn') || link.closest('.welcome-action-pill') || link.classList.contains('cims-spa-link'));
        const cleanRoute = url.split('?')[0].replace(/^\/+/, '').replace(/\.php$/, '').toLowerCase();
        const isKnownRoute = /^(dashboard|po|requisitions|withdrawals|suppliers|index|analytics|physical_count|users|units|categories|profile|about|settings|audit|projects)$/i.test(cleanRoute);

        if (!isAppShell && !isKnownRoute) return;

        // Prevent concurrent navigation requests while one is in-flight (HCI principle: error prevention)
        if (isNavigating) {
            e.preventDefault();
            return;
        }

        e.preventDefault(); 

        const contentDiv = document.getElementById('content');
        if (!contentDiv) return;

        isNavigating = true;
        
        // Dim content smoothly and lock user input during transit
        contentDiv.style.transition = 'opacity 0.25s ease';
        contentDiv.style.opacity = '0.55';
        contentDiv.style.pointerEvents = 'none';

        // Start top nano progress bar & floating route pill
        startProgressBar();
        const routeLabel = getHumanRouteLabel(url, link);
        showRouteLoadingPill(routeLabel);

        // Subtle link loading indicator inside sidebar or shortcut buttons
        let tempSpinner = null;
        if (link.closest('#sidebar')) {
            tempSpinner = document.createElement('span');
            tempSpinner.className = 'spinner-border spinner-border-sm text-warning ms-auto me-1 cims-nav-spinner';
            tempSpinner.style.width = '12px';
            tempSpinner.style.height = '12px';
            tempSpinner.setAttribute('role', 'status');
            link.appendChild(tempSpinner);
        } else if (link.classList.contains('shortcut-btn') || link.classList.contains('welcome-action-pill')) {
            link.style.opacity = '0.75';
        }

        // On mobile, immediately close the drawer so user sees progress bar & loading pill right away
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.remove('active');
        }

        try {
            const fetchFn = window.cimsFetchWithTimeout || fetch;
            const response = await fetchFn(url, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }, 25000);

            const htmlText = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');
            const newContent = doc.getElementById('content');

            if (newContent) {
                if (doc.title) {
                    document.title = doc.title;
                }

                contentDiv.innerHTML = newContent.innerHTML;
                contentDiv.style.opacity = '1';
                contentDiv.style.pointerEvents = '';
                window.scrollTo({ top: 0, behavior: 'instant' });

                window.history.pushState(null, '', url);

                document.querySelectorAll('#sidebar li').forEach(li => li.classList.remove('active'));
                const activeLink = document.querySelector(`#sidebar a[href="${url}"]`);
                if (activeLink) {
                    const parentLi = activeLink.closest('li');
                    if (parentLi) parentLi.classList.add('active');
                }

                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) sidebar.classList.remove('active');
                }

                const scripts = contentDiv.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                // Immediately sync offline UI badges and locks on newly swapped content
                if (typeof updateOfflineUI === 'function') {
                    updateOfflineUI();
                }

                // Immediately initialize table sorting on newly routed page
                if (window.CimsTableSorter && typeof window.CimsTableSorter.init === 'function') {
                    window.CimsTableSorter.init();
                }
                document.dispatchEvent(new CustomEvent('cims:content-loaded'));
                document.dispatchEvent(new CustomEvent('cims:route-changed', { detail: { url } }));

                hideRouteLoadingPill();
                finishProgressBar();

            } else {
                hideRouteLoadingPill();
                finishProgressBar();
                window.location.href = url;
            }
        } catch (err) {
            console.error("Routing error:", err);
            resetProgressBar();
            contentDiv.style.opacity = '1';

            if (!navigator.onLine) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Page Not Available Offline',
                        text: 'This section was not cached yet. Connect to the internet to view it for the first time.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert("This page was not cached for offline use yet. Please reconnect to view it.");
                }
            } else {
                // If it timed out on a slow connection, ask user if they want to force full navigation
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Slow Connection Detected',
                        text: 'The page took too long to load over your network connection. Would you like to force a full reload?',
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Force Reload',
                        cancelButtonText: 'Stay on Current Page',
                        confirmButtonColor: '#0d6efd',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = url;
                        }
                    });
                } else {
                    if (confirm("Slow connection detected. Force full page reload?")) {
                        window.location.href = url;
                    }
                }
            }
        } finally {
            isNavigating = false;
            hideRouteLoadingPill();
            if (tempSpinner && tempSpinner.parentNode) {
                tempSpinner.remove();
            }
            if (link && link.style) {
                link.style.opacity = '';
            }
            contentDiv.style.opacity = '1';
            contentDiv.style.pointerEvents = '';
        }
    });

    window.addEventListener('popstate', () => {
        window.location.reload();
    });
});