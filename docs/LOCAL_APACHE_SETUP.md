# Local Development Setup for MasterLink

## Option 1: PHP Built-in Server (Recommended for Quick Setup)

No Apache required! Use PHP's built-in development server:

```bash
cd /path/to/masterlink
php -S localhost:8000 server-router.php
```

Then access: `http://localhost:8000`

This handles all URL routing including:
- `.md` file rendering as styled HTML
- Password-protected files
- Shortener links
- All admin pages

**Note:** The built-in server is for development only. Use Apache/Nginx for production.

---

## Option 2: Apache Setup

### Requirements for .md file rendering on localhost

### 1. Enable mod_rewrite

**macOS (MAMP/Built-in Apache):**
```bash
# Edit Apache config
sudo nano /etc/apache2/httpd.conf

# Uncomment this line (remove the #):
LoadModule rewrite_module libexec/apache2/mod_rewrite.so
```

**MAMP:**
- Already enabled by default

**XAMPP:**
- Already enabled by default

### 2. Enable AllowOverride

Find your Apache config file and update the `<Directory>` block for your web root:

**macOS Built-in Apache** (`/etc/apache2/httpd.conf`):
```apache
<Directory "/Library/WebServer/Documents">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**MAMP** (`/Applications/MAMP/conf/apache/httpd.conf`):
```apache
<Directory "/Applications/MAMP/htdocs">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**XAMPP** (`/opt/lampp/etc/httpd.conf` or `C:\xampp\apache\conf\httpd.conf`):
```apache
<Directory "/opt/lampp/htdocs">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### 3. Restart Apache

**macOS:**
```bash
sudo apachectl restart
```

**MAMP:**
Stop and Start from MAMP app

**XAMPP:**
Stop and Start Apache from XAMPP Control Panel

### 4. Test

Access a .md file through the browser:
```
http://localhost/masterlink/uploads/test.md
```

Should render as styled HTML, not raw markdown text.

## Troubleshooting

### Check if mod_rewrite is enabled
```bash
apachectl -M | grep rewrite
# Should output: rewrite_module (shared)
```

### Check .htaccess is being read
Add this to the top of `.htaccess` temporarily:
```apache
# This should cause a 500 error if .htaccess is being read
InvalidDirective Test
```

If you don't get an error, `AllowOverride` is not set correctly.

### Check Apache error logs
```bash
# macOS
tail -f /var/log/apache2/error_log

# MAMP
tail -f /Applications/MAMP/logs/apache_error_log

# XAMPP
tail -f /opt/lampp/logs/error_log
```
