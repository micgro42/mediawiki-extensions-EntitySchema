<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\DataAccess\LabelLookup;
use HtmlArmor;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;
use RequestContext;

/**
 * @license GPL-2.0-or-later
 */
class HtmlPageLinkRendererEndHookHandler implements HtmlPageLinkRendererEndHook {

	private LabelLookup $labelLookup;
	private RequestContext $context;

	public function __construct(
		LabelLookup $labelLookup,
		RequestContext $context
	) {
		$this->labelLookup = $labelLookup;
		$this->context = $context;
	}

	public static function factory( LabelLookup $labelLookup ): self {
		return new self(
			$labelLookup,
			RequestContext::getMain()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererEnd(
		$linkRenderer,
		$target,
		$isKnown,
		&$text,
		&$extraAttribs,
		&$ret
	): bool {
		if ( !$this->context->hasTitle() ) {
			// Short-circuit this hook if no title is
			// set in the main context (T131176)
			return true;
		}

		if ( !$target->inNamespace( NS_ENTITYSCHEMA_JSON ) ) {
			return true;
		}

		return $this->doHtmlPageLinkRendererEnd(
			$linkRenderer,
			Title::newFromLinkTarget( $target ),
			$text
		);
	}

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param Title $target
	 * @param HtmlArmor|string|null &$text
	 *
	 * @return bool always true
	 */
	private function doHtmlPageLinkRendererEnd(
		LinkRenderer $linkRenderer,
		Title $target,
		&$text
	): bool {
		// if custom link text is given, there is no point in overwriting it
		// but not if it is similar to the plain title
		if ( $text !== null
			&& $target->getFullText() !== HtmlArmor::getHtml( $text )
			&& $target->getText() !== HtmlArmor::getHtml( $text )
		) {
			return true;
		}

		$outputTitle = $this->context->getTitle();
		'@phan-var Title $outputTitle';
		if ( !$this->shouldConvertNoBadTitle( $outputTitle, $linkRenderer ) ) {
			return true;
		}

		return $this->internalDoHtmlPageLinkRendererEnd(
			$target,
			$text,
		);
	}

	/**
	 * Parts of the hook handling logic for the HtmlPageLinkRendererEnd hook that potentially
	 * interact with entity storage.
	 *
	 * @param Title $target
	 * @param HtmlArmor|string|null &$text
	 *
	 * @return bool always true
	 */
	private function internalDoHtmlPageLinkRendererEnd(
		Title $target,
		&$text
	): bool {
		$label = $this->labelLookup->getLabelForTitle( $target, $this->context->getLanguage()->getCode() );
		if ( $label === null ) {
			return true;
		}

		$wrappedEntitySchemaId = $this->context->msg(
			'wikibase-itemlink-id-wrapper',
			$target->getText()
		)->inContentLanguage()->escaped();
		$text = $this->context->msg( 'wikibase-itemlink' )->rawParams(
			$label->getText(),
			$wrappedEntitySchemaId
		)->inContentLanguage()->escaped();

		return true;
	}

	private function shouldConvertNoBadTitle( Title $currentTitle, LinkRenderer $linkRenderer ): bool {
		return $linkRenderer->isForComment() ||
			// Note: this may not work right with special page transclusion. If $out->getTitle()
			// doesn't return the transcluded special page's title, the transcluded text will
			// not have entity IDs resolved to labels.
			// Also Note: Badtitle is excluded because it is used in rendering actual page content
			// that is added to the ParserCache. See T327062#8796532 and https://www.mediawiki.org/wiki/API:Stashedit
			( $currentTitle->isSpecialPage() && !$currentTitle->isSpecial( 'Badtitle' ) );
	}
}
