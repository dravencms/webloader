<?php

declare(strict_types = 1);

namespace WebLoader;

class Path
{
	public static function normalize(string $path): string
	{
		$path = strtr($path, '\\', '/');
		$root = (strpos($path, '/') === 0) ? '/' : '';
		$pieces = explode('/', trim($path, '/'));
		$res = [];

		foreach ($pieces as $piece) {
			if ($piece === '.' || $piece === '') {
				continue;
			}
			if ($piece === '..') {
				array_pop($res);
			} else {
				array_push($res, $piece);
			}
		}

		return $root . implode('/', $res);
	}
}
