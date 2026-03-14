# InfinityFree Deployment Guide

## Connection Details

### FTP

| Field | Value |
|-------|-------|
| Hostname | `ftpupload.net` |
| Port | `21` |
| Username | `if0_41390850` |
| Password | *(set in GitHub Secrets as `FTP_PASSWORD`)* |
| Upload directory | `/htdocs/` |

### MySQL

| Field | Value |
|-------|-------|
| Hostname | `sql305.infinityfree.com` |
| Port | `3306` |
| Username | `if0_41390850` |
| Password | *(set in GitHub Secrets as `MYSQL_PASSWORD`)* |
| Database | `if0_41390850_masterlink` |

> **Note:** MySQL is only accessible from InfinityFree servers (no remote connections).

---

## GitHub Actions Auto-Deploy via FTP

Add this workflow to `.github/workflows/deploy.yml`:

```yaml
name: Deploy to InfinityFree

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Deploy via FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ftpupload.net
          username: if0_41390850
          password: ${{ secrets.FTP_PASSWORD }}
          server-dir: /htdocs/
          exclude: |
            **/.git*
            **/.git*/**
            .github/**
            DEPLOY-INFINITYFREE.md
            CREDENTIALS.md
            DEVELOPMENT.md
            README.md
            docs/**
            mcp/**
            dev-router.php
            server-router.php
            database/**
            migrations/**
```

### GitHub Secrets Required

In your GitHub repo, go to **Settings → Secrets and variables → Actions** and add:

| Secret Name | Value |
|-------------|-------|
| `FTP_PASSWORD` | Your InfinityFree FTP password |

---

## First-Time Setup

### 1. Database Setup

Since there's no remote MySQL access, you need to import the schema via InfinityFree's **phpMyAdmin**:

1. Log in to InfinityFree control panel
2. Go to **MySQL Databases** → click **phpMyAdmin** next to `if0_41390850_masterlink`
3. Click **Import** tab
4. Upload `database/schema.sql`
5. Click **Go**

### 2. Config File

Create `admin/includes/config.php` on the server (or upload via FTP):

```php
<?php
define('DB_HOST', 'sql305.infinityfree.com');
define('DB_NAME', 'if0_41390850_masterlink');
define('DB_USER', 'if0_41390850');
define('DB_PASS', 'YOUR_MYSQL_PASSWORD_HERE');

define('SITE_URL', 'https://yourdomain.com');
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'CHANGE_THIS');
```

> **Important:** Do NOT commit `config.php` to the repo. It should be in `.gitignore`.

### 3. .htaccess

Ensure the `.htaccess` in project root has URL rewriting enabled. InfinityFree supports `mod_rewrite`.

### 4. File Permissions

The `uploads/` directory needs to be writable. Via FTP, set permissions to `755` or `777` on the `uploads/` folder.

---

## Manual FTP Upload (without GitHub Actions)

1. Connect to `ftpupload.net:21` with an FTP client (FileZilla, Cyberduck, etc.)
2. Navigate to `/htdocs/`
3. Upload all project files (excluding `.git/`, `docs/`, `mcp/`, `database/`, `migrations/`)
4. Create `admin/includes/config.php` with your DB credentials

---

## Custom Domain Setup

1. In InfinityFree panel, go to **Addon Domains** or **Parked Domains**
2. Add your custom domain
3. Update your domain's DNS:
   - Point **A record** or **CNAME** to the address InfinityFree provides
4. Wait for DNS propagation (up to 24 hours)
5. Enable **Free SSL** in the InfinityFree panel
