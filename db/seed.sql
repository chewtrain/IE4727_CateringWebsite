-- Sample data for Makan & Co. Run after schema.sql.
USE makanco;

-- ---------------------------------------------------------------------
-- Packages
-- ---------------------------------------------------------------------
INSERT INTO packages (id, name, tagline, description, price_per_pax, min_pax, image, image_alt, is_featured, sort_order) VALUES
(1, 'Classic Local', 'Hawker favourites, done properly.',
 'Nasi goreng, satay, sambal prawns and more. Choose 8 dishes.',
 16.80, 30, 'images/pkg-classic-local.jpg', 'Nasi goreng topped with a fried egg, with satay and prawn crackers', 1, 1),
(2, 'Nusantara Feast', 'Rich, spiced and slow-cooked.',
 'Coconut rice, beef rendang, ayam goreng and sambal. Choose 9 dishes.',
 19.80, 30, 'images/pkg-nusantara.jpg', 'Nasi lemak with fried chicken, egg and sambal on a banana leaf', 1, 2),
(3, 'Dim Sum High Tea', 'Light bites for long afternoons.',
 'Har gow, siew mai, buns and kueh. Choose 7 items.',
 14.80, 20, 'images/pkg-dim-sum-tea.jpg', 'Bamboo steamers of har gow, siew mai and other dim sum', 1, 3);

-- ---------------------------------------------------------------------
-- Categories and dishes
-- ---------------------------------------------------------------------
INSERT INTO categories (id, name, sort_order) VALUES
(1, 'Rice & Noodles', 1),
(2, 'Mains', 2),
(3, 'Vegetables', 3),
(4, 'Sides', 4),
(5, 'Dim Sum', 5),
(6, 'Desserts', 6),
(7, 'Drinks', 7);

