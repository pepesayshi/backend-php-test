/* Only using MD5 here as the password is preloaded from sql
 * If loaded from php would be using password_hash() or equivalent
 */
INSERT INTO users (username, password) VALUES
('user1', MD5('user1')),
('user2', MD5('user2')),
('user3', MD5('user3'));

INSERT INTO todos (user_id, description) VALUES
(1, 'Vivamus tempus'),
(1, 'lorem ac odio'),
(1, 'Ut congue odio'),
(1, 'Sodales finibus'),
(1, 'Accumsan nunc vitae'),
(2, 'Lorem ipsum'),
(2, 'In lacinia est'),
(2, 'Odio varius gravida');