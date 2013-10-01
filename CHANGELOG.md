## SyncData ##

(c) 2011, 2013 phpManufaktur by Ralf Hertsch<br/>
MIT License (MIT) - <http://www.opensource.org/licenses/MIT>

**2.0.23** - 2013-10-01

* updated to ConfirmationLog 0.13

**2.0.22** - 2013-09-30

* fixed: SyncData initialized the setup in wrong order

**2.0.21** - 2013-09-29

* added admin-tool for viewing and checking the confirmations

**2.0.20** - 2013-09-27

* added confirmation log and Droplet `syncdata_confirmation` for the CMS

**2.0.19** - 2013-09-12

* fixed a problem with create synchronize archives, the checksum validation fails and a string comparison used the wrong parameter

**2.0.18** - 2013-09-05

* introduce configuration key `['tables']['ignore']['sub_prefix']` to ignore complete table groups, i.e. `syncdata_` will ignore all tables beginning with `syncdata_`.
* fixed some smaller typos

**2.0.17** - 2013-09-04

* first beta release of SyncData 2.x
