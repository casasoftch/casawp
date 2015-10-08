#Description

This is the dev version of the plugin please refer to ... to learn more about it.

##updateing from casasync to casawp please execute the following queries


This renames to postmetas and removes the unnecesary casasync_ items. It does however first rename the casasyn_id item because this one remains as is

```
UPDATE wp_postmeta SET `meta_key` = 'casawp_id' where `meta_key` = 'casasync_id';
UPDATE wp_postmeta SET `meta_key` = replace(`meta_key`, 'casasync_', '') where instr(`meta_key`, 'casasync_') > 0;
```

this renames the options to the new name

```
UPDATE IGNORE wp_options SET `option_name` = replace(`option_name`, 'casasync_', 'casawp_') where instr(`option_name`, 'casasync_') > 0
```


