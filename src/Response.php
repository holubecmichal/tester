<?php

namespace Michalholubec\Tester;

use Nette\Application\IResponse;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Responses\TextResponse;
use Tester\Assert;

class Response
{
	/** @var IResponse */
	private $response;

	private $html;

	public function __construct(IResponse $response)
	{
		$this->response = $response;
	}

	public function assertTextResponse(): void
	{
		Assert::type(TextResponse::class, $this->response);
	}

	public function assertRedirectResponse(): void
	{
		Assert::type(RedirectResponse::class, $this->response);
	}

	public function assertJsonResponse(): void
	{
		Assert::type(JsonResponse::class, $this->response);
	}

	public function getHtml(): string
	{
		if ($this->html === null) {
			$this->assertTextResponse();

			\assert($this->response instanceof TextResponse);

			$this->html = (string) $this->response->getSource();
		}

		return $this->html;
	}

	public function assertContains(string $contains): void
	{
		Assert::contains($contains, $this->getHtml());
	}

	public function getAppResponse(): IResponse
	{
		return $this->response;
	}
}