#!/bin/bash
# Quick Setup Script for AI Educational Platform
# Run this after installing Apache, PHP, and MariaDB

set -e

echo "=========================================="
echo "AI Educational Platform - Quick Setup"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

# Get the current directory
INSTALL_DIR="/var/www/Professor_Hawkeinstein"
DB_NAME="eduai_platform"
DB_USER="eduai_user"
DB_PASS="EduAI_$(openssl rand -hex 8)"

echo "Installation directory: $INSTALL_DIR"
echo ""

# Create database and user
echo "Step 1: Setting up database..."
mysql -u root -p <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "✓ Database created"

# Import schema
echo "Step 2: Importing database schema..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$INSTALL_DIR/schema.sql"
echo "✓ Schema imported"

# Update config file
echo "Step 3: Configuring application..."
sed -i "s/define('DB_PASS', 'your_secure_password_here');/define('DB_PASS', '$DB_PASS');/" "$INSTALL_DIR/config/database.php"

# Generate JWT secret
JWT_SECRET=$(openssl rand -hex 32)
sed -i "s/define('JWT_SECRET', 'your_jwt_secret_key_here_change_in_production');/define('JWT_SECRET', '$JWT_SECRET');/" "$INSTALL_DIR/config/database.php"

# Generate password pepper
PASSWORD_PEPPER=$(openssl rand -hex 32)
sed -i "s/define('PASSWORD_PEPPER', 'additional_security_pepper_change_in_production');/define('PASSWORD_PEPPER', '$PASSWORD_PEPPER');/" "$INSTALL_DIR/config/database.php"

echo "✓ Configuration updated"

# Create directories
echo "Step 4: Creating directories..."
mkdir -p "$INSTALL_DIR/logs"
mkdir -p "$INSTALL_DIR/media"
chmod 775 "$INSTALL_DIR/logs"
chmod 775 "$INSTALL_DIR/media"
echo "✓ Directories created"

# Set permissions
echo "Step 5: Setting permissions..."
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
echo "✓ Permissions set"

# Enable Apache modules
echo "Step 6: Configuring Apache..."
a2enmod rewrite
a2enmod headers
a2enmod expires
a2enmod deflate

# Configure Apache virtual host
cat > /etc/apache2/sites-available/professorhawkeinstein.conf <<EOF
<VirtualHost *:80>
    ServerName professorhawkeinstein.org
    ServerAdmin admin@professorhawkeinstein.org
    DocumentRoot $INSTALL_DIR
    
    <Directory $INSTALL_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/eduai_error.log
    CustomLog \${APACHE_LOG_DIR}/eduai_access.log combined
</VirtualHost>
EOF

# Enable site
a2ensite Professor_Hawkeinstein.conf
systemctl reload apache2
echo "✓ Apache configured"

# Add to hosts file
if ! grep -q "eduai.local" /etc/hosts; then
    echo "127.0.0.1 eduai.local" >> /etc/hosts
fi

echo ""
echo "=========================================="
echo "✓ Setup Complete!"
echo "=========================================="
echo ""
echo "Database Credentials (SAVE THESE!):"
echo "  Database: $DB_NAME"
echo "  Username: $DB_USER"
echo "  Password: $DB_PASS"
echo ""
echo "Application Access:"
echo "  URL: http://eduai.local"
echo "  Demo Login: john_doe / student123"
echo ""
echo "Next Steps:"
echo "  1. Install Ollama: curl -fsSL https://ollama.com/install.sh | sh"
echo "  2. Pull models: ollama pull llama2 && ollama pull mistral"
echo "  3. Compile C++ agent service (see README.md)"
echo "  4. Start agent service: ./agent_service"
echo ""
echo "Logs location: $INSTALL_DIR/logs/"
echo "Media location: $INSTALL_DIR/media/"
echo ""
