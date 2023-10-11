<?php

namespace XenForoAuth;

use MediaWiki\MediaWikiServices;

class Hooks {
	/**
	 * Creates the user_xenforo_user DB table when the system administrator re-runs
	 * MediaWiki's core updater script, update.php, UNLESS we're on a shared DB setup.
	 *
	 * @param \DatabaseUpdater $updater
	 * @return void
	 */
	public static function onLoadExtensionSchemaUpdates( \DatabaseUpdater $updater ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		// Don't create tables on a shared database
		$sharedDB = $config->get( 'SharedDB' );
		if (
			!empty( $sharedDB ) &&
			$sharedDB !== $config->get( 'DBname' )
		) {
			return;
		}

		$updater->addExtensionTable( 'user_xenforo_user', __DIR__ . '/sql/user_xenforo_user.sql' );
	}

	/**
	 * AuthChangeFormFields hook handler. Give the "Login with XenForo" button a larger
	 * weight as the LocalPasswordAuthentication Log in button.
	 *
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public static function onAuthChangeFormFields( array $requests, array $fieldInfo,
		array &$formDescriptor, $action
	) {
		if ( isset( $formDescriptor['xenforoauth'] ) ) {
			$formDescriptor['xenforoauth'] = array_merge( $formDescriptor['xenforoauth'],
				[
					'weight' => 101,
					'flags' => [],
					'class' => HTMLXenForoButtonField::class
				]
			);
			unset( $formDescriptor['xenforoauth']['type'] );
		}
	}
}
