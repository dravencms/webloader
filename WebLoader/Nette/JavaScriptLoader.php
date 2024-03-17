<?php

declare(strict_types = 1);

namespace WebLoader\Nette;

use Nette\Utils\Html;

/**
 * JavaScript loader
 *
 * @author Jan Marek
 * @license MIT
 */
class JavaScriptLoader extends WebLoader
{
	public function getElement(string $source): Html
	{
		$el = Html::el('script');
		$el->setAttribute('async', $this->getCompiler()->isAsync());
		$el->setAttribute('defer', $this->getCompiler()->isDefer());
		$el->setAttribute('nonce', $this->getCompiler()->getNonce());
		$el->setAttribute('src', $source);

		return $el;
	}
}
