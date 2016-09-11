<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (https://davidgrudl.com)
 */

namespace Tester\CodeCoverage\Generators;
use Tester\CodeCoverage\TestCoverage;

/**
 * Code coverage report generator.
 */
abstract class AbstractGenerator
{
	const
		CODE_DEAD = -2,
		CODE_UNTESTED = -1,
		CODE_TESTED = 1;

	/** @var array */
	public $acceptFiles = ['php', 'phpc', 'phpt', 'phtml'];

	/** @var array */
	protected $data;

	/** @var string */
	protected $source;

	/** @var int */
	protected $totalSum = 0;

	/** @var int */
	protected $coveredSum = 0;

	/** @var TestCoverage */
	protected $coverage;

	/**
	 * @param  string  path to coverage.dat file
	 * @param  string  path to covered source file or directory
	 */
	public function __construct($file, $source = NULL)
	{
		if (!is_file($file)) {
			throw new \Exception("File '$file' is missing.");
		}

		$this->data = @unserialize(file_get_contents($file)); // @ is escalated to exception
		if (!is_array($this->data)) {
			throw new \Exception("Coverage file '$file' has not been properly initialized.");
		}

		$this->coverage = TestCoverage::_empty();
		foreach($this->data as $testCoverage) {
			if(!$testCoverage instanceof TestCoverage) {
				throw new \Exception("Coverage file '$file' contains unexpected data.");
			}
			$this->coverage = $this->coverage->mergeWith($testCoverage);
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
		$executedFiles = $this->coverage->getExecutedFiles();
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
		return $this->totalSum ? $this->coveredSum * 100 / $this->totalSum : 0;
	}


	/**
	 * @return \CallbackFilterIterator
	 */
	protected function getSourceIterator()
	{
		$iterator = is_dir($this->source)
			? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->source))
			: new \ArrayIterator([new \SplFileInfo($this->source)]);

		return new \CallbackFilterIterator($iterator, function (\SplFileInfo $file) {
			return $file->getBasename()[0] !== '.'  // . or .. or .gitignore
				&& in_array($file->getExtension(), $this->acceptFiles, TRUE);
		});
	}


	abstract protected function renderSelf();

}
