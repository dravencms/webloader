<?php

declare(strict_types = 1);

namespace WebLoader\Nette\SymfonyConsole;

use Nette;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WebLoader;

/**
 * Generate Command
 */
class GenerateCommand extends \Symfony\Component\Console\Command\Command
{

	/** @var \WebLoader\Compiler[] */
	private $compilers = [];

	protected static $defaultName = 'webloader:generate';


	public function __construct(Nette\DI\Container $container)
	{
		parent::__construct();

		$compilers = $container->findByType(WebLoader\Compiler::class);
		foreach ($compilers as $compilerName) {
			$this->compilers[$compilerName] = $container->getService($compilerName);
		}
	}


	protected function configure(): void
	{
		$this->setName(self::$defaultName)
			->setDescription('Generates files.')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Generate if not modified.');
	}


	protected function execute(InputInterface $input, OutputInterface $output): void
	{
		$force = $input->getOption('force');

		$nofiles = true;
		foreach ($this->compilers as $compiler) {
			$files = $compiler->generate(!$force);
			foreach ($files as $file) {
				$output->writeln($file->file);
				$nofiles = false;
			}
		}

		if ($nofiles) {
			$output->writeln('No files generated.');
		}
	}
}
