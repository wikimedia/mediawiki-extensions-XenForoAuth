<?php
/**
 * XenForoPrimaryAuthenticationProvider implementation
 */

namespace XenForoAuth\Auth;

use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;

use MediaWiki\MediaWikiServices;
use StatusValue;
use User;
use XenForoAuth\XenForoUser;
use XenForoBDClient\Scopes;
use XenForoBDClient\Clients\OAuth2Client;

/**
 * Implements a primary authentication provider to authenticate an user using a XenForo forum
 * account where this user has access to. On beginning of the authentication, the provider
 * maybe redirects the user to an external authentication provider (a XenForo forum) to
 * authenticate and permit the access to the data of the foreign account, before it actually
 * authenticates the user.
 */
class XenForoPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {
	/** Name of the button of the GoogleAuthenticationRequest */
	const XENFORO_BUTTONREQUEST_NAME = 'xenforoauth';

	/**
	 * @var null|XenForoUserInfoAuthenticationRequest
	 */
	private $autoCreateLinkRequest;

	public function beginPrimaryAuthentication( array $reqs ) {
		return $this->beginXenForoAuthentication( $reqs, self::XENFORO_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			XenForoServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'xenforoauth-error-no-authentication-workflow' )
			);
		}
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'xenforoauth' );
		$xfUser = $this->getAuthenticatedXFUserFromRequest( $request );
		if ( $xfUser instanceof AuthenticationResponse ) {
			return $xfUser;
		}

		try {
			$userInfo = $xfUser->get( 'me' );
			$connectedUser = XenForoUser::getUserFromXFUserId( $userInfo['user']['user_id'] );
			$mwUser = User::newFromName( $userInfo['user']['username'] );
			if ( $connectedUser ) {
				return AuthenticationResponse::newPass( $connectedUser->getName() );
			} elseif ( $config->get( 'XenForoAuthAutoCreate' ) && $mwUser->isAnon() ) {
				$this->autoCreateLinkRequest =
					new XenForoUserInfoAuthenticationRequest( $userInfo['user'] );

				return AuthenticationResponse::newPass( $mwUser->getName() );
			} elseif ( $config->get( 'XenForoAuthAutoCreate' ) && !$mwUser->isAnon() ) {
				// in this case, XenForoAuth is configured to autocreate accounts, however, the
				// account with the username of the xenforo board is already registered, but not
				// connected with this xenforo account. AuthManager would already give a warning
				// like "The account is not associated with any wiki account", however, as
				// XenForoAuth is configured to autocreate accounts this is not enough
				// information for most of the users reading that (and expecting their account to
				// be autocreated). That's why we throw another error here with some more
				// information and a help link.
				return AuthenticationResponse::newFail(
					wfMessage(
						'xenforoauth-local-exists',
						$mwUser->getName()
					)
				);
			} else {
				$resp = AuthenticationResponse::newPass( null );
				$resp->linkRequest = new XenForoUserInfoAuthenticationRequest( $userInfo['user'] );
				$resp->createRequest = $resp->linkRequest;
				return $resp;
			}
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'xenforoauth-generic-error', $e->getMessage() )
			);
		}
	}

	public function autoCreatedAccount( $user, $source ) {
		if (
			$this->autoCreateLinkRequest !== null &&
			isset( $this->autoCreateLinkRequest->userInfo['user_id'] )
		) {
			XenForoUser::connectWithXenForoUser( $user,
				$this->autoCreateLinkRequest->userInfo['user_id'] );
			if ( isset( $this->autoCreateLinkRequest->userInfo['user_email'] ) ) {
				$user->setEmailWithConfirmation( $this->autoCreateLinkRequest->userInfo['user_email'] );
				$user->saveSettings();
			}
		}
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [ new XenForoAuthenticationRequest(
					wfMessage( 'xenforoauth' ),
					wfMessage( 'xenforoauth-loginbutton-help' )
				) ];
				break;
			case AuthManager::ACTION_LINK:
				// TODO: Probably not the best message currently.
				return [ new XenForoAuthenticationRequest(
					wfMessage( 'xenforoauth-form-merge' ),
					wfMessage( 'xenforoauth-link-help' )
				) ];
				break;
			case AuthManager::ACTION_REMOVE:
				$user = User::newFromName( $options['username'] );
				if ( !$user || !XenForoUser::hasConnectedXFUserAccount( $user ) ) {
					return [];
				}
				$xfUserId = XenForoUser::getXFUserIdFromUser( $user );
				return [ new XenForoRemoveAuthenticationRequest( $xfUserId ) ];
				break;
			case AuthManager::ACTION_CREATE:
				// TODO: ACTION_CREATE doesn't really need all
				// the things provided by inheriting
				// ButtonAuthenticationRequest, so probably it's better
				// to create it's own Request
				return [ new XenForoAuthenticationRequest(
					wfMessage( 'xenforoauth-create' ),
					wfMessage( 'xenforoauth-link-help' )
				) ];
				break;
			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function testUserCanAuthenticate( $username ) {
		$user = User::newFromName( $username );
		if ( $user ) {
			return XenForoUser::hasConnectedXFUserAccount( $user );
		}
		return false;
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		if (
			get_class( $req ) === XenForoRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE
		) {
			$user = User::newFromName( $req->username );
			if (
				$user &&
				$req->getXenForoUserId() === XenForoUser::getXFUserIdFromUser( $user )
			) {
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( wfMessage( 'xenforoauth-change-account-not-linked' ) );
			}
		}
		return StatusValue::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		if (
			get_class( $req ) === XenForoRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE
		) {
			$user = User::newFromName( $req->username );
			XenForoUser::terminateXFUserConnection( $user, $req->getXenForoUserId() );
		}
	}

	public function providerNormalizeUsername( $username ) {
		return null;
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			XenForoUserInfoAuthenticationRequest::class );
		if ( $request ) {
			if ( XenForoUser::isXFUserIdFree( $request->userInfo['user_id'] ) ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = $request;
				return $resp;
			}
		}
		return $this->beginXenForoAuthentication( $reqs, self::XENFORO_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			XenForoServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'xenforoauth-error-no-authentication-workflow' )
			);
		}
		$xfUser = $this->getAuthenticatedXFUserFromRequest( $request );
		if ( $xfUser instanceof AuthenticationResponse ) {
			return $xfUser;
		}
		try {
			$userInfo = $xfUser->get( 'me' );
			$isXFUserIdFree = XenForoUser::isXFUserIdFree( $userInfo['user']['user_id'] );
			if ( $isXFUserIdFree ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = new XenForoUserInfoAuthenticationRequest( $userInfo );
				return $resp;
			}
			return AuthenticationResponse::newFail( wfMessage( 'xenforoauth-link-other' ) );
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'xenforoauth-generic-error', $e->getMessage() )
			);
		}
	}

	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$userInfo = $response->linkRequest->userInfo;
		$user->setEmail( $userInfo['user']['user_email'] );
		$user->saveSettings();
		XenForoUser::connectWithXenForoUser( $user, $userInfo['user']['user_id'] );

		return null;
	}

	public function beginPrimaryAccountLink( $user, array $reqs ) {
		return $this->beginXenForoAuthentication( $reqs, self::XENFORO_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountLink( $user, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			XenForoServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'xenforoauth-error-no-authentication-workflow' )
			);
		}
		$xfUser = $this->getAuthenticatedXFUserFromRequest( $request );
		if ( $xfUser instanceof AuthenticationResponse ) {
			return $xfUser;
		}
		try {
			$userInfo = $xfUser->get( 'me' );
			$xfUserId = $userInfo['user']['user_id'];
			$potentialUser = XenForoUser::getUserFromXFUserId( $xfUserId );
			if ( $potentialUser && !$potentialUser->equals( $user ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'xenforoauth-link-other' ) );
			} elseif ( $potentialUser ) {
				return AuthenticationResponse::newFail( wfMessage( 'xenforoauth-link-same' ) );
			} else {
				$result = XenForoUser::connectWithXenForoUser( $user, $xfUserId );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		} catch ( \Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'xenforoauth-generic-error', $e->getMessage() )
			);
		}
	}

	/**
	 * Handler for a primary authentication, which currently begins. Checks, if the Authentication
	 * request can be handled by XenForoAuth and, if so, returns an AuthenticationResponse that
	 * redirects to the external authentication site, otherwise returns an abstain response.
	 * @param array $reqs
	 * @param $buttonAuthenticationRequestName
	 * @return AuthenticationResponse
	 */
	private function beginXenForoAuthentication( array $reqs, $buttonAuthenticationRequestName ) {
		$req = XenForoAuthenticationRequest::getRequestByName( $reqs,
			$buttonAuthenticationRequestName );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$client = $this->getXenForoClient( $req->returnToUrl );

		return AuthenticationResponse::newRedirect( [
			new XenForoServerAuthenticationRequest()
		], $client->getAuthenticationRequestUrl() );
	}

	/**
	 * Returns an instance of OAuth2Client, which is set up for the use in an authentication
	 * workflow.
	 *
	 * @param string $returnUrl
	 * @return OAuth2Client
	 */
	public function getXenForoClient( $returnUrl ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'xenforoauth' );
		$client = new OAuth2Client();
		$client->setBaseUrl( $config->get( 'XenForoAuthBaseUrl' ) )
			->setClientId( $config->get( 'XenForoAuthClientId' ) )
			->setClientSecret( $config->get( 'XenForoAuthClientSecret' ) )
			->addScope( Scopes::READ )
			->setRedirectUri( $returnUrl );

		return $client;
	}

	/**
	 * Returns an authenticated \XenForoBDClient\Users\User object.
	 *
	 * @param $request
	 * @return \XenForoBDClient\Users\User|AuthenticationResponse
	 */
	private function getAuthenticatedXFUserFromRequest( XenForoServerAuthenticationRequest
		$request
	) {
		if ( !$request->accessToken || $request->errorCode ) {
			switch ( $request->errorCode ) {
				case 'access_denied':
					return AuthenticationResponse::newFail( wfMessage( 'xenforoauth-access-denied'
						) );
					break;
				default:
					return AuthenticationResponse::newFail( wfMessage(
						'xenforoauth-generic-error', $request->errorCode ? $request->errorCode :
						'unknown' ) );
			}
		}
		$client = $this->getXenForoClient( $request->returnToUrl );
		$client->authenticate( $request->accessToken );
		$user = new \XenForoBDClient\Users\User( $client );

		return $user;
	}
}
