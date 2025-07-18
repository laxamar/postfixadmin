-- MySQL table for PostfixAdmin Recipient Blacklist feature
-- This table stores email addresses or patterns that should be rejected/discarded

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

-- Example entries
INSERT IGNORE INTO `recipient_blacklist` (`address`, `action`, `domain`) VALUES
('@spam.example.com', 'REJECT', 'example.com'),
('baduser@anywhere.com', 'DISCARD', 'example.com');
