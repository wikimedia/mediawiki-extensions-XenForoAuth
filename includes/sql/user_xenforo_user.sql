--
-- extension XenForoAuth SQL schema
--
CREATE TABLE /*$wgDBprefix*/user_xenforo_user (
  user_xfuserid DECIMAL(25,0) unsigned NOT NULL PRIMARY KEY,
  user_id int(10) unsigned NOT NULL,
  KEY(user_id)
) /*$wgDBTableOptions*/;
