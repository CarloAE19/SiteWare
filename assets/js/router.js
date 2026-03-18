/* ==========================================================
 * GB INVENTORY - SPA ROUTER & SIDEBAR
 * Handles page transitions without reloading the browser
 * ========================================================== */

document.addEventListener("DOMContentLoaded", () => {
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

        if (!link.closest('#sidebar') && !link.closest('.top-navbar') && !link.closest('.app-footer')) return;

        const url = link.getAttribute('href');
        
        if (!url || url === '#' || url.startsWith('http') || url.includes('logout') || link.target === '_blank') return;

        e.preventDefault(); 

        const contentDiv = document.getElementById('content');
        if (!contentDiv) return;
        
        contentDiv.style.transition = 'opacity 0.2s';
        contentDiv.style.opacity = '0.5';

        try {
            const response = await fetch(url);
            const htmlText = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');
            const newContent = doc.getElementById('content');

            if (newContent) {
                contentDiv.innerHTML = newContent.innerHTML;
                contentDiv.style.opacity = '1';

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

            } else {
                window.location.href = url;
            }
        } catch (err) {
            console.error("Routing error:", err);
            window.location.href = url;
        }
    });

    window.addEventListener('popstate', () => {
        window.location.reload();
    });
});