# MasterLink

A lightweight, self-hosted link management system. Like Linktree + Bitly + QR Generator in one.

**Version 1.3.0**

## Features

- **Link Shortener** - Clean branded URLs (e.g., `yourdomain.com/form`)
- **Multiple Link Types**
  - **URL** - Display on homepage, opens in new tab
  - **Redirect** - 301 redirect to destination
  - **Embed** - Show destination in iframe (great for Google Forms)
  - **File** - Serve uploaded files with optional password protection
- **Categories** - Organize links with custom icons and direct filter URLs (`/?cat=slug`)
- **Contacts** - Social/contact links displayed in footer
- **QR Code Generator** - One-click QR for any link
- **PWA Support** - Embed links can be installed as standalone apps on mobile/desktop
- **Visibility Control** - Toggle what appears publicly
- **Theme System** - Light, Dark, Auto, or custom brand theme
- **File Manager** - Upload and manage images and documents
- **Database Manager** - Browse tables, run SQL, edit rows from admin
- **REST API** - Full CRUD with OpenAPI documentation
- **MCP Server** - Claude AI integration for link management

## Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite (or nginx)

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/mcailab/masterlink.git
cd masterlink
```

### 2. Create the database

```sql
CREATE DATABASE masterlink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the schema:

```bash
mysql -u username -p masterlink < database/schema.sql
```

### 3. Configure the application

```bash
cp admin/includes/config.example.php admin/includes/config.php
```

Edit `config.php` with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'masterlink');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('SITE_URL', 'https://yourdomain.com');
```

### 4. Set up uploads directory

```bash
mkdir -p uploads
chmod 755 uploads
```

### 5. Configure web server

**Apache** - The included `.htaccess` handles routing. Ensure `mod_rewrite` is enabled.

**Nginx** - Add to your server block:

```nginx
location / {
    try_files $uri $uri/ /router.php?$query_string;
}
```

### 6. Log in

Visit `/admin/` and log in with the default password: `admin`

Change it immediately in Settings.

## Usage

### Link Types

| Type | Behavior | Use Case |
|------|----------|----------|
| URL | Shown on homepage, opens in new tab | Regular bookmarks |
| Redirect | 301 redirect to target | Short URLs, tracking |
| Embed | Shows target in iframe, optional PWA | Google Forms, Apps Script web apps |
| File | Serves uploaded file, optional password | Documents, downloads |

### API

API documentation available at `/api/` (Swagger UI).

Generate API keys in Settings > API Keys.

```bash
curl -H "X-API-Key: your-key" https://yourdomain.com/api/bookmarks.php
```

**Endpoints:**

| Endpoint | Methods |
|----------|---------|
| `/api/bookmarks.php` | GET, POST, PUT, DELETE |
| `/api/categories.php` | GET, POST, PUT, DELETE |
| `/api/contacts.php` | GET, POST, PUT, DELETE |
| `/api/media.php` | GET, POST, DELETE |
| `/api/files.php` | GET, POST, DELETE |

### PWA for Embed Links

Embed-type bookmarks can be installed as standalone apps (Progressive Web Apps). Toggle "PWA" in the bookmark editor when link type is Embed.

**How it works:**
- The embed page dynamically serves a `manifest.json` and service worker
- On Android Chrome: "Add to Home Screen" banner appears automatically
- On iOS Safari: tap Share → Add to Home Screen
- The installed app opens fullscreen without browser chrome

PWA is enabled by default for new embed bookmarks. Toggle it off per bookmark if not needed.

### MCP Server

For Claude AI integration, configure MCP in your Claude settings:

```json
{
  "mcpServers": {
    "masterlink": {
      "command": "php",
      "args": ["/path/to/masterlink/mcp/server.php"],
      "env": {
        "API_KEY": "your-api-key"
      }
    }
  }
}
```

## File Structure

```
masterlink/
├── admin/              # Admin panel
│   ├── includes/       # Config, auth, functions
│   ├── bookmarks.php   # Link management
│   ├── categories.php  # Category management
│   ├── contacts.php    # Contact links
│   ├── shortener.php   # URL shortener
│   ├── files.php       # File manager
│   ├── database.php    # Database manager
│   ├── settings.php    # Site settings & API keys
│   └── qr.php          # QR code generator
├── api/                # REST API endpoints
├── assets/css/         # Stylesheets
├── database/           # SQL schema
├── includes/           # Parsedown library
├── mcp/                # MCP server for AI
├── templates/          # Page, embed, PWA manifest/SW, password-gate, 404
├── uploads/            # User uploads (gitignored)
├── index.php           # Public homepage
├── router.php          # URL routing
└── file-serve.php      # File serving with auth
```

## Configuration

### Subfolder Installation

If installing in a subfolder (e.g., `example.com/link`):

```php
define('SITE_URL', 'https://example.com/link');
// BASE_PATH will automatically be '/link'
```

### Theme Customization

Choose from built-in themes in Settings:
- **Auto** - Follows system preference
- **Light** - Always light mode
- **Dark** - Always dark mode
- **MC** - Custom brand colors

## License

MIT License - feel free to use and modify.

## Author

Created by [Tommy Shum](mailto:tommy.shum@hkmci.com)
