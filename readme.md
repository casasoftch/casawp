#Description

This is the dev version of the plugin please refer to ... to learn more about it.

##If you are updating from "casasync" to "casawp" please execute the following queries

This renames to postmetas and removes the unnecesary casasync_ prefixes. It does however first rename the casasync_id item because this one remains solely with a prefix

```
UPDATE wp_postmeta SET `meta_key` = 'casawp_id' where `meta_key` = 'casasync_id';
UPDATE wp_postmeta SET `meta_key` = replace(`meta_key`, 'casasync_', '') where instr(`meta_key`, 'casasync_') > 0;
```

this renames the options to the new prefix

```
UPDATE IGNORE wp_options SET `option_name` = replace(`option_name`, 'casasync_', 'casawp_') where instr(`option_name`, 'casasync_') > 0
```

updates post type prefixes

```
UPDATE wp_posts SET `post_type` = replace(`post_type`, 'casasync_', 'casawp_') where instr(`post_type`, 'casasync_'); 
```
