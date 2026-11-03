-- Run this once in pgAdmin Query Tool
-- Adds fine_rate to borrow_transactions and enforces uniqueness + non-negative constraints

-- 1. Fine rate column (PHP per day, default 5.00)
ALTER TABLE borrow_transactions
    ADD COLUMN IF NOT EXISTS fine_rate NUMERIC(8,2) NOT NULL DEFAULT 5.00
        CHECK (fine_rate >= 0);

-- 2. No duplicate borrower name
ALTER TABLE borrowers
    ADD CONSTRAINT uq_borrowers_full_name UNIQUE (full_name);

-- 3. No duplicate equipment name
ALTER TABLE equipment
    ADD CONSTRAINT uq_equipment_name UNIQUE (equipment_name);

-- 4. Quantity must be non-negative (safety re-add)
ALTER TABLE equipment
    DROP CONSTRAINT IF EXISTS equipment_quantity_available_check;
ALTER TABLE equipment
    ADD CONSTRAINT equipment_quantity_available_check CHECK (quantity_available >= 0);

ALTER TABLE borrow_items
    DROP CONSTRAINT IF EXISTS borrow_items_quantity_check;
ALTER TABLE borrow_items
    ADD CONSTRAINT borrow_items_quantity_check CHECK (quantity > 0);

-- 5. Computed fine view (uses ROUND)
CREATE OR REPLACE VIEW v_overdue_fines AS
SELECT
    t.borrow_id,
    b.borrower_no,
    b.full_name,
    t.borrow_date,
    t.due_date,
    t.status,
    t.fine_rate,
    GREATEST(0, CURRENT_DATE - t.due_date)                          AS days_overdue,
    ROUND(GREATEST(0, CURRENT_DATE - t.due_date) * t.fine_rate, 0) AS total_fine
FROM borrow_transactions t
JOIN borrowers b ON b.borrower_id = t.borrower_id;
