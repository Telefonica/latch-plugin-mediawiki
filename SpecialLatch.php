<?php

# Including Latch SDK
require_once("SDK/Latch.php");
require_once("SDK/LatchResponse.php");
require_once("SDK/Error.php");


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
	function accDB_useraccid ($user, &$user_id, &$acc_id, &$otp = null, &$att = 0) {
		$dbr = wfGetDB( DB_SLAVE );
		$dbr->begin();
		$res=$dbr->select('latch',
		array( 'user_id', 'acc_id', 'otp', 'attempts' ),
		array ('user_id' => $user));
		foreach( $res as $row ) {
			$user_id = $row->user_id;	
			$acc_id = $row->acc_id;
			$otp = $row->otp;
			$att = $row->attempts;
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
	
	# Function to update user_id and account_id in DB.
	function updDB_useraccid ($user, $accountId, $otp = "", $attempts = 0) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->update('latch', #Table
					array( 'user_id' => $user , 'acc_id' => $accountId, 'otp' => $otp, 'attempts' => $attempts),	# Set
					array( 'user_id' => $user, 'acc_id' => $accountId)  # Where
					);
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
				'size' => 5
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
				'size' => 5
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
	
	# Function to set the user in the session and redirect to the main page
	function putUserInSession() {
		global $wgUser, $wgRequest, $wgOut;
		$wgRequest->setSessionData( 'wsUserName', $wgUser->whoIs($wgUser->getId()) );
		$wgOut->redirect('/mediawiki/index.php/Main_Page');
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
				if ((empty($par_appid)) || (ereg('[[:punct:]]', $par_appid))) {
					$msg = wfMsg('latch-error-appid');
				}
				else if ((empty($par_secret)) || (ereg('[[:punct:]]', $par_secret))) {
					$msg = wfMsg('latch-error-secret');
				} 
				else {
					# We create a new Latch object from the Latch SDK
					$api = new Latch($par_appid, $par_secret);
					
					# We try to make a call to the status function with the new values
					$statusResponse = $api->status("");
					$responseError = $statusResponse->getError();
					
					# If a 102 code is returned, then the app_id or the secret are not correct
					if ($responseError->getCode() == 102) {
						$msg = wfMsg( 'latch-error-appIdSecret');
						$this->draw_cnfig($app_id, $secret, $wgRequest, $msg);	
						return;
					}
					
					# If app_id/secret weren't in the DB and we insert them 
					if (empty($app_id) and empty($secret)) {
						$this->insDB_appsecret($par_appid,$par_secret);
					}
					# Otherwise we update those values
					else {
						$this->updDB_appsecret($app_id, $secret, $par_appid,$par_secret);
					}
					# A message of the successful changes is printed
					$msg = wfMsg( 'latch-parameter1-entered');
				}
			}
			$this->draw_cnfig($par_appid, $par_secret, $wgRequest, $msg);
		}
		# If the user didn't press any button, we print the form anyway
		else {
			$this->draw_cnfig($app_id, $secret, $wgRequest, $msg);	
		}	
	}
	
	# Function to include Latch in user's preferences page
	function wfPrefHook($user, &$preferences) {
		global $wgUser, $wgRequest, $wgOut;
		$user_id = "";
		$acc_id = "";
		$app_id = "";
		$secret = "";
		$error_msg = "";
		$pairResponse=null;
					
		# If app_id, secret, user_id and the account_id are already in the DB, we take them
		SpecialLatch::accDB_appsecret ($app_id, $secret);
		SpecialLatch::accDB_useraccid ($wgUser->getId(), $user_id, $acc_id);
		
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
				# Not empty or extrange characters
				if ((empty($pair_token)) || (ereg('[[:punct:]]', $pair_token))) {
					throw new DBExpectedError( null, wfMsg('latch-error-pair'));
				}
				else {
					$pairResponse = $api->pair($pair_token);
					$responseData = $pairResponse->getData();
					if (!empty($responseData)) {
						$accountId = $responseData->{"accountId"};
					}
					# If everything is OK, we insert the data in the DB
					if (!empty($accountId)) {
						SpecialLatch::insDB_useraccid($wgUser, $accountId);
					}
					# If Account ID is empty and no error fields are found, there are problems with the connection to the server
					elseif ($pairResponse->getError() == NULL) {
						throw new DBExpectedError( null, wfMsg('default-error-pair'));
					}
					# Controlled errors
					else {
						switch ($pairResponse->getError()->getCode()) {
						case 205:
							$error_msg = wfMsg('205-pair');
						break;
						case 206:
							$error_msg = wfMsg('206-pair');
						break;
						case 401:
							$error_msg = wfMsg('error-401');
						break;
						default:
							$error_msg = wfMsg('default-error-pair');
						break;
					}
					throw new DBExpectedError( null, $pairResponse->getError()->getCode() . " - " . $error_msg);
					}	
				}
			}
		}
		# If the Unpair button is pressed, we try to unpair the account
		if ( $wgRequest->getCheck('latchUnpair')) {
			SpecialLatch::accDB_useraccid ($wgUser->getId(), $user_id, $acc_id);
			# CSRF protection
			if (!$wgUser->matchEditToken($wgRequest->getVal('hiddToken'))) {
				return;
			}
			else {
				$pairResponse = $api->unpair($acc_id);
				# If Account ID is empty and no error fields are found, there are problems with the connection to the server
				if ($pairResponse->getError() == NULL) {
					SpecialLatch::delDB_useraccid($wgUser);
				}	
				# Controlled errors
				else {
					switch ($pairResponse->getError()->getCode()) {
						case 201:
							$error_msg = wfMsg('201-unpair');
						break;
						case 401:
							$error_msg = wfMsg('error-401');
						break;
						default:
							$error_msg = wfMsg('error-unpair');
						break;
					}
					throw new DBExpectedError( null, $pairResponse->getError()->getCode() . " - " . $error_msg);
				}
			}
		}
		# We print the Latch preferences again to make sure that nothing strange happens
		SpecialLatch::drawUserPreferences($acc_id, $wgUser, $preferences);
		# Required return value of a hook function.
		return true;
	}	
		
	# Hook that is going to run after a successful login
	public static function wfLoginHook( &$returnTo, &$returnToQuery, &$type ) {
		global $wgUser, $wgOut, $wgRequest, $wgTitle;
		$acc_id = "";
		$msg = "";
		$app_id = "";
		$secret = "";
		$type = 'error';
		$two_factor_token = "";
		$user_id = "";

		# We remove the user's name to "freeze" the session
		$wgRequest->setSessionData( 'wsUserName', "" );
	
		# If app_id, secret, user_id and the account_id are already in the DB, we take them
		SpecialLatch::accDB_appsecret ($app_id, $secret);
		SpecialLatch::accDB_useraccid ($wgUser->getId(), $user_id, $acc_id);

		# If the user doesn't have Latch configured we redirect him to Main Page without checking anything
		if (!empty($user_id) && !empty($acc_id)) {
			# We call the Status function from the Latch SDK		
			$api = new Latch($app_id, $secret);
			$statusResponse = $api->status($acc_id);
			$responseData = $statusResponse->getData();
			$responseError = $statusResponse->getError();
			
			if (empty($statusResponse) || (empty($responseData) && empty($responseError))) {
				return false;
			} 
			else {
				# If everything is OK and the status is on, we redirect the user to the main page and set the user's name again
				if (!empty($responseData) && $responseData->{"operations"}->{$app_id}->{"status"} === "on") {
					if	(!empty($responseData->{"operations"}->{$app_id}->{"two_factor"})) {
						$two_factor_token = $responseData->{"operations"}->{$app_id}->{"two_factor"}->{"token"};
						# We have another special page for the OTP page. We insert the OTP token on DB and we redirect to that page
						if (!empty($two_factor_token)) {
							SpecialLatch::updDB_useraccid ($user_id, $acc_id, $two_factor_token);
							$wgOut->redirect(
								SpecialPage::getTitleFor( 'LatchOTP' )
								->getFullURL( '', false, PROTO_CURRENT )
							);	
						}
					}
					# If the status is on and there's no two factor, we redirect to the main page and set the correct user name.
					else {
						SpecialLatch::putUserInSession();
					}
				}
				# If the status is off, we logout the user and we show the same message that when a wrong password is used
				else if (!empty($responseData) && $responseData->{"operations"}->{$app_id}->{"status"} === "off") {
					$wgUser->logout();
					$specialUserlogin = new LoginForm();
					$specialUserlogin->load();
					$error = $specialUserlogin->mAbortLoginErrorMsg ?: 'wrongpassword';
					$specialUserlogin->mainLoginForm( $specialUserlogin->msg( $error )->text() );
				}
				# Otherwise we login normally
				else {
					SpecialLatch::putUserInSession();
				}
			}
		}
		else {
			SpecialLatch::putUserInSession();
		}
		return true;
	}

}