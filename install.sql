create table pastes (
	id char(9) character set ascii not null,
	username varchar(18) character set ascii default null,
	trip varchar(18) character set ascii default null,
	ip_hash char(9) character set ascii not null,
	timestamp int(11) unsigned not null,
	content binary not null,
	deleted tinyint(1) unsigned not null default 0,
	is_mod_action tinyint(1) unsigned not null default 0,
	flags tinyint(1) unsigned not null default 0,
	cloned_from char(9) character set ascii default null,
	times_viewed int(11) unsigned not null default 0,
	primary key id (id),
	key trip_deleted (trip, deleted),
	key ip_hash_deleted (ip_hash, deleted),
	key is_mod_action_deleted (is_mod_action, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

create table limits (
	ip_hash char(9) character set ascii not null,
	blocked_since int(11) unsigned not null default 0,
	blocked_until int(11) unsigned not null default 0,
	reason_text varchar(36) not null,
	mod_paste_id char(9) character set ascii default null,
	primary key ip_hash (ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
