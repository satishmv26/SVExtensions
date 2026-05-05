# SVExtensions_DynamicLabel

**Magento 2 / Adobe Commerce Module**  
Vendor: `SVExtensions` | Module: `DynamicLabel`

---

## Overview

Allows admin users to override any frontend text label globally from the Admin Panel — **without** modifying translation CSV files, editing templates, or triggering a code deployment.

Changes are reflected immediately on the next page request after saving.

---

## Architecture & How It Works

### Core Hook: `afterRender` Plugin on `Magento\Framework\Phrase\Renderer\Translate`

Every string passed through `__()` in Magento — whether in a `.phtml` template, a PHP class, a block, or a UI component — is eventually resolved by `Magento\Framework\Phrase\Renderer\Translate::render()`.

This module intercepts that single method via an `afterRender` plugin:

```
__('Add to Cart')
  └─▶ Magento\Framework\Phrase\Renderer\Translate::render()
        └─▶ SVExtensions\DynamicLabel\Plugin\TranslateRendererPlugin::afterRender()
              └─▶ DB override map lookup (memory-cached per request)
                    └─▶ Returns "Add to Basket" (or original if no override)
```

This approach requires **zero template changes** — existing `__()` calls are overridden transparently.

### Override Priority (when multiple records match)

| Store View      | Locale          | Priority        |
|-----------------|-----------------|-----------------|
| Specific store  | Specific locale | **Highest** (3) |
| Specific store  | All Locales     | High (2)        |
| All Store Views | Specific locale | Medium (1)      |
| All Store Views | All Locales     | Global fallback (0) |

### Cache Strategy

- **Cache tag**: `LJDYNAMIC_LABEL`
- **Cache key**: `LJDYNAMIC_LABEL_MAP_{storeId}_{locale}`
- **TTL**: 86,400 seconds (1 day)
- **Invalidation**: Targeted tag-based clean on every label save/delete — never a full flush
- **In-process map**: Loaded once per request into PHP memory — zero DB hits per phrase

---

## Installation

### Via Composer (recommended)

```bash
composer require svextensions/module-dynamic-label
bin/magento module:enable SVExtensions_DynamicLabel
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Manual Installation

1. Copy the module directory to `app/code/SVExtensions/DynamicLabel/`
2. Run:

```bash
bin/magento module:enable SVExtensions_DynamicLabel
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

---

## Configuration

### Enable / Disable

**Admin → Stores → Configuration → LJ International → Dynamic Labels → Enable Dynamic Labels**

When disabled, the plugin exits immediately — no DB or cache access occurs. This is a zero-cost kill switch.

### Debug Mode

**Admin → Stores → Configuration → LJ International → Dynamic Labels → Debug Mode**

> ⚠️ **Never enable on production.** Logs every phrase lookup to `var/log/debug.log`.

---

## Usage

### Admin Panel

Navigate to **Content → Dynamic Labels** to manage overrides.

#### Creating a Label Override

| Field | Description |
|-------|-------------|
| **Enable Override** | Toggle to activate/deactivate without deleting |
| **Store View** | "All Store Views" for global, or a specific store |
| **Locale** | "All Locales" or a specific locale (e.g. `en_US`) |
| **Original Text** | Exact phrase passed to `__()` — case-sensitive |
| **Override Text** | Replacement text shown to customers |

#### Example — Rename "Add to Cart" globally

| Field | Value |
|-------|-------|
| Store View | All Store Views |
| Locale | All Locales |
| Original Text | `Add to Cart` |
| Override Text | `Add to Basket` |

#### Example — Per-locale override

| Field | Value |
|-------|-------|
| Store View | UK Store |
| Locale | `en_GB` |
| Original Text | `Zip Code` |
| Override Text | `Postcode` |

### Placeholder Support

If the original phrase contains `%1`, `%2` placeholders, include them in the override:

| Original | `Hello, %1! You have %2 item(s) in your cart.` |
|----------|--------------------------------------------------|
| Override | `Welcome back, %1! Your basket has %2 item(s).` |

---

## Finding Phrase Keys

The **Original Text** must match exactly what is passed to `__()` in the source code.

### Common examples

| Page / Element | Phrase Key |
|---------------|------------|
| Add to Cart button | `Add to Cart` |
| Out of stock label | `Out of Stock` |
| My Account link | `My Account` |
| Checkout button | `Proceed to Checkout` |
| Search placeholder | `Search entire store here...` |
| Newsletter signup | `Subscribe` |

### Finding custom keys

1. Search templates: `grep -r "__('Your phrase'" app/design/ vendor/magento/`
2. Check browser source — look for the text in `data-mage-init` or visible on page
3. Enable Debug Mode in staging and check `var/log/debug.log` for lookup hits

---

## Database Schema

**Table**: `svextensions_dynamic_label`

