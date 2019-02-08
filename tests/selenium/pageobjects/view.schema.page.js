'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class ViewSchemaPage extends Page {

	static get SCHEMA_SELECTORS() {
		return {
			LABEL: '#wbschema-title-label',
			DESCRIPTION: '#wbschema-heading-description',
			ALIASES: '#wbschema-heading-aliases',
			SCHEMA_SHEXC: '#wbschema-schema-shexc'
		};
	}

	static get MEDIAWIKI_SELECTORS() {
		return {
			EDIT_LINK: '#ca-edit > span > a'
		};
	}

	open( schemaId, query = {}, fragment = '' ) {
		super.openTitle( `Schema:${schemaId}`, query, fragment );
	}

	getNamespace() {
		const namespace = browser.executeAsync( ( done ) => {
			done( window.mw.config.get( 'wgCanonicalNamespace' ) );
		} ).value;
		return namespace;
	}

	getLabel() {
		browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).getText();
	}

	getDescription() {
		browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION ).getText();
	}

	getAliases() {
		browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES ).getText();
	}

	/**
	 * Note: This method unfortunately trims the content of the element
	 *
	 * @return {string}
	 */
	getShExC() {
		browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_SHEXC ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_SHEXC ).getText();
	}

	/**
	 * Return the ShExC as it is in the HTML
	 *
	 * Note:
	 * that will return it without the webdriver mangling the whitespace, but with HTML entities
	 *
	 * @return {string}
	 */
	getShExCHTML() {
		browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_SHEXC ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_SHEXC ).getHTML( false );
	}

	getId() {
		browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).waitForVisible();
		let id = browser.execute( () => {
			return window.mw.config.get( 'wgTitle' );
		} );
		return id.value;
	}

	get editLink() {
		return browser.$( this.constructor.MEDIAWIKI_SELECTORS.EDIT_LINK );
	}

}

module.exports = new ViewSchemaPage();
