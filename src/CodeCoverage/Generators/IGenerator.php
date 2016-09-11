<?php
/**
 * This file is part of nette-tester.
 */
namespace Tester\CodeCoverage\Generators;

/**
 * Code coverage report generator.
 */
interface IGenerator
{


	public function render($file = NULL);


	/**
	 * @return float
	 */
	public function getCoveredPercent();

}