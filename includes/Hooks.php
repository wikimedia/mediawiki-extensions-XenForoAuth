<?php

namespace XenForoAuth;

use MediaWiki\MediaWikiServices;

class Hooks {
	public static function onLoadExtensionSchemaUpdates( \DatabaseUpdater $updater = null ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		// Don't create tables on a shared database
		$sharedDB = $config->get( 'SharedDB' );
		if (
			!empty( $sharedDB ) &&
			$sharedDB !== $config->get( 'DBname' )
		) {
			return true;
		}

		// Sql directory inside the extension folder
		$sql = __DIR__ . '/sql';
		$schema = "$sql/user_xenforo_user.sql";
		$updater->addExtensionUpdate( [ 'addTable', 'user_xenforo_user', $schema, true ] );
		return true;
	}

	/**
	 * AuthChangeFormFields hook handler. Give the "Login with XenForo" button a larger
	 * weight as the LocalPasswordAuthentication Log in button.
	 *
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array $formDescriptor
	 * @param $action
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

	/**
	 * ResourceLoaderGetLessVars hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetLessVars
	 * @param array &$lessVars Variables already added
	 */
	public static function onResourceLoaderGetLessVars( &$lessVars ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'xenforoauth' );
		$lessVars = array_merge( $lessVars,
			[
				'wgXenForoAuthButtonIcon' => $config->get( 'XenForoAuthButtonIcon' )
			]
		);
	}
}
