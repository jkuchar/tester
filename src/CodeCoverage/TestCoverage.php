<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (https://davidgrudl.com)
 */

namespace Tester\CodeCoverage;

/**
 * Represents coverage for given TODO
 * @internal
 */
final class TestCoverage
{
	/** @var array[] */
	private $negative;

	/** @var array[] */
	private $positive;

	/** @var array[]|NULL */
	private $data = NULL;

	/**
	 * TestCoverage constructor.
	 * @param  array[]  in format [file => [line => status]]
	 * @param  array[]  in format [file => [line => status]]
	 */
	public function __construct(array $negative, array $positive)
	{
		$this->negative = $negative;
		$this->positive = $positive;
	}

	public static function _empty() // keywords as names are not supported < php7
	{
		return new self([], []);
	}

	/**
	 * Returns coverage data for all covered files
	 * @return array[]
	 * @internal
	 * @todo private
	 */
	public function getData()
	{
		if(is_array($this->data)) {
			return $this->data;
		}
		return $this->data = array_replace_recursive($this->negative, $this->positive);
	}

	/**
	 * Return array of all executed files
	 * @return array
	 */
	public function getExecutedFiles()
	{
		return array_keys($this->getData());
	}

	public function hasBeenExecuted($path)
	{
		return isset($this->getData()[$path]);
	}

	/**
	 * @param  string  path to source file
	 * @return  array|NULL  Key is line number; values
	 */
	public function getForFile($path)
	{
		$data = $this->getData();
		return isset($data[$path]) ? $data[$path] : [];
	}

	public function mergeWith(TestCoverage $testCoverage)
	{
		return new self(
			array_replace_recursive($this->negative, $testCoverage->negative),
			array_replace_recursive($this->positive, $testCoverage->positive)
		);
	}

}