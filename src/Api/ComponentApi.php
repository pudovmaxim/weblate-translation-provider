<?php

/*
 * This file is part of the weblate-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace M2MTech\WeblateTranslationProvider\Api;

use M2MTech\WeblateTranslationProvider\Api\DTO\Component;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ComponentApi
{
    /** @var array<string,Component> */
    private array $components = [];

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger,
        private string $project,
        private string $defaultLocale
    ) {
    }

    /**
     * @throws ExceptionInterface
     *
     * @return array<string,Component>
     */
    public function getComponents(bool $reload = false): array
    {
        if ($reload) {
            $this->components = [];
        }

        if ($this->components) {
            return $this->components;
        }

        /**
         * GET /api/projects/(string: project)/components/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#get--api-projects-(string-project)-components-
         */
        $response = $this->client->request('GET', 'projects/' . $this->project . '/components/');

        if (200 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode() . ': ' . $response->getContent(false));
            throw new ProviderException('Unable to get weblate components.', $response);
        }

        $results = $response->toArray()['results'];

        foreach ($results as $result) {
            $component = new Component($result);

            if ('glossary' === $component->slug) {
                continue;
            }

            $this->components[$component->slug] = $component;
            $this->logger->debug('Loaded component ' . $component->slug);
        }

        return $this->components;
    }

    /**
     * @throws ExceptionInterface
     */
    public function hasComponent(string $slug): bool
    {
        $this->getComponents();

        if (isset($this->components[$slug])) {
            return true;
        }

        return false;
    }

    /**
     * @throws ExceptionInterface
     */
    public function getComponent(string $slug, string $optionalContent = ''): ?Component
    {
        if ($this->hasComponent($slug)) {
            return $this->components[$slug];
        }

        if (!$optionalContent) {
            return null;
        }

        return $this->addComponent($slug, $optionalContent);
    }

    /**
     * @throws ExceptionInterface
     */
    public function addComponent(string $domain, string $content): Component
    {
        $content = str_replace('<trans-unit', '<trans-unit xml:space="preserve"', $content);

        /**
         * POST /api/projects/(string: project)/components/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#post--api-projects-(string-project)-components-
         */
        $formFields = [
            'name' => $domain,
            'slug' => $domain,
            'edit_template' => 'true',
            'manage_units' => 'true',
            'source_language' => $this->defaultLocale,
            'file_format' => 'xliff',
            'docfile' => new DataPart($content, $domain . '/' . $this->defaultLocale . '.xlf'),
        ];
        $formData = new FormDataPart($formFields);

        $response = $this->client->request('POST', 'projects/' . $this->project . '/components/', [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToString(),
        ]);

        if (201 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode() . ': ' . $response->getContent(false));
            throw new ProviderException('Unable to add weblate component ' . $domain . '.', $response);
        }

        $result = $response->toArray();
        $component = new Component($result);
        $component->created = true;
        $this->components[$component->slug] = $component;

        $this->logger->debug('Added component ' . $component->slug);

        return $component;
    }

    /**
     * @throws ExceptionInterface
     */
    public function deleteComponent(Component $component): void
    {
        /**
         * DELETE /api/components/(string: project)/(string: component)/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#delete--api-components-(string-project)-(string-component)-
         */
        $response = $this->client->request('DELETE', $component->url);

        if (204 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode() . ': ' . $response->getContent(false));
            throw new ProviderException('Unable to delete weblate component ' . $component->slug . '.', $response);
        }

        unset($this->components[$component->slug]);

        $this->logger->debug('Deleted component ' . $component->slug);
    }

    /**
     * @throws ExceptionInterface
     */
    public function commitComponent(Component $component): void
    {
        /**
         * POST /api/components/(string: project)/(string: component)/repository/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#post--api-components-(string-project)-(string-component)-repository-
         */
        $response = $this->client->request('POST', $component->repository_url, [
            'body' => ['operation' => 'commit'],
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode() . ': ' . $response->getContent(false));
            throw new ProviderException('Unable to commit weblate component ' . $component->slug . '.', $response);
        }

        $this->logger->debug('Committed component ' . $component->slug);
    }
}
