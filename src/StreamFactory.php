<?php
declare(strict_types=1);

namespace Lsr\Core\Requests;

use InvalidArgumentException;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use function error_get_last;
use function fopen;
use function in_array;
use function sprintf;

final readonly class StreamFactory implements StreamFactoryInterface
{

    /**
     * @inheritDoc
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return Stream::create($content);
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if ('' === $filename) {
            throw new RuntimeException('Path cannot be empty');
        }

        if (false === $resource = @fopen($filename, $mode)) {
            if ('' === $mode || false === in_array($mode[0], ['r', 'w', 'a', 'x', 'c'], true)) {
                throw new InvalidArgumentException(sprintf('The mode "%s" is invalid.', $mode));
            }

            throw new RuntimeException(sprintf('The file "%s" cannot be opened: %s', $filename, error_get_last()['message'] ?? ''));
        }

        return Stream::create($resource);
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return Stream::create($resource);
    }
}