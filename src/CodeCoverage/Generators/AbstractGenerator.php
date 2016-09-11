<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (https://davidgrudl.com)
 */

namespace Tester\CodeCoverage\Generators;
use Tester\CodeCoverage\TestCoverage;
use Tester\CodeCoverage\CoverageData;
use Tester\Runner\TestInstance;

/**
 * Code coverage report generator.
 */
abstract class AbstractGenerator implements IGenerator
{

	/** @var CoverageData */
	protected $coverage;


	public function __construct(CoverageData $testCoverageResult)
	{
		$this->coverage = $testCoverageResult;
	}


	public function render($file = NULL)
	{
		$handle = $file ? @fopen($file, 'w') : STDOUT; // @ is escalated to exception
		if (!$handle) {
			throw new \Exception("Unable to write to file '$file'.");
		}

		ob_start(function ($buffer) use ($handle) { fwrite($handle, $buffer); }, 4096);
		try {
			$this->renderSelf();
		} catch (\Exception $e) {
		}
		ob_end_flush();
		fclose($handle);

		if (isset($e)) {
			if ($file) {
				unlink($file);
			}
			throw $e;
		}
	}


	/**
	 * @return float
	 */
	public function getCoveredPercent()
	{
		return $this->getTotalSum() ? $this->getCoveredSum() * 100 / $this->getTotalSum() : 0;
	}


	abstract protected function renderSelf();

	abstract protected function getCoveredSum();
	abstract protected function getTotalSum();

}
