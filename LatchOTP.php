<?php

class SpecialLatchOTP extends SpecialPage {
	function __construct() { parent::__construct( 'LatchOTP', 'editinterface'); } # Adding editinterface allows only admins to see Latch in Special pages.
	
	# Main function
	function execute( $par ) {	
		global $wgRequest, $wgUser;
		$two_factor_token = "";
		# We take user name and user id from the current session
		$user_id = $wgRequest->getSessionData( 'wsUserID' );
		$user_name = $wgUser->whoIs($user_id);
		# We draw the form and get ready for 
		$this->draw_OTP();
		$this->submit_OTP($user_id, $user_name);
	}
	
	# Showing a form to input the OTP
	function draw_OTP () {
		global $wgOut, $wgUser; 
		$formu = Xml::openElement('form', array( 'method' => 'post', 'action' => $this->getTitle()->getLocalUrl( 'action=submit' )));
		$formu .= Xml::inputLabel( wfMsg( 'latch-OTP' ). ' ', 'txt_OTP', 'txt_OTP', 20) . '<BR>&nbsp';
		$formu .= Xml::submitButton( wfMsg( 'latch-enter' ), array( 'name' => 'clickBotOTP' ) ) . '<BR>';	
		# Adding protection against CSRF
		$formu .= Html::hidden('token', $wgUser->getEditToken(), array( 'id' => 'token' ));
		$formu .= Xml::closeElement( 'form' );		
		$wgOut->addHTML($formu);	
	}
	
	# Function to manage the OTP login
	function submit_OTP($user_id, $user_name) {
		global $wgRequest, $wgOut, $wgUser;
		$otp_DB = "";
		$attempts = 0;
		# When OTP button is pressed we we check if the OTP is set on DB.
		if ( $wgRequest->getCheck( 'clickBotOTP') ) {
			SpecialLatch::accDB_useraccid ($user_id, $user_id, $acc_id, $otp_DB, $attempts);
			# CSRF protection
			if (!$wgUser->matchEditToken($wgRequest->getVal('token'))) {
				return;
			}
			else {
				# If it's correct we set again the correct user name to session and redirect to the main page
				if ($otp_DB == $wgRequest->getText('txt_OTP')) {
					$wgRequest->setSessionData( 'wsUserName', $user_name );
					$wgOut->redirect('/mediawiki/index.php/Main_Page');
				}
				# updates the DB if the attempts are lower than 0 and show a warning message
				else if ( $attempts < 2) {
					SpecialLatch::updDB_useraccid ($user_id, $acc_id, $otp_DB, $attempts+1);
					$wgOut->addWikiText(wfMsg( 'latch-OTP-error'));
				}
				# if the user puts 3 times the incorrect otp, we logout and show an invalid password error
				else{
					$wgUser->logout();
					$wgOut->clearHTML();
					$specialUserlogin = new LoginForm();
					$specialUserlogin->load();
					$error = $specialUserlogin->mAbortLoginErrorMsg ?: 'wrongpassword';
					$specialUserlogin->mainLoginForm( $specialUserlogin->msg( $error )->text() );
				}
			}
		}
	}
}