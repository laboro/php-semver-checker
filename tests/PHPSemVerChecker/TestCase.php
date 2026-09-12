<?php

namespace PHPSemVerChecker\Test;

use Mockery as m;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
	protected function tearDown(): void
	{
		m::close();

		parent::tearDown();
	}

	/**
	 * Assert that no operation with the given code was reported, at any context and level.
	 */
	protected function assertNoOperationWithCode(\PHPSemVerChecker\Report\Report $report, $code)
	{
		$codes = [];
		foreach ($report->getDifferences() as $levels) {
			foreach ($levels as $operations) {
				foreach ($operations as $operation) {
					$codes[] = $operation->getCode();
				}
			}
		}

		$this->assertNotContains($code, $codes, sprintf('Did not expect %s to be reported.', $code));
	}

}
