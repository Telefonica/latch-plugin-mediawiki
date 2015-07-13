CREATE TABLE IF NOT EXISTS latch (
	user_id int unsigned not null,
	acc_id VARCHAR(64) not null,
     foreign key(user_id) references user(user_id)
);