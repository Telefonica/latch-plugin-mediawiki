<?php

# Including Latch SDK
require_once("latch/Latch.php");
require_once("latch/LatchResponse.php");
require_once("latch/Error.php");

class SpecialLatch extends SpecialPage {
	function __construct() { parent::__construct( 'LatchConf', 'editinterface' ); } # Adding editinterface allows only admins to see Latch in Special pages.
	
	################################################################################################################################################
	#########################################################   DATABASE FUNCTIONS #################################################################
	################################################################################################################################################
	
	/* This function will be called when an update is made (mandatory for the installation of this plugin).
	   It will create the tables that we are going to use in our plugin. */
	function fnMyHook( DatabaseUpdater $updater ) {
		if ( is_null( $updater ) ) {
			throw new MWException( "Latch extension requires Mediawiki 1.18 or above" );
		}
		$type = $updater->getDB()->getType();
		if ( $type == "mysql" ) {
			$dir = __DIR__ ;
			$updater->addExtensionTable('latch_conf', $dir . '/create_table_latchConf.sql');
			$updater->addExtensionTable('latch', $dir . '/create_table_latch.sql');
		}
		return true;
	}
	
	# Function to get user_id and account_id from DB. Notice that there will be only one row for each user.
	function accDB_useraccid ($user, &$user_id, &$acc_id) {
		$dbr = wfGetDB( DB_SLAVE );
		$dbr->begin();
		$res=$dbr->select('latch',
		array( 'user_id', 'acc_id' ),
		array ('user_id' => $user->getId()));
		foreach( $res as $row ) {
			$user_id = $row->user_id;	
			$acc_id = $row->acc_id;
		}
	}
	
