<?php
	
# Alert the user that this is not a valid entry point to MediaWiki
if (!defined('MEDIAWIKI')) { exit( 1 ); }

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'LatchConf',
	'author' => 'Eleven Paths',
	'version' => '1.0',
	'descriptionmsg' => 'latch-desc',
);
	
$dir = dirname(__FILE__) . '/';

# Location of the SpecialLatch class (Tell MediaWiki to load this file)
$wgAutoloadClasses['SpecialLatch'] = $dir . 'SpecialLatch.php';

# Location of localisation files (Tell MediaWiki to load them)
$wgMessagesDirs['LatchConf'] = __DIR__ . "/i18n";

# Location of an aliases file (Tell MediaWiki to load it)
$wgExtensionMessagesFiles['LatchAlias'] = $dir . 'Latch.alias.php';

# Tell MediaWiki about the new special page and its class name
$wgSpecialPages['LatchConf'] = 'SpecialLatch'; 
$wgSpecialPages['LatchOTP'] = 'SpecialLatchOTP'; 