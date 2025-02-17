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
use M2MTech\WeblateTranslationProvider\Api\DTO\Translation;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationApi
{
    /** @var array<string,array<string,Translation>> */
    private array $translations = [];

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function hasTranslation(Component $component, string $locale): bool
    {
        if (isset($this->translations[$component->slug][$locale])) {
            return true;
        }

        if (isset($this->translations[$component->slug])) {
            // already tried to load translation from server before

            return false;
        }

        /**
         * GET /api/components/(string: project)/(string: component)/translations/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#get--api-components-(string-project)-(string-component)-translations-
         */
        $response = $this->client->request('GET', $component->translations_url);

        if (200 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode().': '.$response->getContent(false));
            throw new ProviderException('Unable to get weblate components translations for '.$component->slug.'.', $response);
        }

        $results = $response->toArray()['results'];
        foreach ($results as $result) {
            $translation = new Translation($result);
            $this->translations[$component->slug][$translation->language_code] = $translation;
            $this->logger->debug('Loaded translation '.$component->slug.' '.$translation->language_code);
        }

        if (isset($this->translations[$component->slug][$locale])) {
            return true;
        }

        return false;
    }

    /**
     * @throws ExceptionInterface
     */
    public function getTranslation(Component $component, string $locale): Translation
    {
        if (self::hasTranslation($component, $locale)) {
            return $this->translations[$component->slug][$locale];
        }

        return self::addTranslation($component, $locale);
    }

    /**
     * @throws ExceptionInterface
     */
    public function addTranslation(Component $component, string $locale): Translation
    {
        /**
         * POST /api/components/(string: project)/(string: component)/translations/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#post--api-components-(string-project)-(string-component)-translations-
         */
        $response = $this->client->request('POST', $component->translations_url, [
            'body' => ['language_code' => $locale],
        ]);

        if (201 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode().': '.$response->getContent(false));
            throw new ProviderException('Unable to add weblate components translation for '.$component->slug.' '.$locale.'.', $response);
        }

        $result = $response->toArray()['data'];
        $translation = new Translation($result);
        $translation->created = true;
        $this->translations[$component->slug][$locale] = $translation;

        $this->logger->debug('Added translation '.$component->slug.' '.$locale);

        return $translation;
    }

    /**
     * @throws ExceptionInterface
     */
    public function uploadTranslation(Translation $translation, string $content): void
    {
        $content = str_replace('<trans-unit', '<trans-unit xml:space="preserve"', $content);

        /**
         * POST /api/translations/(string: project)/(string: component)/(string: language)/file/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#post--api-translations-(string-project)-(string-component)-(string-language)-file-
         */
        $formFields = [
            'method' => 'replace',
            'file' => new DataPart($content, $translation->filename),
        ];
        $formData = new FormDataPart($formFields);

        $response = $this->client->request('POST', $translation->file_url, [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToString(),
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode().': '.$response->getContent(false));
            throw new ProviderException('Unable to upload weblate translation '.$content.'.', $response);
        }

        $this->logger->debug('Uploaded translation '.$translation->filename);
    }

    /**
     * @throws ExceptionInterface
     */
    public function downloadTranslation(Translation $translation): string
    {
        /**
         * GET /api/translations/(string: project)/(string: component)/(string: language)/file/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#get--api-translations-(string-project)-(string-component)-(string-language)-file-
         */
        $response = $this->client->request('GET', $translation->file_url);

        if (200 !== $response->getStatusCode()) {
            $this->logger->debug($response->getStatusCode().': '.$response->getContent(false));
            throw new ProviderException('Unable to download weblate translation.', $response);
        }

        $this->logger->debug('Downloaded translation '.$translation->filename);

        return $response->getContent();
    }
}
