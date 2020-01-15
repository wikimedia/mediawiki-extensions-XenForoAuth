<?php
/**
 * XenForoUserInfoAuthenticationRequest implementation
 */

namespace XenForoAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use XenForoAuth\XenForoUser;

/**
 * An AUthenticationRequest that holds Google user information.
 */
class XenForoUserInfoAuthenticationRequest extends AuthenticationRequest {
	public $required = self::OPTIONAL;
	/** @var array An array of infos (provided from XenForo)
	 * about an user.
	 */
	public $userInfo;

	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
	}

	public function getFieldInfo() {
		return [];
	}

	public function describeCredentials() {
		$googleUser = XenForoUser::newFromUserInfo( $this->userInfo );
		return [
			'provider' => wfMessage( 'xenforoauth-auth-service-name' ),
			'account' =>
				$googleUser ? new \RawMessage( '$1', [ $googleUser->getFullNameWithId() ] ) :
					wfMessage( 'xenforoauth-auth-service-unknown-account' )
		];
	}
}
