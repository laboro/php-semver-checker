<?php

namespace PHPSemVerChecker\Test\Comparator;

use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\UnionType;
use PHPSemVerChecker\Comparator\Type;
use PHPSemVerChecker\Test\TestCase;

class TypeComparatorTest extends TestCase
{
	/**
	 * @dataProvider isSameProvider
	 */
	public function testIsSame($typeA, $typeB)
	{
		$this->assertTrue(Type::isSame($typeA, $typeB));
	}

	public function isSameProvider()
	{
		return [
			[Name::concat(null, 'test'), Name::concat(null, 'test')],
			['test', 'test'],
			[null, null],
			[new UnionType([new Identifier('self'), new Identifier('array')]), new UnionType([new Identifier('array'), new Identifier('self')])],
		];
	}

	/**
	 * @dataProvider isNotSameProvider
	 */
	public function testIsNotSame($typeA, $typeB)
	{
		$this->assertFalse(Type::isSame($typeA, $typeB));
	}

	public function isNotSameProvider()
	{
		return [
			[Name::concat(null, 'test'), Name::concat(null, 'test1')],
			['test', 'test1'],
			[null, 'test'],
			[new UnionType([new Identifier('self'), new Identifier('array')]), null],
		];
	}

	/**
	 * @dataProvider getProvider
	 */
	public function testGet($type, $expected)
	{
		$this->assertSame($expected, Type::get($type));
	}

	public function getProvider()
	{
		return [
			[null, null],
			['test', 'test'],
			[Name::concat('namespaced', 'test'), 'namespaced\test'],
			[new NullableType(new Identifier('test')), '?test'],
			[new NullableType(Name::concat('namespaced', 'test')), '?namespaced\test'],
			[new UnionType([new Identifier('self'), new Identifier('array')]), 'array|self'],
		];
	}

	/**
	 * A parameter with a non-nullable type and a null default accepts null as well, so the implicit
	 * and the explicit spelling declare the same type.
	 * Rewriting one as the other, which the PHP 8.4 deprecation of implicit nullable parameters requires,
	 * is not a change of signature.
	 *
	 * @dataProvider parameterTypeIsSameProvider
	 */
	public function testGetForParameterIsSame(Param $parameterA, Param $parameterB)
	{
		$this->assertSame(Type::getForParameter($parameterA), Type::getForParameter($parameterB));
	}

	public function parameterTypeIsSameProvider()
	{
		return [
			'implicit and explicit nullable' => [
				self::param(new Identifier('string'), true),
				self::param(new NullableType(new Identifier('string')), true),
			],
			'implicit nullable union' => [
				self::param(new UnionType([new Identifier('string'), new Identifier('int')]), true),
				self::param(new UnionType([new Identifier('string'), new Identifier('int'), new Identifier('null')]), true),
			],
			'nullable shorthand and union spelling' => [
				self::param(new NullableType(new Identifier('string')), false),
				self::param(new UnionType([new Identifier('string'), new Identifier('null')]), false),
			],
			'mixed is already nullable and cannot be marked nullable' => [
				self::param(new Identifier('mixed'), true),
				self::param(new Identifier('mixed'), false),
			],
			'no type on either side' => [
				self::param(null, true),
				self::param(null, false),
			],
		];
	}

	/**
	 * @dataProvider parameterTypeIsNotSameProvider
	 */
	public function testGetForParameterIsNotSame(Param $parameterA, Param $parameterB)
	{
		$this->assertNotSame(Type::getForParameter($parameterA), Type::getForParameter($parameterB));
	}

	public function parameterTypeIsNotSameProvider()
	{
		return [
			'nullable added without a null default' => [
				self::param(new Identifier('string'), false),
				self::param(new NullableType(new Identifier('string')), false),
			],
			'the null default was removed, so the parameter no longer accepts null' => [
				self::param(new Identifier('string'), true),
				self::param(new Identifier('string'), false),
			],
			'a different type with the same null default' => [
				self::param(new Identifier('string'), true),
				self::param(new Identifier('int'), true),
			],
			'a type was added' => [
				self::param(null, true),
				self::param(new Identifier('string'), true),
			],
		];
	}

	private static function param($type, bool $nullDefault): Param
	{
		return new Param(
			new Variable('parameter'),
			$nullDefault ? new ConstFetch(new Name('null')) : null,
			$type
		);
	}

}
