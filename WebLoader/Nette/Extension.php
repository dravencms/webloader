<?php

declare(strict_types = 1);

namespace WebLoader\Nette;

use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Helpers;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Finder;
use SplFileInfo;
use WebLoader\FileNotFoundException;

/**
 * @author Jan Marek
 */
class Extension extends CompilerExtension
{
	/** @var string */
	public const DEFAULT_TEMP_PATH = 'webtemp';

	/** @var string */
	public const EXTENSION_NAME = 'webloader';


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'jsDefaults' => Expect::structure([
				'checkLastModified' => Expect::bool(true),
				'debug' => Expect::bool(false),
				'sourceDir' => Expect::string('%wwwDir%'),
				'tempDir' => Expect::string('%wwwDir%/' . self::DEFAULT_TEMP_PATH),
				'tempPath' => Expect::string(self::DEFAULT_TEMP_PATH),
				'files' => Expect::array(),
				'watchFiles' => Expect::array(),
				'remoteFiles' => Expect::array(),
				'filters' => Expect::array(),
				'fileFilters' => Expect::array(),
				'joinFiles' => Expect::bool(true),
				'async' => Expect::bool(false),
				'defer' => Expect::bool(false),
				'nonce' => Expect::string()->nullable(),
				'absoluteUrl' => Expect::bool(false),
				'namingConvention' => Expect::string('@' . $this->prefix('jsNamingConvention')),
			]),
			'cssDefaults' => Expect::structure([
				'checkLastModified' => Expect::bool(true),
				'debug' => Expect::bool(false),
				'sourceDir' => Expect::string('%wwwDir%')->dynamic(),
				'tempDir' => Expect::string('%wwwDir%/' . self::DEFAULT_TEMP_PATH),
				'tempPath' => Expect::string(self::DEFAULT_TEMP_PATH),
				'files' => Expect::array(),
				'watchFiles' => Expect::array(),
				'remoteFiles' => Expect::array(),
				'filters' => Expect::array(),
				'fileFilters' => Expect::array(),
				'joinFiles' => Expect::bool(true),
				'async' => Expect::bool(false),
				'defer' => Expect::bool(false),
				'nonce' => Expect::string()->nullable(),
				'absoluteUrl' => Expect::bool(false),
				'namingConvention' => Expect::string('@' . $this->prefix('cssNamingConvention')),
			]),
			'js' => Expect::array(),
			'css' => Expect::array(),
			'debugger' => Expect::bool('%debugMode%'),
		]);
	}


    /**
     * @throws CompilationException
     */
    public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$params = $this->getContainerBuilder()->parameters;
		$json = json_encode($this->getConfig());
		$config = json_decode((string) $json, true);
		$config = Helpers::expand($config, $params);

		$builder->addDefinition($this->prefix('cssNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createCssConvention');

		$builder->addDefinition($this->prefix('jsNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createJsConvention');

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('tracyPanel'))
				->setClass('WebLoader\Nette\Diagnostics\Panel')
				->setArguments([$params['appDir']]);
		}

		$builder->parameters['webloader'] = $config;

		$loaderFactoryTempPaths = [];

		foreach (['css', 'js'] as $type) {
			foreach ($config[$type] as $name => $wlConfig) {
				/** @var array $wlConfig */
				$wlConfig = \Nette\Schema\Helpers::merge($wlConfig, $config[$type . 'Defaults']);
				$this->addWebLoader($builder, $type . ucfirst($name), $wlConfig);
				$loaderFactoryTempPaths[strtolower($name)] = $wlConfig['tempPath'];

				if (!is_dir($wlConfig['tempDir']) || !is_writable($wlConfig['tempDir'])) {
					throw new CompilationException(sprintf("You must create a writable directory '%s'", $wlConfig['tempDir']));
				}
			}
		}

		$builder->addDefinition($this->prefix('factory'))
			->setFactory('WebLoader\Nette\LoaderFactory', [$loaderFactoryTempPaths, $this->name]);

		if (class_exists('Symfony\Component\Console\Command\Command')) {
			$builder->addDefinition($this->prefix('generateCommand'))
				->setClass('WebLoader\Nette\SymfonyConsole\GenerateCommand')
				->addTag('kdyby.console.command');
		}
	}


	private function addWebLoader(ContainerBuilder $builder, string $name, array $config): void
	{
		$filesServiceName = $this->prefix($name . 'Files');

		$files = $builder->addDefinition($filesServiceName)
			->setClass('WebLoader\FileCollection')
			->setArguments([$config['sourceDir']]);

		foreach ($this->findFiles($config['files'], $config['sourceDir']) as $file) {
			$files->addSetup('addFile', [$file]);
		}

		foreach ($this->findFiles($config['watchFiles'], $config['sourceDir']) as $file) {
			$files->addSetup('addWatchFile', [$file]);
		}

		$files->addSetup('addRemoteFiles', [$config['remoteFiles']]);

		$compiler = $builder->addDefinition($this->prefix($name . 'Compiler'))
			->setClass('WebLoader\Compiler')
			->setArguments([
				'@' . $filesServiceName,
				$config['namingConvention'],
				$config['tempDir'],
			]);

		$compiler
			->addSetup('setJoinFiles', [$config['joinFiles']])
			->addSetup('setAsync', [$config['async']])
			->addSetup('setDefer', [$config['defer']])
			->addSetup('setNonce', [$config['nonce']])
			->addSetup('setAbsoluteUrl', [$config['absoluteUrl']]);

		if ($builder->parameters['webloader']['debugger']) {
			$compiler->addSetup('@' . $this->prefix('tracyPanel') . '::addLoader', [
				$name,
				'@' . $this->prefix($name . 'Compiler'),
			]);
		}

		foreach ($config['filters'] as $filter) {
			$compiler->addSetup('addFilter', [$filter]);
		}

		foreach ($config['fileFilters'] as $filter) {
			$compiler->addSetup('addFileFilter', [$filter]);
		}

		if (isset($config['debug']) && $config['debug']) {
			$compiler->addSetup('enableDebugging');
		}

		$compiler->addSetup('setCheckLastModified', [$config['checkLastModified']]);

		// todo css media
	}


	// I have no clue what this is supposed to do...
	// public function afterCompile(Nette\PhpGenerator\ClassType $class): void
	// {
	// 	$meta = $class->getProperty('meta');
	// 	if (array_key_exists('webloader\\nette\\loaderfactory', $meta->value['types'])) {
	// 		$meta->value['types']['webloader\\loaderfactory'] = $meta->value['types']['webloader\\nette\\loaderfactory'];
	// 	}
	// 	if (array_key_exists('WebLoader\\Nette\\LoaderFactory', $meta->value['types'])) {
	// 		$meta->value['types']['WebLoader\\LoaderFactory'] = $meta->value['types']['WebLoader\\Nette\\LoaderFactory'];
	// 	}
	//
	// 	$init = $class->methods['initialize'];
	// 	$init->addBody('if (!class_exists(?, ?)) class_alias(?, ?);', ['WebLoader\\LoaderFactory', false, 'WebLoader\\Nette\\LoaderFactory', 'WebLoader\\LoaderFactory']);
	// }


	public function install(Configurator $configurator): void
	{
		$self = $this;
		$configurator->onCompile[] = function ($configurator, Compiler $compiler) use ($self): void {
			$compiler->addExtension($self::EXTENSION_NAME, $self);
		};
	}


    /**
     * @param array $filesConfig
     * @param string $sourceDir
     * @return array
     * @throws FileNotFoundException
     */
    private function findFiles(array $filesConfig, string $sourceDir): array
	{
		$normalizedFiles = [];

		/** @var array|string $file */
		foreach ($filesConfig as $file) {
			// finder support
			if (is_array($file) && isset($file['files']) && (isset($file['in']) || isset($file['from']))) {
				$finder = Finder::findFiles($file['files']);

				if (isset($file['exclude'])) {
					$finder->exclude($file['exclude']);
				}

				if (isset($file['in'])) {
					$finder->in(is_dir($file['in']) ? $file['in'] : $sourceDir . DIRECTORY_SEPARATOR . $file['in']);
				} else {
					$finder->from(is_dir($file['from']) ? $file['from'] : $sourceDir . DIRECTORY_SEPARATOR . $file['from']);
				}

				$foundFilesList = [];
				foreach ($finder as $foundFile) {
					/** @var SplFileInfo $foundFile */
					$foundFilesList[] = $foundFile->getPathname();
				}

				natsort($foundFilesList);

				/** @var string $foundFilePathname */
				foreach ($foundFilesList as $foundFilePathname) {
					$normalizedFiles[] = $foundFilePathname;
				}

			} else {
				if (is_string($file)) {
					$this->checkFileExists($file, $sourceDir);
					$normalizedFiles[] = $file;
				}
			}
		}

		return $normalizedFiles;
	}


    /**
     * @param string $file
     * @param string $sourceDir
     * @throws FileNotFoundException
     */
    protected function checkFileExists(string $file, string $sourceDir): void
	{
		if (!$this->fileExists($file)) {
			$tmp = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . $file;
			if (!$this->fileExists($tmp)) {
				throw new FileNotFoundException(sprintf("Neither '%s' or '%s' was found", $file, $tmp));
			}
		}
	}


	/**
	 * Some servers seem to have problems under cron user with open_basedir restriction when using relative paths
	 */
	protected function fileExists(string $file): bool
	{
		$file = realpath($file);

		if ($file === false) {
			$file = '';
		}

		return file_exists($file);
	}
}