| Column | Type | Description |
|--------|------|-------------|
| `label_id` | INT UNSIGNED PK | Auto-increment |
| `store_id` | SMALLINT | 0 = global |
| `locale` | VARCHAR(20) | Empty = all locales |
| `original_text` | TEXT | Phrase key (max 1000 chars) |
| `override_text` | TEXT | Replacement text (max 1000 chars) |
| `is_active` | SMALLINT | 1 = active |
| `created_at` | TIMESTAMP | Auto |
| `updated_at` | TIMESTAMP | Auto-update |

**Unique index** on `(store_id, locale, original_text)` — prevents duplicate overrides for the same combination.

---

## Module File Structure

```
SVExtensions/DynamicLabel/
├── Api/
│   ├── Data/LabelInterface.php          # Entity contract
│   └── LabelRepositoryInterface.php     # Repository contract
├── Block/Adminhtml/Label/Edit/
│   ├── DeleteButton.php                 # Form toolbar delete button
│   ├── SaveAndContinueButton.php        # Form toolbar save+continue button
│   └── UsageGuide.php                  # Collapsible help block
├── Controller/Adminhtml/Label/
│   ├── Delete.php                       # Single delete
│   ├── Edit.php                         # Edit form page
│   ├── Index.php                        # Grid listing page
│   ├── InlineEdit.php                   # Grid inline edit (JSON)
│   ├── MassDelete.php                   # Bulk delete
│   ├── MassStatus.php                   # Bulk enable/disable
│   ├── NewAction.php                    # New label form
│   └── Save.php                         # Form POST handler
├── Cron/
│   └── WarmLabelCache.php               # Daily cache warm-up job
├── Helper/
│   └── Data.php                         # Store / locale option helpers
├── Model/
│   ├── Label.php                        # ORM model
│   ├── LabelDataProvider.php            # UI form data provider
│   ├── LabelRepository.php             # Repository + cache management
│   ├── ResourceModel/
│   │   ├── Label.php                    # DB resource model
│   │   └── Label/
│   │       ├── Collection.php           # Base collection
│   │       └── Grid/Collection.php      # SearchResultInterface grid collection
│   └── Source/
│       ├── IsActive.php                 # Status options
│       ├── Locale.php                   # Locale options
│       └── Store.php                    # Store options
├── Observer/
│   └── InvalidateLabelCacheObserver.php # Cache tag invalidation on save/delete
├── Plugin/
│   └── TranslateRendererPlugin.php      # ⭐ Core: intercepts __() rendering
├── Setup/Patch/Data/
│   └── InstallDynamicLabelTable.php     # DB table creation (revertable)
├── Ui/Component/Listing/Column/
│   └── LabelActions.php                 # Grid row Edit/Delete actions
├── etc/
│   ├── acl.xml                          # ACL resource definitions
│   ├── adminhtml/
│   │   ├── di.xml                       # Grid data provider wiring
│   │   ├── menu.xml                     # Admin menu entry
│   │   ├── routes.xml                   # Admin route: ljdynamiclabel
│   │   └── system.xml                   # Stores > Config settings
│   ├── config.xml                       # Default config values
│   ├── crontab.xml                      # Daily cache warm-up schedule
│   ├── di.xml                           # Plugin registration, preferences
│   ├── events.xml                       # Save/delete cache invalidation events
│   └── module.xml                       # Module declaration
├── view/adminhtml/
│   ├── layout/
│   │   ├── ljdynamiclabel_label_edit.xml
│   │   ├── ljdynamiclabel_label_index.xml
│   │   └── ljdynamiclabel_label_new.xml
│   ├── templates/label/
│   │   └── usage_guide.phtml            # Admin help panel
│   └── ui_component/
│       ├── ljdynamiclabel_label_form.xml     # Edit/create form
│       └── ljdynamiclabel_label_listing.xml  # Grid listing
├── composer.json
└── registration.php
```

---

## Production Considerations

### What is safe on production

- ✅ Adding new label overrides — only `LJDYNAMIC_LABEL` cache tag is cleaned
- ✅ Disabling a label — same targeted cache clean
- ✅ Mass enabling/disabling — single targeted clean
- ✅ Module disable via config — zero overhead, plugin short-circuits immediately

### What to avoid

- ❌ Enabling Debug Mode on production — writes a log entry per `__()` call
- ❌ Setting very large numbers of overrides (10,000+) — the entire map is serialized in cache; keep it to hundreds

### Rollback

To fully revert the module:

```bash
bin/magento module:disable SVExtensions_DynamicLabel
bin/magento setup:upgrade
# Optionally drop the table:
# bin/magento setup:db-declaration:generate-whitelist --module-name SVExtensions_DynamicLabel
```

The `InstallDynamicLabelTable` patch implements `PatchRevertableInterface` so `setup:rollback` is also supported.

---

## Compatibility

| Platform | Version |
|----------|---------|
| Magento Open Source | 2.4.x |
| Adobe Commerce | 2.4.x |
| PHP | 8.1, 8.2, 8.3 |

---

## License

Proprietary — LJ International. All rights reserved.
