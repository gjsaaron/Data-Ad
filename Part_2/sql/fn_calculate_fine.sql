CREATE OR REPLACE FUNCTION fn_calculate_fine(p_borrow_id INT)
RETURNS NUMERIC AS $$
DECLARE
    v_due_date  DATE;
    v_fine_rate NUMERIC(8,2);
    v_status    VARCHAR(20);
    v_days      INT;
BEGIN
    SELECT due_date, fine_rate, status
      INTO v_due_date, v_fine_rate, v_status
      FROM borrow_transactions
     WHERE borrow_id = p_borrow_id;

    IF NOT FOUND THEN
        RETURN 0;
    END IF;

    -- If status is Borrowed but past due date, treat as Overdue for fine calculation
    IF v_status <> 'Overdue' AND NOT (v_status = 'Borrowed' AND CURRENT_DATE > v_due_date) THEN
        RETURN 0;
    END IF;

    v_days := GREATEST(0, CURRENT_DATE - v_due_date);
    RETURN ROUND(v_days * v_fine_rate, 0);
END;
$$ LANGUAGE plpgsql;
