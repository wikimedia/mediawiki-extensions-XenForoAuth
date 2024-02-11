<?php
namespace XenForoAuth;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FileModule;

class XenForoResourceLoaderModule extends FileModule {
	/**
	 * @param Context $context
	 * @return array
	 */
	protected function getLessVars( Context $context ) {
		$vars = parent::getLessVars( $context );
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'xenforoauth' );
		$vars['wgXenForoAuthButtonIcon'] = $config->get( 'XenForoAuthButtonIcon' );
		return $vars;
	}
}