	# Function to insert user_id and account_id in DB.
	function insDB_useraccid ($user, $accountId) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->insert('latch', array( 'user_id' => $user->getId() , 'acc_id' => $accountId));
		$dbw->commit();
	}
	
	# Function to delete user_id and account_id of the DB.
	function delDB_useraccid ($user) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->delete('latch', array( 'user_id' => $user->getId()));
		$dbw->commit();
	}
		
	# Function to get app_id and secret from DB. Notice that there will be only one row on that table.
	function accDB_appsecret (&$app_id, &$secret) {
		$dbr = wfGetDB( DB_SLAVE );
		$dbr->begin();
		$res=$dbr->select('latch_conf',
		array( 'app_id', 'secret' ));
		foreach( $res as $row ) {
			$app_id = $row->app_id;	
			$secret = $row->secret;
		}			
	}
	
	# Function to insert app_id and secret in DB.
	function insDB_appsecret ($par_appid, $par_secret) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->insert('latch_conf', array( 'app_id' => $par_appid , 'secret' => $par_secret));
		$dbw->commit();
	}
	
	# Function to update app_id and secret in DB.
	function updDB_appsecret ($app_id, $secret, $par_appid, $par_secret) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->update('latch_conf', #Table
					array( 'app_id' => $par_appid , 'secret' => $par_secret),	# Set
					array( 'app_id' => $app_id, 'secret' => $secret)  # Where
					);
		$dbw->commit();
	}
	
	################################################################################################################################################
	#########################################################   INTERFACE FUNCTIONS ################################################################
	################################################################################################################################################
	
	# Function to print a form in order to show the app_id/secret or ask for them
	function draw_cnfig ($app_id, $secret, $wgRequest, $msg) {
		global $wgOut, $wgUser;
		
		$form = Xml::openElement(
			'form', array( 'method' => 'post',
			'action' => $this->getTitle()->getLocalUrl( 'action=submit' ) ) );
		$form .= Xml::inputLabel( wfMsg( 'latch-app_id' ) , 'txt_appid', 'txt_appid', 20, $app_id ) . '<BR><BR>&nbsp';
		$form .= Xml::inputLabel( wfMsg( 'latch-secret' ) , 'txt_secret', 'txt_secret', 40, $secret ) . '<BR><BR>';
		$form .= Xml::submitButton( wfMsg( 'latch-enter' ),
									array( 'name' => 'clickBotEnviar' ) );					
		
		# Adding protection against CSRF
		$form .= Html::hidden('token', $this->getUser()->getEditToken(), array( 'id' => 'token' ));
		$form .= Xml::closeElement( 'form' );
		$wgOut->addHTML($form);	
		
		# Print a confirmation box
		if (!empty($msg)) 
			$wgOut->addWikiText($msg);
	}
	
	# Function used to print the modified user preferences
	function drawUserPreferences ($acc_id, $user, &$preferences) {
		# If the user doesn't have already an account_id we ask for it
		if (empty($acc_id)) {
			# A textBox to ask for the Latch token
			$preferences['latchTok'] = array(
				'type' => 'text',
				'label' => wfMsg('latch-token'),
				'section' => 'personal/info',
				'name' => 'latchTok',
				'maxlength' => 6,
				'size' => 5
			);
			# A submit button to insert the Latch token
			$preferences['latchTokBot'] = array(
				'type' => 'submit',
				'default' => wfMsg('latchpair'),
				'section' => 'personal/info',
				'name' => 'latchTokBot',
				'maxlength' => 6,
				'size' => 5,
				'token' => $user->getEditToken()
			);		
		}
		# If the user has an account_id we only show him the option to unpair
		else {
			$preferences['latchUnpair'] = array(
				'type' => 'submit',
				'label' => wfMsg('latchunpair').':',
				'default' => wfMsg('latchunpair'),
				'section' => 'personal/info',
				'name' => 'latchUnpair',
				'maxlength' => 6,
				'size' => 5,
				'token' => $user->getEditToken()
			);	
		}
		# Additional field for CSRF protection
		$preferences['hiddToken'] = array(
			'type' => 'hidden',
			'default' => $user->getEditToken(),
			'section' => 'personal/info',
			'name' => 'hiddToken',
			'maxlength' => 0,
			'size' => 0
		);
	}
	
	################################################################################################################################################
	########################################################   MAIN HOOK FUNCTIONS #################################################################
	################################################################################################################################################
	
	
	# Main function to configure Latch (only for admins)
	function execute( $par ) {	
		# Limit access to non-admin users via url
		if (!$this->userCanExecute($this->getUser())) {
			$this->displayRestrictionError();
			return;
		}
		global $wgRequest, $wgUser;
		$app_id = "";
		$secret = "";
		$msg = "";
		
		# If app_id and secret are already in the DB, we take them
		$this->accDB_appsecret($app_id, $secret);				

		# Get request data 
		if ( $wgRequest->getCheck( 'clickBotEnviar') ) {
			# CSRF protection
			if (!$wgUser->matchEditToken($wgRequest->getVal('token'))) {
				return;
			}
			else {
				$par_appid = $wgRequest->getText( 'txt_appid' );
				$par_secret = $wgRequest->getText( 'txt_secret' );
				# App_id or secret can't be null or have extrange characters
				if ((empty($par_appid)) || (ereg('[[:punct:]]', $par_appid))) $msg = wfMsg('latch-error-appid');
				else if ((empty($par_secret)) || (ereg('[[:punct:]]', $par_secret))) $msg = wfMsg('latch-error-secret');
				else {
					# If app_id/secret weren't in the DB and we insert them 
					if (empty($app_id) and empty($secret)) 
						$this->insDB_appsecret($par_appid,$par_secret);
					# Otherwise we update those values
					else 
						$this->updDB_appsecret($app_id, $secret, $par_appid,$par_secret);
					# A message of the successful changes is printed
					$msg = wfMsg( 'latch-parameter1-entered');
				}
			}
			$this->draw_cnfig($par_appid, $par_secret, $wgRequest, $msg);
		}
		# If the user didn't press any button, we print the form anyway
		else
			$this->draw_cnfig($app_id, $secret, $wgRequest, $msg);		
	}
	
	# Function to include Latch in user's preferences page
	function wfPrefHook($user, &$preferences) {
		global $wgUser, $wgRequest, $wgOut;
		$user_id = "";
		$acc_id = "";
		$msg = "";
		$app_id = "";
		$secret = "";
		$pairResponse=null;
		$hola = true;			
					
		# If app_id, secret, user_id and the account_id are already in the DB, we take them
		SpecialLatch::accDB_appsecret ($app_id, $secret);
		SpecialLatch::accDB_useraccid ($wgUser, $user_id, $acc_id);
		
		# We create a new Latch object from the Latch SDK
		$api = new Latch($app_id, $secret);
	
		# We print the Latch preferences
		SpecialLatch::drawUserPreferences($acc_id, $wgUser, $preferences);
		
		# If the Pair button is pressed, we try to pair the account
		if ( $wgRequest->getCheck( 'latchTokBot') ) {
			# CSRF protection
			if (!$wgUser->matchEditToken($wgRequest->getVal('hiddToken'))) {
				return;
			}
			else {
				$pair_token = $wgRequest->getText('latchTok');
				if ((empty($pair_token)) || (ereg('[[:punct:]]', $pair_token))) {
					$hola = false;
					$msg = wfMsg('latch-error-appid');
				}
				else {
					$pairResponse = $api->pair($pair_token);
					$responseData = $pairResponse->getData();

					if (!empty($responseData)) 
						$accountId = $responseData->{"accountId"};
					# If everything is OK, we insert the data in the DB
					if (!empty($accountId)) 
						SpecialLatch::insDB_useraccid($wgUser, $accountId);
					elseif ($pairResponse->getError() == NULL) 
						# If Account ID is empty and no error fields are found, there are problems with the connection to the server
						throw new DBExpectedError( null, 'Latch pairing error: Cannot connect to the server.');
					else 
						# Controlled errors
						throw new DBExpectedError( null, $pairResponse->getError()->getCode() . " - " . $pairResponse->getError()->getMessage());
				}
			}
		}
		# If the Unpair button is pressed, we try to unpair the account
		if ( $wgRequest->getCheck('latchUnpair')) {
			SpecialLatch::accDB_useraccid ($wgUser, $user_id, $acc_id);
			
			# CSRF protection
			if (!$wgUser->matchEditToken($wgRequest->getVal('hiddToken'))) {
				return;
			}
			else {
				$pairResponse = $api->unpair($acc_id);

				# If Account ID is empty and no error fields are found, there are problems with the connection to the server
				if ($pairResponse->getError() == NULL) 
					SpecialLatch::delDB_useraccid($wgUser);
				# Controlled errors
				else 
					throw new DBExpectedError( null, $pairResponse->getError()->getCode() . " - " . $pairResponse->getError()->getMessage());
			}
		}
		# We print the Latch preferences again to make sure that nothing strange happens
		SpecialLatch::drawUserPreferences($acc_id, $wgUser, $preferences);
		# Required return value of a hook function.
		return true;
	}	
	
	# Hook that is going to run after a successful login
	public static function wfLoginHook( &$returnTo, &$returnToQuery, &$type ) {
		global $wgUser, $wgOut;
		
		$user_id = "";
		$acc_id = "";
		$msg = "";
		$app_id = "";
		$secret = "";
		$type = 'error';
		
		# If app_id, secret, user_id and the account_id are already in the DB, we take them
		SpecialLatch::accDB_appsecret ($app_id, $secret);
		SpecialLatch::accDB_useraccid ($wgUser, $user_id, $acc_id);
		
		# If the user doesn't have Latch configured we redirect him to Main Page without checking anything
		if (!empty($user_id) && !empty($acc_id)) {
			# We call the Status function from the Latch SDK		
			$api = new Latch($app_id, $secret);
			$statusResponse = $api->status($acc_id);
			$responseData = $statusResponse->getData();
			$responseError = $statusResponse->getError();
		
			# If everything is OK and the status is on, we redirect the user to the main page
			if (!empty($responseData) && $responseData->{"operations"}->{$app_id}->{"status"} === "on") 
				$wgOut->redirect("/mediawiki/index.php/Main_Page");
			# Otherwise we logout the user and we show the same message that when a wrong password is used
			else {
				$wgUser->logout();
				$specialUserlogin = new LoginForm();
				$specialUserlogin->load();
				$specialUserlogin->mainLoginForm( $specialUserlogin->msg(wfMsg('wrongpassword')));
			}
		}
		else 
			$wgOut->redirect("/mediawiki/index.php/Main_Page");
		return true;
	}
}