INSERT INTO dishes (id, category_id, name, description, is_vegetarian, is_spicy) VALUES
-- Rice & Noodles
(1,  1, 'Nasi Goreng Kampung', 'Village-style fried rice with ikan bilis and chilli', 0, 1),
(2,  1, 'Yang Chow Fried Rice', 'Egg fried rice with char siew and shrimp', 0, 0),
(3,  1, 'Mee Goreng Mamak', 'Wok-fried yellow noodles, tangy and spicy', 0, 1),
(4,  1, 'Vegetarian Fried Bee Hoon', 'Rice vermicelli with cabbage and carrot', 1, 0),
(5,  1, 'Nasi Lemak Coconut Rice', 'Pandan coconut rice with ikan bilis and peanuts', 0, 0),
(6,  1, 'Nasi Kuning', 'Fragrant turmeric rice', 1, 0),
(7,  1, 'Chicken Briyani', 'Spiced basmati rice with chicken', 0, 0),
-- Mains
(8,  2, 'Chicken Satay', 'Grilled skewers with peanut sauce', 0, 0),
(9,  2, 'Sambal Prawns', 'Prawns tossed in house sambal', 0, 1),
(10, 2, 'Sweet and Sour Fish', 'Crispy fish fillet with pineapple', 0, 0),
(11, 2, 'Cereal Butter Chicken', 'Crispy chicken with buttery oat cereal', 0, 0),
(12, 2, 'Black Pepper Beef', 'Tender beef strips in black pepper sauce', 0, 1),
(13, 2, 'Beef Rendang', 'Slow-cooked beef in coconut and spices', 0, 1),
(14, 2, 'Ayam Goreng Berempah', 'Spiced fried chicken with crispy shallots', 0, 0),
(15, 2, 'Ayam Masak Merah', 'Chicken in a sweet tomato chilli sauce', 0, 1),
(16, 2, 'Ikan Asam Pedas', 'Fish in a sour and spicy tamarind gravy', 0, 1),
(17, 2, 'Sambal Sotong', 'Squid in sambal', 0, 1),
(18, 2, 'Mutton Curry', 'Tender mutton in a rich curry', 0, 1),
-- Vegetables
(19, 3, 'Garlic Broccoli', 'Stir-fried broccoli with garlic', 1, 0),
(20, 3, 'Sambal Kangkong', 'Water spinach in belacan sambal', 0, 1),
(21, 3, 'Sayur Lodeh', 'Vegetables in a light coconut curry', 1, 0),
(22, 3, 'Chap Chye', 'Braised mixed vegetables, Nyonya style', 1, 0),
-- Sides
(23, 4, 'Ngoh Hiang', 'Five-spice pork and prawn rolls', 0, 0),
(24, 4, 'Vegetarian Spring Rolls', 'Crispy rolls with sweet chilli dip', 1, 0),
(25, 4, 'Bergedil', 'Spiced potato patties', 1, 0),
(26, 4, 'Otah Otah', 'Grilled spiced fish cake in banana leaf', 0, 1),
(27, 4, 'Achar', 'Nyonya pickled vegetables', 1, 1),
-- Dim Sum
(28, 5, 'Har Gow', 'Steamed prawn dumplings', 0, 0),
(29, 5, 'Siew Mai', 'Pork and prawn dumplings', 0, 0),
(30, 5, 'Char Siew Bao', 'Fluffy buns with barbecued pork', 0, 0),
(31, 5, 'Lotus Paste Bao', 'Sweet lotus seed buns', 1, 0),
(32, 5, 'Chee Cheong Fun', 'Rice rolls with sweet soy', 1, 0),
(33, 5, 'Lo Mai Kai', 'Glutinous rice with chicken in lotus leaf', 0, 0),
(34, 5, 'Lo Bak Go', 'Pan-fried radish cake', 0, 0),
(35, 5, 'Vegetable Dumplings', 'Crystal dumplings with mixed vegetables', 1, 0),
-- Desserts
(36, 6, 'Chendol', 'Shaved ice, coconut milk and gula melaka', 1, 0),
(37, 6, 'Bubur Cha Cha', 'Sweet potato and yam in coconut milk', 1, 0),
(38, 6, 'Assorted Nyonya Kueh', 'Kueh lapis, ondeh ondeh and more', 1, 0),
(39, 6, 'Mango Pudding', 'Chilled mango pudding', 1, 0),
(40, 6, 'Egg Tarts', 'Flaky pastry with silky egg custard', 1, 0),
(41, 6, 'Almond Jelly with Longan', 'Light and refreshing', 1, 0),
(42, 6, 'Pulut Hitam', 'Black glutinous rice with coconut cream', 1, 0),
-- Drinks
(43, 7, 'Iced Lemon Tea', 'Freshly brewed', 1, 0),
(44, 7, 'Bandung', 'Rose syrup with milk', 1, 0),
(45, 7, 'Chrysanthemum Tea', 'Lightly sweetened', 1, 0),
(46, 7, 'Teh Tarik', 'Pulled milk tea', 1, 0),
(47, 7, 'Hot Chinese Tea', 'Pu-erh or jasmine', 1, 0);

-- How many dishes to pick per category.
INSERT INTO package_rules (package_id, category_id, choose_count) VALUES
-- Classic Local: 8 dishes
(1, 1, 1), (1, 2, 3), (1, 3, 1), (1, 4, 1), (1, 6, 1), (1, 7, 1),
-- Nusantara Feast: 9 dishes
(2, 1, 1), (2, 2, 4), (2, 3, 1), (2, 4, 1), (2, 6, 1), (2, 7, 1),
-- Dim Sum High Tea: 7 items
(3, 5, 4), (3, 1, 1), (3, 6, 1), (3, 7, 1);

-- Dishes offered in each package.
INSERT INTO package_dishes (package_id, dish_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4),
(1, 8), (1, 9), (1, 10), (1, 11), (1, 12),
(1, 19), (1, 20), (1, 22),
(1, 23), (1, 24), (1, 26),
(1, 36), (1, 37), (1, 38), (1, 39),
(1, 43), (1, 44), (1, 45),

(2, 5), (2, 6), (2, 7),
(2, 8), (2, 13), (2, 14), (2, 15), (2, 16), (2, 17), (2, 18),
(2, 20), (2, 21),
(2, 24), (2, 25), (2, 26), (2, 27),
(2, 36), (2, 37), (2, 38), (2, 42),
(2, 43), (2, 44), (2, 46),

(3, 28), (3, 29), (3, 30), (3, 31), (3, 32), (3, 33), (3, 34), (3, 35),
(3, 2), (3, 4),
(3, 38), (3, 39), (3, 40), (3, 41),
(3, 43), (3, 45), (3, 47);

