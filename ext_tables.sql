#
# Table structure for table 'jobqueue_job'
#
CREATE TABLE jobqueue_job (

  uid int(11) NOT NULL auto_increment,

  queue varchar(255) DEFAULT '' NOT NULL,
  locked int(11) DEFAULT '0' NOT NULL,
  payload text NOT NULL,
  attempts int(11) DEFAULT '0' NOT NULL,
  nextexecution int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (uid),
  KEY queue (queue),

) ENGINE=InnoDB;