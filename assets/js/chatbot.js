document.addEventListener("DOMContentLoaded", () => {
    // Prevent duplicate initialization on SPA routing
    if (window.cimsChatbotInitialized) return;
    window.cimsChatbotInitialized = true;

    const trigger = document.getElementById("cims-chatbot-trigger");
    const panel = document.getElementById("cims-chatbot-panel");
    const closeBtn = document.getElementById("cims-chatbot-close");
    const form = document.getElementById("cims-chatbot-form");
    const input = document.getElementById("cims-chatbot-input");
    const messagesContainer = document.getElementById("cims-chatbot-messages");
    const suggestionsBar = document.querySelector(".chatbot-suggestions-bar");

    if (!trigger || !panel || !closeBtn || !form || !messagesContainer) return;

    // Chat History Array
    let chatHistory = [
        {
            role: "model",
            text: messagesContainer.querySelector(".chatbot-msg.assistant").innerHTML.trim()
        }
    ];

    // Determine role from container data attribute or fallback
    const container = document.getElementById("cims-chatbot-container");
    const userRole = (container && container.dataset.userRole) ? container.dataset.userRole.toLowerCase() : "requestor";
    
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

    // Load suggestions based on active role
    let activeSuggestions = [];
    if (isRole("admin")) activeSuggestions = suggestions.admin;
    else if (isRole("management") || isRole("approver")) activeSuggestions = suggestions.management;
    else if (isRole("purchasing")) activeSuggestions = suggestions.purchasing;
    else if (isRole("warehouse")) activeSuggestions = suggestions.warehouse;
    else activeSuggestions = suggestions.requestor;

    // Render Suggestions Chips
    suggestionsBar.innerHTML = "";
    activeSuggestions.forEach(chip => {
        const btn = document.createElement("div");
        btn.className = "chatbot-chip";
        btn.innerText = chip.text;
        btn.addEventListener("click", () => {
            sendUserMessage(chip.prompt);
        });
        suggestionsBar.appendChild(btn);
    });

    // Toggle panel visibility
    trigger.addEventListener("click", () => {
        panel.classList.toggle("d-none");
        scrollToBottom();
        // Clear pulse animation once clicked
        const badge = trigger.querySelector(".pulse-badge");
        if (badge) badge.remove();
        if (!panel.classList.contains("d-none")) {
            input.focus();
        }
    });

    closeBtn.addEventListener("click", () => {
        panel.classList.add("d-none");
    });

    // Handle Form Submit
    form.addEventListener("submit", (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        sendUserMessage(text);
        input.value = "";
    });

    // Send user message
    async function sendUserMessage(text) {
        // Render user message bubble
        renderMessage("user", text);
        chatHistory.push({ role: "user", text: text });

        // Show thinking loading indicator
        const loadingEl = renderLoadingIndicator();
        scrollToBottom();

        let rawResponse = "";
        try {
            // Construct base path dynamically
            const basePath = window.cimsBasePath || "";
            const response = await fetch(`${basePath}/process/chatbot_chat.php`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    messages: chatHistory
                })
            });

            rawResponse = await response.text();
            const data = JSON.parse(rawResponse);
            
            // Remove loading bubble
            loadingEl.remove();

            if (data.reply) {
                renderMessage("assistant", formatMarkdown(data.reply));
                chatHistory.push({ role: "model", text: data.reply });
            } else if (data.error) {
                renderMessage("assistant", `<div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Error: ${data.error}</div>`);
            } else {
                renderMessage("assistant", `<div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Failed to communicate with the system.</div>`);
            }
        } catch (err) {
            loadingEl.remove();
            console.error("Chat error details:", err, "Raw response received was:", rawResponse);
            
            // Inform the user of parse errors vs network connection errors
            let userErrMsg = "Connection Error. Please verify your internet connection.";
            if (err instanceof SyntaxError) {
                userErrMsg = "System Error: Failed to parse chatbot response. Please contact administration.";
            }
            
            renderMessage("assistant", `<div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${userErrMsg}</div>`);
        }
        scrollToBottom();
    }

    // Helper functions
    function renderMessage(sender, text) {
        const bubble = document.createElement("div");
        bubble.className = `chatbot-msg ${sender}`;
        bubble.innerHTML = text;
        messagesContainer.appendChild(bubble);
    }

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

    function formatMarkdown(text) {
        let escaped = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
        
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
        escaped = escaped.replace(/\*(.*?)\*/g, "<em>$1</em>");
        
        // Clean up redundant AI filler text like ": [View Details] (link to modal)" or "[View Details]"
        escaped = escaped.replace(/:\s*\[View Details\](\s*\(link to modal\))?/gi, "");
        escaped = escaped.replace(/\[View Details\]/gi, "");
        escaped = escaped.replace(/\(link to modal\)/gi, "");

        // Auto-detect and convert Purchase Order numbers (e.g. PO-20260805-123, PO-0001) into interactive trigger buttons
        escaped = escaped.replace(/\b(PO-\d{4,8}-\d+|PO-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-info text-dark shadow-sm chatbot-po-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="window.openPoModalByNo('${match}'); return false;" title="Click to open ${match} details modal"><i class="bi bi-file-earmark-spreadsheet me-1"></i>${match}</button>`;
        });

        // Auto-detect and convert Withdrawal numbers (e.g. WD-2026-001, WS-2026-001, WITH-0001, WS-0001) into interactive trigger buttons
        escaped = escaped.replace(/\b(WD-\d{4}-\d+|WS-\d{4}-\d+|WITH-\d+|WS-\d+|WD-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-secondary shadow-sm chatbot-wd-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="window.openWithdrawalModalByNo('${match}'); return false;" title="Click to open ${match} details modal"><i class="bi bi-box-arrow-up-right me-1"></i>${match}</button>`;
        });

        // Auto-detect and convert Inventory Item codes (e.g. ITM-4430, ITM-4130, ITM-3) into interactive trigger buttons
        escaped = escaped.replace(/\b(ITM-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-success shadow-sm chatbot-itm-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="window.openItemModalByCode('${match}'); return false;" title="Click to open ${match} profile modal"><i class="bi bi-box-seam me-1"></i>${match}</button>`;
        });

        // Auto-detect and convert RS Numbers (e.g. RS-0001, RS-2026-1001) into interactive trigger buttons
        escaped = escaped.replace(/\b(RS-\d{4}-\d+|RS-\d+)\b/gi, (match) => {
            return `<button type="button" class="btn btn-sm btn-primary shadow-sm chatbot-rs-trigger ms-1 me-1 px-2 py-0 align-baseline fw-bold" onclick="window.openRsModalByNo('${match}'); return false;" title="Click to open ${match} details modal"><i class="bi bi-file-earmark-text me-1"></i>${match}</button>`;
        });
        
        let lines = escaped.split("\n");
        let inList = false;
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (line.startsWith("- ") || line.startsWith("* ")) {
                let content = line.substring(2);
                if (!inList) {
                    lines[i] = "<ul><li>" + content + "</li>";
                    inList = true;
                } else {
                    lines[i] = "<li>" + content + "</li>";
                }
            } else {
                if (inList) {
                    lines[i] = "</ul>" + lines[i];
                    inList = false;
                }
            }
        }
        if (inList) {
            lines[lines.length - 1] += "</ul>";
        }
        
        return lines.join("\n").replace(/\n/g, "<br>");
    }

    // Auto-hide Chatbot when ANY modal opens (e.g. Create RS, View Details, Edit RS)
    document.addEventListener("show.bs.modal", () => {
        if (container) {
            container.classList.add("d-none");
        }
        if (panel && !panel.classList.contains("d-none")) {
            panel.classList.add("d-none");
        }
    });

    // Restore Chatbot when all modals are closed
    document.addEventListener("hidden.bs.modal", () => {
        setTimeout(() => {
            if (!document.querySelector(".modal.show") && !document.body.classList.contains("modal-open")) {
                if (container) {
                    container.classList.remove("d-none");
                }
            }
        }, 150);
    });
});
