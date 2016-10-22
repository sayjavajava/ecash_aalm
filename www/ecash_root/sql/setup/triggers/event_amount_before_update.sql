DELIMITER |

CREATE DEFINER='ecashtrigger'@'%' TRIGGER
    event_amount_before_update
BEFORE UPDATE ON
    event_amount
FOR EACH ROW
BEGIN
	DECLARE amount_type VARCHAR(255);
	IF (NEW.amount <> OLD.amount OR NEW.event_amount_type_id <> OLD.event_amount_type_id) THEN
		SELECT eat.name_short INTO amount_type FROM event_amount_type eat WHERE eat.event_amount_type_id = OLD.event_amount_type_id;
		
		SET NEW.event_amount_type_id = OLD.event_amount_type_id;
		IF amount_type = 'principal' THEN
			UPDATE event_schedule SET amount_principal = amount_principal + NEW.amount - OLD.amount WHERE event_schedule_id = NEW.event_schedule_id;
		ELSE
			UPDATE event_schedule SET amount_non_principal = amount_non_principal + NEW.amount - OLD.amount WHERE event_schedule_id = NEW.event_schedule_id;
		END IF;
	END IF;
END

|
