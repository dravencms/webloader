<?php

declare(strict_types = 1);

namespace WebLoader;

/**
 * @author Jan Marek
 */
interface IFileCollection
{
	public function getRoot(): string;

	public function getFiles(): array;

	public function getRemoteFiles(): array;

	public function getWatchFiles(): array;
}
