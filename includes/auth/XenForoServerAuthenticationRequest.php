<?php
/**
 * XenForoServerAuthenticationRequest implementation
 */

namespace XenForoAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;

/**
 * Implements a XenForoServerAuthenticationRequest that holds the data returned by a
 * redirect from XenForo into the authentication workflow.
 */
class XenForoServerAuthenticationRequest extends AuthenticationRequest {
	/**
	 * Verification code provided by the server. Needs to be sent back in the last leg of the
	 * authorization process.
	 * @var string
	 */
	public $accessToken;

	/**
	 * An error code returned in case of Authentication failure
	 * @var string
	 */
	public $errorCode;

	public function getFieldInfo() {
		return [
			'error' => [
				'type' => 'string',
				'label' => wfMessage( 'xenforoauth-param-error-label' ),
				'help' => wfMessage( 'xenforoauth-param-error-help' ),
				'optional' => true,
			],
			'code' => [
				'type' => 'string',
				'label' => wfMessage( 'xenforoauth-param-code-label' ),
				'help' => wfMessage( 'xenforoauth-param-code-help' ),
				'optional' => true,
			],
		];
	}

	/**
	 * Load data from query parameters in an OAuth return URL
	 *
	 * @param array $data Submitted data as an associative array
	 * @return bool
	 */
	public function loadFromSubmission( array $data ) {
		if ( isset( $data['code'] ) ) {
			$this->accessToken = $data['code'];
			return true;
		}

		if ( isset( $data['error'] ) ) {
			$this->errorCode = $data['error'];
			return true;
		}

		return false;
	}
}
