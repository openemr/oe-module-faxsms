# module-faxsms
Fax and SMS feature for OpenEMR

Twilio has dropped support for the Fax Api so currently this modules fax feature is only supported using the Ring Central vendor.
We are seeking sponsors to add the Documo vendor or another repacement vendore. Twilios SMS still works well if massaging is all you need.

### Install
- v5.0.2: `composer require "openemr/oe-module-faxsms:1.2.0"`
- v6.0.0 `composer require "openemr/oe-module-faxsms:2.2.0-beta"`
- v6.0.0(1) `composer require "openemr/oe-module-faxsms:2.2.0"`
### Install Notes
For RingCentral installs you will be required to provided a `OAuth Redirect URI` for Login. 
Use or change to reflect your instance pathing: https://{your domain}/interface/modules/custom_modules/oe-module-faxsms/rcauth.php?site={site id}
The {site id} default is `default` or simply leave the query off the URI.
You can have more than one OAuth Redirect URI depending how many sites you wish to have using the same RC account.

RingCentral account setup needs to have these minimum settings.
- Available Auth Flows `Auth Code` and `Refresh token` selected when sandbox app is created
- Must have `Faxes`, `SMS` and `Read Call Log` for App Permissions
- Then Username, Password, SMS Number, Client ID and Client Secret of course.
- Don't forget to select `Production Check` status in you fax/sms account setup. Check it when you go into production with RC or unchecked for development sandbox account.

