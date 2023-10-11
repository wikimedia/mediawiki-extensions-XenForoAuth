<?php
/**
 * XenForoUserInfoAuthenticationRequest implementation
 */

namespace XenForoAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use XenForoAuth\XenForoUser;

/**
 * An AUthenticationRequest that holds XenForo user information.
 */
class XenForoUserInfoAuthenticationRequest extends AuthenticationRequest {
	public $required = self::OPTIONAL;

	/** @var array An array of infos (provided from XenForo) about a user. */
	public $userInfo;

	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
	}

	public function getFieldInfo() {
		return [];
	}

	public function describeCredentials() {
		$xenForoUser = XenForoUser::newFromUserInfo( $this->userInfo );
		return [
			'provider' => wfMessage( 'xenforoauth-auth-service-name' ),
			'account' =>
				$xenForoUser ? new \RawMessage( '$1', [ $xenForoUser->getFullNameWithId() ] ) :
					wfMessage( 'xenforoauth-auth-service-unknown-account' )
		];
	}
}
