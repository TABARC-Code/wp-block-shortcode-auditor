<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Block and Shortcode Auditor
This plugin answers the question nobody wants to ask on a WordPress site with 40 plugins.
What is actually used.
People keep plugins installed because maybe something uses a block, or maybe a shortcode is buried on a page made in 2019, or maybe the site is haunted. This tool gives me evidence.

## What it does
Adds:
Tools
Block Audit
It scans published content and reports:
Blocks detected with usage counts
Registered blocks not detected in published content
Shortcodes detected with usage counts
Shortcodes that appear in content but have no handler registered on the site
It also exports JSON so I can diff audits or attach it to a ticket.

## What it does not do
It does not uninstall plugins.
It does not remove blocks.
It does not rewrite content.
It is read only, on purpose.

## Notes
Block detection is based on the standard block comment format in post_content.
If your builder stores layouts in post meta or JSON blobs, this scan will not see it. Builders love hiding the truth.
Shortcode scanning is best effort. If someone typed a shortcode inside a code block, I might still count it. I prefer false positives to silent misses.

## Filters
Limit scanned posts:
```php
add_filter( 'wbsa_scan_post_limit', function() { return 2000; } );
Override scanned post types:

php
Copy code
add_filter( 'wbsa_scan_post_types', function( $types ) {
    return array( 'post', 'page' );
} );
