<?php
/**
 * XenForoAuthenticationRequest implementation
 */

namespace XenForoAuth\Auth;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;

/**
 * Implements a XenForoAuthenticationRequest by extending a ButtonAuthenticationRequest
 * and describes the credentials used/needed by this AuthenticationRequest.
 */
class XenForoAuthenticationRequest extends ButtonAuthenticationRequest {
	public function __construct( \Message $label, \Message $help ) {

		parent::__construct(
			XenForoPrimaryAuthenticationProvider::XENFORO_BUTTONREQUEST_NAME,
			$label,
			$help,
			true
		);
	}

	public function getFieldInfo() {
		if ( $this->action === AuthManager::ACTION_REMOVE ) {
			return [];
		}
		return parent::getFieldInfo();
	}
}
