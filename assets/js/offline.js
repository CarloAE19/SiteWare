/* ── Retry handler ── */
function handleRetry() {
    const btn   = document.getElementById('retryBtn');
    const label = document.getElementById('retryLabel');
    const icon  = btn.querySelector('.btn-icon');

    btn.disabled = true;
    icon.classList.add('spin');
    label.textContent = 'Reconnecting…';

    setTimeout(() => window.location.reload(), 1200);
}

/* ── Auto-reload when connection is restored ── */
window.addEventListener('online', () => {
    window.location.reload();
});
