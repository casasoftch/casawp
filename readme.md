#Description

Synchronisieren Sie Ihre Immobilien Objekte mit CasaSync, Lassen Sie die Einträge automatisch Kategorisieren und passen Sie das Plugin an jedes Theme an. CasaSync importiert, flexibel, zuverlässig und schnell die üblichsten Standards.

#Installation

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page.

#Does this plugin require a real-estate software?

It makes things a lot easier. See casasoft.ch[http://www.casasoft.ch] for more details. There are currently no plans to make this plugin editable/interactive, this can however change.

#So there is absolutely no way to edit the real-estate listings manually!?

Well, yes and no. Essentially the plugin activates custom post types and a rendering engine for custom meta information. If you are savvy enough to enter custom fields yourself, you may go ahead and use this plugin without a export Software. If you wish to build a interface on top of that, we recommend the "Advanced Custom Fields" plugin to make your job easier. However, be aware of changes/additions to fields and behaviors within the future.

This plugin also works hand-in-hand with the [CasaGateway](https://casasoft.ch/casagateway) Service and therefore can indirectly support alternate Standards such as OpenImmo, Homegate IDX etc. For a full list of 

#How can I import properties

The plugin is based on the [casaXML](http://github.com/casasoftCH/casaXML) standard. If you can somehow generate this simple xml standard, than you can simply place it in the appropriate directory `/wp-content/uploads/casasync/import/data.xml` and activate a import (either this happens automatically, or you may activate it manually within WordPress). Essentially you can keep this file up-to-date to ensure a synchronized state (be aware that the file will be renamed data-done.xml once imported).

#How do I change the html markup and add my own view files

Simply copy the relevant view files ...

![image](assets/custom-viewfiles-plugin.png)

into your theme directory ...

![image](assets/custom-viewfiles-theme.png)
