#!/bin/bash

# Git Commit Co-Author Cleanup Script
# Removes co-author and "generated with" lines from git history
# WARNING: This rewrites git history and requires force push

set -e

echo "Git Commit Co-Author Cleanup Script"
echo "===================================="
echo ""
echo "WARNING: This script will rewrite git history!"
echo "Make sure you have:"
echo "1. Committed and pushed all current work"
echo "2. Coordinated with any other developers"
echo "3. Understand the implications of force pushing"
echo ""
read -p "Continue? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

# Create backup branch
BACKUP_BRANCH="backup-before-cleanup-$(date +%Y%m%d-%H%M%S)"
echo "Creating backup branch: $BACKUP_BRANCH"
git branch "$BACKUP_BRANCH"
echo "Backup created at: $BACKUP_BRANCH"
echo ""

# Clean commit messages
echo "Cleaning commit messages..."
FILTER_BRANCH_SQUELCH_WARNING=1 git filter-branch --force --msg-filter '
sed -e "/Generated with \[Claude Code\]/d" \
    -e "/via \[Happy\]/d" \
    -e "/Co-Authored-By: Claude/d" \
    -e "/Co-Authored-By: Happy/d" \
    -e "/🤖 Generated with/d" | \
awk "NF {p=1} p" | \
sed "/^$/N;/\n$/d"' \
--tag-name-filter cat -- --all

echo ""
echo "Commit messages cleaned!"
echo ""

# Show example of cleaned commits
echo "Sample of cleaned commits:"
git log --oneline -5
echo ""

# Ask about force push
read -p "Force push to remote? (y/N): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Force pushing to origin..."
    git push --force origin main
    echo "Force push complete!"
else
    echo "Skipped force push."
    echo "To push later, run: git push --force origin main"
fi

echo ""
echo "Cleanup complete!"
echo "Backup branch preserved at: $BACKUP_BRANCH"
echo ""
echo "To restore original history if needed:"
echo "  git reset --hard $BACKUP_BRANCH"
echo "  git push --force origin main"