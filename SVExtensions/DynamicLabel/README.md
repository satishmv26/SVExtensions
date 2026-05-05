# Dynamic Label Manager for Adobe Commerce

## Overview

Dynamic Label Manager enables real-time frontend text replacement using Magento Custom Variables—without relying on translation CSV files.

This module is designed for high-performance environments and allows merchants to update key UI labels instantly from the Admin panel without code deployment.

---

## Key Features

* Replace frontend labels dynamically using Custom Variables
* No dependency on `i18n/*.csv` translation files
* Supports **Plain Text** and **HTML** values
* Store view–specific label overrides
* CDN optimized (Fastly / Varnish support)
* Works without CDN (Magento Open Source compatible)
* CLI tools for bulk import and cleanup
* Tag-based cache invalidation (no full cache flush)
* Multi-layer architecture:

  * PHP Plugin (server-side rendering)
  * ESI block (CDN acceleration)
  * JavaScript fallback (browser-level)

---

## Use Cases

* Change “Add to Cart” → “Add to Basket”
* Update “Sign In” → “Login”
* Modify CTA labels during campaigns
* Perform UI A/B testing without deployment
* Customize store-specific terminology

---

## How It Works

1. Admin creates Custom Variables
2. Module generates a normalized label map
3. Labels are replaced at multiple layers:

   * PHP rendering (fastest)
   * CDN edge (ESI block)
   * Browser fallback (JavaScript)

This ensures maximum compatibility and performance across environments.

---

## Configuration

**Path:**
`Stores → Configuration → Dynamic Label`

### Settings

* **Enable Module**
  Enable or disable functionality

* **Value Type**

  * Plain Text
  * HTML

---

## Installation

### Composer

```bash
composer require svextensions/module-dynamiclabel
bin/magento module:enable SVExtensions_DynamicLabel
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## CLI Commands

### Import Custom Variables

```bash
bin/magento sv:dynamiclabel:import var/import.csv
```

### Delete Custom Variables

```bash
bin/magento sv:dynamiclabel:delete
```

---

## Example

| Original Label     | Replaced Label |
| ------------------ | -------------- |
| Add to Cart        | Add to Basket  |
| Sign In            | Login          |
| View and Edit Cart | My Cart        |

---

## Performance & Caching

* Uses Magento cache with custom cache tags
* Supports Fastly soft purge (grace mode)
* Avoids full-page cache flush
* Optimized for high-traffic environments

---

## Compatibility

* Magento Open Source 2.4.x
* Adobe Commerce (Cloud & On-Prem)
* Fastly CDN
* Varnish Cache
* Luma and custom themes

---

## Limitations

* Intended for **short UI labels only**
* Not recommended for:

  * Long paragraphs or content blocks
  * Complex translations
* Some dynamically rendered Knockout (KO) components (e.g., checkout) may not be fully covered
* Magento’s native CSV translation is still recommended for full localization

---

## Best Practices

* Use for key UI labels (buttons, links, headings)
* Keep variable names normalized (lowercase, trimmed)
* Avoid special characters mismatch
* Test changes per store view

---

## Security & Scope

* Applies only to frontend area
* Does not affect admin panel
* Uses Magento ACL for configuration access

---

## Troubleshooting

### Labels not updating?

* Ensure module is enabled
* Verify Custom Variable exists
* Clear cache once:

  ```bash
  bin/magento cache:clean
  ```

---

## Support

For issues or feature requests, contact the extension provider.

---

## License

Proprietary (or specify your license type)

---

## Summary

Dynamic Label Manager provides a lightweight, high-performance alternative to Magento translation CSV for managing key frontend labels dynamically—ideal for modern commerce workflows.

---
