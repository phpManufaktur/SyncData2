## SyncData ##

(c) 2011, 2013 phpManufaktur by Ralf Hertsch<br/>
MIT License (MIT) - <http://www.opensource.org/licenses/MIT>

**2.0.33** - 2013-11-06

* removed UTF-8 force settings (wrong way)
* added Uninstall routine for the SyncData tables

**2.0.31** - 2013-11-01

* added UTF-8 compatibility and force settings to the `syncdata.json`

**2.0.30** - 2013-10-25

* SystemCheck: set always a default time zone (Europe/Berlin)
* SystemCheck: add missing support for BlackCat CMS
* disable checksum check for restored tables (seems sometimes to differ out of reason) 

**2.0.29** - 2013-10-20

* changed definition for `CMS_ADMIN_PATH` and `CMS_ADMIN_URL` (could fail)
* fixed: using `addError()` instead of `addInfo()` in `SynchronizeClient.php`
* updated to ConfirmationLog 0.20
* added function `parseFileForConstants()` to `$app['utils']`
* if a `INSTALLATION_NAME` exists, the logger will now use it for better identify

**2.0.28** - 2013-10-10

* added missing INSTALLATION_NAME definition
* updated to ConfirmationLog 0.19 with extended report functions

**2.0.27** - 2013-10-09

* updated to ConfirmationLog 0.18
* added: report filter for usergroup/persons
* fixed: droplet `[[syncdata_confirmation]]` used displayname instead of username 

**2.0.26** - 2013-10-07

* updated to ConfirmationLog 0.17
* fixed: compatibility problem with old droplet code `[[confirmation_log]]`
* fixed: problem to detect the correct URL of a NEWS article
* fixed: access to undefined indexes while adding new records in SYNC mode
* added: SYNC copy now new archives directly to /outbox for further processing

**2.0.25** - 2013-10-03

* updated to ConfirmationLog 0.16
* added submission of pending confirmations

**2.0.24** - 2013-10-02

* if the old droplet `[[confirmation_log]]` exists, rewrite it to the new code, so it can be also used (compatibility) with SyncData
* added JSONFormat() to `$app['utils']`
* updated to ConfirmationLog 0.14 - introduce reports and add droplet `[[syncdata_confirmation_report]]`

**2.0.23** - 2013-10-01

* updated to ConfirmationLog 0.13

**2.0.22** - 2013-09-30

* fixed: SyncData initialized the setup in wrong order

**2.0.21** - 2013-09-29

* added admin-tool for viewing and checking the confirmations

**2.0.20** - 2013-09-27

* added confirmation log and Droplet `[[syncdata_confirmation]]` for the CMS

**2.0.19** - 2013-09-12

* fixed a problem with creation of synchronized archives, the checksum validation fails and a string comparison used the wrong parameter

**2.0.18** - 2013-09-05

* introduce configuration key `['tables']['ignore']['sub_prefix']` to ignore complete table groups, i.e. `syncdata_` will ignore all tables beginning with `syncdata_`.
* fixed some smaller typos

**2.0.17** - 2013-09-04

* first beta release of SyncData 2.x
