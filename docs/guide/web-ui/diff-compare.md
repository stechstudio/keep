# Diff & Compare

The Diff view provides a powerful matrix visualization for comparing secrets across multiple stages and vaults simultaneously.

## Overview

The diff matrix shows:
- **Rows** - Each secret key
- **Columns** - Each vault/stage combination
- **Cells** - Secret values with visual status indicators

## Using the Diff View

### Selecting Comparisons
1. Click **Toggle Vaults / Stages** dropdown
2. Check the combinations you want to compare
3. The matrix updates automatically

Quick selection:
- **Select All** - Compare everything
- **Clear All** - Start fresh
- Individual checkboxes for fine control

### Understanding the Matrix

Cell states:
- **✓ Green** - Secret exists with this value
- **Empty** - Secret doesn't exist in this environment
- **Yellow highlight** - Value differs from other environments
- **Masked** - Click to reveal actual values

### Row Actions
Each row has indicators showing:
- **Complete** - Secret exists in all selected environments
- **Incomplete** - Missing from some environments
- **Inconsistent** - Different values across environments

## Interactive Features

### Edit from Diff
Click any cell to:
- **Create** - If secret is missing
- **Edit** - If secret exists
- **Copy** - To another environment

### Bulk Unmask
Toggle **Show Values** to reveal all secrets at once. Use with caution.

### Search Filter
The search box filters rows in real-time, helpful for large comparisons.

## Common Workflows

### Environment Promotion
1. Select source and target stages
2. Identify differences (yellow cells)
3. Click to copy values from source to target
4. Verify all secrets match

### Audit Inconsistencies
1. Select all environments
2. Look for yellow highlighted cells
3. Investigate why values differ
4. Standardize as needed

### Fill Missing Secrets
1. Look for empty cells
2. Click to create the missing secret
3. Copy value from another environment
4. Ensure completeness

## Export Options

Click **Export** to download the diff:
- **CSV format** - For spreadsheet analysis
- **JSON format** - For programmatic processing
- **Filtered export** - Only exports visible rows

## Best Practices

### Regular Audits
- Weekly diff between staging and production
- Identify drift before deployments
- Ensure environment parity

### Before Deployments
- Compare local → staging → production
- Verify all required secrets exist
- Check for value consistency

### Team Collaboration
- Export diffs for review
- Share CSV reports with team
- Document intentional differences