<?php

/*
 * This file is part of the weblate-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace M2MTech\WeblateTranslationProvider;

use M2MTech\WeblateTranslationProvider\Api\ComponentApi;
use M2MTech\WeblateTranslationProvider\Api\TranslationApi;
use M2MTech\WeblateTranslationProvider\Api\UnitApi;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeblateProviderFactory extends AbstractProviderFactory
{
    public function __construct(
        private HttpClientInterface $client,
        private LoaderInterface     $loader,
        private LoggerInterface     $logger,
        private XliffFileDumper     $xliffFileDumper,
        private string              $defaultLocale
    ) {
    }


    protected function getSupportedSchemes(): array
    {
        return ['weblate'];
    }

    public function create(Dsn $dsn): ProviderInterface
    {
        if ('weblate' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'weblate', $this->getSupportedSchemes());
        }

        $endpoint = $dsn->getHost();
        $endpoint .= $dsn->getPort() ? ':' . $dsn->getPort() : '';
        $path = trim($dsn->getPath() ?? '', '/');
        if ('' !== $path) {
            $endpoint .= '/' . $path;
        }

        $api = $dsn->getOption('https', true) ? 'https://' : 'http://';
        $api .= $endpoint . '/api/';

        $client = ScopingHttpClient::forBaseUri(
            $this->client,
            $api,
            [
                'headers' => [
                    'Authorization' => 'Token ' . $this->getPassword($dsn),
                ],
                'verify_peer' => $dsn->getOption('verify_peer', true),
            ],
            preg_quote($api, '/')
        );

        $project = $this->getUser($dsn);

        $componentApi = new ComponentApi($client, $this->logger, $project, $this->defaultLocale);
        $translationApi = new TranslationApi($client, $this->logger);
        $unitApi = new UnitApi($client, $this->logger);

        return new WeblateProvider(
            $this->loader,
            $this->logger,
            $this->xliffFileDumper,
            $this->defaultLocale,
            $endpoint,
            $componentApi,
            $translationApi,
            $unitApi
        );
    }
}
