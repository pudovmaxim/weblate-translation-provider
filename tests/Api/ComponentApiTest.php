<?php

/*
 * This file is part of the weblate-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace M2MTech\WeblateTranslationProvider\Tests\Api;

use M2MTech\WeblateTranslationProvider\Api\ComponentApi;
use M2MTech\WeblateTranslationProvider\Api\DTO\Component;
use M2MTech\WeblateTranslationProvider\Tests\Api\DTO\DTOFaker;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class ComponentApiTest extends ApiTest
{
    private ComponentApi $componentApi;

    /**
     * @param callable[] $responses
     */
    private function setupFactory(array $responses): void
    {
        $this->componentApi = new ComponentApi(
            new MockHttpClient($responses, 'https://v5.3.ignores/baseUri'),
            $this->createMock(LoggerInterface::class),
            'project',
            'en'
        );
    }

    /**
     * @param array<array<string,string>> $results
     */
    private function getGetComponentsResponse(array $results): callable
    {
        return $this->getResponse(
            '/projects/project/components/',
            'GET',
            '',
            (string) json_encode(['results' => $results])
        );
    }

    /**
     * @param array<string,string> $result
     */
    private function getAddComponentResponse(string $fileContent, array $result): callable
    {
        return $this->getResponse(
            '/projects/project/components/',
            'POST',
            $fileContent,
            (string) json_encode($result),
            201
        );
    }

    private function getDeleteComponentResponse(Component $component): callable
    {
        return $this->getResponse(
            $component->url,
            'DELETE',
            '',
            '',
            204
        );
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetComponentsEmpty(): void
    {
        $this->setupFactory([$this->getGetComponentsResponse([])]);

        $this->assertEmpty($this->componentApi->getComponents());
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetComponents(): void
    {
        $results = [
            DTOFaker::createComponentData(),
            DTOFaker::createComponentData(),
        ];
        $this->setupFactory([$this->getGetComponentsResponse($results)]);

        $components = $this->componentApi->getComponents();
        foreach ($results as $result) {
            $this->assertSame($result['translations_url'], $components[$result['slug']]->translations_url);
        }
    }

    /**
     * @throws ExceptionInterface
     */
    public function testHasComponentFalse(): void
    {
        $this->setupFactory([
            $this->getGetComponentsResponse([]),
            $this->getGetComponentsResponse([
                DTOFaker::createComponentData(),
            ]),
        ]);

        $this->assertFalse($this->componentApi->hasComponent('notExisting'));

        // calling getComponents a second time because it was empty the first time
        $this->assertFalse($this->componentApi->hasComponent('notExisting'));

        // not calling getComponents a third time
        $this->assertFalse($this->componentApi->hasComponent('notExisting'));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testHasComponent(): void
    {
        $data = DTOFaker::createComponentData();
        $this->setupFactory([$this->getGetComponentsResponse([$data])]);

        $this->assertTrue($this->componentApi->hasComponent($data['slug']));

        // not calling getComponents a second time
        $this->assertTrue($this->componentApi->hasComponent($data['slug']));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testAddComponent(): void
    {
        $content = DTOFaker::getFaker()->paragraph();
        $data = DTOFaker::createComponentData();
        $newComponent = new Component($data);
        $newComponent->created = true;

        $this->setupFactory([$this->getAddComponentResponse($content, $data)]);

        $component = $this->componentApi->addComponent($newComponent->slug, $content);
        $this->assertEquals($newComponent, $component);

        $components = $this->componentApi->getComponents();
        $this->assertEquals($newComponent, $components[$newComponent->slug]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetComponent(): void
    {
        $existingData = DTOFaker::createComponentData();
        $existingComponent = new Component($existingData);
        $newData = DTOFaker::createComponentData();
        $newComponent = new Component($newData);
        $newComponent->created = true;
        $newContent = DTOFaker::getFaker()->paragraph();

        $this->setupFactory([
            $this->getGetComponentsResponse([$existingData]),
            $this->getAddComponentResponse($newContent, $newData),
        ]);

        $component = $this->componentApi->getComponent($existingComponent->slug);
        if (!$component) {
            $this->fail();
        }

        $this->assertEquals($existingComponent, $component);

        $this->assertNull($this->componentApi->getComponent($newComponent->slug));

        $component = $this->componentApi->getComponent($newComponent->slug, $newContent);
        if (!$component) {
            $this->fail();
        }

        $this->assertEquals($newComponent, $component);

        $components = $this->componentApi->getComponents();
        $this->assertEquals($newComponent, $components[$newComponent->slug]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testDeleteComponent(): void
    {
        $component = DTOFaker::createComponent();

        $this->setupFactory([
            $this->getDeleteComponentResponse($component),
        ]);

        $this->componentApi->deleteComponent($component);
    }
}
