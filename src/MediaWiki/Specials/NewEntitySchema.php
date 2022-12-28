<?php

namespace EntitySchema\MediaWiki\Specials;

use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter;
use EntitySchema\DataAccess\SqlIdGenerator;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Presentation\InputValidator;
use Html;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use OutputPage;
use PermissionsError;
use SpecialPage;
use Status;
use Title;

/**
 * Page for creating a new EntitySchema.
 *
 * @license GPL-2.0-or-later
 */
class NewEntitySchema extends SpecialPage {

	public const FIELD_DESCRIPTION = 'description';

	public const FIELD_LABEL = 'label';

	public const FIELD_ALIASES = 'aliases';

	public const FIELD_SCHEMA_TEXT = 'schema-text';

	public const FIELD_LANGUAGE = 'languagecode';

	public function __construct() {
		parent::__construct(
			'NewEntitySchema',
			'createpage'
		);
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->checkPermissionsWithSubpage( $subPage );
		$this->checkReadOnly();

		$form = HTMLForm::factory( 'ooui', $this->getFormFields(), $this->getContext() )
			->setSubmitName( 'submit' )
			->setSubmitID( 'entityschema-newschema-submit' )
			->setSubmitTextMsg( 'entityschema-newschema-submit' )
			->setValidationErrorMessage( [ [
				'entityschema-error-possibly-multiple-messages-available'
			] ] )
			->setSubmitCallback( [ $this, 'submitCallback' ] );
		$form->prepareForm();

		/** @var Status|false $submitStatus `false` if form was not submitted */
		$submitStatus = $form->tryAuthorizedSubmit();

		if ( $submitStatus && $submitStatus->isGood() ) {
			$this->getOutput()->redirect(
				$submitStatus->getValue()
			);
			return;
		}

		$this->addJavaScript();
		$this->displayBeforeForm( $this->getOutput() );

		$form->displayForm( $submitStatus ?: Status::newGood() );
	}

	public function submitCallback( $data, HTMLForm $form ) {
		// TODO: no form data validation??

		$idGenerator = new SqlIdGenerator(
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			'entityschema_id_counter',
			$this->getConfig()->get( 'EntitySchemaSkippedIDs' )
		);

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $this->getUser() );

		$schemaInserter = new MediaWikiRevisionSchemaInserter(
			$pageUpdaterFactory,
			new WatchlistUpdater( $this->getUser(), NS_ENTITYSCHEMA_JSON ),
			$idGenerator,
			MediaWikiServices::getInstance()->getLanguageFactory()
		);
		$newId = $schemaInserter->insertSchema(
			$data[self::FIELD_LANGUAGE],
			$data[self::FIELD_LABEL],
			$data[self::FIELD_DESCRIPTION],
			array_filter( array_map( 'trim', explode( '|', $data[self::FIELD_ALIASES] ) ) ),
			$data[self::FIELD_SCHEMA_TEXT]
		);

		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $newId->getId() );

		return Status::newGood( $title->getFullURL() );
	}

	public function getDescription() {
		return $this->msg( 'special-newschema' )->text();
	}

	protected function getGroupName() {
		return 'wikibase';
	}

	private function getFormFields(): array {
		$langCode = $this->getLanguage()->getCode();
		$langName = MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageName( $langCode, $langCode );
		$inputValidator = InputValidator::newFromGlobalState();
		return [
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'type' => 'text',
				'id' => 'entityschema-newschema-label',
				'required' => true,
				'default' => '',
				'placeholder-message' => $this->msg( 'entityschema-label-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-newschema-label',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength'
				],
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'type' => 'text',
				'default' => '',
				'id' => 'entityschema-newschema-description',
				'placeholder-message' => $this->msg( 'entityschema-description-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-newschema-description',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength'
				],
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'type' => 'text',
				'default' => '',
				'id' => 'entityschema-newschema-aliases',
				'placeholder-message' => $this->msg( 'entityschema-aliases-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-newschema-aliases',
				'validation-callback' => [
					$inputValidator,
					'validateAliasesLength'
				],
			],
			self::FIELD_SCHEMA_TEXT => [
				'name' => self::FIELD_SCHEMA_TEXT,
				'type' => 'textarea',
				'default' => '',
				'id' => 'entityschema-newschema-schema-text',
				'placeholder' => "<human> {\n  wdt:P31 [wd:Q5]\n}",
				'label-message' => 'entityschema-newschema-schema-shexc',
				'validation-callback' => [
					$inputValidator,
					'validateSchemaTextLength'
				],
				'useeditfont' => true,
			],
			self::FIELD_LANGUAGE => [
				'name' => self::FIELD_LANGUAGE,
				'type' => 'hidden',
				'default' => $langCode,
			],
		];
	}

	private function displayBeforeForm( OutputPage $output ) {
		$output->addHTML( $this->getCopyrightHTML() );

		foreach ( $this->getWarnings() as $warning ) {
			$output->addHTML( Html::rawElement( 'div', [ 'class' => 'warning' ], $warning ) );
		}
	}

	/**
	 * @return string HTML
	 */
	private function getCopyrightHTML() {
		return $this->msg( 'entityschema-newschema-copyright' )
			->params(
				$this->msg( 'entityschema-newschema-submit' )->text(),
				$this->msg( 'copyrightpage' )->inContentLanguage()->text(),
				// FIXME: make license configurable
				'[https://creativecommons.org/publicdomain/zero/1.0/ Creative Commons CC0 License]'
			)
			->parse();
	}

	private function getWarnings(): array {
		if ( $this->getUser()->isAnon() ) {
			return [
				$this->msg(
					'entityschema-anonymouseditwarning'
				)->parse(),
			];
		}

		return [];
	}

	private function addJavaScript() {
		$output = $this->getOutput();
		$output->addModules( [
			'ext.EntitySchema.special.newSchema',
		] );
		$output->addJsConfigVars( [
			'wgEntitySchemaSchemaTextMaxSizeBytes' =>
				intval( $this->getConfig()->get( 'EntitySchemaSchemaTextMaxSizeBytes' ) ),
			'wgEntitySchemaNameBadgeMaxSizeChars' =>
				intval( $this->getConfig()->get( 'EntitySchemaNameBadgeMaxSizeChars' ) )
		] );
	}

	/**
	 * Checks if the user has permissions to perform this page’s action,
	 * and throws a {@link PermissionsError} if they don’t.
	 *
	 * @throws PermissionsError
	 */
	protected function checkPermissionsWithSubpage( $subPage ) {
		$pm = MediaWikiServices::getInstance()->getPermissionManager();
		$checkReplica = !$this->getRequest()->wasPosted();
		$permissionErrors = $pm->getPermissionErrors(
			$this->getRestriction(),
			$this->getUser(),
			$this->getPageTitle( $subPage ),
			$checkReplica ? $pm::RIGOR_FULL : $pm::RIGOR_SECURE,
			[
				'ns-specialprotected', // ignore “special pages cannot be edited”
			]
		);
		if ( $permissionErrors !== [] ) {
			// reindex $permissionErrors:
			// the ignoreErrors param (ns-specialprotected) may have left holes,
			// but PermissionsError expects $errors[0] to exist
			$permissionErrors = array_values( $permissionErrors );
			throw new PermissionsError( $this->getRestriction(), $permissionErrors );
		}
	}

}
