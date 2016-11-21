<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (https://davidgrudl.com)
 */

namespace Tester\CodeCoverage\Generators;
use Tester\CodeCoverage\CoverageData;
use Tester\Runner\TestInstance;

/**
 * Code coverage report generator.
 */
class HtmlGenerator extends AbstractGenerator
{
	/** @var string */
	private $title;

	/** @var array */
	private $files = [];

	/** @var array */
	public static $classes = [
		CoverageData::CODE_TESTED   => 't', // tested
		CoverageData::CODE_UNTESTED => 'u', // untested
		CoverageData::CODE_DEAD     => 'dead', // dead code
	];

	/** @var int */
	private $totalSum = 0;

	/** @var int */
	private $coveredSum = 0;


	/**
	 * @param  string  path to coverage.dat file
	 * @param  TestInstance[]
	 * @param  string  path to source file/directory
	 * @param  string
	 */
	public function __construct(CoverageData $coverageResult, $title = NULL)
	{
		parent::__construct($coverageResult);
		$this->title = $title;
	}


	protected function renderSelf()
	{
		$this->setupHighlight();
		$this->parse();

		$title = $this->title;
		$classes = self::$classes;
		$files = $this->files;
		$coveredPercent = $this->getCoveredPercent();

		$testReach = $this->prepareTestsReach();

		include __DIR__ . '/template.phtml';
	}


	private function setupHighlight()
	{
		ini_set('highlight.comment', 'hc');
		ini_set('highlight.default', 'hd');
		ini_set('highlight.html', 'hh');
		ini_set('highlight.keyword', 'hk');
		ini_set('highlight.string', 'hs');
	}


	private function parse()
	{
		if (count($this->files) > 0) {
			return;
		}

		$summary = $this->coverage->getSummary();

		$this->files = [];
		foreach ($this->coverage->getSourceIterator() as $entry) {
			$entry = (string) $entry;
			$coveredBy = [];

			$coverage = $covered = $total = 0;
			$executed = $summary->hasCoverage($entry);
			$lines = [];
			if ($executed) {
				$lines = $summary->getForFile($entry);
				foreach ($lines as $line => $flag) {
					if ($flag >= CoverageData::CODE_UNTESTED) {
						$total++;
					}
					if ($flag >= CoverageData::CODE_TESTED) {
						$covered++;

						// get proper name for test instance
						$coveredByNames = array_map(function(TestInstance $instance) {
							$name = $instance->getTestName();
							if($instanceDetails = $instance->getInstanceName()) {
								$name .= " " . $instanceDetails;
							}
							return $name;
						}, $this->coverage->getCoveredBy($entry, $line));
						// sort them
						sort($coveredByNames);
						// number them
						$i = 0;
						$coveredBy[$line] = array_map(function($value) use (&$i) {
							return sprintf("%2s. ", ++$i) . $value;
						}, $coveredByNames);
					}
				}
				$coverage = round($covered * 100 / $total);
				$this->totalSum += $total;
				$this->coveredSum += $covered;
			} else {
				$this->totalSum += count(file($entry, FILE_SKIP_EMPTY_LINES));
			}

			$light = $total ? $total < 5 : count(file($entry)) < 50;
			$this->files[] = (object) [
				'name' => str_replace($this->coverage->getSourcesDirectory() . DIRECTORY_SEPARATOR, '', $entry),
				'file' => $entry,
				'lines' => $lines,
				'coveredBy' => $coveredBy,
				'coverage' => $coverage,
				'total' => $total,
				'class' => $light ? 'light' : ($executed ? NULL : 'not-loaded'),
			];
		}
	}


	protected function getCoveredSum()
	{
		return $this->coveredSum;
	}


	protected function getTotalSum()
	{
		return $this->totalSum;
	}


	/**
	 * @return array[] which will be in this format: [ [ name => string, coveredLines => int ], ... ]
	 */
	private function prepareTestsReach()
	{
		// extract number of covered lines per test
		$testsLineReach = []; // testId => lines covered <int>
		foreach ($this->coverage->getTestsCoverage() as $testId => $testCoverage) {
			$testsLineReach[$testId] = $testCoverage->countTestedLines();
		}

		// sort descending
		assert(asort($testsLineReach) === TRUE);

		// average
		$avg = array_sum($testsLineReach) / count($testsLineReach);

		// compute standard deviation
			// Function to calculate square of value - mean

			// Function to calculate standard deviation (uses sd_square)
			$fn_standardDeviation = function ($array): float {
				$sd_square = function($x, $mean) { return pow($x - $mean,2); };
				// square root of sum of squares devided by N-1
				return sqrt(array_sum(array_map($sd_square, $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
			};
		$fn_standardDeviation = $fn_standardDeviation($testsLineReach);

		// produce data for view
		$reach = [];
		foreach ($testsLineReach as $testID => $covered) {
			$testInstance = $this->coverage->getTestInstanceFor($testID);
			$reach[] = [
				"id" => $testID,
				"name" => $testInstance->getTestName(),
				"linesCovered" => $covered,
				"standardDeviation" => $fn_standardDeviation,
				"fromAverage" => $covered - $avg
			];
		}
		return array_reverse($reach);
	}
}
