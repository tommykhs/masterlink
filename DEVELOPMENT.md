# Development Guide

## Repository Structure

| Repo | Visibility | Purpose |
|------|------------|---------|
| `mcailab/mcai` | Private | Development with deployment workflows |
| `mcailab/masterlink` | Public | Stable releases (no deployment config) |

## Git Remotes

```bash
origin  -> mcailab/mcai (private)
public  -> mcailab/masterlink (public)
```

## Branches

- `main` - Production deployment (SiteGround)
- `mclink` - Staging deployment (alternate FTP)

## Update Workflow

### 1. Develop locally on main branch
```bash
git checkout main
# make changes...
```

### 2. Commit and push to private repo
```bash
git add <files>
git commit -m "Your commit message"
git push origin main
```

### 3. Update mclink branch
```bash
git checkout mclink
git merge main --no-edit
git push origin mclink
git checkout main
```

### 4. Push stable version to public repo
```bash
git push public main
```

**Note:** The public repo excludes `.github/workflows/` (deployment config).

## Version Updates

When releasing a new version, update these files:

1. `README.md` - Version badge at top
2. `admin/about.php` - Version in info list (line ~49)
3. `api/openapi.json` - Version in info.version field

## File Structure

```
masterlink/
├── admin/           # Admin panel
├── api/             # REST API (bookmarks, categories, contacts, media)
├── assets/          # CSS, JS
├── database/        # SQL schema
├── mcp/             # Claude AI MCP server
├── templates/       # Embed template, 404
├── uploads/         # User uploads (gitignored)
├── .github/         # Deployment workflows (private only)
├── index.php        # Public homepage
└── router.php       # URL routing
```

## API Endpoints

| Endpoint | Methods |
|----------|---------|
| `/api/bookmarks.php` | GET, POST, PUT, DELETE |
| `/api/categories.php` | GET, POST, PUT, DELETE |
| `/api/contacts.php` | GET, POST, PUT, DELETE |
| `/api/media.php` | GET, POST, DELETE |

## Local Development

```bash
php -S localhost:8000
```

Then visit:
- http://localhost:8000 - Public homepage
- http://localhost:8000/admin - Admin panel
- http://localhost:8000/api - API docs
