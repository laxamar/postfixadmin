# Regex Feature for Aliases

## Overview
Added a regex flag (`x_regexp`) to the alias table and edit forms to enable regular expression matching for aliases.

## Changes Made

### 1. Database Structure
- Added `x_regexp` field to the alias table in `public/upgrade.php` (line ~454)
- Field type: `tinyint(1) NOT NULL default '0'` (boolean with default false)

### 2. Model Changes
- Modified `model/AliasHandler.php` to include the `x_regexp` field in the struct
- Field is editable, displays in form and list, with proper language keys

### 3. Language Files
- Added language entries to `languages/en.lang`:
  - `pEdit_alias_regex` = 'Regular Expression'  
  - `pEdit_alias_regex_desc` = 'Enable regular expression matching for this alias (advanced users only)'

### 4. Database Migration
- Added `upgrade_5()` function to add the field to existing installations
- Uses `_db_add_field()` function for cross-database compatibility

## Usage
When editing an alias, administrators will see a checkbox labeled "Regular Expression" with a helpful description. When enabled, this flag indicates that the alias should be processed using regular expression matching instead of literal string matching.

## Template Support
The existing `templates/editform.tpl` already supports boolean fields via the `{elseif $field.type == 'bool'}` condition, so no template changes were needed.

## Field Name
The field is named `x_regexp` to be consistent with the existing database structure.