-- ---------------------------------------------------------------------
-- Staff login: username admin, password admin123 (change after setup)
-- ---------------------------------------------------------------------
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$Pzt0MyuLiQZxSej3ae9Hmea/0tboEkcyrcKi8zUR16sHR3PmhAdCu');

-- ---------------------------------------------------------------------
-- Sample orders so the admin pages and reports have data.
-- All customers are fictional.
-- ---------------------------------------------------------------------
INSERT INTO orders (id, order_ref, customer_name, email, phone, address, postal_code, event_date, time_slot, notes, subtotal, delivery_fee, total, status, created_at, paid_at) VALUES
(1,  'MC-A7K2QD', 'Tan Wei Ming',  'weiming.tan@example.com', '91234567', '10 Anson Road, #12-01',         '079903', '2026-09-12', '11:00 - 12:00', '', 672.00, 0.00, 672.00, 'delivered', '2026-09-03 10:15:00', '2026-09-03 10:18:00'),
(2,  'MC-B3M8RT', 'Siti Rahimah',  'siti.r@example.com',      '82345678', 'Blk 456 Tampines St 42, #08-12', '520456', '2026-09-20', '18:00 - 19:00', 'Wedding reception, please arrive early', 1188.00, 0.00, 1188.00, 'delivered', '2026-09-08 20:41:00', '2026-09-08 20:44:00'),
(3,  'MC-C9P4LX', 'Rajesh Kumar',  'rajesh.k@example.com',    '93456789', '21 Science Park Road',           '117628', '2026-09-27', '15:00 - 16:00', '', 370.00, 30.00, 400.00, 'delivered', '2026-09-20 09:02:00', '2026-09-20 09:05:00'),
(4,  'MC-D2N6VW', 'Lim Hui Ling',  'huiling.lim@example.com', '84567890', '1 Raffles Place, Tower 2',       '048616', '2026-10-04', '11:00 - 12:00', 'Year-end lunch for 80', 1344.00, 0.00, 1344.00, 'delivered', '2026-09-22 14:30:00', '2026-09-22 14:31:00'),
(5,  'MC-E5Q3JZ', 'Nurul Aisyah',  'nurul.a@example.com',     '95678901', 'Blk 12 Jurong West St 91, #03-44', '640012', '2026-10-11', '18:00 - 19:00', '', 693.00, 0.00, 693.00, 'delivered', '2026-10-02 19:12:00', '2026-10-02 19:14:00'),
(6,  'MC-F8R3KY', 'Jason Goh',     'jason.goh@example.com',   '86789012', '8 Marina View, Level 30',        '018960', '2026-10-18', '15:00 - 16:00', 'Client high tea', 740.00, 0.00, 740.00, 'delivered', '2026-10-09 11:48:00', '2026-10-09 11:50:00'),
(7,  'MC-G4T7HB', 'Priya Nair',    'priya.nair@example.com',  '97890123', '33 Holland Grove Road',          '278827', '2026-10-25', '12:00 - 13:00', 'Birthday party', 504.00, 0.00, 504.00, 'delivered', '2026-10-15 16:20:00', '2026-10-15 16:22:00'),
(8,  'MC-H6V2MC', 'Daniel Ong',    'daniel.ong@example.com',  '88901234', '50 Jurong Gateway Road, #05-01', '608549', '2026-11-07', '11:00 - 12:00', 'Company family day', 1980.00, 0.00, 1980.00, 'confirmed', '2026-10-24 10:05:00', '2026-10-24 10:07:00'),
(9,  'MC-J2W9NE', 'Farah Lim',     'farah.lim@example.com',   '99012345', 'Blk 7 Bishan St 13, #10-21',     '570007', '2026-11-01', '15:00 - 16:00', '', 296.00, 30.00, 326.00, 'confirmed', '2026-10-26 21:33:00', '2026-10-26 21:35:00'),
(10, 'MC-K7X5PF', 'Marcus Teo',    'marcus.teo@example.com',  '81122334', '2 Tampines Central 5, #04-08',   '529509', '2026-11-14', '18:00 - 19:00', '', 756.00, 0.00, 756.00, 'confirmed', '2026-10-27 13:10:00', '2026-10-27 13:12:00'),
(11, 'MC-L3Y8QG', 'Chloe Wong',    'chloe.wong@example.com',  '92233445', '15 Pasir Ris Drive 3, #06-15',   '519498', '2026-11-03', '15:00 - 16:00', '', 444.00, 30.00, 474.00, 'payment_failed', '2026-10-27 18:02:00', NULL),
(12, 'MC-M9Z4RH', 'Ahmad Hafiz',   'ahmad.hafiz@example.com', '83344556', '9 Woodlands Avenue 9, #02-30',   '738964', '2026-10-30', '12:00 - 13:00', 'Hari Raya open house', 792.00, 0.00, 792.00, 'preparing', '2026-10-20 08:45:00', '2026-10-20 08:47:00');

