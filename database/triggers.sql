DELIMITER //

CREATE TRIGGER order_status_insert
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    INSERT INTO order_status_history (order_id, status, changed_by, notes)
    VALUES (NEW.id, NEW.status, NULL, 'Order placed');
END//

CREATE TRIGGER order_status_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status <> OLD.status THEN
        INSERT INTO order_status_history (order_id, status, changed_by, notes)
        VALUES (
            NEW.id, 
            NEW.status,
            COALESCE(@current_admin_id, (SELECT id FROM admin_users WHERE username = SUBSTRING_INDEX(USER(), '@', 1)), NULL),
            CONCAT('Status changed from ', OLD.status, ' to ', NEW.status)
        );
    END IF;
END//

DELIMITER ;