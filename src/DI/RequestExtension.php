<?php
declare(strict_types=1);

namespace Lsr\Core\Requests\DI;

use Lsr\Core\Requests\RequestFactory;
use Lsr\Core\Requests\ResponseFactory;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\ResponseFactoryInterface;
use Nette;
use Nette\DI\CompilerExtension;

/**
 * @property object{responseFactory:class-string<ResponseFactoryInterface>, requestFactory:class-string<RequestFactoryInterface>} $config
 */
class RequestExtension extends CompilerExtension
{

	public function getConfigSchema(): Nette\Schema\Schema {
		return Nette\Schema\Expect::structure(
			[
				'requestFactory' => Nette\Schema\Expect::string(
					RequestFactory::class
				)->assert(
					fn($value) => class_exists($value) && is_subclass_of($value, RequestFactoryInterface::class),
					'Request factory must be a valid class that implements ' . RequestFactoryInterface::class,
				),
				'responseFactory' => Nette\Schema\Expect::string(
					ResponseFactory::class
				)->assert(
					fn($value) => class_exists($value) && is_subclass_of($value, ResponseFactoryInterface::class),
					'Response factory must be a valid class that implements ' . ResponseFactoryInterface::class,
				),
			]
		);
	}

	public function loadConfiguration(): void {
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('requestFactory'))
		        ->setType(RequestFactoryInterface::class)
		        ->setFactory($this->config->requestFactory)
		        ->setAutowired()
		        ->setTags(['lsr', 'request']);

		$responseFactory = $builder->addDefinition($this->prefix('responseFactory'))
		                           ->setType(ResponseFactoryInterface::class)
		                           ->setFactory($this->config->responseFactory)
		                           ->setAutowired()
		                           ->setTags(['lsr', 'request']);

		$this->initialization->addBody(
			'\Lsr\Exceptions\DispatchBreakException::setResponseFactory($this->getService(?));',
			[
				$responseFactory->getName(),
			]
		);
	}

}