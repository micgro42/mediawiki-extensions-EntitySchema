<?php

namespace Wikibase\Schema\DataAccess;

use InvalidArgumentException;
use Language;
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
		foreach ( $aliasGroups as &$aliasGroup ) {
			$aliasGroup = array_values( array_unique( $aliasGroup ) );
		}
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
