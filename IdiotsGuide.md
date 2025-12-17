```markdown

# IdiotsGuide
WP Block and Shortcode Auditor
This is for when you want to remove plugins without playing roulette.

## What this is really for
You have a plugin list that looks like a junk drawer.
You want to clean it.
But you do not know what is used, because WordPress does not give you a usage map.
Blocks and shortcodes are the easiest receipts to read.

## How to use it
Open:
Tools
Block Audit
Look at four things.
1) Blocks used in published content
If a plugin registers fancy blocks but none of them appear here, the plugin might be dead weight.
Might. Not guaranteed. But worth checking.
2) Registered blocks not seen
This list is your suspicion list.
If a block is registered but not used, ask why that plugin exists.
Sometimes a plugin registers blocks as editor tools and they never show up in content. Still counts as “why is it installed”.
3) Shortcodes found in content
This tells you what legacy content still depends on old patterns.
If you see a shortcode used 80 times, do not uninstall the plugin behind it unless you like blank spaces on pages.
4) Shortcodes with no handler
These are shortcodes in content that WordPress does not recognise anymore.
That usually means:
a plugin was removed
a plugin was disabled
a shortcode was renamed
someone copied content from another site and never cleaned it
These are the ones that make pages silently degrade.

## A safe cleanup pattern
Make a staging copy.
Run this audit.
Pick one plugin you want gone.
Check whether it owns blocks or shortcodes that are used.
If it is unused, disable it on staging.
Browse key pages.
If nothing breaks, proceed on production with backups.
I know that sounds slow. It is still faster than fixing a broken homepage after lunch.

## One annoying truth
Some builders store layout data in post meta, not in post_content.
This audit will not see that.
If your site is heavy Elementor or similar, treat this tool as partial evidence, not final truth.
Partial evidence is still better than vibes.
