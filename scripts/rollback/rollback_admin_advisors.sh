#!/bin/bash
# Rollback Admin Advisors Feature
# Removes admin advisor instances and optionally drops table

set -e

echo "=========================================="
echo "Admin Advisors Rollback Script"
echo "=========================================="
echo ""

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}This will remove all admin advisor instances.${NC}"
echo ""
read -p "Continue with rollback? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Rollback cancelled."
    exit 0
fi

echo ""
echo "Step 1: Deleting admin advisor instances..."
mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "DELETE FROM agent_instances WHERE owner_type = 'admin';" 2>&1
DELETED=$(mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT ROW_COUNT();" -sN 2>&1 | tail -1)
echo -e "${GREEN}✓ Deleted $DELETED admin advisor instances${NC}"

echo ""
read -p "Drop agent_instances table completely? (yes/no): " drop_table

if [ "$drop_table" = "yes" ]; then
    echo "Step 2: Dropping agent_instances table..."
    mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "DROP TABLE IF EXISTS agent_instances;" 2>&1
    mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "DROP VIEW IF EXISTS admin_advisors;" 2>&1
    echo -e "${GREEN}✓ Table and views dropped${NC}"
else
    echo "Skipping table drop (instances preserved for students)"
fi

echo ""
echo "Step 3: Reverting API files (optional)..."
read -p "Remove admin advisor API files? (yes/no): " remove_apis

if [ "$remove_apis" = "yes" ]; then
    rm -f /var/www/html/Professor_Hawkeinstein/app/api/admin/create_agent_instance.php
    rm -f /var/www/html/Professor_Hawkeinstein/app/api/admin/chat_instance.php
    rm -f /var/www/html/Professor_Hawkeinstein/app/api/admin/get_agent_instance.php
    rm -f app/api/admin/create_agent_instance.php
    rm -f app/api/admin/chat_instance.php
    rm -f app/api/admin/get_agent_instance.php
    echo -e "${GREEN}✓ API files removed${NC}"
else
    echo "Keeping API files"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✅ Rollback Complete!${NC}"
echo "=========================================="
echo ""
echo "What was removed:"
echo "  • All admin advisor instances (owner_type='admin')"
if [ "$drop_table" = "yes" ]; then
    echo "  • agent_instances table (dropped)"
fi
if [ "$remove_apis" = "yes" ]; then
    echo "  • Admin advisor API endpoints"
fi
echo ""
echo "To restore: Run ./setup_admin_advisors.sh"
echo ""
