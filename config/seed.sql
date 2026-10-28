-- PHPSwapMeet 2 - Seed Data
-- Run this after migrate.php to populate sample data

-- Categories
INSERT INTO categories (name, description, sort_order) VALUES
('Electronics', 'Computers, phones, gadgets', 1),
('Clothing', 'Apparel, shoes, accessories', 2),
('Books', 'Fiction, non-fiction, textbooks', 3),
('Home & Garden', 'Furniture, decor, tools', 4),
('Sports', 'Equipment, apparel, outdoor gear', 5),
('Toys & Games', 'Board games, action figures, puzzles', 6),
('Music', 'Instruments, records, CDs', 7),
('Art', 'Paintings, sculptures, prints', 8),
('Collectibles', 'Coins, stamps, memorabilia', 9),
('Vehicles', 'Cars, bikes, parts', 10),
('Food & Beverage', 'Food, drinks, cooking supplies', 11),
('Health & Beauty', 'Cosmetics, skincare, supplements', 12),
('Pet Supplies', 'Food, toys, accessories for pets', 13),
('Office Supplies', 'Stationery, desks, equipment', 14),
('Other', 'Miscellaneous items', 15);

-- Users (password hash will be replaced by bin/seed.php)
-- Password for all: "password123"
INSERT INTO users (username, email, password_hash, paypal_email, bio, is_admin, active) VALUES
('admin', 'admin@phpswapmeet.local', 'PLACEHOLDER_HASH_ADMIN', 'admin@phpswapmeet.local', 'Site administrator', 1, 1),
('johndoe', 'john@example.com', 'PLACEHOLDER_HASH_1', 'john@example.com', 'Love trading books and electronics!', 0, 1),
('janedoe', 'jane@example.com', 'PLACEHOLDER_HASH_2', 'jane@example.com', 'Art enthusiast and collector.', 0, 1),
('bobsmith', 'bob@example.com', 'PLACEHOLDER_HASH_3', 'bob@example.com', 'Sports fan looking to trade gear.', 0, 1),
('alicejohnson', 'alice@example.com', 'PLACEHOLDER_HASH_4', 'alice@example.com', 'Looking for vintage items.', 0, 1);

-- Items
INSERT INTO items (owner_id, category_id, name, description, post_type, price, currency, status) VALUES
(2, 1, 'Vintage Calculator', 'Working vintage calculator from the 1980s. Great condition.', 'offer', '25.00', 'USD', 'active'),
(2, 3, 'First Edition Novel', 'Rare first edition of a classic novel. Collectible.', 'offer', '150.00', 'USD', 'active'),
(3, 8, 'Original Painting', 'Hand-painted landscape oil on canvas. 24x36 inches.', 'offer', '200.00', 'USD', 'active'),
(3, 14, 'Desk Lamp', 'Modern LED desk lamp, adjustable brightness.', 'offer', '0.00', 'USD', 'active'),
(4, 5, 'Mountain Bike', 'Trek mountain bike, size medium. Great for trails.', 'offer', '350.00', 'USD', 'active'),
(4, 1, 'Guitar Pedals', 'Set of 3 guitar effect pedals. Works great.', 'offer', '75.00', 'USD', 'active'),
(5, 3, 'Textbook Collection', 'College textbooks - Math, Physics, Chemistry.', 'offer', '50.00', 'USD', 'active'),
(5, 12, 'Skincare Bundle', 'Premium skincare products. Unopened.', 'offer', '45.00', 'USD', 'active');

-- Feedback
INSERT INTO feedback (from_user_id, to_user_id, item_id, rating, comment) VALUES
(2, 3, 3, 5, 'Amazing painting, exactly as described!'),
(3, 2, 2, 4, 'Good book, fast shipping.'),
(4, 2, NULL, 5, 'Great trader, would do business again!'),
(2, 4, NULL, 4, 'Reliable and friendly.'),
(5, 3, NULL, 5, 'Excellent communication and fast delivery.'),
(3, 5, NULL, 5, 'Wonderful to deal with!'),
(2, 5, NULL, 4, 'Good experience overall.'),
(4, 3, 3, 5, 'Beautiful artwork, very happy!');

-- News
INSERT INTO news (title, content, is_active) VALUES
('Welcome to PHPSwapMeet 2!', 'We are excited to launch the new version of PHPSwapMeet with improved security and a modern design. Enjoy browsing and trading!', 1),
('New Feature: RSS Feeds', 'You can now subscribe to RSS feeds to stay updated on new items and news articles. Check out the links in the footer.', 1),
('Site Maintenance Notice', 'Scheduled maintenance on the first Saturday of each month. The site may be temporarily unavailable during this time.', 0);