INSERT INTO order_items (id, order_id, package_id, package_name, price_per_pax, guests, line_total) VALUES
(1,  1,  1, 'Classic Local',    16.80, 40,  672.00),
(2,  2,  2, 'Nusantara Feast',  19.80, 60,  1188.00),
(3,  3,  3, 'Dim Sum High Tea', 14.80, 25,  370.00),
(4,  4,  1, 'Classic Local',    16.80, 80,  1344.00),
(5,  5,  2, 'Nusantara Feast',  19.80, 35,  693.00),
(6,  6,  3, 'Dim Sum High Tea', 14.80, 50,  740.00),
(7,  7,  1, 'Classic Local',    16.80, 30,  504.00),
(8,  8,  2, 'Nusantara Feast',  19.80, 100, 1980.00),
(9,  9,  3, 'Dim Sum High Tea', 14.80, 20,  296.00),
(10, 10, 1, 'Classic Local',    16.80, 45,  756.00),
(11, 11, 3, 'Dim Sum High Tea', 14.80, 30,  444.00),
(12, 12, 2, 'Nusantara Feast',  19.80, 40,  792.00);

-- Chosen dishes, copied from the dishes table with their category names.
INSERT INTO order_item_dishes (order_item_id, dish_id, dish_name, category_name)
SELECT t.item_id, d.id, d.name, c.name
FROM (
          SELECT 1 AS item_id, 1 AS dish_id UNION ALL SELECT 1, 8  UNION ALL SELECT 1, 9  UNION ALL SELECT 1, 11 UNION ALL SELECT 1, 19 UNION ALL SELECT 1, 23 UNION ALL SELECT 1, 36 UNION ALL SELECT 1, 43
UNION ALL SELECT 2, 5  UNION ALL SELECT 2, 8  UNION ALL SELECT 2, 13 UNION ALL SELECT 2, 14 UNION ALL SELECT 2, 16 UNION ALL SELECT 2, 21 UNION ALL SELECT 2, 25 UNION ALL SELECT 2, 36 UNION ALL SELECT 2, 46
UNION ALL SELECT 3, 28 UNION ALL SELECT 3, 29 UNION ALL SELECT 3, 30 UNION ALL SELECT 3, 34 UNION ALL SELECT 3, 2  UNION ALL SELECT 3, 40 UNION ALL SELECT 3, 45
UNION ALL SELECT 4, 2  UNION ALL SELECT 4, 8  UNION ALL SELECT 4, 10 UNION ALL SELECT 4, 11 UNION ALL SELECT 4, 22 UNION ALL SELECT 4, 24 UNION ALL SELECT 4, 38 UNION ALL SELECT 4, 44
UNION ALL SELECT 5, 5  UNION ALL SELECT 5, 13 UNION ALL SELECT 5, 14 UNION ALL SELECT 5, 15 UNION ALL SELECT 5, 18 UNION ALL SELECT 5, 20 UNION ALL SELECT 5, 26 UNION ALL SELECT 5, 42 UNION ALL SELECT 5, 43
UNION ALL SELECT 6, 28 UNION ALL SELECT 6, 29 UNION ALL SELECT 6, 31 UNION ALL SELECT 6, 33 UNION ALL SELECT 6, 4  UNION ALL SELECT 6, 39 UNION ALL SELECT 6, 47
UNION ALL SELECT 7, 1  UNION ALL SELECT 7, 8  UNION ALL SELECT 7, 9  UNION ALL SELECT 7, 12 UNION ALL SELECT 7, 20 UNION ALL SELECT 7, 26 UNION ALL SELECT 7, 37 UNION ALL SELECT 7, 45
UNION ALL SELECT 8, 7  UNION ALL SELECT 8, 8  UNION ALL SELECT 8, 13 UNION ALL SELECT 8, 14 UNION ALL SELECT 8, 17 UNION ALL SELECT 8, 21 UNION ALL SELECT 8, 24 UNION ALL SELECT 8, 38 UNION ALL SELECT 8, 44
UNION ALL SELECT 9, 28 UNION ALL SELECT 9, 30 UNION ALL SELECT 9, 32 UNION ALL SELECT 9, 35 UNION ALL SELECT 9, 2  UNION ALL SELECT 9, 41 UNION ALL SELECT 9, 43
UNION ALL SELECT 10, 3 UNION ALL SELECT 10, 8  UNION ALL SELECT 10, 9  UNION ALL SELECT 10, 11 UNION ALL SELECT 10, 19 UNION ALL SELECT 10, 23 UNION ALL SELECT 10, 39 UNION ALL SELECT 10, 43
UNION ALL SELECT 11, 28 UNION ALL SELECT 11, 29 UNION ALL SELECT 11, 30 UNION ALL SELECT 11, 31 UNION ALL SELECT 11, 4 UNION ALL SELECT 11, 40 UNION ALL SELECT 11, 45
UNION ALL SELECT 12, 6 UNION ALL SELECT 12, 8  UNION ALL SELECT 12, 13 UNION ALL SELECT 12, 15 UNION ALL SELECT 12, 16 UNION ALL SELECT 12, 20 UNION ALL SELECT 12, 25 UNION ALL SELECT 12, 36 UNION ALL SELECT 12, 46
) AS t
JOIN dishes d ON d.id = t.dish_id
JOIN categories c ON c.id = d.category_id;

