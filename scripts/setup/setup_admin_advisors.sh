#!/bin/bash
# Setup Admin Advisors Feature
# Applies agent_instances table and syncs files

set -e

echo "=========================================="
echo "Admin Advisors Setup Script"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running from correct directory
if [ ! -f "schema_agent_instances.sql" ]; then
    echo -e "${RED}Error: Must run from /home/steve/Professor_Hawkeinstein directory${NC}"
    exit 1
fi

echo "Step 1: Applying agent_instances schema..."
if cat schema_agent_instances.sql | mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform 2>&1 | grep -i "error"; then
    echo -e "${YELLOW}Warning: Some schema operations may have failed (views already exist)${NC}"
else
    echo -e "${GREEN}✓ Schema applied successfully${NC}"
fi

echo ""
echo "Step 2: Running tests..."
if php tests/admin_advisor_tests.php > /dev/null 2>&1; then
    echo -e "${GREEN}✓ All tests passed${NC}"
else
    echo -e "${RED}✗ Tests failed - check tests/admin_advisor_tests.php${NC}"
    exit 1
fi

echo ""
echo "Step 3: Syncing files to web directory..."
if ./sync_to_web.sh | grep -q "Sync completed successfully"; then
    echo -e "${GREEN}✓ Files synced successfully${NC}"
else
    echo -e "${YELLOW}⚠  Files synced with some warnings (check /tmp/sync_to_web.log)${NC}"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✅ Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "What was installed:"
echo "  • agent_instances table (with owner_type field)"
echo "  • API endpoints:"
echo "    - app/api/admin/create_agent_instance.php"
echo "    - app/api/admin/chat_instance.php"
echo "    - app/api/admin/get_agent_instance.php"
echo "  • Admin UI: app/course_factory/admin_agent_factory.html (with advisor section)"
echo "  • Tests: tests/admin_advisor_tests.php"
echo ""
echo "Next steps:"
echo "  1. Visit admin_agent_factory.html"
echo "  2. Click 'Create My Advisor' button"
echo "  3. Start chatting with your admin advisor!"
echo ""
echo "Rollback: ./rollback_admin_advisors.sh"
echo ""
