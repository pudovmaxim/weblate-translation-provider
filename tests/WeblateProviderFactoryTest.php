<?php

/*
 * This file is part of the weblate-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace M2MTech\WeblateTranslationProvider\Tests;

use M2MTech\WeblateTranslationProvider\WeblateProviderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeblateProviderFactoryTest extends TestCase
{
    /** @var ?HttpClientInterface */
    protected $client;

    /** @var ?LoggerInterface */
    protected $logger;

    /** @var ?LoaderInterface */
    protected $loader;

    /** @var ?XliffFileDumper */
    protected $xliffFileDumper;

    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(bool $expected, string $dsn): void
    {
        $factory = $this->createFactory();

        $this->assertSame($expected, $factory->supports(new Dsn($dsn)));
    }

    /**
     * @dataProvider createProvider
     */
    public function testCreate(string $expected, string $dsn): void
    {
        $factory = $this->createFactory();
        $provider = $factory->create(new Dsn($dsn));

        $this->assertSame($expected, (string) $provider);
    }

    /**
     * @dataProvider unsupportedSchemeProvider
     */
    public function testUnsupportedSchemeException(string $dsn, ?string $message = null): void
    {
        $factory = $this->createFactory();

        $dsn = new Dsn($dsn);

        $this->expectException(UnsupportedSchemeException::class);
        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }

        $factory->create($dsn);
    }

    /**
     * @dataProvider incompleteDsnProvider
     */
    public function testIncompleteDsnException(string $dsn, ?string $message = null): void
    {
        $factory = $this->createFactory();

        $dsn = new Dsn($dsn);

        $this->expectException(IncompleteDsnException::class);
        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }

        $factory->create($dsn);
    }

    protected function getClient(): HttpClientInterface
    {
        return $this->client ?? $this->client = new MockHttpClient();
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger ?? $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function getLoader(): LoaderInterface
    {
        return $this->loader ?? $this->loader = $this->createMock(LoaderInterface::class);
    }

    protected function getXliffFileDumper(): XliffFileDumper
    {
        return $this->xliffFileDumper ?? $this->xliffFileDumper = $this->createMock(XliffFileDumper::class);
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new WeblateProviderFactory(
            $this->getClient(),
            $this->getLoader(),
            $this->getLogger(),
            $this->getXliffFileDumper(),
            'en'
        );
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'weblate://project:key@server'];
        yield [false, 'somethingElse://project:key@server'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://project:key@server', 'scheme is not supported'];
    }

    public function createProvider(): iterable
    {
        yield [
            'weblate://server',
            'weblate://project:key@server',
        ];

        yield [
            'weblate://server/path',
            'weblate://project:key@server/path',
        ];

        yield [
            'weblate://server/bla/bla/bla',
            'weblate://project:key@server/bla/bla/bla/',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield ['weblate://project@default', 'Password is not set'];
        yield ['weblate://default', 'Password is not set'];
    }
}