-- Status history: every order was placed, then paid (except the failed one).
INSERT INTO order_status_history (order_id, status, note, changed_at)
SELECT id, 'pending_payment', 'Order placed', created_at FROM orders;

INSERT INTO order_status_history (order_id, status, note, changed_at)
SELECT id, 'payment_failed', 'Payment was declined', DATE_ADD(created_at, INTERVAL 2 MINUTE)
FROM orders WHERE status = 'payment_failed';

INSERT INTO order_status_history (order_id, status, note, changed_at)
SELECT id, 'confirmed', 'Payment received', paid_at FROM orders WHERE paid_at IS NOT NULL;

INSERT INTO order_status_history (order_id, status, note, changed_at)
SELECT id, 'preparing', 'Ingredients ordered and kitchen scheduled', TIMESTAMP(DATE_SUB(event_date, INTERVAL 1 DAY), '09:00:00')
FROM orders WHERE status IN ('preparing', 'out_for_delivery', 'delivered');

INSERT INTO order_status_history (order_id, status, note, changed_at)
SELECT id, 'out_for_delivery', 'Driver on the way', TIMESTAMP(event_date, '09:30:00')
FROM orders WHERE status IN ('out_for_delivery', 'delivered');

INSERT INTO order_status_history (order_id, status, note, changed_at)
SELECT id, 'delivered', 'Buffet set up', TIMESTAMP(event_date, '10:45:00')
FROM orders WHERE status = 'delivered';

-- ---------------------------------------------------------------------
-- Sample feedback
-- ---------------------------------------------------------------------
INSERT INTO feedback (name, email, order_ref, rating, message, created_at) VALUES
('Lim Hui Ling', 'huiling.lim@example.com', 'MC-D2N6VW', 5, 'Food arrived hot and the set-up was very neat. The cereal butter chicken was gone in ten minutes!', '2026-10-05 09:12:00'),
('Rajesh Kumar', 'rajesh.k@example.com', 'MC-C9P4LX', 4, 'Dim sum was great. Would love more vegetarian options in the high tea package.', '2026-09-28 13:40:00'),
('Priya Nair', 'priya.nair@example.com', 'MC-G4T7HB', 5, 'Ordering online was easy and the tracking page meant I did not have to call anyone.', '2026-10-26 10:03:00');
