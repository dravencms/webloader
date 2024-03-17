<?php

declare(strict_types = 1);

namespace WebLoader;

/**
 * IOutputNamingConvention
 *
 * @author Jan Marek
 */
interface IOutputNamingConvention
{
	public function getFilename(array $files, Compiler $compiler): string;
}
