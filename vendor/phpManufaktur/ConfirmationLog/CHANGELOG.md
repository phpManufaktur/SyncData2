## ConfirmationLog ##

(c) 2012, 2013 phpManufaktur by Ralf Hertsch<br/>
MIT License (MIT) - <http://www.opensource.org/licenses/MIT>

**0.17** - 2013-10-07

* Setup and Update does not change the old compatibility droplet `[[confirmation_log]]` in the expected way, so some parameters does not work as expected

**0.16** - 2013-10-03

* added support for SyncData, submission of confirmations

**0.15** - 2013-10-03

* added kitCommand `~~ ConfirmationReport ~~` 
* added missing comments, cleanup code

**0.14** - 2013-10-02

* if the old droplet `[[confirmation_log]]` exists, rewrite it to the new code, so it can be also used (compatibility)
* added reporting of missing confirmations, use groups of installation names for the check
* added droplet `[[syncdata_confirmation_report]]` to enable the reports at the frontend 

**0.13** - 2013-10-01

* just in progress, added compatibility functions SyncData/kitFramework

**0.12** - 2013-09-30

* first beta release