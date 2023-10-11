<?php
/**
 * XenForoRemoveAuthenticationRequest implementation
 */

namespace XenForoAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use XenForoAuth\XenForoUser;

/**
 * Implementation of an AuthenticationReuqest that is used to remove a
 * connection between a XenForo account and a local wiki account.
 */
class XenForoRemoveAuthenticationRequest extends AuthenticationRequest {
	private $xenForoUserId = null;

	public function __construct( $xenForoUserId ) {
		$this->xenForoUserId = $xenForoUserId;
	}

	public function getUniqueId() {
		return parent::getUniqueId() . ':' . $this->xenForoUserId;
	}

	public function getFieldInfo() {
		return [];
	}

	/**
	 * Returns the XenForo ID, that should be removed from the valid
	 * credentials of the user.
	 *
	 * @return string
	 */
	public function getXenForoUserId() {
		return $this->xenForoUserId;
	}

	public function describeCredentials() {
		$xfUser = XenForoUser::newFromXFUserId( (int)$this->xenForoUserId );
		return [
			'provider' => wfMessage( 'xenforoauth-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $xfUser->getFullNameWithId() ] ),
		];
	}
}
