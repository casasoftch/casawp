#Description

This is the dev version of the plugin please refer to ... to learn more about it.

##updateing from casasync to casawp please execute the following queries

```UPDATE wp_postmeta SET `meta_key` = replace(`meta_key`, 'casasync_', '') where instr(`meta_key`, 'casasync_') > 0```

```UPDATE IGNORE wp_options SET `option_name` = replace(`option_name`, 'casasync_', 'casawp_') where instr(`option_name`, 'casasync_') > 0```

