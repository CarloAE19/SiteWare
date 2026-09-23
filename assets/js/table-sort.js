/**
 * CIMS Universal Table Sorter (table-sort.js)
 * High-performance, accessible, and non-destructive table sorting for CIMS.
 * 
 * Standards Compliance:
 * - quality-standards.md: Zero disruption to PHP sessions, prepared statements, or client filters.
 * - cims-modal-ajax-handler: Preserves DOM nodes, handles dynamic row injections via 'cims:table-updated'.
 * - Accessible (a11y): ARIA attributes (aria-sort), keyboard navigation (Enter/Space), min 44px touch targets.
 */

(function () {
    'use strict';

    // Target table selectors across CIMS modules
    const DEFAULT_TABLE_SELECTORS = [
        '#poTable',
        '#rsTable',
        '#inventoryTable',
        '#withdrawalsTable',
        '#suppliersTable',
        '#projectsTable',
        '#recountTable',
        '#historyTable',
        '#usersTable',
        '#categoriesTable',
        '#unitsTable',
        '#backupsTable',
        '.table-sortable'
    ];

    // Columns that must never be sorted (e.g. actions, checkboxes)
    const EXCLUDED_HEADER_KEYWORDS = [
        'action',
        'actions',
        'logistics action',
        'logistics actions',
        'operation',
        'select',
        'checkbox'
    ];

    /**
     * Determines whether a table header should be sortable.
     */
    function isSortableHeader(th) {
        if (!th) return false;
        if (th.classList.contains('no-sort') || th.hasAttribute('data-no-sort')) return false;

        const headerText = (th.textContent || '').trim().toLowerCase();
        if (!headerText) return false;

        for (const keyword of EXCLUDED_HEADER_KEYWORDS) {
            if (headerText === keyword || headerText.startsWith(keyword + ' ') || headerText.endsWith(' ' + keyword)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Guesses the column data type based on header text and sample cell contents.
     */
    function detectColumnType(th, table, colIndex) {
        const customType = th.getAttribute('data-sort-type');
        if (customType) return customType.toLowerCase();

        const title = (th.textContent || '').trim().toLowerCase();

        // Keyword detection for Dates
        if (title.includes('date') || title.includes('time') || title.includes('eta') ||
            title.includes('created') || title.includes('log') || title.includes('schedule') ||
            title.includes('deadline')) {
            return 'date';
        }

        // Keyword detection for Numbers/Currency/Quantities
        if (title.includes('qty') || title.includes('quantity') || title.includes('amount') ||
            title.includes('price') || title.includes('total') || title.includes('cost') ||
            title.includes('stock') || title.includes('count') || title.includes('variance') ||
            title.includes('level') || title.includes('balance')) {
            return 'number';
        }

        // Sample rows to test values
        const rows = table.querySelectorAll('tbody tr:not([id*="noResults"]):not(.no-sort)');
        let sampleCount = 0;
        let dateCount = 0;
        let numCount = 0;

        for (let i = 0; i < rows.length && sampleCount < 10; i++) {
            const cell = rows[i].children[colIndex];
            if (!cell) continue;

            const val = getCleanCellValue(cell, rows[i]);
            if (!val) continue;

            sampleCount++;

            // Test if numeric (strip currencies, commas, units)
            const cleanNum = val.replace(/^[₱$€£\s]+/, '').replace(/,/g, '').replace(/\s*(pcs|kg|meters|bags|units|boxes|rolls|liters|items|m)\b/gi, '').trim();
            if (cleanNum !== '' && !isNaN(cleanNum)) {
                numCount++;
            }

            // Test if date (e.g. 2026-09-23, Sep 23, 2026, 09/23/2026)
            if (isValidDateString(val)) {
                dateCount++;
            }
        }

        if (sampleCount > 0) {
            if (dateCount / sampleCount >= 0.7) return 'date';
            if (numCount / sampleCount >= 0.7) return 'number';
        }

        return 'string';
    }

    /**
     * Checks if a string looks like a parseable date.
     */
    function isValidDateString(str) {
        if (!str || typeof str !== 'string') return false;
        // Common date formats: YYYY-MM-DD, MMM DD, YYYY, MM/DD/YYYY, etc.
        if (/\d{4}-\d{2}-\d{2}/.test(str) || /[a-z]{3}\s+\d{1,2},?\s+\d{4}/i.test(str) || /\d{1,2}\/\d{1,2}\/\d{2,4}/.test(str)) {
            const parsed = Date.parse(str);
            return !isNaN(parsed);
        }
        return false;
    }

    /**
     * Extracts a sort-friendly value from a cell.
     * Looks at data attributes first, then text content.
     */
    function getCleanCellValue(cell, row) {
        if (!cell) return '';

        // Priority 1: Direct cell attribute
        if (cell.hasAttribute('data-sort-value')) {
            return cell.getAttribute('data-sort-value');
        }
        if (cell.hasAttribute('data-value')) {
            return cell.getAttribute('data-value');
        }
        if (cell.hasAttribute('data-date')) {
            return cell.getAttribute('data-date');
        }

        // Priority 2: Row-level attribute for specific columns
        if (row) {
            const colName = cell.getAttribute('data-label');
            if (colName) {
                const normCol = colName.toLowerCase();
                if ((normCol.includes('date') || normCol.includes('created')) && row.hasAttribute('data-created-date')) {
                    return row.getAttribute('data-created-date');
                }
                if (normCol.includes('status') && row.hasAttribute('data-status')) {
                    return row.getAttribute('data-status');
                }
                if (normCol.includes('urgency') && row.hasAttribute('data-urgency')) {
                    return row.getAttribute('data-urgency');
                }
            }
        }

        // Priority 3: Inner element attributes (e.g. badge, time tag)
        const timeEl = cell.querySelector('time');
        if (timeEl && timeEl.getAttribute('datetime')) {
            return timeEl.getAttribute('datetime');
        }

        // Priority 4: Plain trimmed text content without hidden elements
        return (cell.textContent || '').replace(/\s+/g, ' ').trim();
    }

    /**
     * Parses a date value into a comparable timestamp number.
     */
    function parseDateToTimestamp(rawVal) {
        if (!rawVal) return 0;
        const val = rawVal.trim();

        // Direct numeric timestamp
        if (/^\d{10,13}$/.test(val)) {
            return parseInt(val, 10);
        }

        // Try direct parse
        const parsed = Date.parse(val);
        if (!isNaN(parsed)) {
            return parsed;
        }

        // Handle relative strings (e.g. "In 3d (Sep 26)" or "Overdue (2d)")
        const inMatch = val.match(/In\s+(\d+)\s*d/i);
        if (inMatch) {
            return Date.now() + parseInt(inMatch[1], 10) * 86400000;
        }
        const overdueMatch = val.match(/Overdue\s*\((\d+)\s*d\)/i);
        if (overdueMatch) {
            return Date.now() - parseInt(overdueMatch[1], 10) * 86400000;
        }

        return 0;
    }

    /**
     * Parses a numeric value into a float.
     */
    function parseNumberVal(rawVal) {
        if (!rawVal) return 0;
        // Strip currencies, commas, units, leave digits, minus, and dot
        const cleaned = rawVal
            .replace(/^[₱$€£\s]+/, '')
            .replace(/,/g, '')
            .replace(/\s*(pcs|kg|meters|bags|units|boxes|rolls|liters|items|m)\b/gi, '')
            .trim();
        const num = parseFloat(cleaned);
        return isNaN(num) ? 0 : num;
    }

    /**
     * Builds interactive sort UI on a single table.
     */
    function enhanceTable(table) {
        if (!table || table.dataset.cimsSorterInitialized === 'true') return;

        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        if (!thead || !tbody) return;

        const headers = thead.querySelectorAll('th');
        if (headers.length === 0) return;

        table.dataset.cimsSorterInitialized = 'true';
        table.classList.add('cims-sortable-table');

        // Snapshot original order index on rows for clean "reset" state
        snapshotRowOrder(tbody);

        headers.forEach((th, colIndex) => {
            if (!isSortableHeader(th)) return;

            const colType = detectColumnType(th, table, colIndex);
            th.dataset.cimsColType = colType;
            th.classList.add('cims-sortable-th');
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');
            th.setAttribute('aria-sort', 'none');

            // Get initial tooltips
            const tooltips = getTooltipTexts(colType);
            th.title = tooltips.initial;

            // Wrap or create clean indicator
            let indicator = th.querySelector('.cims-sort-indicator');
            if (!indicator) {
                indicator = document.createElement('span');
                indicator.className = 'cims-sort-indicator ms-2 d-inline-flex align-items-center';
                indicator.innerHTML = '<i class="bi bi-arrow-down-up cims-sort-icon"></i>';
                th.appendChild(indicator);
            }

            // Click listener
            th.addEventListener('click', (e) => {
                // Ignore if clicked on an interactive control inside th
                if (e.target.closest('button, a, input, select')) return;
                toggleColumnSort(table, th, colIndex, colType);
            });

            // Keyboard accessibility (Enter / Space)
            th.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleColumnSort(table, th, colIndex, colType);
                }
            });
        });
    }

    /**
     * Returns descriptive tooltips based on column type.
     */
    function getTooltipTexts(colType) {
        if (colType === 'date') {
            return {
                initial: 'Click to sort: Latest / Newest first',
                nextAsc: 'Click to sort: Oldest first',
                nextDesc: 'Click to sort: Latest first',
                nextReset: 'Click to reset to default order'
            };
        } else if (colType === 'number') {
            return {
                initial: 'Click to sort: Lowest to Highest',
                nextAsc: 'Click to sort: Highest to Lowest',
                nextDesc: 'Click to sort: Lowest to Highest',
                nextReset: 'Click to reset to default order'
            };
        } else {
            return {
                initial: 'Click to sort: A to Z',
                nextAsc: 'Click to sort: Z to A',
                nextDesc: 'Click to sort: A to Z',
                nextReset: 'Click to reset to default order'
            };
        }
    }

    /**
     * Stores original DOM index on rows.
     */
    function snapshotRowOrder(tbody) {
        const rows = tbody.querySelectorAll('tr:not([id*="noResults"]):not(.no-sort)');
        rows.forEach((row, idx) => {
            if (!row.dataset.cimsOrigIndex) {
                row.dataset.cimsOrigIndex = String(idx);
            }
        });
    }

    /**
     * Toggles sorting state for a header column.
     * State cycle:
     * - Dates: 'desc' (Newest) -> 'asc' (Oldest) -> 'none' (Original)
     * - Others: 'asc' (A-Z / Low-High) -> 'desc' (Z-A / High-Low) -> 'none' (Original)
     */
    function toggleColumnSort(table, activeTh, colIndex, colType) {
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Reset other headers in the table
        const allTh = thead.querySelectorAll('th.cims-sortable-th');
        allTh.forEach((th) => {
            if (th !== activeTh) {
                th.setAttribute('aria-sort', 'none');
                th.classList.remove('cims-sorted-asc', 'cims-sorted-desc');
                const icon = th.querySelector('.cims-sort-icon');
                if (icon) {
                    icon.className = 'bi bi-arrow-down-up cims-sort-icon';
                }
                const otherType = th.dataset.cimsColType || 'string';
                th.title = getTooltipTexts(otherType).initial;
            }
        });

        const currentSort = activeTh.getAttribute('aria-sort') || 'none';
        const tooltips = getTooltipTexts(colType);
        let nextSort = 'none';

        if (colType === 'date') {
            // For dates: Newest first is most useful initially!
            if (currentSort === 'none') {
                nextSort = 'desc';
            } else if (currentSort === 'desc') {
                nextSort = 'asc';
            } else {
                nextSort = 'none';
            }
        } else {
            // For text/number: Ascending first
            if (currentSort === 'none') {
                nextSort = 'asc';
            } else if (currentSort === 'asc') {
                nextSort = 'desc';
            } else {
                nextSort = 'none';
            }
        }

        activeTh.setAttribute('aria-sort', nextSort);
        activeTh.classList.remove('cims-sorted-asc', 'cims-sorted-desc');

        const activeIcon = activeTh.querySelector('.cims-sort-icon');

        if (nextSort === 'asc') {
            activeTh.classList.add('cims-sorted-asc');
            if (activeIcon) {
                activeIcon.className = 'bi bi-arrow-up cims-sort-icon active-sort';
            }
            activeTh.title = (colType === 'date') ? tooltips.nextReset : tooltips.nextAsc;
        } else if (nextSort === 'desc') {
            activeTh.classList.add('cims-sorted-desc');
            if (activeIcon) {
                activeIcon.className = 'bi bi-arrow-down cims-sort-icon active-sort';
            }
            activeTh.title = (colType === 'date') ? tooltips.nextAsc : tooltips.nextReset;
        } else {
            if (activeIcon) {
                activeIcon.className = 'bi bi-arrow-down-up cims-sort-icon';
            }
            activeTh.title = tooltips.initial;
        }

        // Perform the row reordering
        executeSort(tbody, colIndex, colType, nextSort);
    }

    /**
     * Sorts the tbody rows in place, keeping pinned rows intact.
     */
    function executeSort(tbody, colIndex, colType, sortDirection) {
        // Collect rows, separating normal data rows from fixed/pinned rows (like no-results)
        const allRows = Array.from(tbody.children);
        const dataRows = [];
        const pinnedRows = [];

        allRows.forEach((row) => {
            if (row.id && row.id.toLowerCase().includes('noresults')) {
                pinnedRows.push(row);
            } else if (row.classList.contains('no-sort') || row.classList.contains('pinned-row')) {
                pinnedRows.push(row);
            } else {
                dataRows.push(row);
            }
        });

        if (dataRows.length <= 1) return;

        if (sortDirection === 'none') {
            // Reset to original snapshot order
            dataRows.sort((a, b) => {
                const idxA = parseInt(a.dataset.cimsOrigIndex || '0', 10);
                const idxB = parseInt(b.dataset.cimsOrigIndex || '0', 10);
                return idxA - idxB;
            });
        } else {
            // Comparative sorting
            dataRows.sort((rowA, rowB) => {
                const cellA = rowA.children[colIndex];
                const cellB = rowB.children[colIndex];

                const valA = getCleanCellValue(cellA, rowA);
                const valB = getCleanCellValue(cellB, rowB);

                let cmp = 0;

                if (colType === 'date') {
                    const timeA = parseDateToTimestamp(valA);
                    const timeB = parseDateToTimestamp(valB);
                    cmp = timeA - timeB;
                } else if (colType === 'number') {
                    const numA = parseNumberVal(valA);
                    const numB = parseNumberVal(valB);
                    cmp = numA - numB;
                } else {
                    // Natural alphanumeric string comparison
                    cmp = valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' });
                }

                return sortDirection === 'desc' ? -cmp : cmp;
            });
        }

        // Re-append data rows in new order (non-destructive, preserves event listeners)
        const fragment = document.createDocumentFragment();
        dataRows.forEach((row) => fragment.appendChild(row));
        pinnedRows.forEach((row) => fragment.appendChild(row));

        tbody.appendChild(fragment);

        // Notify other components if needed
        tableSortingCompleted(tbody.closest('table'), colIndex, sortDirection);
    }

    /**
     * Sorts mobile cards container by a specific field and direction.
     */
    function sortMobileCards(containerSelector, sortField, sortDirection, clickedItem) {
        const container = typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
        if (!container) return;

        // Visual update on dropdown item if provided
        if (clickedItem) {
            const menu = clickedItem.closest('.dropdown-menu');
            if (menu) {
                menu.querySelectorAll('.dropdown-item').forEach(item => {
                    item.classList.remove('active');
                    const check = item.querySelector('.sort-check');
                    if (check) check.classList.add('d-none');
                });
                clickedItem.classList.add('active');
                const check = clickedItem.querySelector('.sort-check');
                if (check) check.classList.remove('d-none');
            }
        }

        const cards = Array.from(container.children).filter(el => 
            el.classList.contains('cims-mobile-card') || el.classList.contains('po-card') || el.classList.contains('rs-card') || el.classList.contains('card')
        );

        if (cards.length <= 1) return;

        // Snapshot original order index on cards
        cards.forEach((card, idx) => {
            if (!card.dataset.cimsOrigCardIndex) {
                card.dataset.cimsOrigCardIndex = String(idx);
            }
        });

        if (sortDirection === 'none' || sortField === 'reset') {
            cards.sort((a, b) => {
                const idxA = parseInt(a.dataset.cimsOrigCardIndex || '0', 10);
                const idxB = parseInt(b.dataset.cimsOrigCardIndex || '0', 10);
                return idxA - idxB;
            });
        } else {
            cards.sort((cardA, cardB) => {
                let valA = '';
                let valB = '';
                let isDate = false;
                let isNum = false;

                if (sortField === 'date') {
                    isDate = true;
                    valA = cardA.dataset.createdTimestamp || cardA.dataset.createdDate || '';
                    valB = cardB.dataset.createdTimestamp || cardB.dataset.createdDate || '';
                } else if (sortField === 'supplier' || sortField === 'name' || sortField === 'project') {
                    valA = cardA.dataset.supplierName || cardA.dataset.project || (cardA.querySelector('.po-supplier, .rs-project, .card-title')?.textContent || '');
                    valB = cardB.dataset.supplierName || cardB.dataset.project || (cardB.querySelector('.po-supplier, .rs-project, .card-title')?.textContent || '');
                } else if (sortField === 'code' || sortField === 'po' || sortField === 'rs') {
                    valA = cardA.dataset.poNo || cardA.dataset.rsNo || (cardA.querySelector('.po-no, .rs-no')?.textContent || '');
                    valB = cardB.dataset.poNo || cardB.dataset.rsNo || (cardB.querySelector('.po-no, .rs-no')?.textContent || '');
                } else if (sortField === 'status') {
                    valA = cardA.dataset.status || '';
                    valB = cardB.dataset.status || '';
                }

                let cmp = 0;
                if (isDate) {
                    const timeA = parseDateToTimestamp(String(valA));
                    const timeB = parseDateToTimestamp(String(valB));
                    cmp = timeA - timeB;
                } else if (isNum) {
                    const numA = parseNumberVal(String(valA));
                    const numB = parseNumberVal(String(valB));
                    cmp = numA - numB;
                } else {
                    cmp = String(valA).trim().localeCompare(String(valB).trim(), undefined, { numeric: true, sensitivity: 'base' });
                }

                return sortDirection === 'desc' ? -cmp : cmp;
            });
        }

        // Re-append cards non-destructively
        const fragment = document.createDocumentFragment();
        cards.forEach(card => fragment.appendChild(card));
        container.appendChild(fragment);
    }

    /**
     * Programmatically triggers desktop table sorting to match mobile selection.
     */
    function sortDesktopByHeader(tableSelector, colIndex, sortDirection) {
        const table = typeof tableSelector === 'string' ? document.querySelector(tableSelector) : tableSelector;
        if (!table) return;

        const thList = table.querySelectorAll('thead th');
        if (colIndex === -1 || sortDirection === 'none') {
            const tbody = table.querySelector('tbody');
            if (tbody) {
                executeSort(tbody, 0, 'string', 'none');
                thList.forEach(th => {
                    th.setAttribute('aria-sort', 'none');
                    th.classList.remove('cims-sorted-asc', 'cims-sorted-desc');
                    const icon = th.querySelector('.cims-sort-icon');
                    if (icon) icon.className = 'bi bi-arrow-down-up cims-sort-icon';
                });
            }
            return;
        }

        const th = thList[colIndex];
        if (!th) return;

        const colType = th.dataset.cimsColType || detectColumnType(th, table, colIndex);
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Reset other headers
        thList.forEach(otherTh => {
            if (otherTh !== th) {
                otherTh.setAttribute('aria-sort', 'none');
                otherTh.classList.remove('cims-sorted-asc', 'cims-sorted-desc');
                const icon = otherTh.querySelector('.cims-sort-icon');
                if (icon) icon.className = 'bi bi-arrow-down-up cims-sort-icon';
            }
        });

        th.setAttribute('aria-sort', sortDirection);
        th.classList.remove('cims-sorted-asc', 'cims-sorted-desc');
        const activeIcon = th.querySelector('.cims-sort-icon');

        if (sortDirection === 'asc') {
            th.classList.add('cims-sorted-asc');
            if (activeIcon) activeIcon.className = 'bi bi-arrow-up cims-sort-icon active-sort';
        } else if (sortDirection === 'desc') {
            th.classList.add('cims-sorted-desc');
            if (activeIcon) activeIcon.className = 'bi bi-arrow-down cims-sort-icon active-sort';
        }

        executeSort(tbody, colIndex, colType, sortDirection);
    }

    /**
     * Hook triggered after desktop sorting to sync mobile cards if present.
     */
    function tableSortingCompleted(table, colIndex, sortDirection) {
        if (!table) return;

        // Auto-sync mobile cards for PO and RS
        if (table.id === 'poTable') {
            const poMobile = document.getElementById('poMobileCards');
            if (poMobile) {
                let field = 'date';
                if (colIndex === 0) field = 'code';
                if (colIndex === 1) field = 'date';
                if (colIndex === 3) field = 'supplier';
                if (colIndex === 4) field = 'status';
                sortMobileCards(poMobile, field, sortDirection);
            }
        } else if (table.id === 'rsTable') {
            const rsMobile = document.getElementById('rsMobileCards');
            if (rsMobile) {
                let field = 'date';
                if (colIndex === 0) field = 'code';
                if (colIndex === 1) field = 'project';
                if (colIndex === 3) field = 'date';
                if (colIndex === 5) field = 'status';
                sortMobileCards(rsMobile, field, sortDirection);
            }
        }

        // Custom event for reactive components or modals
        table.dispatchEvent(new CustomEvent('cims:table-sorted', {
            bubbles: true,
            detail: { colIndex, sortDirection }
        }));
    }

    /**
     * Initializes all recognized tables on the current page.
     */
    function initAllTables() {
        DEFAULT_TABLE_SELECTORS.forEach((selector) => {
            const tables = document.querySelectorAll(selector);
            tables.forEach((table) => enhanceTable(table));
        });
    }

    // Expose global initializer for AJAX modals or dynamically loaded tables
    window.CimsTableSorter = {
        init: initAllTables,
        enhance: enhanceTable,
        sortMobileCards: sortMobileCards,
        sortDesktopByHeader: sortDesktopByHeader,
        refresh: function (tableEl) {
            if (tableEl) {
                const tbody = tableEl.querySelector('tbody');
                if (tbody) snapshotRowOrder(tbody);
            }
        }
    };

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllTables);
    } else {
        initAllTables();
    }

    // Handle AJAX updates from modals (cims-modal-ajax-handler standard)
    document.addEventListener('cims:table-updated', (e) => {
        const table = (e.detail && e.detail.table) || (e.target && e.target.closest && e.target.closest('table')) || document.querySelector(DEFAULT_TABLE_SELECTORS.join(','));
        if (table) {
            window.CimsTableSorter.refresh(table);
        } else {
            initAllTables();
        }
    });

    // SPA Router Navigation Event Listeners
    document.addEventListener('cims:content-loaded', initAllTables);
    document.addEventListener('cims:route-changed', initAllTables);

    // MutationObserver: Automatically observes DOM changes so SPA router page swaps and dynamic tables auto-initialize immediately
    try {
        const observer = new MutationObserver((mutations) => {
            let foundTable = false;
            for (let i = 0; i < mutations.length; i++) {
                const added = mutations[i].addedNodes;
                for (let j = 0; j < added.length; j++) {
                    const node = added[j];
                    if (node.nodeType === 1) { // Node.ELEMENT_NODE
                        if (node.tagName === 'TABLE' || (node.querySelector && node.querySelector('table'))) {
                            foundTable = true;
                            break;
                        }
                    }
                }
                if (foundTable) break;
            }
            if (foundTable) {
                initAllTables();
            }
        });

        const targetNode = document.getElementById('content') || document.body || document.documentElement;
        if (targetNode) {
            observer.observe(targetNode, { childList: true, subtree: true });
        }
    } catch (obsErr) {
        console.warn('CimsTableSorter: MutationObserver fallback', obsErr);
    }

})();

