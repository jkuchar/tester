<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (https://davidgrudl.com)
 */

namespace Tester\CodeCoverage;

use Tester\Runner\TestInstance;

/**
 * Holds coverage for given test runs
 * @package Tester\CodeCoverage
 * @todo find better name: CompositeCoverage?
 * @todo more logic should be moved here
 */
class CoverageData
{

	/** @var TestInstance[]  where key is instance id */
	private $testInstances;

	/** @var TestCoverage[] */
	private $testsCoverage;

	/** @var TestCoverage */
	private $coverageSummary;

	/** @var array */
	public $acceptFiles = ['php', 'phpc', 'phpt', 'phtml'];

	const
		CODE_DEAD = -2,
		CODE_UNTESTED = -1,
		CODE_TESTED = 1;


	public function __construct($file, array $testInstances, $source = NULL)
	{
		if (!is_file($file)) {
			throw new \Exception("File '$file' is missing.");
		}

		$this->testInstances = [];
		foreach($testInstances as $testInstance) {
			if(!$testInstance instanceof TestInstance) {
				throw new \Exception("Test instance array must contain only TestInstance.");
			}
			$this->testInstances[$testInstance->getId()] = $testInstance;
		}

		$this->testsCoverage = @unserialize(file_get_contents($file)); // @ is escalated to exception
		if (!is_array($this->testsCoverage)) {
			throw new \Exception("Coverage file '$file' has not been properly initialized.");
		}

		$this->coverageSummary = TestCoverage::_empty();
		foreach($this->testsCoverage as $testCoverage) {
			if(!$testCoverage instanceof TestCoverage) {
				throw new \Exception("Coverage file '$file' contains unexpected data.");
			}
			$this->coverageSummary = $this->coverageSummary->mergeWith($testCoverage);
		}

		if (!$source) {
			$source = $this->detectSourcePath();

		} elseif (!file_exists($source)) {
			throw new \Exception("File or directory '$source' is missing.");
		}

		$this->source = realpath($source);
	}


	private function detectSourcePath()
	{
		$executedFiles = $this->coverageSummary->getExecutedFiles();
		$source = reset($executedFiles);
		for ($i = 0; $i < strlen($source); $i++) {
			foreach ($executedFiles as $s) {
				if (!isset($s[$i]) || $source[$i] !== $s[$i]) {
					$source = substr($source, 0, $i);
					break 2;
				}
			}
		}
		return dirname($source . 'x'); // returns '.' for empty $source
	}


	/**
	 * @param  string[]  file extensions to accept
	 * @return \CallbackFilterIterator
	 */
	public function getSourceIterator(array $acceptFiles = NULL) // todo: move param to $this?
	{
		$acceptFiles = $acceptFiles === NULL ? $this->acceptFiles : $acceptFiles;
		$iterator = is_dir($this->source)
			? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->source))
			: new \ArrayIterator([new \SplFileInfo($this->source)]);

		return new \CallbackFilterIterator($iterator, function (\SplFileInfo $file) use ($acceptFiles) {
			return $file->getBasename()[0] !== '.'  // . or .. or .gitignore
			&& in_array($file->getExtension(), $acceptFiles, TRUE);
		});
	}


	/**
	 * @param string instance identifier
	 * @return TestCoverage|NULL
	 */
	public function getFor($testInstanceId)
	{
		return isset($this->testsCoverage[$testInstanceId]) ?
			$this->testsCoverage[$testInstanceId] : NULL;
	}


	/**
	 * @param string test instance identifier
	 * @return TestInstance|NULL
	 */
	public function getTestInstanceFor($testInstanceId)
	{
		return isset($this->testInstances[$testInstanceId]) ?
			$this->testInstances[$testInstanceId] : NULL;
	}


	/**
	 * @return TestCoverage
	 */
	public function getSummary()
	{
		return $this->coverageSummary;
	}


	/**
	 * @return TestInstance[]
	 */
	public function getTestInstances()
	{
		return $this->testInstances;
	}


	/**
	 * @return TestCoverage[]
	 */
	public function getTestsCoverage()
	{
		return $this->testsCoverage;
	}


	/**
	 * @return string
	 */
	public function getSource()
	{
		return $this->source;
	}


	/**
	 * @return string the path to covered sources
	 */
	public function getSourcesDirectory()
	{
		return is_dir($this->source) ? $this->source : dirname($this->source);
	}


}