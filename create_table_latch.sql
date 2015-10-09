CREATE TABLE IF NOT EXISTS latch (
	user_id int unsigned not null,
	acc_id VARCHAR(64) not null,
	otp VARCHAR(10),
	attempts int default 0,
     foreign key(user_id) references user(user_id)
);