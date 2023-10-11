<?php

namespace XenForoAuth;

/**
 * Same as HTMLSubmitField, the only difference is, that the style module to
 * style the XenForo login button is added.
 */
class HTMLXenForoButtonField extends \HTMLSubmitField {
	public function getInputHTML( $value ) {
		$this->addXenForoButtonStyleModule();
		return parent::getInputHTML( $value );
	}

	public function getInputOOUI( $value ) {
		$this->addXenForoButtonStyleModule( 'ooui' );
		return parent::getInputOOUI( $value );
	}

	/**
	 * Adds the required style module to the OutputPage object for styling of the Login
	 * with XenForo button.
	 *
	 * @param string $target Defines which style module should be added (vform, ooui)
	 */
	private function addXenForoButtonStyleModule( $target = 'vform' ) {
		if ( $this->mParent instanceof \HTMLForm ) {
			$out = $this->mParent->getOutput();
		} else {
			$out = \RequestContext::getMain()->getOutput();
		}
		if ( $target === 'vform' ) {
			$out->addModuleStyles( 'ext.XenForo.userlogincreate.style' );
		} else {
			$out->addModuleStyles( 'ext.XenForo.userlogincreate.ooui.style' );
		}
	}
}
