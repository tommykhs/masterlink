#!/bin/bash
# api-fetch.sh - Bypass InfinityFree bot protection and call MasterLink API
#
# Usage:
#   ./tools/api-fetch.sh GET /api/bookmarks.php
#   ./tools/api-fetch.sh POST /api/sql.php '{"query":"SHOW TABLES"}'
#   ./tools/api-fetch.sh GET /api/categories.php
#   ./tools/api-fetch.sh GET /migrate.php?key=mk_ad3f313587bcb7a98503b1e4eaea4c5e

DOMAIN="https://ts.page.gd"
API_KEY="ml-f28e125e4bdfef419e351e4398351d47"
UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"

METHOD="${1:-GET}"
ENDPOINT="${2:-/api/bookmarks.php}"
BODY="${3:-}"

# Step 1: Get the bot challenge page
CHALLENGE=$(curl -s -A "$UA" "http://ts.page.gd$ENDPOINT" 2>/dev/null)

# Step 2: Extract AES values and solve with Node.js
COOKIE=$(echo "$CHALLENGE" | node -e "
const crypto = require('crypto');
let input = '';
process.stdin.on('data', d => input += d);
process.stdin.on('end', () => {
  const m = input.match(/toNumbers\(\"([a-f0-9]+)\"\).*toNumbers\(\"([a-f0-9]+)\"\).*toNumbers\(\"([a-f0-9]+)\"\)/s);
  if (!m) { process.exit(1); }
  const [_, a, b, c] = m;
  const key = Buffer.from(a, 'hex');
  const iv = Buffer.from(b, 'hex');
  const enc = Buffer.from(c, 'hex');
  const dec = crypto.createDecipheriv('aes-128-cbc', key, iv);
  dec.setAutoPadding(false);
  const result = Buffer.concat([dec.update(enc), dec.final()]);
  console.log(result.toString('hex'));
});
" 2>/dev/null)

if [ -z "$COOKIE" ]; then
  # No challenge needed (cookie might still be valid), try direct
  echo "$CHALLENGE"
  exit 0
fi

# Step 3: Make the actual request with the solved cookie
if [ "$METHOD" = "POST" ] && [ -n "$BODY" ]; then
  curl -sL -A "$UA" \
    -b "__test=$COOKIE" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "X-API-Key: $API_KEY" \
    -d "$BODY" \
    "$DOMAIN$ENDPOINT"
else
  curl -sL -A "$UA" \
    -b "__test=$COOKIE" \
    -H "X-API-Key: $API_KEY" \
    "$DOMAIN$ENDPOINT"
fi
