/**
 * SiteWare Intelligent Assistant JavaScript Handler
 * Conforms to CIMS Modal & AJAX Handler Standards and Quality Guidelines
 */
document.addEventListener("DOMContentLoaded", () => {
    // Prevent duplicate initialization on SPA routing
    if (window.cimsChatbotInitialized) return;
    window.cimsChatbotInitialized = true;

    const trigger = document.getElementById("cims-chatbot-trigger");
    const panel = document.getElementById("cims-chatbot-panel");
    const closeBtn = document.getElementById("cims-chatbot-close");
    const clearBtn = document.getElementById("cims-chatbot-clear");
    const expandBtn = document.getElementById("cims-chatbot-expand");
    const form = document.getElementById("cims-chatbot-form");
    const input = document.getElementById("cims-chatbot-input");
    const submitBtn = form ? form.querySelector("button[type='submit']") : null;
    const messagesContainer = document.getElementById("cims-chatbot-messages");
    const suggestionsBar = document.querySelector(".chatbot-suggestions-bar");
    const container = document.getElementById("cims-chatbot-container");

    if (!trigger || !panel || !closeBtn || !form || !messagesContainer || !input) return;

    // Determine user role and storage key for conversation persistence
    const userRole = (container && container.dataset.userRole) ? container.dataset.userRole.toLowerCase() : "requestor";
    const storageKey = `cims_chat_history_${userRole}`;
    const fullscreenKey = `cims_chatbot_fullscreen`;

    // Track and restore fullscreen state
    let isFullscreen = false;
    try {
        isFullscreen = sessionStorage.getItem(fullscreenKey) === "true";
    } catch (e) {}

    function updateFullscreenUI() {
        if (!panel) return;
        if (isFullscreen) {
            panel.classList.add("chatbot-fullscreen");
            if (expandBtn) {
                expandBtn.innerHTML = '<i class="bi bi-fullscreen-exit fs-6"></i>';
                expandBtn.title = "Exit Fullscreen / Minimize Window";
                expandBtn.setAttribute("aria-label", "Exit Fullscreen");
            }
        } else {
            panel.classList.remove("chatbot-fullscreen");
            if (expandBtn) {
                expandBtn.innerHTML = '<i class="bi bi-arrows-fullscreen fs-6"></i>';
                expandBtn.title = "Toggle Fullscreen / Expand Window";
                expandBtn.setAttribute("aria-label", "Toggle Fullscreen");
            }
        }
    }

    // Apply stored fullscreen preference
    updateFullscreenUI();

    // Get default initial greeting from DOM
    const initialGreetingEl = messagesContainer.querySelector(".chatbot-msg.assistant");
    const initialWelcomeHtml = initialGreetingEl ? initialGreetingEl.innerHTML.trim() : `Hi! I am your SiteWare assistant. How can I help you manage the system today?`;

    // Load Chat History from sessionStorage if available
    let chatHistory = [];
    let hasSavedHistory = false;
    try {
        const savedHistory = sessionStorage.getItem(storageKey);
        if (savedHistory) {
            const parsed = JSON.parse(savedHistory);
            if (Array.isArray(parsed) && parsed.length > 0) {
                chatHistory = parsed;
                hasSavedHistory = true;
            }
        }
    } catch (e) {
        console.warn("Failed to load chat history from sessionStorage:", e);
    }

    if (!hasSavedHistory) {
        chatHistory = [{ role: "model", text: initialWelcomeHtml }];
    } else {
        // Render restored history items if user has an ongoing session
        messagesContainer.innerHTML = "";
        chatHistory.forEach(item => {
            const roleClass = (item.role === "user") ? "user" : "assistant";
            const content = (item.role === "user") ? escapeHtml(item.text) : formatMarkdown(item.text);
            renderMessageBubble(roleClass, content);
        });
    }

    // Role-specific quick prompts
    const isRole = (r) => userRole.includes(r);
    const suggestions = {
        admin: [
            { text: "👥 Role Counts", prompt: "Provide a breakdown of registered users by role in the CIMS." },
            { text: "🛠️ System Summary", prompt: "Give me a general overview of the database size and system records count." }
        ],
        management: [
            { text: "📄 Approved RS Slips", prompt: "List approved requisition slips with direct links to open their detail modals." },
            { text: "⚠️ Pending Requisitions", prompt: "Are there any pending requisitions that require my approval right now?" },
            { text: "📈 Top Consumed Items", prompt: "Show me the top consumed items in the last 30 days." },
            { text: "📉 Low Stock Alert", prompt: "What items have low stock under 15 units?" }
        ],
        purchasing: [
            { text: "🚚 Pending Deliveries", prompt: "List the purchase orders currently pending delivery." },
            { text: "🏢 Supplier Summary", prompt: "Show me a count of our active suppliers in the system." },
            { text: "🔄 Items to Reorder", prompt: "Which items are low in stock and need a new Purchase Order?" }
        ],
        warehouse: [
            { text: "📄 Approved RS Slips", prompt: "Show approved requisition slips that are ready for material staging or pickup." },
            { text: "📦 Low Stock & Stockout", prompt: "Are there any items currently out of stock or low in quantity?" },
            { text: "🔄 Recent Withdrawals", prompt: "Show me the last 5 material withdrawal transactions." },
            { text: "📊 Last Audit Info", prompt: "What was the result of our last physical inventory recount and discrepancies?" }
        ],
        requestor: [
            { text: "📝 My Request Status", prompt: "Check the status of my latest requisition slips." },
            { text: "📄 Approved RS Slips", prompt: "Show my approved requisition slips with direct links to view the details modal." },
            { text: "🏗️ Active Projects", prompt: "List the active projects currently registered in the system." }
        ]
    };

    let activeSuggestions = [];
    if (isRole("admin")) activeSuggestions = suggestions.admin;
    else if (isRole("management") || isRole("approver")) activeSuggestions = suggestions.management;
    else if (isRole("purchasing")) activeSuggestions = suggestions.purchasing;
    else if (isRole("warehouse")) activeSuggestions = suggestions.warehouse;
    else activeSuggestions = suggestions.requestor;

    // Render Suggestion Chips
    if (suggestionsBar) {
        suggestionsBar.innerHTML = "";
        activeSuggestions.forEach(chip => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "chatbot-chip btn btn-sm";
            btn.innerText = chip.text;
            btn.addEventListener("click", () => {
                if (input.disabled) return;
                sendUserMessage(chip.prompt);
            });
            suggestionsBar.appendChild(btn);
        });
    }

    /**
     * Safely focus the chatbot input field without popping up the virtual
     * software keyboard on mobile phones / touchscreens (HCI & Mobile Usability)
     */
    function safeFocusInput() {
        const isTouchOrMobile = window.innerWidth < 768 || 
            (window.matchMedia && window.matchMedia("(pointer: coarse)").matches) ||
            ('ontouchstart' in window) ||
            (navigator.maxTouchPoints > 0 && window.innerWidth < 992);

        if (!isTouchOrMobile && input && !input.disabled) {
            input.focus();
        }
    }

    // Toggle Panel Visibility
    trigger.addEventListener("click", () => {
        panel.classList.toggle("d-none");
        scrollToBottom();
        const badge = trigger.querySelector(".pulse-badge");
        if (badge) badge.remove();
        if (!panel.classList.contains("d-none")) {
            safeFocusInput();
        }
    });

    closeBtn.addEventListener("click", () => {
        panel.classList.add("d-none");
    });

    // Clear Chat History Action
    if (clearBtn) {
        clearBtn.addEventListener("click", () => {
            if (input.disabled) return;
            chatHistory = [{ role: "model", text: initialWelcomeHtml }];
            try {
                sessionStorage.removeItem(storageKey);
            } catch (e) {}
            messagesContainer.innerHTML = "";
            renderMessageBubble("assistant", initialWelcomeHtml);
            scrollToBottom();
        });
    }

    // Toggle Fullscreen / Window Expansion Action
    if (expandBtn) {
        expandBtn.addEventListener("click", () => {
            isFullscreen = !isFullscreen;
            try {
                sessionStorage.setItem(fullscreenKey, isFullscreen ? "true" : "false");
            } catch (e) {}
            updateFullscreenUI();
            scrollToBottom();
            safeFocusInput();
        });
    }

    // Escape Key Handler for User Control & Freedom (HCI standard)
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && panel && !panel.classList.contains("d-none")) {
            if (isFullscreen) {
                isFullscreen = false;
                try {
                    sessionStorage.setItem(fullscreenKey, "false");
                } catch (err) {}
                updateFullscreenUI();
                e.preventDefault();
            }
        }
    });

    // Handle Form Submit
    form.addEventListener("submit", (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text || input.disabled) return;
        sendUserMessage(text);
        input.value = "";
    });

    /**
     * Disable/Enable Controls to Prevent Double-Submissions
     */
    function setControlsDisabled(disabled) {
        input.disabled = disabled;
        if (submitBtn) {
            submitBtn.disabled = disabled;
            if (disabled) {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            } else {
                submitBtn.innerHTML = '<i class="bi bi-send-fill"></i>';
            }
        }
        if (suggestionsBar) {
            const chips = suggestionsBar.querySelectorAll(".chatbot-chip");
            chips.forEach(c => c.disabled = disabled);
        }
        if (clearBtn) {
            clearBtn.disabled = disabled;
        }
    }

    /**
     * Send User Message via AJAX
     */
    async function sendUserMessage(text) {
        // Render sanitized user message bubble
        renderMessageBubble("user", escapeHtml(text));
        chatHistory.push({ role: "user", text: text });
        saveHistory();

        // Lock controls & show loading indicator
        setControlsDisabled(true);
        const loadingEl = renderLoadingIndicator();
        scrollToBottom();

        let rawResponse = "";
        try {
            const basePath = window.cimsBasePath || "";
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.csrfToken || "");

            // Sliding window before dispatching network request
            const payloadHistory = chatHistory.length > 10 ? chatHistory.slice(-10) : chatHistory;

            const response = await fetch(`${basePath}/process/chatbot_chat.php`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({
                    messages: payloadHistory
                })
            });

            rawResponse = await response.text();
            let data;
            try {
                data = JSON.parse(rawResponse);
            } catch (jsonErr) {
                throw new Error("Invalid server response format.");
            }

            loadingEl.remove();

            // Extract reply supporting both standard data.data.reply and legacy data.reply
            const replyText = data.data?.reply || data.reply;
            const errorMsg = data.message || data.error;

            if (replyText) {
                renderMessageBubble("assistant", formatMarkdown(replyText));
                chatHistory.push({ role: "model", text: replyText });
                saveHistory();
            } else if (errorMsg) {
                renderMessageBubble("assistant", `<div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${escapeHtml(errorMsg)}</div>`);
            } else {
                renderMessageBubble("assistant", `<div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Unable to process request. Please try again.</div>`);
            }
        } catch (err) {
            loadingEl.remove();
            console.error("Chat error details:", err, "Raw response was:", rawResponse);

            let userErrMsg = "Connection Error: Unable to communicate with the assistant. Please verify your connection.";
            if (err.message && err.message.includes("response format")) {
                userErrMsg = "System Error: Failed to parse chatbot response. Please contact administration.";
            }

            renderMessageBubble("assistant", `<div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${escapeHtml(userErrMsg)}</div>`);
        } finally {
            setControlsDisabled(false);
            scrollToBottom();
            if (!panel.classList.contains("d-none")) {
                safeFocusInput();
            }
        }
    }

    /**
     * Save sliding window chat history to sessionStorage
     */
    function saveHistory() {
        try {
            const historyToSave = chatHistory.length > 10 ? chatHistory.slice(-10) : chatHistory;
            sessionStorage.setItem(storageKey, JSON.stringify(historyToSave));
        } catch (e) {
            console.warn("Unable to save chat history to sessionStorage:", e);
        }
    }

    /**
     * Render message bubble in chat container
     */
    function renderMessageBubble(sender, contentHtml) {
        const bubble = document.createElement("div");
        bubble.className = `chatbot-msg ${sender}`;
        bubble.innerHTML = contentHtml;
        messagesContainer.appendChild(bubble);
    }

    /**
     * Render animated loading dots indicator
     */
    function renderLoadingIndicator() {
        const loadingBubble = document.createElement("div");
        loadingBubble.className = "chatbot-loading-bubble";
        loadingBubble.innerHTML = `
            <div class="chatbot-loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        messagesContainer.appendChild(loadingBubble);
        return loadingBubble;
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /**
     * Robust Markdown & Entity Code Parser
     */
    function formatMarkdown(text) {
        if (!text) return "";

        let escaped = escapeHtml(text);

        // Fenced Code Blocks: ```code```
        escaped = escaped.replace(/```([\s\S]*?)```/g, '<pre class="bg-dark text-light p-2 rounded small font-monospace overflow-x-auto"><code>$1</code></pre>');

        // Inline Code: `code`
        escaped = escaped.replace(/`([^`]+)`/g, '<code class="bg-secondary bg-opacity-10 text-primary px-1 rounded font-monospace small">$1</code>');

        // Bold & Italic
        escaped = escaped.replace(/\*\*\*(.*?)\*\*\*/g, "<strong><em>$1</em></strong>");
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
        escaped = escaped.replace(/\*(.*?)\*/g, "<em>$1</em>");

        // Clean redundant AI filler text like "[View Details]"
        escaped = escaped.replace(/:\s*\[View Details\](\s*\(link to modal\))?/gi, "");
        escaped = escaped.replace(/\[View Details\]/gi, "");
        escaped = escaped.replace(/\(link to modal\)/gi, "");

        // Entity Triggers: Purchase Orders (PO-xxxx)
        escaped = escaped.replace(/\b(PO-\d{4,8}-\d+|PO-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-info text-dark shadow-sm chatbot-po-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="if(window.openPoModalByNo){window.openPoModalByNo('${match}');}return false;" title="Open ${match} modal"><i class="bi bi-file-earmark-spreadsheet me-1"></i>${match}</button>`;
        });

        // Entity Triggers: Material Withdrawals (WD-xxxx, WS-xxxx)
        escaped = escaped.replace(/\b(WD-\d{4}-\d+|WS-\d{4}-\d+|WITH-\d+|WS-\d+|WD-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-secondary shadow-sm chatbot-wd-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="if(window.openWithdrawalModalByNo){window.openWithdrawalModalByNo('${match}');}return false;" title="Open ${match} modal"><i class="bi bi-box-arrow-up-right me-1"></i>${match}</button>`;
        });

        // Entity Triggers: Inventory Items (ITM-xxxx)
        escaped = escaped.replace(/\b(ITM-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-success shadow-sm chatbot-itm-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="if(window.openItemModalByCode){window.openItemModalByCode('${match}');}return false;" title="Open ${match} profile modal"><i class="bi bi-box-seam me-1"></i>${match}</button>`;
        });

        // Entity Triggers: Requisition Slips (RS-xxxx)
        escaped = escaped.replace(/\b(RS-\d{4}-\d+|RS-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-primary shadow-sm chatbot-rs-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="if(window.openRsModalByNo){window.openRsModalByNo('${match}');}return false;" title="Open ${match} modal"><i class="bi bi-file-earmark-text me-1"></i>${match}</button>`;
        });

        // List Parsing (Unordered and Ordered)
        const lines = escaped.split("\n");
        let inUl = false;
        let inOl = false;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            
            // Bullet list items (- or *)
            if (/^[-*]\s+(.*)$/.test(line)) {
                const content = line.replace(/^[-*]\s+/, "");
                if (inOl) { lines[i] = "</ol>"; inOl = false; }
                if (!inUl) {
                    lines[i] = `<ul class="ps-3 mb-1"><li>${content}</li>`;
                    inUl = true;
                } else {
                    lines[i] = `<li>${content}</li>`;
                }
            } 
            // Numbered list items (1. 2. etc.)
            else if (/^\d+\.\s+(.*)$/.test(line)) {
                const content = line.replace(/^\d+\.\s+/, "");
                if (inUl) { lines[i] = "</ul>"; inUl = false; }
                if (!inOl) {
                    lines[i] = `<ol class="ps-3 mb-1"><li>${content}</li>`;
                    inOl = true;
                } else {
                    lines[i] = `<li>${content}</li>`;
                }
            } else {
                if (inUl) {
                    lines[i] = "</ul>" + lines[i];
                    inUl = false;
                }
                if (inOl) {
                    lines[i] = "</ol>" + lines[i];
                    inOl = false;
                }
            }
        }
        if (inUl) lines[lines.length - 1] += "</ul>";
        if (inOl) lines[lines.length - 1] += "</ol>";

        return lines.join("\n").replace(/\n/g, "<br>");
    }

    // Auto-hide Chatbot when ANY Bootstrap Modal Opens
    document.addEventListener("show.bs.modal", () => {
        if (container) container.classList.add("d-none");
        if (panel && !panel.classList.contains("d-none")) panel.classList.add("d-none");
    });

    // Restore Chatbot when all modals are closed
    document.addEventListener("hidden.bs.modal", () => {
        setTimeout(() => {
            if (!document.querySelector(".modal.show") && !document.body.classList.contains("modal-open")) {
                if (container) container.classList.remove("d-none");
            }
        }, 150);
    });
});
