<?php

namespace Wikibase\Schema\Tests\MediaWiki;

use MediaWikiTestCase;
use PermissionsError;
use ReadOnlyError;
use ReadOnlyMode;
use RequestContext;
use Title;
use Wikibase\Schema\MediaWiki\SchemaEditAction;
use WikiPage;

/**
 * @covers \Wikibase\Schema\MediaWiki\SchemaEditAction
 *
 * @license GPL-2.0-or-later
 */
class SchemaEditActionTest extends MediaWikiTestCase {

	/**
	 * @expectedException ReadOnlyError
	 */
	public function testReadOnly() {
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$action = new SchemaEditAction(
			new WikiPage( Title::newFromDBkey( 'O1' ) ),
			new RequestContext()
		);

		$action->show();
	}

	/**
	 * @expectedException PermissionsError
	 */
	public function testNoRights() {
		global $wgGroupPermissions;

		$groupPermissions = $wgGroupPermissions;
		$groupPermissions['*']['edit'] = false;
		$this->setMwGlobals( 'wgGroupPermissions', $groupPermissions );
		$action = new SchemaEditAction(
			new WikiPage( Title::newFromDBkey( 'O1' ) ),
			new RequestContext()
		);

		$action->show();
	}

}
