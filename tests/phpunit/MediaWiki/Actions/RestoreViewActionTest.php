<?php

namespace Wikibase\Schema\Tests\MediaWiki\Actions;

use CommentStoreComment;
use FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use RequestContext;
use TextSlotDiffRenderer;
use Title;
use Wikibase\Schema\MediaWiki\Actions\RestoreViewAction;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaSlotDiffRenderer;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \Wikibase\Schema\MediaWiki\Actions\RestoreViewAction
 * @covers \Wikibase\Schema\Services\RenderDiffHelper\RenderDiffHelper
 */
final class RestoreViewActionTest extends MediaWikiTestCase {

	public function testRestoreView() {
		// arrange
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O1234' ) );

		$firstID = $this->saveSchemaPageContent(
			$page,
			[ 'schema' => 'abc' ]
		);
		$this->saveSchemaPageContent( $page, [ 'schema' => 'def', 'serializationVersion' => '2.0' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest(
			new FauxRequest(
				[
					'action' => 'edit',
					'restore' => $firstID,
				],
				false
			)
		);

		$textSlotDiffRenderer = new TextSlotDiffRenderer();
		$textSlotDiffRenderer->setEngine( TextSlotDiffRenderer::ENGINE_PHP );
		$diffRenderer = new WikibaseSchemaSlotDiffRenderer(
			$context,
			$textSlotDiffRenderer
		);
		$undoViewAction = new RestoreViewAction( $page, $diffRenderer, $context );

		// act
		$undoViewAction->show();

		// assert
		$actualHTML = $undoViewAction->getContext()->getOutput()->getHTML();
		$this->assertContains( '<ins class="diffchange diffchange-inline">abc</ins>', $actualHTML );
		$this->assertContains( '<del class="diffchange diffchange-inline">def</del>', $actualHTML );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ) {
		$content['serializationVersion'] = '2.0';
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new WikibaseSchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);

		return $firstRevRecord->getId();
	}

}