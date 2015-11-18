# latch-plugin-mediawiki

##LATCH INSTALLATION GUIDE FOR MEDIAWIKI##

####PREREQUISITES####

PHP version 5.3.2 or later.

Mediawiki version 1.25.1 or later.

To get the "Application ID" and "Secret", (fundamental values for integrating Latch in any application), it’s necessary to register a developer account in Latch's website. On the upper right side, click on "Developer area".

####DOWNLOADING THE MEDIAWIKI PLUGIN####

When the account is activated, the user will be able to create applications with Latch and access to developer documentation, including existing SDKs and plugins. The user has to access again to Developer area, and browse his applications from "My applications" section in the side menu.

When creating an application, two fundamental fields are shown: "Application ID" and "Secret", keep these for later use. There are some additional parameters to be chosen, as the application icon (that will be shown in Latch) and whether the application will support OTP (One Time Password) or not.

From the side menu in developers area, the user can access the "Documentation & SDKs" section. Inside it, there is a "SDKs and Plugins" menu. Links to different SDKs in different programming languages and plugins developed so far, are shown.

Also you can download the plugin by getting the executable from our GitHub repository inside Releases section.

####INSTALLING THE PLUGIN IN MEDIAWIKI####

Once the administrator has downloaded the plugin, it has to be added as a plugin adding these lines at the bottom of LocalSettings.php:
~~~
	require_once "extensions/Latch/LatchConf.php";
    require_once "extensions/Latch/LatchOTP.php";
	# Crate Latch DB tables 
	$wgHooks['LoadExtensionSchemaUpdates'][] = 'SpecialLatch::fnMyHook';
	# Adds a link in the user's preferences menu
	$wgHooks['GetPreferences'][] = 'SpecialLatch::wfPrefHook';
	# Login hook that checks Latch status
	$wgHooks['PostLoginRedirect'][] = 'SpecialLatch::wfLoginHook';
~~~
Now the administrator has to go to the URL:

your_host/mediawiki/mw-config/

and configure the installation.

Now go to "Special Pages" and click on "LatchConf", introduce the "Application ID" and "Secret" previously obtained and save the changes.

From now on, on user's preferences, a new section about Latch will appear. Tokens generated in the app should be introduced there.

####UNINSTALLING THE PLUGIN IN MEDIAWIKI####

To remove the plugin, the administrator has to remove the lines previously inserted on LocalSettings.php and go again to the URL:

your_host/mediawiki/mw-config/

and configure the uninstallation.

####USE OF LATCH MODULE FOR THE USERS####

Latch does not affect in any case or in any way the usual operations with an account. It just allows or denies actions over it, acting as an independent extra layer of security that, once removed or without effect, will have no effect over the accounts, that will remain with its original state.

#####Pairing a user in Mediawiki#####

The user needs the Latch application installed on the phone, and follow these steps:

1. Log in your own Mediawiki account and go to the Latch section in your "Preferences" menu.

2. From the Latch app on the phone, the user has to generate the token, pressing on "Add a new service" at the bottom of the application, and pressing "Generate new code" will take the user to a new screen where the pairing code will be displayed.

3. The user has to type the characters generated on the phone into the "Enter your Latch Token" text box displayed on the web page. Click on "Pair Latch" button.

4. Now the user may lock and unlock the account, preventing any unauthorized access.

#####Unpairing a user in Mediawiki#####

The user should access their Mediawiki account and click the "Unpair Latch" button. He will receive a notification indicating that the service has been unpaired.

#####OTP (One-Time Password):#####

In case the user wants to have a second factor of authorization, it's possible to configure it by clicking Mediawiki in the Latch app and enabling the OTP.
If the user does that, the next time he will try to login, a new page will be shown asking for the OTP that the Latch App will show in the user's phone.
The user must remember that if the OTP option is enabled, only three attempts are possible to enter it properly.

####TROUBLESHOOTING####


Latch plugin for MediaWiki
