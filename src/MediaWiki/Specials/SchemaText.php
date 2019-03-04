<?php

namespace Wikibase\Schema\MediaWiki\Specials;

use HttpError;
use InvalidArgumentException;
use SpecialPage;
use Title;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 */
class SchemaText extends SpecialPage {

	public function __construct() {
		parent::__construct(
			'SchemaText',
			'read'
		);
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );
		$schemaId = $this->getIdFromSubpage( $subPage );
		if ( !$schemaId ) {
			$this->getOutput()->addWikiMsg( 'wikibaseschema-schematext-text' );
			$this->getOutput()->returnToMain();
			return;
		}
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $schemaId->getId() );

		if ( !$title->exists() ) {
			throw new HttpError( 404, $this->getOutput()->msg(
				'wikibaseschema-schematext-missing', $subPage
			) );
		}

		$this->sendContentSchemaText( WikiPage::factory( $title )->getContent(), $schemaId );
	}

	public function getDescription() {
		return $this->msg( 'special-schematext' )->text();
	}

	protected function getGroupName() {
		return 'wikibase';
	}

	private function sendContentSchemaText( WikibaseSchemaContent $schemaContent, SchemaId $id ) {
		$dispatcher = new SchemaDispatcher();
		$schemaText = $dispatcher->getSchemaText( $schemaContent->getText() );
		$out = $this->getOutput();
		$out->disable();
		$webResponse = $out->getRequest()->response();
		$webResponse->header( 'Content-Type: text/shex; charset=UTF-8' );
		$webResponse->header( 'Content-Disposition:  attachment; filename="' . $id->getId() . '.shex"' );
		ob_clean(); // remove anything that might already be in the output buffer.
		echo $schemaText;
	}

	/**
	 * @param string $subPage
	 *
	 * @return bool|SchemaId
	 */
	private function getIdFromSubpage( $subPage ) {
		if ( !$subPage ) {
			return false;
		}
		try {
			$schemaId = new SchemaId( $subPage );
		} catch ( InvalidArgumentException $e ) {
			return false;
		}
		return $schemaId;
	}

}
