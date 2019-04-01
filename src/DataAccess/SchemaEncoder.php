<?php

namespace Wikibase\Schema\DataAccess;

use InvalidArgumentException;
use Language;
use MediaWiki\MediaWikiServices;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
class SchemaEncoder {

	/**
	 * @param SchemaId $id
	 * @param array    $labels       labels  with langCode as key, e.g. [ 'en' => 'Cat' ]
	 * @param array    $descriptions descriptions with langCode as key, e.g. [ 'en' => 'A cat' ]
	 * @param array    $aliases      aliases with langCode as key, e.g. [ 'en' => [ 'tiger' ], ]
	 * @param string   $schemaText
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 *
	 * @return string
	 */
	public static function getPersistentRepresentation(
		SchemaId $id,
		array $labels,
		array $descriptions,
		array $aliases,
		$schemaText
	) {
		self::validateParameters(
			$labels,
			$descriptions,
			$aliases,
			$schemaText
		);
		self::cleanupParameters(
			$labels,
			$descriptions,
			$aliases,
			$schemaText
		);
		return json_encode(
			[
				'id' => $id->getId(),
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			]
		);
	}

	/**
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param array<string,string[]> $aliasGroups
	 * @param string $schemaText
	 */
	private static function cleanupParameters(
		array &$labels,
		array &$descriptions,
		array &$aliasGroups,
		&$schemaText
	) {
		self::trimStartAndEnd( $labels, $descriptions, $aliasGroups, $schemaText );
		$labels = self::filterEmptyStrings( $labels );
		$descriptions = self::filterEmptyStrings( $descriptions );
		foreach ( $aliasGroups as $languageCode => &$aliasGroup ) {
			$aliasGroup = array_values( array_unique( $aliasGroup ) );
			if ( $aliasGroup === [] ) {
				unset( $aliasGroups[$languageCode] );
			}
		}
	}

	/**
	 * @return void
	 */
	private static function trimStartAndEnd(
		array &$labels,
		array &$descriptions,
		array &$aliasGroups,
		&$schemaText
	) {
		foreach ( $labels as &$label ) {
			$label = self::trimWhitespaceAndControlChars( $label );
		}
		foreach ( $descriptions as &$description ) {
			$description = self::trimWhitespaceAndControlChars( $description );
		}
		foreach ( $aliasGroups as &$aliasGroup ) {
			$aliasGroup = array_filter( array_map(
				[ self::class, 'trimWhitespaceAndControlChars' ],
				$aliasGroup
			) );
		}
		$schemaText = self::trimWhitespaceAndControlChars( $schemaText );
	}

	/**
	 * @param  string $string The string to trim
	 * @return string The trimmed string after applying the regex
	 */
	private static function trimWhitespaceAndControlChars( $string ) {
		return preg_replace( '/^[\p{Z}\p{Cc}\p{Cf}]+|[\p{Z}\p{Cc}\p{Cf}]+$/u', '', $string );
	}

	/**
	 * @param string[] &$array
	 * @return string[]
	 */
	private static function filterEmptyStrings( array $array ): array {
		foreach ( $array as $key => $value ) {
			if ( $value === '' ) {
				unset( $array[$key] );
			}
		}
		return $array;
	}

	/**
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param array<string,string[]> $aliasGroups
	 * @param string $schemaText
	 */
	private static function validateParameters(
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText
	) {
		self::validateLangCodes( $labels, $descriptions, $aliasGroups );
		self::validateParameterTypes(
			$labels,
			$descriptions,
			$aliasGroups,
			$schemaText
		);
		self::validateIdentifyingInfoMaxLength(
			$labels,
			$descriptions,
			$aliasGroups
		);
		self::validateSchemaMaxLength( $schemaText );
	}

	private static function validateLangCodes(
		array $labels,
		array $descriptions,
		array $aliasGroups
	) {
		$providedLangCodes = array_unique(
			array_merge(
				array_keys( $labels ),
				array_keys( $descriptions ),
				array_keys( $aliasGroups )
			)
		);
		$invalidLangCodes = array_filter(
			$providedLangCodes,
			function( $langCode ) {
				return !Language::isSupportedLanguage( $langCode );
			}
		);
		if ( count( $invalidLangCodes ) > 0 ) {
			throw new InvalidArgumentException( 'language codes must be valid!' );
		}
	}

	private static function validateParameterTypes(
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText
	) {
		if ( count( array_filter( $labels, 'is_string' ) ) !== count( $labels )
			|| count( array_filter( $descriptions, 'is_string' ) ) !== count( $descriptions )
			|| !is_string( $schemaText )
			|| count( array_filter( $aliasGroups, [ self::class, 'isSequentialArrayOfStrings' ] ) )
			!== count( $aliasGroups )
		) {
			throw new InvalidArgumentException(
				'language, label, description and schemaText must be strings '
				. 'and aliases must be an array of strings'
			);
		}
	}

	private static function validateIdentifyingInfoMaxLength(
		array $labels,
		array $descriptions,
		array $aliasGroups
	) {
		foreach ( $labels as $label ) {
			self::validateLDAMaxLength( $label );
		}

		foreach ( $descriptions as $description ) {
			self::validateLDAMaxLength( $description );
		}

		foreach ( $aliasGroups as $aliasGroup ) {
			self::validateLDAMaxLength( implode( '', $aliasGroup ) );
		}
	}

	private static function validateLDAMaxLength( $localizedString ) {
		$maxLengthChars = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'WBSchemaNameBadgeMaxSizeChars' );
		if ( mb_strlen( $localizedString ) > $maxLengthChars ) {
			throw new InvalidArgumentException(
				'Identifying information is longer than the allowed max of ' . $maxLengthChars . ' characters!'
			);
		}
	}

	private static function validateSchemaMaxLength( $schemaText ) {
		$maxLengthBytes = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'WBSchemaSchemaTextMaxSizeBytes' );
		if ( strlen( $schemaText ) > $maxLengthBytes ) {
			throw new InvalidArgumentException(
				'Schema text is longer than the allowed max of ' . $maxLengthBytes . ' bytes!'
			);
		}
	}

	private static function isSequentialArrayOfStrings( array $array ) {
		$values = array_values( $array );
		if ( $array !== $values ) {
			return false; // array is associative - fast solution see: https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
		}
		foreach ( $values as $value ) {
			if ( !is_string( $value ) ) {
				return false;
			}
		}
		return true;
	}

}
