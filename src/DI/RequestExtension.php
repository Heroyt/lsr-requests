<?php
declare(strict_types=1);

namespace Lsr\Core\Requests\DI;

use Lsr\Core\Requests\RequestFactory;
use Lsr\Core\Requests\ResponseFactory;
use Lsr\Core\Requests\StreamFactory;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\ResponseFactoryInterface;
use Nette;
use Nette\DI\CompilerExtension;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @property object{
 *     responseFactory:class-string<ResponseFactoryInterface>,
 *     requestFactory:class-string<RequestFactoryInterface>,
 *     streamFactory:class-string<StreamFactoryInterface>,
 *         } $config
 */
class RequestExtension extends CompilerExtension
{

    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure(
            [
                'requestFactory' => Nette\Schema\Expect::string(
                    RequestFactory::class
                )->assert(
                    fn($value) => class_exists($value) && is_a($value, RequestFactoryInterface::class, true),
                    'Request factory must be a valid class that implements ' . RequestFactoryInterface::class,
                ),
                'responseFactory' => Nette\Schema\Expect::string(
                    ResponseFactory::class
                )->assert(
                    fn($value) => class_exists($value) && is_a($value, ResponseFactoryInterface::class, true),
                    'Response factory must be a valid class that implements ' . ResponseFactoryInterface::class,
                ),
                'streamFactory' => Nette\Schema\Expect::string(
                    StreamFactory::class
                )->assert(
                    fn($value) => class_exists($value) && is_a($value, StreamFactoryInterface::class, true),
                    'Response factory must be a valid class that implements ' . StreamFactoryInterface::class,
                ),
            ]
        );
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();

        $streamFactory = $builder->addDefinition($this->prefix('streamFactory'))
            ->setType(StreamFactoryInterface::class)
            ->setFactory($this->config->streamFactory)
            ->setAutowired()
            ->setTags(['lsr', 'request']);

        $requestFactory = $builder->addDefinition($this->prefix('requestFactory'))
            ->setType(RequestFactoryInterface::class)
            ->setFactory($this->config->requestFactory)
            ->setAutowired()
            ->setTags(['lsr', 'request']);

        $responseFactory = $builder->addDefinition($this->prefix('responseFactory'))
            ->setType(ResponseFactoryInterface::class)
            ->setFactory($this->config->responseFactory)
            ->setArgument('streamFactory', $streamFactory)
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