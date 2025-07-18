-- Migration script: mysql_virtual_recipient_blacklist → recipient_blacklist
-- 
-- This script migrates from the old table name to the new standardized name
-- Run this after updating the PostfixAdmin code

-- Step 1: Create the new table with the correct name
CREATE TABLE IF NOT EXISTS `recipient_blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `address` varchar(255) NOT NULL COMMENT 'Email address or pattern (e.g., bad@domain.com or @spammer.com)',
  `action` varchar(20) NOT NULL DEFAULT 'REJECT' COMMENT 'Action to take: REJECT, DISCARD, DEFER',
  `domain` varchar(255) NOT NULL DEFAULT '' COMMENT 'Domain for organization/filtering',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`),
  KEY `domain` (`domain`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Recipient blacklist for email rejection/filtering';

-- Step 2: Migrate data from old table if it exists
INSERT IGNORE INTO recipient_blacklist (address, action, domain, created, modified, active)
SELECT address, action, domain, created, modified, active
FROM mysql_virtual_recipient_blacklist
WHERE NOT EXISTS (SELECT 1 FROM recipient_blacklist WHERE recipient_blacklist.address = mysql_virtual_recipient_blacklist.address);

-- Step 3: Verify data migration
SELECT 'Old table count:' as info, COUNT(*) as count FROM mysql_virtual_recipient_blacklist
UNION ALL
SELECT 'New table count:' as info, COUNT(*) as count FROM recipient_blacklist;

-- Step 4: After verifying data, uncomment the following line to drop the old table
-- DROP TABLE mysql_virtual_recipient_blacklist;
