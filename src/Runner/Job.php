<?php

/**
 * This file is part of the Nette Tester.
 * Copyright (c) 2009 David Grudl (https://davidgrudl.com)
 */

namespace Tester\Runner;

use Tester\Helpers;


/**
 * Single test job.
 */
class Job
{
	const
		CODE_NONE = -1,
		CODE_OK = 0,
		CODE_SKIP = 177,
		CODE_FAIL = 178,
		CODE_ERROR = 255;

	/** waiting time between process activity check in microseconds */
	const RUN_USLEEP = 10000;

	const
		RUN_ASYNC = 1,
		RUN_COLLECT_ERRORS = 2;

	/** @var string  test file */
	private $file;

	/** @var string[]  test arguments */
	private $args;

	/** @var string[]  environment variables for test */
	private $envVars;

	/** @var string  test output */
	private $output;

	/** @var string|NULL  test error output */
	private $errorOutput;

	/** @var string[]  output headers */
	private $headers;

	/** @var PhpInterpreter */
	private $interpreter;

	/** @var resource */
	private $proc;

	/** @var resource */
	private $stdout;

	/** @var resource */
	private $stderr;

	/** @var int */
	private $exitCode = self::CODE_NONE;

	/** @var float */
	private $time;


	/**
	 * @param  string  test file name
	 * @return void
	 */
	public function __construct($testFile, PhpInterpreter $interpreter, array $args = NULL, array $envVars = NULL)
	{
		$this->file = (string) $testFile;
		$this->interpreter = $interpreter;
		$this->args = (array) $args;
		$this->envVars = (array) $envVars;
	}


	/**
	 * @param  string
	 * @param  string
	 * @return void
	 */
	public function setEnvironmentVariable($name, $value)
	{
		$this->envVars[$name] = $value;
	}


	/**
	 * @param  string
	 * @return string
	 */
	public function getEnvironmentVariable($name)
	{
		return $this->envVars[$name];
	}


	/**
	 * Runs single test.
	 * @param  int RUN_ASYNC | RUN_COLLECT_ERRORS
	 * @return void
	 */
	public function run($flags = NULL)
	{
		$this->time = microtime(TRUE);

		foreach ($this->envVars as $name => $value) {
			putenv("$name=$value");
		}

		$this->proc = proc_open(
			$this->interpreter->getCommandLine()
			. ' -d register_argc_argv=on ' . Helpers::escapeArg($this->file) . ' ' . $this->buildArgumentsList(),
			[
				['pipe', 'r'],
				['pipe', 'w'],
				['pipe', 'w'],
			],
			$pipes,
			dirname($this->file),
			NULL,
			['bypass_shell' => TRUE]
		);

		foreach (array_keys($this->envVars) as $name) {
			putenv($name);
		}

		list($stdin, $this->stdout, $stderr) = $pipes;
		fclose($stdin);
		if ($flags & self::RUN_COLLECT_ERRORS) {
			$this->stderr = $stderr;
		} else {
			fclose($stderr);
		}

		if ($flags & self::RUN_ASYNC) {
			stream_set_blocking($this->stdout, 0); // on Windows does not work with proc_open()
			if ($this->stderr) {
				stream_set_blocking($this->stderr, 0);
			}
		} else {
			while ($this->isRunning()) {
				usleep(self::RUN_USLEEP); // stream_select() doesn't work with proc_open()
			}
		}
	}


	/**
	 * Checks if the test is still running.
	 * @return bool
	 */
	public function isRunning()
	{
		if (!is_resource($this->stdout)) {
			return FALSE;
		}
		$this->output .= stream_get_contents($this->stdout);
		if ($this->stderr) {
			$this->errorOutput .= stream_get_contents($this->stderr);
		}

		$status = proc_get_status($this->proc);
		if ($status['running']) {
			return TRUE;
		}

		fclose($this->stdout);
		if ($this->stderr) {
			fclose($this->stderr);
		}
		$code = proc_close($this->proc);
		$this->exitCode = $code === self::CODE_NONE ? $status['exitcode'] : $code;
		$this->time = microtime(TRUE) - $this->time;

		if ($this->interpreter->isCgi() && count($tmp = explode("\r\n\r\n", $this->output, 2)) >= 2) {
			list($headers, $this->output) = $tmp;
			foreach (explode("\r\n", $headers) as $header) {
				$a = strpos($header, ':');
				if ($a !== FALSE) {
					$this->headers[trim(substr($header, 0, $a))] = (string) trim(substr($header, $a + 1));
				}
			}
		}
		return FALSE;
	}


	/**
	 * Returns test file path.
	 * @return string
	 */
	public function getFile()
	{
		return $this->file;
	}


	/**
	 * Returns script arguments.
	 * @return string[]
	 */
	public function getArguments()
	{
		return $this->args;
	}


	/**
	 * Returns exit code.
	 * @return int
	 */
	public function getExitCode()
	{
		return $this->exitCode;
	}


	/**
	 * Returns test output.
	 * @return string
	 */
	public function getOutput()
	{
		return $this->output;
	}


	/**
	 * Returns test error output.
	 * @return string|NULL
	 */
	public function getErrorOutput()
	{
		return $this->errorOutput;
	}


	/**
	 * Returns output headers.
	 * @return string[]
	 */
	public function getHeaders()
	{
		return $this->headers;
	}


	/**
	 * Returns how long the job run.
	 * @return float
	 */
	public function getTime()
	{
		return $this->time;
	}


	/**
	 * @return string
	 */
	private function buildArgumentsList()
	{
		$args = [];

		foreach ($this->args as $key => $arg) {
			if (is_numeric($key)) {
				$args[] = Helpers::escapeArg($arg);

			} else {
				$args[] = Helpers::escapeArg("--$key=$arg");
			}
		}

		return implode(' ', $args);
	}

}
