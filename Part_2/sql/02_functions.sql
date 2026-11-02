-- ============================================================
-- 02_functions.sql
-- Run AFTER 01_views.sql
-- Creates: fn_total_borrowed, fn_check_equipment_availability
-- ============================================================

-- A. SQL Function: total units currently borrowed for one equipment
CREATE OR REPLACE FUNCTION fn_total_borrowed(p_equipment_id INTEGER)
RETURNS INTEGER
LANGUAGE SQL AS $$
    SELECT COALESCE(SUM(bi.quantity), 0)::INTEGER
    FROM   borrow_items bi
    JOIN   borrow_transactions bt ON bt.borrow_id = bi.borrow_id
    WHERE  bi.equipment_id = p_equipment_id
      AND  bt.status IN ('Borrowed', 'Overdue');
$$;


-- B. PL/pgSQL Function: check if a quantity can be accommodated
CREATE OR REPLACE FUNCTION fn_check_equipment_availability(
    p_equipment_id INTEGER,
    p_requested    INTEGER
)
RETURNS TEXT
LANGUAGE plpgsql AS $$
DECLARE
    v_name  TEXT;
    v_owned INTEGER;
    v_out   INTEGER;
    v_avail INTEGER;
BEGIN
    -- Get equipment details
    SELECT equipment_name, quantity_available
    INTO   v_name, v_owned
    FROM   equipment
    WHERE  equipment_id = p_equipment_id;

    IF NOT FOUND THEN
        RETURN 'ERROR: Equipment ID ' || p_equipment_id || ' does not exist.';
    END IF;

    IF p_requested <= 0 THEN
        RETURN 'ERROR: Requested quantity must be greater than 0.';
    END IF;

    -- Use the SQL function to get units currently out
    v_out   := fn_total_borrowed(p_equipment_id);
    v_avail := v_owned - v_out;

    IF p_requested <= v_avail THEN
        RETURN 'AVAILABLE — ' || v_name
            || ': ' || v_avail || ' units in stock, '
            || p_requested || ' requested. OK to borrow.';
    ELSE
        RETURN 'UNAVAILABLE — ' || v_name
            || ': only ' || v_avail || ' units in stock, '
            || p_requested || ' requested. Not enough.';
    END IF;
END;
$$;

-- Quick test queries (optional — run to verify)
-- SELECT fn_total_borrowed(1);
-- SELECT fn_check_equipment_availability(1, 1);
-- SELECT fn_check_equipment_availability(1, 9999);
