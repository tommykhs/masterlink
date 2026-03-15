# MasterLink

A lightweight, self-hosted link management system. Like Linktree + Bitly + QR Generator in one.

**Version 1.2.0**

## Features

- **Link Shortener** - Clean branded URLs (e.g., `yourdomain.com/form`)
- **Multiple Link Types**
  - **URL** - Display on homepage, opens in new tab
  - **Redirect** - 301 redirect to destination
  - **Embed** - Show destination in iframe (great for Google Forms)
- **Categories** - Organize links with custom icons and direct filter URLs (`/?cat=slug`)
- **Contacts** - Social/contact links displayed in footer
- **QR Code Generator** - One-click QR for any link
- **Visibility Control** - Toggle what appears publicly
- **Theme System** - Light, Dark, Auto, or custom brand theme
- **REST API** - Full CRUD with OpenAPI documentation (Bookmarks, Categories, Contacts, Media)
- **MCP Server** - Claude AI integration for link management

## Screenshots

| Homepage | Admin Panel |
|----------|-------------|
| Bookmark grid with categories | Full link management |

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

### 6. Set admin password

Visit `/admin/` and log in with the default password. Go to Settings to change it.

**Default password**: `admin123` (change immediately!)

## Usage

### Link Types

| Type | Behavior | Use Case |
|------|----------|----------|
| URL | Shown on homepage, opens in new tab | Regular bookmarks |
| Redirect | 301 redirect to target | Short URLs, tracking |
| Embed | Shows target in iframe | Google Forms, external tools |

### API

API documentation available at `/api/` (Swagger UI).

Generate API keys in Settings > API Keys.

Example:
```bash
curl -H "X-API-Key: your-key" https://yourdomain.com/api/bookmarks.php
```

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
│   ├── includes/       # PHP includes (config, auth, functions)
│   ├── bookmarks.php   # Link management
│   ├── categories.php  # Category management
│   ├── settings.php    # Site settings
│   └── ...
├── api/                # REST API endpoints
├── assets/             # CSS, JS, images
├── database/           # SQL schema
├── mcp/                # MCP server for AI
├── templates/          # Embed template
├── uploads/            # User uploads (gitignored)
├── index.php           # Public homepage
└── router.php          # URL routing
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
