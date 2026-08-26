<!-- ========================================== -->
<!-- SYSTEM FOOTER -->
<!-- ========================================== -->
<footer class="app-footer">
    <div class="text-muted small">
        <span class="fw-bold">Copyright &copy; <?= date('Y') ?> Genetian Builders & Enterprises inc.</span>
        <span style="opacity: 0.8;"> | Powered by <a href="about" class="text-decoration-none text-blue fw-bold">The
                Medyas</a></span>
    </div>
</footer>

<script>
    window.cimsBasePath = '<?= rtrim(dirname($_SERVER['PHP_SELF']), "/\\") ?>';
</script>

</div> <!-- End #content wrapper opened in header.php -->

</div> <!-- End .wrapper opened in header.php -->

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Firebase CDN Scripts -->
<script src="https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js"></script>

<!-- Modular Application Scripts -->
<script src="assets/js/router.js?v=<?= time() ?>"></script>
<script src="assets/js/pwa.js?v=<?= time() ?>"></script>
<script src="assets/js/modals.js?v=<?= time() ?>"></script>
<script src="assets/js/inventory.js?v=<?= time() ?>"></script>

<!-- Notification Scripts -->
<script src="assets/js/notifications.js"></script>
<script src="assets/js/fcm.js"></script>

<!-- DYNAMIC ROLE-BASED CHATBOT WIDGET -->
<style>
    #cims-chatbot-container {
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 10000;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s ease;
    }

    /* Auto-hide floating chatbot whenever a modal is open */
    body.modal-open #cims-chatbot-container,
    body:has(.modal.show) #cims-chatbot-container {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transform: scale(0.7) translateY(30px) !important;
    }

    #cims-chatbot-trigger {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--gb-blue, #0033CC) 0%, #0d6efd 100%);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 8px 24px rgba(13, 110, 253, 0.4);
        position: relative;
    }

    #cims-chatbot-trigger:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 10px 30px rgba(13, 110, 253, 0.6);
    }

    #cims-chatbot-trigger i {
        animation: bounceSlow 3s infinite;
    }

    @keyframes bounceSlow {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-3px);
        }
    }

    .pulse-badge {
        animation: badgePulse 2s infinite;
    }

    @keyframes badgePulse {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }

        70% {
            box-shadow: 0 0 0 8px rgba(220, 53, 69, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }

    #cims-chatbot-panel {
        position: fixed;
        bottom: 95px;
        right: 25px;
        width: 380px;
        height: 520px;
        max-height: 80vh;
        max-width: 90vw;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.25);
        transform-origin: bottom right;
    }

    #cims-chatbot-panel.d-none {
        display: none !important;
    }

    #cims-chatbot-panel .card-header {
        background: linear-gradient(135deg, var(--gb-dark, #0A111F) 0%, #15253F 100%);
        border-bottom: 2px solid var(--gb-yellow, #FFD700);
    }

    .chatbot-avatar-container {
        width: 32px;
        height: 32px;
    }

    .chatbot-avatar {
        width: 32px;
        height: 32px;
        font-size: 1.1rem;
    }

    .chatbot-suggestions-bar {
        scrollbar-width: none;
    }

    .chatbot-suggestions-bar::-webkit-scrollbar {
        display: none;
    }

    .chatbot-chip {
        font-size: 0.75rem;
        padding: 6px 12px;
        border-radius: 50px;
        background-color: #f1f3f5;
        border: 1px solid #e9ecef;
        color: #495057;
        cursor: pointer;
        transition: all 0.2s;
        font-weight: 600;
    }

    .chatbot-chip:hover {
        background-color: var(--gb-blue, #0033CC);
        color: #ffffff;
        border-color: var(--gb-blue, #0033CC);
        transform: translateY(-1px);
    }

    #cims-chatbot-messages {
        flex: 1;
        scrollbar-width: thin;
    }

    #cims-chatbot-messages::-webkit-scrollbar {
        width: 5px;
    }

    #cims-chatbot-messages::-webkit-scrollbar-track {
        background: transparent;
    }

    #cims-chatbot-messages::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .chatbot-msg {
        max-width: 85%;
        clear: both;
        margin-bottom: 12px;
        line-height: 1.5;
        font-size: 0.9rem;
        word-wrap: break-word;
        border-radius: 12px;
        animation: messageFadeIn 0.25s ease-out;
    }

    @keyframes messageFadeIn {
        from {
            opacity: 0;
            transform: translateY(5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .chatbot-msg.user {
        float: right;
        background: linear-gradient(135deg, var(--gb-blue, #0033CC) 0%, #0d6efd 100%);
        color: white;
        border-top-right-radius: 2px;
        padding: 10px 14px;
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.15);
    }

    .chatbot-msg.assistant {
        float: left;
        background-color: #ffffff;
        color: #212529;
        border-top-left-radius: 2px;
        border: 1px solid #e9ecef;
        padding: 12px 14px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
    }

    .chatbot-msg.assistant ul,
    .chatbot-msg.assistant ol {
        padding-left: 20px;
        margin-bottom: 0;
        margin-top: 5px;
    }

    .chatbot-msg.assistant li {
        margin-bottom: 4px;
    }

    #cims-chatbot-form .form-control {
        font-size: 0.9rem;
    }

    .chatbot-loading-bubble {
        float: left;
        background-color: #ffffff;
        border: 1px solid #e9ecef;
        padding: 12px 20px;
        border-radius: 12px;
        border-top-left-radius: 2px;
        margin-bottom: 12px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
    }

    .chatbot-loading-dots {
        display: flex;
        align-items: center;
        gap: 4px;
        height: 12px;
    }

    .chatbot-loading-dots span {
        width: 6px;
        height: 6px;
        background-color: #6c757d;
        border-radius: 50%;
        animation: bounceDot 1.4s infinite ease-in-out both;
    }

    .chatbot-loading-dots span:nth-child(1) {
        animation-delay: -0.32s;
    }

    .chatbot-loading-dots span:nth-child(2) {
        animation-delay: -0.16s;
    }

    @keyframes bounceDot {

        0%,
        80%,
        100% {
            transform: scale(0);
        }

        40% {
            transform: scale(1.0);
        }
    }

    @media (max-width: 576px) {
        #cims-chatbot-panel {
            bottom: 85px;
            right: 15px;
            left: 15px;
            width: calc(100vw - 30px);
            height: 480px;
        }

        #cims-chatbot-container {
            bottom: 15px;
            right: 15px;
        }
    }
</style>
<div id="cims-chatbot-container"
    data-user-role="<?= htmlspecialchars(strtolower($_SESSION['user_role'] ?? 'requestor')) ?>">
    <!-- Floating Trigger Button -->
    <button id="cims-chatbot-trigger" class="btn shadow-lg" title="CIMS AI Assistant">
        <i class="bi bi-chat-dots-fill text-white fs-4"></i>
        <span
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light pulse-badge"
            style="font-size: 0.55rem; padding: 0.35em 0.5em;">AI</span>
    </button>

    <!-- Chat Panel -->
    <div id="cims-chatbot-panel" class="card shadow-lg d-none">
        <!-- Header -->
        <div class="card-header text-white d-flex align-items-center justify-content-between py-3 border-0">
            <div class="d-flex align-items-center">
                <div class="chatbot-avatar-container me-2">
                    <div
                        class="chatbot-avatar bg-white text-primary d-flex align-items-center justify-content-center rounded-circle fw-bold">
                        <i class="bi bi-robot"></i>
                    </div>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold lh-1" style="font-size: 0.95rem;">SiteWare Assistant</h6>
                    <span class="badge rounded-pill mt-1"
                        style="font-size: 0.6rem; background-color: #f1f3f5; color: #4f5d75; font-weight: 700; letter-spacing: 0.5px; padding: 0.3em 0.6em; display: inline-block;">BETA</span>
                </div>
            </div>
            <button type="button" id="cims-chatbot-close" class="btn-close btn-close-white" aria-label="Close"></button>
        </div>

        <!-- Action Suggestion Chips -->
        <div class="chatbot-suggestions-bar border-bottom py-2 px-3 bg-light d-flex gap-2 overflow-x-auto text-nowrap">
            <!-- Loaded dynamically via JS depending on role -->
        </div>

        <!-- Chat messages container -->
        <div id="cims-chatbot-messages" class="card-body overflow-y-auto bg-light p-3">
            <div class="chatbot-msg assistant border rounded-3 p-3 mb-2 shadow-sm">
                Hi <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></strong>! I am your
                <strong><?= htmlspecialchars($userBadgeLabel) ?></strong> assistant. How can I help you manage the
                system today?
            </div>
        </div>

        <!-- Chat input area -->
        <div class="card-footer bg-white border-top p-2">
            <form id="cims-chatbot-form" class="input-group">
                <input type="text" id="cims-chatbot-input"
                    class="form-control border-0 bg-light rounded-start-pill py-2 px-3 shadow-none"
                    placeholder="Ask me something..." autocomplete="off" required>
                <button type="submit" class="btn btn-brand rounded-end-pill px-3 shadow-none"><i
                        class="bi bi-send-fill"></i></button>
            </form>
        </div>
    </div>
</div>

<!-- Include SMS Inbox Modal -->
<?php include_once __DIR__ . '/../components/sms_inbox_modal.php'; ?>
<!-- Include Requisition Slips Modal Globally -->
<?php include_once __DIR__ . '/../components/requisition_modals.php'; ?>
<!-- Include PO, Withdrawal & Item Quick View Modals Globally -->
<?php include_once __DIR__ . '/../components/entity_modals.php'; ?>

<script src="assets/js/chatbot.js?v=<?= time() ?>"></script>



// ==========================================
// DEDICATED SUPPLY LOGISTICS UPDATES JS
// ==========================================
let supplyUpdatesData = [];
let currentSupplyFilter = 'all';

async function loadSupplyUpdates() {
const container = document.getElementById('supplyUpdatesContainer');
const badge = document.getElementById('supplyUpdatesBadge');

try {
const formData = new FormData();
formData.append('action', 'fetch_combined_alerts');

const res = await fetch('process/process.php', { method: 'POST', body: formData });
const data = await res.json();

if (data.status === 'success') {
// Filter to supply items only (supply_eta & sms_reply)
const allItems = data.alerts || [];
supplyUpdatesData = allItems.filter(a => a.type === 'supply_eta' || a.type === 'sms_reply');

// Count urgent items (arriving today, overdue, unread SMS)
const urgentCount = supplyUpdatesData.filter(a => a.category === 'arriving_today' || a.category === 'overdue' || (a.type
=== 'sms_reply' && a.is_read === 0)).length;
const icon = document.getElementById('supplyTruckIcon');

if (urgentCount > 0) {
if (badge) {
badge.textContent = urgentCount > 99 ? '99+' : urgentCount;
badge.classList.remove('d-none');
}
if (icon) {
// HIGHLIGHT: Filled warning yellow triangle
icon.className = 'bi bi-exclamation-triangle-fill fs-5 text-warning';
}
} else {
if (badge) {
badge.classList.add('d-none');
}
if (icon) {
// UNHIGHLIGHT: Muted grey outline triangle
icon.className = 'bi bi-exclamation-triangle fs-5 text-muted';
}
}

renderSupplyUpdates(currentSupplyFilter);
} else {
if (container) container.innerHTML = `<div class="p-3 text-center text-muted small">Unable to load supply updates.</div>
`;
}
} catch (err) {
console.error('Error loading supply updates:', err);
}
}

function filterSupplyUpdates(filter, btnEl) {
currentSupplyFilter = filter;

const container = btnEl ? btnEl.closest('.dropdown-menu') : null;
if (container) {
const tabs = container.querySelectorAll('.supply-tab-btn');
tabs.forEach(t => {
t.classList.remove('btn-primary', 'active');
t.classList.add('btn-outline-secondary');
});
}

if (btnEl) {
btnEl.classList.remove('btn-outline-secondary');
btnEl.classList.add('btn-primary', 'active');
}

renderSupplyUpdates(filter);
}

function renderSupplyUpdates(filter) {
const container = document.getElementById('supplyUpdatesContainer');
if (!container) return;

let items = supplyUpdatesData;
if (filter === 'arriving_today') {
items = supplyUpdatesData.filter(a => a.category === 'arriving_today');
} else if (filter === 'overdue') {
items = supplyUpdatesData.filter(a => a.category === 'overdue');
}

if (items.length === 0) {
container.innerHTML = `
<div class="text-center text-muted py-4 px-3">
    <i class="bi bi-truck display-6 opacity-25 d-block mb-2 text-primary"></i>
    <p class="small mb-0 fw-semibold">No supply updates found</p>
    <small class="text-muted" style="font-size: 0.72rem;">No active shipments matching filter.</small>
</div>
`;
return;
}

let html = '';
items.forEach(item => {
const isUnread = item.is_read === 0 || item.category === 'arriving_today' || item.category === 'overdue';
const bgClass = isUnread ? 'bg-light border-start border-4 border-primary' : 'bg-white';

let actionBtn = '';
if (item.type === 'supply_eta') {
actionBtn = `<a href="po" class="btn btn-xs btn-outline-primary py-0 px-2 fw-bold mt-1" style="font-size:0.7rem;"><i
        class="bi bi-box-arrow-up-right me-1"></i>View PO</a>`;
if (item.po_id && ['purchasing', 'admin'].includes(window.currentUserRole)) {
actionBtn += ` <button type="button"
    class="btn btn-xs btn-link text-primary py-0 px-1 fw-bold mt-1 text-decoration-none" style="font-size:0.7rem;"
    onclick="openEditEtaModal(${item.po_id}, '${item.po_no}', '')"><i class="bi bi-pencil-square me-1"></i>Edit
    ETA</button>`;
}
} else if (item.type === 'sms_reply') {
actionBtn = `
<button type="button" class="btn btn-xs btn-primary py-0 px-2 fw-bold mt-1 me-1" style="font-size:0.7rem;"
    onclick="openSmsInboxModal()"><i class="bi bi-chat-dots me-1"></i>Reply SMS</button>
`;
}

html += `
<div class="p-3 border-bottom ${bgClass} transition-all">
    <div class="d-flex align-items-start justify-content-between">
        <div class="d-flex align-items-center mb-1">
            <span class="badge ${item.badge_class} me-2" style="font-size:0.65rem;">
                <i class="bi ${item.icon} me-1"></i>${item.category ? item.category.replace('_', ' ').toUpperCase() :
                'SUPPLY'}
            </span>
            <strong class="text-dark small" style="font-size:0.82rem;">${escapeSmsHtml(item.title)}</strong>
        </div>
        <small class="text-muted text-nowrap ms-2" style="font-size:0.68rem;">${item.time_ago}</small>
    </div>
    <p class="mb-1 text-secondary" style="font-size:0.78rem; line-height: 1.35;">${escapeSmsHtml(item.message)}</p>
    ${actionBtn}
</div>
`;
});

container.innerHTML = html;
}

// Poll for supply updates every 25 seconds
document.addEventListener('DOMContentLoaded', () => {
loadSmsThreads();
loadSupplyUpdates();
setInterval(loadSmsThreads, 30000);
setInterval(loadSupplyUpdates, 25000);
});
<!-- ======================================================== -->
<!-- MODAL: IN-APP PWA & MOBILE MEDIA PROOF VIEWER           -->
<!-- ======================================================== -->
<div class="modal fade" id="pwaMediaViewerModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold text-uppercase mb-0" id="pwaMediaViewerTitle"
                    style="font-size: 0.85rem;">
                    <i class="bi bi-image text-warning me-2"></i> Media Proof Viewer
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 text-center bg-dark d-flex flex-column align-items-center justify-content-center"
                style="min-height: 250px;">
                <img id="pwaMediaViewerImg" src="" class="img-fluid rounded shadow-sm"
                    style="max-height: 75vh; object-fit: contain; background: #fff;">
            </div>
            <div
                class="modal-footer bg-dark border-top border-secondary border-opacity-25 py-2 d-flex justify-content-between align-items-center">
                <span class="text-white-50 small text-truncate pe-2 font-monospace" id="pwaMediaViewerUrl"
                    style="max-width: 60%; font-size: 0.72rem;"></span>
                <div>
                    <a id="pwaMediaViewerOpenBtn" href="#" target="_blank"
                        class="btn btn-sm btn-outline-light me-1 fw-bold" style="font-size: 0.75rem;">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Window
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary fw-bold" data-bs-dismiss="modal"
                        style="font-size: 0.75rem;">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

</body>

</html>