<?php

namespace Wikibase;

use Hooks;
use InvalidArgumentException;
use LogicException;
use MWException;
use Title;
use Wikibase\Content\EntityHolder;
use Wikibase\Content\EntityInstanceHolder;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\ItemSearchTextGenerator;

/**
 * Content object for articles representing Wikibase items.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class ItemContent extends EntityContent {

	/**
	 * For use in the wb-status page property to indicate that the entity is a "linkstub",
	 * that is, it contains sitelinks, but no claims.
	 *
	 * @see getEntityStatus()
	 */
	const STATUS_LINKSTUB = 60;

	/**
	 * @var EntityHolder|null
	 */
	private $itemHolder;

	/**
	 * @var EntityRedirect|null
	 */
	private $redirect;

	/**
	 * @var Title|null Title of the redirect target.
	 */
	private $redirectTitle;

	/**
	 * Do not use to construct new stuff from outside of this class,
	 * use the static newFoobar methods.
	 *
	 * In other words: treat as protected (which it was, but now cannot
	 * be since we derive from Content).
	 *
	 * @param EntityHolder|null $itemHolder
	 * @param EntityRedirect|null $entityRedirect
	 * @param Title|null $redirectTitle Title of the redirect target.
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		EntityHolder $itemHolder = null,
		EntityRedirect $entityRedirect = null,
		Title $redirectTitle = null
	) {
		parent::__construct( CONTENT_MODEL_WIKIBASE_ITEM );

		if ( is_null( $itemHolder ) === is_null( $entityRedirect ) ) {
			throw new InvalidArgumentException(
				'Either $item or $entityRedirect and $redirectTitle must be provided.' );
		}

		if ( $itemHolder !== null && $itemHolder->getEntityType() !== Item::ENTITY_TYPE ) {
			throw new InvalidArgumentException( '$itemHolder must contain a Item entity!' );
		}

		if ( is_null( $entityRedirect ) !== is_null( $redirectTitle ) ) {
			throw new InvalidArgumentException(
				'$entityRedirect and $redirectTitle must both be provided or both be empty.' );
		}

		if ( $redirectTitle !== null
			&& $redirectTitle->getContentModel() !== CONTENT_MODEL_WIKIBASE_ITEM
		) {
			if ( $redirectTitle->exists() ) {
				throw new InvalidArgumentException(
					'$redirectTitle must refer to a page with content model '
					. CONTENT_MODEL_WIKIBASE_ITEM );
			}
		}

		$this->itemHolder = $itemHolder;
		$this->redirect = $entityRedirect;
		$this->redirectTitle = $redirectTitle;
	}

	/**
	 * Create a new ItemContent object for the provided Item.
	 *
	 * @param Item $item
	 *
	 * @return ItemContent
	 */
	public static function newFromItem( Item $item ) {
		return new static( new EntityInstanceHolder( $item ) );
	}

	/**
	 * Create a new ItemContent object representing a redirect to the given item ID.
	 *
	 * @since 0.5
	 *
	 * @param EntityRedirect $redirect
	 * @param Title $redirectTitle Title of the redirect target.
	 *
	 * @return ItemContent
	 */
	public static function newFromRedirect( EntityRedirect $redirect, Title $redirectTitle ) {
		return new static( null, $redirect, $redirectTitle );
	}

	/**
	 * @see Content::getRedirectTarget
	 *
	 * @return Title|null
	 */
	public function getRedirectTarget() {
		return $this->redirectTitle;
	}

	/**
	 * @see EntityContent::getEntityRedirect
	 *
	 * @return null|EntityRedirect
	 */
	public function getEntityRedirect() {
		return $this->redirect;
	}

	/**
	 * Returns the Item that makes up this ItemContent.
	 *
	 * @throws MWException when it's a redirect (targets will never be resolved)
	 * @throws LogicException
	 * @return Item
	 */
	public function getItem() {
		$redirect = $this->getRedirectTarget();

		if ( $redirect ) {
			throw new MWException( 'Unresolved redirect to [[' . $redirect->getFullText() . ']]' );
		}

		if ( !$this->itemHolder ) {
			throw new LogicException( 'Neither redirect nor item found in ItemContent!' );
		}

		return $this->itemHolder->getEntity( 'Wikibase\DataModel\Entity\Item' );
	}

	/**
	 * Returns a new empty ItemContent.
	 *
	 * @return ItemContent
	 */
	public static function newEmpty() {
		return new static( new EntityInstanceHolder( new Item() ) );
	}

	/**
	 * @see EntityContent::getEntity
	 *
	 * @throws MWException when it's a redirect (targets will never be resolved)
	 * @return Item
	 */
	public function getEntity() {
		return $this->getItem();
	}

	/**
	 * @see EntityContent::getEntityHolder
	 *
	 * @return EntityHolder
	 */
	protected function getEntityHolder() {
		return $this->itemHolder;
	}

	/**
	 * @see EntityContent::getTextForSearchIndex()
	 */
	public function getTextForSearchIndex() {
		if ( $this->isRedirect() ) {
			return '';
		}

		// TODO: Refactor ItemSearchTextGenerator to share an interface with
		// FingerprintSearchTextGenerator, so we don't have to re-implement getTextForSearchIndex() here.
		$searchTextGenerator = new ItemSearchTextGenerator();
		$text = $searchTextGenerator->generate( $this->getItem() );

		if ( !Hooks::run( 'WikibaseTextForSearchIndex', array( $this, &$text ) ) ) {
			return '';
		}

		return $text;
	}

	/**
	 * @see EntityContent::isCountable
	 *
	 * @param bool $hasLinks
	 *
	 * @return bool True if this is not a redirect and the item is not empty.
	 */
	public function isCountable( $hasLinks = null ) {
		return !$this->isRedirect() && !$this->getItem()->isEmpty();
	}

	/**
	 * @see EntityContent::isEmpty
	 *
	 * @return bool True if this is not a redirect and the item is empty.
	 */
	public function isEmpty() {
		return !$this->isRedirect() && $this->getItem()->isEmpty();
	}

	/**
	 * @see EntityContent::isStub
	 *
	 * @return bool True if the item is not empty, but does not contain statements.
	 */
	public function isStub() {
		return !$this->isRedirect()
			&& !$this->getItem()->isEmpty()
			&& $this->getItem()->getStatements()->isEmpty();
	}

	/**
	 * @see EntityContent::getEntityPageProperties
	 *
	 * Records the number of statements in the 'wb-claims' key
	 * and the number of sitelinks in the 'wb-sitelinks' key.
	 *
	 * @return array A map from property names to property values.
	 */
	public function getEntityPageProperties() {
		$properties = parent::getEntityPageProperties();

		if ( !$this->isRedirect() ) {
			$item = $this->getItem();
			$properties['wb-claims'] = $item->getStatements()->count();
			$properties['wb-sitelinks'] = $item->getSiteLinkList()->count();
		}

		return $properties;
	}

	/**
	 * @see EntityContent::getEntityStatus()
	 *
	 * An item is considered a stub if it has terms but no statements or sitelinks.
	 * If an item has sitelinks but no statements, it is considered a "linkstub".
	 * If an item has statements, it's not empty nor a stub.
	 *
	 * @see STATUS_LINKSTUB
	 *
	 * @note Will fail of this ItemContent is a redirect.
	 *
	 * @return int
	 */
	public function getEntityStatus() {
		$status = parent::getEntityStatus();

		if ( !$this->isRedirect() ) {
			$hasSiteLinks = !$this->getItem()->getSiteLinkList()->isEmpty();

			if ( $status === self::STATUS_EMPTY && $hasSiteLinks ) {
				$status = self::STATUS_LINKSTUB;
			} elseif ( $status === self::STATUS_STUB && $hasSiteLinks ) {
				$status = self::STATUS_LINKSTUB;
			}
		}

		return $status;
	}

}
