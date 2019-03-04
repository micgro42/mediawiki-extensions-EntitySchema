<?php

namespace Wikibase\Schema\Tests\MediaWiki\Actions;

use Action;
use Block;
use CommentStoreComment;
use FauxRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use RequestContext;
use Title;
use UserBlockedError;
use Wikibase\Schema\MediaWiki\Actions\RestoreSubmitAction;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \Wikibase\Schema\MediaWiki\Actions\RestoreSubmitAction
 */
final class RestoreSubmitActionTest extends MediaWikiTestCase {

	/** @var Block */
	private $block;

	protected function tearDown() {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	public function testRestoreSubmit() {
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schema' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schema' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
				'wpBaseRev' => $secondId
			], true )
		);

		$restoreSubmitAction = new RestoreSubmitAction( $page, $context );

		$restoreSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'O123' );
		$this->assertSame( 'abc', $actualSchema['schema'] );
	}

	public function testRestoreNotCurrent() {
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schema' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schema' => 'def' ] );
		$this->saveSchemaPageContent( $page, [ 'schema' => 'ghi' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
				'wpBaseRev' => $secondId
			], true )
		);

		$restoreSubmitAction = new RestoreSubmitAction( $page, $context );

		$restoreSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'O123' );
		$this->assertSame(
			'ghi',
			$actualSchema['schema'],
			'The restore must fail if wpBaseRev is not the current revision!'
		);
	}

	public function testRestoreSubmitBlocked() {
		$testuser = self::getTestUser()->getUser();
		$this->block = new Block(
			[
				'address' => $testuser,
				'reason' => 'testing in ' . __CLASS__,
				'by' => $testuser->getId(),
			]
		);
		$this->block->insert();

		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schema' => 'abc' ] );
		$this->saveSchemaPageContent( $page, [ 'schema' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
			], true )
		);
		$context->setUser( $testuser );

		$restoreSubmitAction = new RestoreSubmitAction( $page, $context );

		$this->expectException( UserBlockedError::class );

		$restoreSubmitAction->show();
	}

	private function getCurrentSchemaContent( $pageName ) {
		/** @var WikibaseSchemaContent $content */
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $pageName );
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $title->getLatestRevID() );
		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
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

	public function testActionName() {
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' );
		$requestParameters = [ 'action' => 'submit', 'restore' => 1 ];
		$context = RequestContext::newExtraneousContext( $title, $requestParameters );

		$actionName = Action::getActionName( $context );
		$action = Action::factory( $actionName, $context->getWikiPage(), $context );

		$this->assertInstanceOf( RestoreSubmitAction::class, $action );
	}

}
