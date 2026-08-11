<!-- ========================================== -->
<!-- SUPPLIER SMS INBOX & CONVERSATION MODAL   -->
<!-- ========================================== -->
<div class="modal fade" id="smsInboxModal" tabindex="-1" aria-labelledby="smsInboxModalLabel" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 rounded-4">

            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white rounded-top-4 py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="bg-success text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 42px; height: 42px;">
                        <i class="bi bi-chat-left-dots-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="smsInboxModalLabel">Supplier SMS Inbox</h5>
                        <small class="text-white-50">Two-way SMS communication with suppliers</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-0" style="min-height: 520px; max-height: 70vh;">
                <div class="row g-0 h-100">

                    <!-- Left Column: Supplier Thread List -->
                    <div class="col-md-4 border-end bg-light d-flex flex-column" style="min-height: 520px;">

                        <!-- Search Bar -->
                        <div class="p-3 border-bottom bg-white sticky-top">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i
                                        class="bi bi-search text-muted"></i></span>
                                <input type="text" id="smsThreadSearch"
                                    class="form-control bg-light border-start-0 shadow-none"
                                    placeholder="Search supplier or phone..." onkeyup="filterSmsThreads()">
                                <button class="btn btn-outline-secondary" type="button" onclick="loadSmsThreads()"
                                    title="Refresh conversations">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Thread List -->
                        <div class="overflow-auto flex-grow-1" id="smsThreadListContainer" style="max-height: 460px;">
                            <div class="text-center text-muted p-4">
                                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                <p class="mb-0 small">Loading conversations...</p>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Active Chat Area -->
                    <div class="col-md-8 d-flex flex-column bg-white" style="min-height: 520px;">

                        <!-- Chat Active Header -->
                        <div class="p-3 border-bottom bg-white d-flex align-items-center justify-content-between"
                            id="smsActiveChatHeader">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3 bg-secondary text-white fw-bold rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 40px; height: 40px;" id="smsActiveAvatar">
                                    ?
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark" id="smsActiveTitle">Select a conversation</h6>
                                </div>
                            </div>
                            <div>
                                <a href="#" id="smsActiveViberBtn" class="btn btn-sm btn-viber fw-bold shadow-sm d-none" style="font-size: 0.8rem;" title="Open supplier conversation in Viber Desktop">
                                    <i class="fa-brands fa-viber me-1"></i> Open in Viber
                                </a>
                            </div>
                        </div>

                        <!-- Messages Conversation Container -->
                        <div class="p-3 overflow-auto flex-grow-1 bg-light-subtle" id="smsMessagesContainer"
                            style="max-height: 380px; min-height: 320px; background-color: #f8f9fa;">
                            <div class="text-center text-muted my-5 py-5">
                                <i class="bi bi-chat-square-text display-4 text-secondary opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-semibold">No Conversation Selected</h6>
                                <p class="small text-muted mb-0">Select a supplier from the list on the left to view SMS
                                    message history.</p>
                            </div>
                        </div>

                        <!-- Message Input Area -->
                        <div class="p-3 border-top bg-white" id="smsInputArea">
                            <form id="smsReplyForm" onsubmit="handleSendSmsReply(event)">
                                <input type="hidden" id="smsActivePhone" value="">
                                <input type="hidden" id="smsActiveSupplierId" value="">
                                <div class="input-group">
                                    <textarea id="smsReplyText" class="form-control border shadow-none" rows="2"
                                        placeholder="Type your SMS reply to supplier..." required style="resize: none;"
                                        disabled></textarea>
                                    <button
                                        class="btn btn-success px-4 fw-bold shadow-sm d-flex align-items-center justify-content-center"
                                        type="submit" id="smsSendReplyBtn" disabled>
                                        <i class="bi bi-send-fill me-2"></i> Send SMS
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .sms-thread-item {
        transition: all 0.2s ease;
        cursor: pointer;
        border-left: 4px solid transparent;
    }

    .sms-thread-item:hover {
        background-color: #f1f5f9 !important;
    }

    .sms-thread-item.active {
        background-color: #e2e8f0 !important;
        border-left-color: #198754 !important;
    }

    .sms-thread-item.active h6,
    .sms-thread-item.active .text-dark {
        color: #0f172a !important;
    }

    .sms-thread-item.active .text-muted,
    .sms-thread-item.active p,
    .sms-thread-item.active small {
        color: #475569 !important;
    }

    .chat-bubble {
        max-width: 75%;
        padding: 10px 14px;
        border-radius: 16px;
        margin-bottom: 10px;
        font-size: 0.92rem;
        position: relative;
        line-height: 1.4;
    }

    .chat-bubble-inbound {
        background-color: #ffffff;
        color: #212529;
        border: 1px solid #dee2e6;
        border-bottom-left-radius: 4px;
        align-self: flex-start;
    }

    .chat-bubble-outbound {
        background-color: #198754;
        color: #ffffff;
        border-bottom-right-radius: 4px;
        align-self: flex-end;
    }

    /* DARK MODE OVERRIDES FOR SMS INBOX MODAL */
    [data-bs-theme="dark"] #smsInboxModal .modal-content {
        background-color: #161b22 !important;
        border: 1px solid #30363d !important;
    }

    [data-bs-theme="dark"] .sms-thread-item {
        background-color: #161b22 !important;
        border-bottom-color: #30363d !important;
    }

    [data-bs-theme="dark"] .sms-thread-item h6,
    [data-bs-theme="dark"] .sms-thread-item .text-dark {
        color: #f0f6fc !important;
    }

    [data-bs-theme="dark"] .sms-thread-item .text-muted,
    [data-bs-theme="dark"] .sms-thread-item p,
    [data-bs-theme="dark"] .sms-thread-item small {
        color: #8b949e !important;
    }

    [data-bs-theme="dark"] .sms-thread-item:hover {
        background-color: #21262d !important;
    }

    [data-bs-theme="dark"] .sms-thread-item.active {
        background-color: #21262d !important;
        border-left-color: #2ea043 !important;
    }

    [data-bs-theme="dark"] .sms-thread-item.active h6,
    [data-bs-theme="dark"] .sms-thread-item.active .text-dark {
        color: #ffffff !important;
    }

    [data-bs-theme="dark"] .sms-thread-item.active .text-muted,
    [data-bs-theme="dark"] .sms-thread-item.active p,
    [data-bs-theme="dark"] .sms-thread-item.active small {
        color: #c9d1d9 !important;
    }

    [data-bs-theme="dark"] #smsMessagesContainer {
        background-color: #0d1117 !important;
    }

    [data-bs-theme="dark"] .chat-bubble-inbound {
        background-color: #21262d !important;
        color: #f0f6fc !important;
        border-color: #30363d !important;
    }

    [data-bs-theme="dark"] .chat-bubble-inbound strong {
        color: #58a6ff !important;
    }

    [data-bs-theme="dark"] .chat-bubble-outbound {
        background-color: #198754 !important;
        color: #ffffff !important;
    }

    [data-bs-theme="dark"] #smsActiveChatHeader,
    [data-bs-theme="dark"] #smsInputArea,
    [data-bs-theme="dark"] #smsThreadSearch {
        background-color: #161b22 !important;
        color: #f0f6fc !important;
        border-color: #30363d !important;
    }

    [data-bs-theme="dark"] #smsReplyText {
        background-color: #0d1117 !important;
        color: #f0f6fc !important;
        border-color: #30363d !important;
    }
</style>