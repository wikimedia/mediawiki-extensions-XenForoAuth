<?php
namespace XenForoAuth;

use MediaWiki\MediaWikiServices;

class XenForoResourceLoaderModule extends \ResourceLoaderFileModule {
	/**
	 * @param \ResourceLoaderContext $context
	 * @return array
	 */
	protected function getLessVars( \ResourceLoaderContext $context ) {
		$vars = parent::getLessVars( $context );
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'xenforoauth' );
		$vars['wgXenForoAuthButtonIcon'] = $config->get( 'XenForoAuthButtonIcon' );
		return $vars;
	}
}
