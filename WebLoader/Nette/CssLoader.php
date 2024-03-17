<?php

declare(strict_types = 1);

namespace WebLoader\Nette;

use Nette\Utils\Html;

/**
 * Css loader
 *
 * @author Jan Marek
 * @license MIT
 */
class CssLoader extends WebLoader
{

	/** @var string */
	private $media;

	/** @var string */
	private $title;

	/** @var string */
	private $type = 'text/css';

	/** @var bool */
	private $alternate = false;


	public function getMedia(): string
	{
		return $this->media;
	}


	public function getType(): string
	{
		return $this->type;
	}


	public function getTitle(): string
	{
		return $this->title;
	}


	public function isAlternate(): bool
	{
		return $this->alternate;
	}


	public function setMedia(string $media): self
	{
		$this->media = $media;
		return $this;
	}


	public function setType(string $type): self
	{
		$this->type = $type;
		return $this;
	}


	public function setTitle(string $title): self
	{
		$this->title = $title;
		return $this;
	}


	public function setAlternate(bool $alternate): self
	{
		$this->alternate = $alternate;
		return $this;
	}


	public function getElement(string $source): Html
	{
		if ($this->alternate) {
			$alternate = ' alternate';
		} else {
			$alternate = '';
		}

		$el = Html::el('link');
		$el->setAttribute('rel', 'stylesheet' . $alternate);
		$el->setAttribute('type', $this->type);
		$el->setAttribute('media', $this->media);
		$el->setAttribute('title', $this->title);
		$el->setAttribute('nonce', $this->getCompiler()->getNonce());
		$el->setAttribute('href', $source);

		return $el;
	}
}
