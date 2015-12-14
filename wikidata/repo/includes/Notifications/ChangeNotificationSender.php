<?php

namespace Wikibase\Repo\Notifications;

use Wikibase\Change;

/**
 * Channel for sending notifications about changes to a repo's clients.
 *
 * @since 0.5
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
interface ChangeNotificationSender {

	/**
	 * Notifies the client wiki of the given changes.
	 *
	 * @param string   $siteID:  The client wiki's global site identifier, as used by sitelinks.
	 * @param Change[] $changes: The list of changes to post to the wiki.
	 */
	public function sendNotification( $siteID, array $changes );

}
