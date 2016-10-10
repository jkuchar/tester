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

						$i = 0;
						$coveredBy[$line] = array_map(function(TestInstance $instance) use (&$i) {
							$name = sprintf("%2s. ", ++$i) . $instance->getTestName();
							if($instanceDetails = $instance->getInstanceName()) {
								$name .= " " . $instanceDetails;
							}
							return $name;
						}, $this->coverage->getCoveredBy($entry, $line));
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
}
