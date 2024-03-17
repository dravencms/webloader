<?php

declare(strict_types = 1);

namespace WebLoader;

/**
 * Compiler
 *
 * @author Jan Marek
 */
class Compiler
{

	/** @var string */
	private $outputDir;

	/** @var bool */
	private $joinFiles = true;

	/** @var array */
	private $filters = [];

	/** @var array */
	private $fileFilters = [];

	/** @var IFileCollection */
	private $collection;

	/** @var IOutputNamingConvention */
	private $namingConvention;

	/** @var bool */
	private $checkLastModified = true;

	/** @var bool */
	private $debugging = false;

	/** @var bool */
	private $async = false;

	/** @var bool */
	private $defer = false;

	/** @var string|null */
	private $nonce;

	/** @var bool */
	private $absoluteUrl = false;


	public function __construct(IFileCollection $files, IOutputNamingConvention $convention, string $outputDir)
	{
		$this->collection = $files;
		$this->namingConvention = $convention;
		$this->setOutputDir($outputDir);
	}


	/**
	 * Create compiler with predefined css output naming convention
	 */
	public static function createCssCompiler(IFileCollection $files, string $outputDir): self
	{
		return new static($files, DefaultOutputNamingConvention::createCssConvention(), $outputDir);
	}


	/**
	 * Create compiler with predefined javascript output naming convention
	 */
	public static function createJsCompiler(IFileCollection $files, string $outputDir): self
	{
		return new static($files, DefaultOutputNamingConvention::createJsConvention(), $outputDir);
	}


	public function enableDebugging(bool $allow = true): void
	{
		$this->debugging = (bool) $allow;
	}


	public function getNonce(): ?string
	{
		return $this->nonce ?? $this->getGlobalNonce();
	}


	public function setNonce(?string $nonce): void
	{
		$this->nonce = $nonce;
	}


	public function getOutputDir(): string
	{
		return $this->outputDir;
	}


	public function setOutputDir(string $tempPath): void
	{
		$tempPath = Path::normalize($tempPath);

		if (!is_dir($tempPath)) {
			throw new FileNotFoundException("Temp path '$tempPath' does not exist.");
		}

		if (!is_writable($tempPath)) {
			throw new InvalidArgumentException("Directory '$tempPath' is not writeable.");
		}

		$this->outputDir = $tempPath;
	}


	/**
	 * Get join files
	 */
	public function getJoinFiles(): bool
	{
		return $this->joinFiles;
	}


	/**
	 * Set join files
	 */
	public function setJoinFiles(bool $joinFiles): void
	{
		$this->joinFiles = $joinFiles;
	}


	public function isAsync(): bool
	{
		return $this->async;
	}


	public function setAsync(bool $async): self
	{
		$this->async = $async;
		return $this;
	}


	public function isDefer(): bool
	{
		return $this->defer;
	}


	public function setDefer(bool $defer): self
	{
		$this->defer = $defer;
		return $this;
	}


	public function isAbsoluteUrl(): bool
	{
		return $this->absoluteUrl;
	}


	public function setAbsoluteUrl(bool $absoluteUrl): self
	{
		$this->absoluteUrl = $absoluteUrl;
		return $this;
	}


	/**
	 * Set check last modified
	 */
	public function setCheckLastModified(bool $checkLastModified): void
	{
		$this->checkLastModified = $checkLastModified;
	}


	/**
	 * Get last modified timestamp of newest file
	 */
	public function getLastModified(?array $files = null): int
	{
		if ($files === null) {
			$files = $this->collection->getFiles();
		}

		$modified = 0;

		foreach ($files as $file) {
			$modified = max($modified, filemtime((string) realpath($file)));
		}

		return (int) $modified;
	}


	/**
	 * Get joined content of all files
	 */
	public function getContent(?array $files = null): string
	{
		if ($files === null) {
			$files = $this->collection->getFiles();
		}

		// load content
		$content = '';
		foreach ($files as $file) {
			$content .= PHP_EOL . $this->loadFile($file);
		}

		// apply filters
		foreach ($this->filters as $filter) {
			$content = call_user_func($filter, $content, $this);
		}

		return $content;
	}


	/**
	 * Load content and save file
	 */
	public function generate(): array
	{
		$files = $this->collection->getFiles();

		if (!count($files)) {
			return [];
		}

		if ($this->joinFiles) {
			$watchFiles = $this->checkLastModified ? array_unique(array_merge($files, $this->collection->getWatchFiles())) : [];

			return [
				$this->generateFiles($files, $watchFiles),
			];

		} else {
			$arr = [];

			foreach ($files as $file) {
				$watchFiles = $this->checkLastModified ? array_unique(array_merge([$file], $this->collection->getWatchFiles())) : [];
				$arr[] = $this->generateFiles([$file], $watchFiles);
			}

			return $arr;
		}
	}


	protected function generateFiles(array $files, array $watchFiles = [])
	{
		$name = $this->namingConvention->getFilename($files, $this);
		$path = $this->outputDir . '/' . $name;
		$lastModified = $this->checkLastModified ? $this->getLastModified($watchFiles) : 0;

		if (!file_exists($path) || $lastModified > filemtime($path) || $this->debugging === true) {
			$outPath = in_array('nette.safe', stream_get_wrappers(), true) ? 'nette.safe://' . $path : $path;
			file_put_contents($outPath, $this->getContent($files));
		}

		return new File($name, (int) filemtime($path), $files);
	}


	protected function loadFile(string $file): string
	{
		$content = file_get_contents($file);

		foreach ($this->fileFilters as $filter) {
			$content = call_user_func($filter, $content, $this, $file);
		}

		return $content;
	}


	public function getFileCollection(): IFileCollection
	{
		return $this->collection;
	}


	public function getOutputNamingConvention(): IOutputNamingConvention
	{
		return $this->namingConvention;
	}


	public function setFileCollection(IFileCollection $collection): void
	{
		$this->collection = $collection;
	}


	public function setOutputNamingConvention(IOutputNamingConvention $namingConvention): void
	{
		$this->namingConvention = $namingConvention;
	}


	public function addFilter(callable $filter): void
	{
		$this->filters[] = $filter;
	}


	public function getFilters(): array
	{
		return $this->filters;
	}


	public function addFileFilter(callable $filter): void
	{
		$this->fileFilters[] = $filter;
	}


	public function getFileFilters(): array
	{
		return $this->fileFilters;
	}


	/** Copy from \Tracy\Helpers::getNonce() */
	private function getGlobalNonce(): ?string
	{
		return preg_match('#^Content-Security-Policy(?:-Report-Only)?:.*\sscript-src\s+(?:[^;]+\s)?\'nonce-([\w+/]+=*)\'#mi', implode("\n", headers_list()), $m)
			? $m[1]
			: null;
	}
}
