USE scal_e_cdp;

DELETE FROM customers WHERE email LIKE 'demo___@example.test';

INSERT INTO customers (email, name) VALUES
('demo001@example.test', 'Amina Hassan'),
('demo002@example.test', 'Noah Martin'),
('demo003@example.test', 'Leila Ahmed'),
('demo004@example.test', 'Ethan Dubois'),
('demo005@example.test', 'Sofia Njoroge'),
('demo006@example.test', 'Lucas Bernard'),
('demo007@example.test', 'Maya Patel'),
('demo008@example.test', 'Gabriel Moreau'),
('demo009@example.test', 'Ines Diallo'),
('demo010@example.test', 'Adam Laurent'),
('demo011@example.test', 'Chloe Mwangi'),
('demo012@example.test', 'Hugo Petit'),
('demo013@example.test', 'Zara Khan'),
('demo014@example.test', 'Louis Robert'),
('demo015@example.test', 'Nadia Benali'),
('demo016@example.test', 'Theo Richard'),
('demo017@example.test', 'Aya Nakamura'),
('demo018@example.test', 'Jules Simon'),
('demo019@example.test', 'Mariam Yusuf'),
('demo020@example.test', 'Paul Michel');

INSERT INTO event_types (code, label) VALUES
('purchase', 'Achat'),
('signup', 'Inscription'),
('view', 'Consultation')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO events (customer_id, event_type_id, event_timestamp)
SELECT
	c.id,
	et.id,
	DATE_ADD(
		'2026-01-01 00:00:00',
		INTERVAL (CAST(SUBSTRING(c.email, 5, 3) AS UNSIGNED) * 6 + event_number) HOUR
	)
FROM customers c
CROSS JOIN (
	SELECT 1 AS event_number UNION ALL SELECT 2 UNION ALL SELECT 3
	UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
) event_numbers
INNER JOIN event_types et ON et.code = CASE event_number
	WHEN 1 THEN 'signup'
	WHEN 2 THEN 'purchase'
	WHEN 3 THEN 'purchase'
	WHEN 4 THEN 'purchase'
	WHEN 5 THEN 'view'
	ELSE 'view'
END
WHERE c.email LIKE 'demo___@example.test';

INSERT INTO event_properties (
	event_id,
	property_name,
	property_type,
	value_string,
	value_int,
	value_decimal,
	value_bool,
	value_json
)
SELECT e.id, 'amount', 'float', NULL, NULL,
	   40 + CAST(SUBSTRING(c.email, 5, 3) AS UNSIGNED) * 7 + HOUR(e.event_timestamp) % 50,
	   NULL, NULL
FROM events e
INNER JOIN customers c ON c.id = e.customer_id
INNER JOIN event_types et ON et.id = e.event_type_id
WHERE c.email LIKE 'demo___@example.test' AND et.code = 'purchase'
UNION ALL
SELECT e.id, 'product', 'string', CONCAT('product-', LPAD(HOUR(e.event_timestamp) % 8 + 1, 2, '0')),
	   NULL, NULL, NULL, NULL
FROM events e
INNER JOIN customers c ON c.id = e.customer_id
INNER JOIN event_types et ON et.id = e.event_type_id
WHERE c.email LIKE 'demo___@example.test' AND et.code = 'purchase'
UNION ALL
SELECT e.id, 'source', 'string', 'website', NULL, NULL, NULL, NULL
FROM events e
INNER JOIN customers c ON c.id = e.customer_id
INNER JOIN event_types et ON et.id = e.event_type_id
WHERE c.email LIKE 'demo___@example.test' AND et.code = 'signup'
UNION ALL
SELECT e.id, 'page', 'string', 'catalog', NULL, NULL, NULL, NULL
FROM events e
INNER JOIN customers c ON c.id = e.customer_id
INNER JOIN event_types et ON et.id = e.event_type_id
WHERE c.email LIKE 'demo___@example.test' AND et.code = 'view';
