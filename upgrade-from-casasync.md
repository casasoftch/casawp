#Database

Please initiate the following MySQL queries to your old DB

##Postmetas

This renames to postmetas and removes the unnecesary casasync_ prefixes. It does however first rename the casasync_id item because this one remains solely with a prefix

```
UPDATE wp_postmeta SET `meta_key` = 'casawp_id' where `meta_key` = 'casasync_id';
UPDATE wp_postmeta SET `meta_key` = replace(`meta_key`, 'casasync_', '') where instr(`meta_key`, 'casasync_') > 0;
UPDATE wp_postmeta SET `meta_value` = replace(`meta_value`, 'casasync_', 'casawp_') where instr(`meta_value`, 'casasync_') > 0;
```

##Options

this renames the options to the new prefix

```
UPDATE IGNORE wp_options SET `option_name` = replace(`option_name`, 'casasync_', 'casawp_') where instr(`option_name`, 'casasync_') > 0;
```

##Post Types

updates post type prefixes

```
UPDATE wp_posts SET `post_type` = replace(`post_type`, 'casasync_', 'casawp_') where instr(`post_type`, 'casasync_'); 
```

##Option key/value changes

replaces `casawp_load_css` with `casawp_viewgroup` keys and values

```
casawp_load_css:bootstrapv2 -> casawp_viewgroup:bootstrap2
casawp_load_css:none -> casawp_viewgroup:bootstrap3
casawp_load_css:bootstrapv3 -> casawp_viewgroup:bootstrap3

```

##Taxonomy Terms

replaces taxonomy term connection names

```
UPDATE wp_term_taxonomy SET `taxonomy` = replace(`taxonomy`, 'casasync_', 'casawp_') where instr(`taxonomy`, 'casasync_') > 0;
```

#combined for quick pasting

```
UPDATE wp_postmeta SET `meta_key` = 'casawp_id' where `meta_key` = 'casasync_id';
UPDATE wp_postmeta SET `meta_key` = replace(`meta_key`, 'casasync_', '') where instr(`meta_key`, 'casasync_') > 0;
UPDATE wp_postmeta SET `meta_value` = replace(`meta_value`, 'casasync_', 'casawp_') where instr(`meta_value`, 'casasync_') > 0;
UPDATE IGNORE wp_options SET `option_name` = replace(`option_name`, 'casasync_', 'casawp_') where instr(`option_name`, 'casasync_') > 0;
UPDATE wp_posts SET `post_type` = replace(`post_type`, 'casasync_', 'casawp_') where instr(`post_type`, 'casasync_'); 
UPDATE wp_term_taxonomy SET `taxonomy` = replace(`taxonomy`, 'casasync_', 'casawp_') where instr(`taxonomy`, 'casasync_') > 0;
```

#Template changes

You will need to find and replace some things in your theme.

Rename **all** instances of

```
casasync -> casawp
CasaSync -> casawp
Casasync -> casawp
casaSync -> casawp
```

rename `casasync-single.php` and `casasync-archive.php` to `casawp-single.php` and `casawp-archive.php`


