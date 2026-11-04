

CREATE OR REPLACE VIEW vw_borrowing_details AS
SELECT
    bt.borrow_id,
    b.borrower_id,
    b.borrower_no,
    b.full_name,
    b.borrower_type,
    e.equipment_id,
    e.property_no,
    e.equipment_name,
    bi.quantity,
    bt.borrow_date,
    bt.due_date,
    bt.status,
    CASE
        WHEN bt.status IN ('Borrowed','Overdue') AND CURRENT_DATE > bt.due_date
             THEN CURRENT_DATE - bt.due_date
        ELSE 0
    END AS days_overdue,
    CASE bt.status
        WHEN 'Borrowed'  THEN 'Active'
        WHEN 'Overdue'   THEN 'Needs Return'
        WHEN 'Returned'  THEN 'Completed'
        WHEN 'Cancelled' THEN 'Voided'
    END AS loan_status
FROM borrow_transactions bt
JOIN borrowers    b  ON b.borrower_id  = bt.borrower_id
JOIN borrow_items bi ON bi.borrow_id   = bt.borrow_id
JOIN equipment    e  ON e.equipment_id = bi.equipment_id;


CREATE OR REPLACE VIEW vw_equipment_usage AS
SELECT
    e.equipment_id,
    e.property_no,
    e.equipment_name,
    e.quantity_available                                                AS units_owned,
    COUNT(DISTINCT bi.borrow_id)                                       AS total_transactions,
    COALESCE(SUM(bi.quantity), 0)                                      AS total_qty_borrowed,
    COALESCE(SUM(CASE WHEN bt.status IN ('Borrowed','Overdue')
                      THEN bi.quantity END), 0)                        AS units_out,
    e.quantity_available
      - COALESCE(SUM(CASE WHEN bt.status IN ('Borrowed','Overdue')
                          THEN bi.quantity END), 0)                    AS available_now,
    ROUND(COALESCE(AVG(bi.quantity), 0), 2)                           AS avg_qty_per_borrow,
    ABS(e.quantity_available
      - COALESCE(SUM(CASE WHEN bt.status IN ('Borrowed','Overdue')
                          THEN bi.quantity END), 0))                   AS abs_stock_diff,
    CASE
        WHEN e.quantity_available
             - COALESCE(SUM(CASE WHEN bt.status IN ('Borrowed','Overdue')
                                 THEN bi.quantity END), 0) <= 0
             THEN 'Out of Stock'
        WHEN (e.quantity_available
              - COALESCE(SUM(CASE WHEN bt.status IN ('Borrowed','Overdue')
                                  THEN bi.quantity END), 0)) BETWEEN 1 AND 2
             OR (e.quantity_available > 0
                 AND (  e.quantity_available
                      - COALESCE(SUM(CASE WHEN bt.status IN ('Borrowed','Overdue')
                                          THEN bi.quantity END), 0)
                     )::NUMERIC / e.quantity_available <= 0.25)
             THEN 'Low Stock'
        ELSE 'Available'
    END AS availability_status
FROM equipment e
LEFT JOIN borrow_items        bi ON bi.equipment_id = e.equipment_id
LEFT JOIN borrow_transactions bt ON bt.borrow_id    = bi.borrow_id
GROUP BY e.equipment_id, e.property_no, e.equipment_name, e.quantity_available;
