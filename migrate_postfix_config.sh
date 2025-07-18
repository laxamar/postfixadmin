#!/bin/bash
# Postfix configuration migration script
# This script updates Postfix configuration to use the new table name

set -e

echo "Migrating Postfix configuration for recipient blacklist..."

# Backup current configuration
cp /etc/postfix/main.cf /etc/postfix/main.cf.backup.$(date +%Y%m%d_%H%M%S)

# Update main.cf to use new table name
if grep -q "mysql_virtual_recipient_blacklist" /etc/postfix/main.cf; then
    echo "Updating main.cf..."
    sed -i 's/mysql_virtual_recipient_blacklist/recipient_blacklist/g' /etc/postfix/main.cf
    echo "Updated main.cf"
else
    echo "No references to mysql_virtual_recipient_blacklist found in main.cf"
fi

# Rename the MySQL configuration file
if [ -f /etc/postfix/mysql_virtual_recipient_blacklist.cf ]; then
    echo "Renaming MySQL config file..."
    mv /etc/postfix/mysql_virtual_recipient_blacklist.cf /etc/postfix/recipient_blacklist.cf
    echo "Renamed to recipient_blacklist.cf"
fi

# Update the MySQL config file content
if [ -f /etc/postfix/recipient_blacklist.cf ]; then
    echo "Updating MySQL config file content..."
    sed -i 's/mysql_virtual_recipient_blacklist/recipient_blacklist/g' /etc/postfix/recipient_blacklist.cf
    echo "Updated recipient_blacklist.cf"
fi

# Check configuration
echo "Checking Postfix configuration..."
postfix check

if [ $? -eq 0 ]; then
    echo "Configuration check passed!"
    echo "Reloading Postfix..."
    postfix reload
    echo "Migration completed successfully!"
else
    echo "Configuration check failed! Please review the changes."
    exit 1
fi

echo ""
echo "Migration Summary:"
echo "- Updated table name from mysql_virtual_recipient_blacklist to recipient_blacklist"
echo "- Updated Postfix main.cf configuration"
echo "- Renamed MySQL config file"
echo "- Reloaded Postfix configuration"
echo ""
echo "Please run the database migration script (migrate_blacklist_table.sql) to complete the process."
