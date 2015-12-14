<?php

namespace Wikibase\Repo\Interactors;

use User;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Services\Lookup\EntityRedirectLookup;
use Wikibase\DataModel\Services\Lookup\EntityRedirectLookupException;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\RevisionedUnresolvedRedirectException;
use Wikibase\Repo\Hooks\EditFilterHookRunner;
use Wikibase\Repo\Store\EntityPermissionChecker;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * An interactor implementing the use case of creating a redirect.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Addshore
 */
class RedirectCreationInteractor {

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var EntityStore
	 */
	private $entityStore;

	/**
	 * @var EntityPermissionChecker
	 */
	private $permissionChecker;

	/**
	 * @var SummaryFormatter
	 */
	private $summaryFormatter;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @var EditFilterHookRunner
	 */
	private $editFilterHookRunner;

	/**
	 * @var EntityRedirectLookup
	 */
	private $entityRedirectLookup;

	/**
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param EntityStore $entityStore
	 * @param EntityPermissionChecker $permissionChecker
	 * @param SummaryFormatter $summaryFormatter
	 * @param User $user
	 * @param EditFilterHookRunner $editFilterHookRunner
	 * @param EntityRedirectLookup $entityRedirectLookup
	 */
	public function __construct(
		EntityRevisionLookup $entityRevisionLookup,
		EntityStore $entityStore,
		EntityPermissionChecker $permissionChecker,
		SummaryFormatter $summaryFormatter,
		User $user,
		EditFilterHookRunner $editFilterHookRunner,
		EntityRedirectLookup $entityRedirectLookup
	) {
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->entityStore = $entityStore;
		$this->permissionChecker = $permissionChecker;
		$this->summaryFormatter = $summaryFormatter;
		$this->user = $user;
		$this->editFilterHookRunner = $editFilterHookRunner;
		$this->entityRedirectLookup = $entityRedirectLookup;
	}

	/**
	 * Create a redirect at $fromId pointing to $toId.
	 *
	 * @param EntityId $fromId The ID of the entity to be replaced by the redirect. The entity
	 * must exist and be empty (or be a redirect already).
	 * @param EntityId $toId The ID of the entity the redirect should point to. The Entity must
	 * exist and must not be a redirect.
	 * @param bool $bot Whether the edit should be marked as bot
	 *
	 * @return EntityRedirect
	 *
	 * @throws RedirectCreationException If creating the redirect fails. Calling code may use
	 * RedirectCreationException::getErrorCode() to get further information about the cause of
	 * the failure. An explanation of the error codes can be obtained from getErrorCodeInfo().
	 */
	public function createRedirect( EntityId $fromId, EntityId $toId, $bot ) {
		$this->checkCompatible( $fromId, $toId );
		$this->checkPermissions( $fromId );

		$this->checkExistsNoRedirect( $toId );
		$this->checkEmpty( $fromId );

		$summary = new Summary( 'wbcreateredirect' );
		$summary->addAutoCommentArgs( $fromId->getSerialization(), $toId->getSerialization() );

		$redirect = new EntityRedirect( $fromId, $toId );
		$this->saveRedirect( $redirect, $summary, $bot );

		return $redirect;
	}

	/**
	 * Check all applicable permissions for redirecting the given $entityId.
	 *
	 * @param EntityId $entityId
	 *
	 * @throws RedirectCreationException if a permission check fails
	 */
	private function checkPermissions( EntityId $entityId ) {
		$permissions = array(
			'edit',
			$entityId->getEntityType() . '-redirect'
		);

		foreach ( $permissions as $permission ) {
			$this->checkPermission( $entityId, $permission );
		}
	}

	/**
	 * Check the given permissions for the given $entityId.
	 *
	 * @param EntityId $entityId
	 * @param string $permission
	 *
	 * @throws RedirectCreationException if the permission check fails
	 */
	private function checkPermission( EntityId $entityId, $permission ) {
		$status = $this->permissionChecker->getPermissionStatusForEntityId( $this->user, $permission, $entityId );

		if ( !$status->isOK() ) {
			// XXX: This is silly, we really want to pass the Status object to the API error handler.
			// Perhaps we should get rid of RedirectCreationException and use Status throughout.
			throw new RedirectCreationException( $status->getWikiText(), 'permissiondenied' );
		}
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @throws RedirectCreationException
	 */
	private function checkEmpty( EntityId $entityId ) {
		try {
			$revision = $this->entityRevisionLookup->getEntityRevision( $entityId, EntityRevisionLookup::LATEST_FROM_MASTER );

			if ( !$revision ) {
				throw new RedirectCreationException(
					"Entity $entityId not found",
					'no-such-entity'
				);
			}

			$entity = $revision->getEntity();

			if ( !$entity->isEmpty() ) {
				throw new RedirectCreationException(
					"Entity $entityId is not empty",
					'target-not-empty'
				);
			}
		} catch ( RevisionedUnresolvedRedirectException $ex ) {
			// Nothing to do. It's ok to override a redirect with a redirect.
		} catch ( StorageException $ex ) {
			throw new RedirectCreationException( $ex->getMessage(), 'cant-load-entity-content', $ex );
		}
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @throws RedirectCreationException
	 */
	private function checkExistsNoRedirect( EntityId $entityId ) {
		try {
			$redirect = $this->entityRedirectLookup->getRedirectForEntityId(
				$entityId,
				'for update'
			);
		} catch ( EntityRedirectLookupException $ex ) {
			throw new RedirectCreationException(
				$ex->getMessage(),
				'no-such-entity',
				$ex
			);
		}

		if ( $redirect !== null ) {
			throw new RedirectCreationException(
				"Entity $entityId is a redirect",
				'target-is-redirect'
			);
		}
	}

	/**
	 * @param EntityId $fromId
	 * @param EntityId $toId
	 *
	 * @throws RedirectCreationException
	 */
	private function checkCompatible( EntityId $fromId, EntityId $toId ) {
		if ( $fromId->getEntityType() !== $toId->getEntityType() ) {
			throw new RedirectCreationException(
				'Incompatible entity types',
				'target-is-incompatible'
			);
		}
	}

	/**
	 * @param EntityRedirect $redirect
	 * @param Summary $summary
	 * @param bool $bot Whether the edit should be marked as bot
	 *
	 * @throws RedirectCreationException
	 */
	private function saveRedirect( EntityRedirect $redirect, Summary $summary, $bot ) {
		$summary = $this->summaryFormatter->formatSummary( $summary );
		$flags = EDIT_UPDATE;
		if ( $bot ) {
			$flags = $flags | EDIT_FORCE_BOT;
		}

		$hookStatus = $this->editFilterHookRunner->run( $redirect, $this->user, $summary );
		if ( !$hookStatus->isOK() ) {
			throw new RedirectCreationException(
				'EditFilterHook stopped redirect creation',
				'cant-redirect'
			);
		}

		try {
			$this->entityStore->saveRedirect(
				$redirect,
				$summary,
				$this->user,
				$flags
			);
		} catch ( StorageException $ex ) {
			throw new RedirectCreationException( $ex->getMessage(), 'cant-redirect', $ex );
		}
	}

}
