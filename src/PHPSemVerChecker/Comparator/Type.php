<?php
declare(strict_types=1);

namespace PHPSemVerChecker\Comparator;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\UnionType;

class Type
{
	/**
	 * @param \PhpParser\Node\Name|string|null $typeA
	 * @param \PhpParser\Node\Name|string|null $typeB
	 * @return bool
	 */
	public static function isSame($typeA, $typeB): bool
	{
		$typeA = self::get($typeA);
		$typeB = self::get($typeB);
		return $typeA === $typeB;
	}

	/**
	 * The effective type of a parameter, which is not always the type it declares:
	 * a parameter with a non-nullable type and a null default accepts null as well,
	 * so `T $x = null` and `?T $x = null` declare the same type.
	 * The implicit form is deprecated as of PHP 8.4, and rewriting one as the other
	 * is not a change of signature.
	 *
	 * @return string|null
	 */
	public static function getForParameter(Param $parameter): ?string
	{
		$type = self::get($parameter->type);
		if ($type === null) {
			return null;
		}

		if (self::isNullDefault($parameter->default)) {
			$type = self::withNull($type);
		}

		return self::canonical($type);
	}

	/**
	 * @param \PhpParser\Node\Name|\PhpParser\Node\NullableType|\PhpParser\Node\UnionType|string|null $type
	 * @return string|null
	 */
	public static function get($type): ?string
	{
		if ( ! is_object($type)) {
			return $type;
		}

		if ($type instanceof NullableType) {
			return '?' . static::get($type->type);
		}

		if ($type instanceof UnionType) {
			$types = [];
			foreach ($type->types as $unionType) {
				$types[] = static::get($unionType);
			}
			// Sort to ensure consistent comparison even with different order of types
			sort($types);
			return implode('|', $types);
		}

		return $type->toString();
	}

	/**
	 * `?T` and `T|null` are the same type, but one is a NullableType node and the other a UnionType,
	 * so they need a common spelling before they can be compared.
	 */
	private static function canonical(string $type): string
	{
		if ($type[0] !== '?') {
			return $type;
		}

		$types = [substr($type, 1), 'null'];
		sort($types);

		return implode('|', $types);
	}

	private static function isNullDefault(?Expr $default): bool
	{
		return $default instanceof ConstFetch && strtolower($default->name->toString()) === 'null';
	}

	private static function withNull(string $type): string
	{
		// `mixed` already accepts null and cannot be made nullable
		if ($type === 'mixed' || $type === 'null' || $type[0] === '?') {
			return $type;
		}

		if (strpos($type, '|') !== false) {
			$types = explode('|', $type);
			if (in_array('null', $types, true)) {
				return $type;
			}
			$types[] = 'null';
			// Sort to match the order get() produces for union types
			sort($types);
			return implode('|', $types);
		}

		return '?' . $type;
	}
}
