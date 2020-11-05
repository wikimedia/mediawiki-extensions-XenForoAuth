<?php
namespace XenForoAuth;

use MediaWiki\MediaWikiServices;
use User;
use XenForoBDClient\Clients\UnauthenticatedClient;

class XenForoUser {
	/**
	 * @var string The XenForo User ID of this object
	 */
	private $xfUserId = '';
	private $userData = null;

	/**
	 * XenForoUser constructor.
	 * @param int $xfUserId The XenForo User ID
	 */
	private function __construct( $xfUserId ) {
		$this->xfUserId = $xfUserId;
	}

	/**
	 * Creates a new XenForoUser object based on the given XenForo User ID. This function
	 * will start a request to the XenFor API to find out the information about
	 * the XenForo User.
	 *
	 * @param int $xfUserId The XenForo User ID
	 * @return XenForoUser
	 */
	public static function newFromXFUserId( $xfUserId ) {
		$user = new self( $xfUserId );
		$user->init();

		return $user;
	}

	/**
	 * Creates a new XenForo User object based on the given user data. This
	 * function will not start a request to the XenFor API and takes the
	 * information given in the $userInfo array as they are.
	 *
	 * @param array $userInfo An array of information about the user returned by the XenForo
	 * OAuth2 api
	 * @return XenForoUser|null Returns the XenForo User object or null, if the
	 *  $userInfo array does not contain an "user_id" key.
	 */
	public static function newFromUserInfo( $userInfo ) {
		if ( !is_array( $userInfo ) ) {
			throw new \InvalidArgumentException( 'The first paramater of ' . __METHOD__ .
				' is required to be an array, ' .
				get_class( $userInfo ) . ' given.' );
		}
		if ( !isset( $userInfo['user']['user_id'] ) ) {
			return null;
		}
		$user = new self( $userInfo['user']['user_id'] );
		$user->userData = $userInfo['user'];

		return $user;
	}

	/**
	 * Loads the data of the person represented by the XenForo User ID.
	 */
	private function init() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'xenforoauth' );
		$client = new UnauthenticatedClient();
		$client->setBaseUrl( $config->get( 'XenForoAuthBaseUrl' ) );
		$user = new \XenForoBDClient\Users\User( $client );
		$userInfo = $user->get( $this->xfUserId );
		if ( $userInfo ) {
			$this->userData = $userInfo['user'];
		}
	}

	/**
	 * Returns the requested user data of the user.
	 *
	 * @param string $data The data to retrieve
	 * @return null
	 */
	public function getData( $data ) {
		if ( $this->userData !== null && isset( $this->userData[$data] ) ) {
			return $this->userData[$data];
		}
		return null;
	}

	/**
	 * Returns the username and the user id, or the user id only, if no username was returned by
	 * the api.
	 *
	 * @return string
	 */
	public function getFullNameWithId() {
		if ( $this->getData( 'username' ) ) {
			return $this->getData( 'username' ) . ' ' . wfMessage( 'parentheses', $this->xfUserId );
		}
		return $this->xfUserId;
	}

	/**
	 * Check, if the data for the ID could be loaded.
	 * @return bool Returns true, if data could be loaded, false otherwise
	 */
	public function isDataLoaded() {
		return $this->userData !== null;
	}

	/**
	 * Check, if the XenForo user ID is already connected to another wiki account or not.
	 *
	 * @param string $xfUserId
	 * @param int $flags
	 * @return bool
	 */
	public static function isXFUserIdFree( $xfUserId, $flags = User::READ_LATEST ) {
		return self::getUserFromXFUserId( $xfUserId, $flags ) === null;
	}

	/**
	 * Loads the XenForo user Id from a User Id set to this object.
	 *
	 * @param User $user The user to get the XenForo user Id for
	 * @param int $flags User::READ_* constant bitfield
	 * @return null|int Null, if no XenForo user ID connected with this User ID, the id
	 * otherwise
	 */
	public static function getXFUserIdFromUser( User $user, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_REPLICA );

		$s = $db->select(
			'user_xenforo_user',
			[ 'user_xfuserid' ],
			[ 'user_id' => $user->getId() ],
			__METHOD__,
			( ( $flags & User::READ_LOCKING ) == User::READ_LOCKING )
				? [ 'LOCK IN SHARE MODE' ]
				: []
		);

		if ( $s !== false ) {
			foreach ( $s as $obj ) {
				return $obj->user_xfuserid;
			}
		}
		// Invalid user_id
		return null;
	}

	/**
	 * Helper function for load* functions. Loads the Google Id from a
	 * User Id set to this object.
	 *
	 * @param string $xfUserId The XenForo User ID to get the user to
	 * @param int $flags User::READ_* constant bitfield
	 * @return null|User The local User account connected with the XenForo user ID if
	 * the XenForo user ID is connected to an User, null otherwise.
	 */
	public static function getUserFromXFUserId( $xfUserId, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_REPLICA );

		$s = $db->selectRow(
			'user_xenforo_user',
			[ 'user_id' ],
			[ 'user_xfuserid' => $xfUserId ],
			__METHOD__,
			( ( $flags & User::READ_LOCKING ) == User::READ_LOCKING )
				? [ 'LOCK IN SHARE MODE' ]
				: []
		);

		if ( $s !== false ) {
			// Initialise user table data;
			return User::newFromId( $s->user_id );
		}
		// Invalid user_id
		return null;
	}

	/**
	 * Returns true, if this user object is connected with a google account,
	 * otherwise false.
	 *
	 * @param User $user The user to check
	 * @return bool
	 */
	public static function hasConnectedXFUserAccount( User $user ) {
		return (bool)self::getXFUserIdFromUser( $user );
	}

	/**
	 * Terminates a connection between this wiki account and the
	 * connected Google account.
	 *
	 * @param User $user The user to connect from where to remove the connection
	 * @param string $xfUserId The Google ID to remove
	 * @return bool
	 */
	public static function terminateXFUserConnection( User $user, $xfUserId ) {
		$connectedId = self::getXFUserIdFromUser( $user );
		// make sure, that the user has a connected user account
		if ( $connectedId === null ) {
			// already terminated
			return true;
		}

		// get DD master
		$dbr = wfGetDB( DB_MASTER );
		// try to delete the row with this google id
		if (
			$dbr->delete(
				"user_xenforo_user",
				"user_xfuserid = " . $xfUserId,
				__METHOD__
			)
		) {
			return true;
		}

		// something went wrong
		return false;
	}

	/**
	 * Insert's or update's the Google ID connected with this user account.
	 *
	 * @param User $user The user to connect the Google ID with
	 * @param string $xfUserId The new XenForo ID
	 * @return bool Whether the insert/update statement was successful
	 */
	public static function connectWithXenForoUser( User $user, $xfUserId ) {
		$dbr = wfGetDB( DB_MASTER );

		return $dbr->insert(
			"user_xenforo_user",
			[
				'user_id' => $user->getId(),
				'user_xfuserid' => $xfUserId
			]
		);
	}
}
