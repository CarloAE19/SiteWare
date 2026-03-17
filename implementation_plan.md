# Implementation Plan: PO Supplier History & SMS Blaster

This plan details the steps to fulfill your request: providing the Purchase Officer with supplier history for items in an Approved Requisition and automating the exact item list inside the SMS Blast.

## User Review Required
> [!IMPORTANT] 
> 1. **SMS Blaster API**: You mentioned "we are using a SMS Blaster API". What is the specific name or endpoint of the API you are using (e.g., Semaphore, Twilio, PhilSMS)? I will write a generic cURL POST request structure that you can configure, but if you give me the API docs/name, I can implement it exactly as required.
> 2. **PO Modal UI**: I will add an interactive table inside the "Create PO" modal that will instantly load the requested items and show "Past Supplier: [Name]" when checking the RS dropdown.

## Proposed Changes

### Component 1: Purchasing Backend (AJAX & SMS)
#### [MODIFY] [process/process.php](file:///c:/xampp/htdocs/CIMS/process/process.php)
- Add `fetch_rs_with_history` to the allowed actions Router under [module_transactions.php](file:///c:/xampp/htdocs/CIMS/process/module_transactions.php).

#### [MODIFY] [process/module_transactions.php](file:///c:/xampp/htdocs/CIMS/process/module_transactions.php)
- Add the `fetch_rs_with_history` action block. It will grab all `requisition_items` for a specific `rs_id`, then do a reverse lookup on `po_items` and `purchase_orders` to find the last known `supplier_id` and `company_name` for that specific item.
- Modify the `send_po_sms` action block. Instead of just simulating the SMS, it will:
    1. Query all `po_items` connected to the `po_id`.
    2. Build a message body: 
       ```
       GB Construction PO: [PO_NO]
       Items:
       - 10x Item1
       - 5x Item2
       
       If you have any concerns or clarrifications text or email here
       ```
    3. Send this specifically formatted text to the target SMS API gateway through a structured POST request.

### Component 2: Purchasing Interface
#### [MODIFY] [components/po_modal.php](file:///c:/xampp/htdocs/CIMS/components/po_modal.php)
- Add a new `div` container inside the PO Modal (`#rsItemsPreview`) under the Requisition (RS) dropdown. 

#### [MODIFY] [po.php](file:///c:/xampp/htdocs/CIMS/po.php)
- Add a JavaScript event listener on the RS dropdown. When the Purchase Officer selects an Approved Requisition, it will trigger an AJAX call to `fetch_rs_with_history`, and dynamically populate the `#rsItemsPreview` displaying each requested item alongside its historical supplier.

## Verification Plan
### Automated / Manual Verification
1. **Supplier History Test**: Log in as a Purchase Officer, click "Create PO", and select an Approved Requisition. Verify that a table drops down showing the exact items needed, alongside the names of suppliers who previously fulfilled them.
2. **SMS Output Test**: Click the "SMS" button on an existing PO. Verify in the Network/PHP logs that the specific formatted message string (containing the specific items and the required concern/clarification sentence) is correctly constructed and passed to the payload for the SMS API.
