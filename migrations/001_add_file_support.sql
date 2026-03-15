-- Migration: Add file support to bookmarks table
-- Run this SQL to add file hosting feature

-- Add file_path column for file type shortener links
ALTER TABLE bookmarks ADD COLUMN file_path VARCHAR(500) DEFAULT NULL;

-- Add password column for password-protected files/links
ALTER TABLE bookmarks ADD COLUMN password VARCHAR(255) DEFAULT NULL;

-- Note: link_type options are now: 'url', 'redirect', 'embed', 'file'
-- file_path stores relative path like 'readme.md' or 'tommy/notes.md'
-- password stores bcrypt hash (NULL = public, non-null = protected)
