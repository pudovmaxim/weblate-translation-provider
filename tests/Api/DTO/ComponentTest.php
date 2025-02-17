<?php
/*
 * This file is part of the weblate-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace M2MTech\WeblateTranslationProvider\Tests\Api\DTO;

use M2MTech\WeblateTranslationProvider\Api\DTO\Component;
use PHPUnit\Framework\TestCase;

class ComponentTest extends TestCase
{
    public function testImport(): void
    {
        $data = DTOFaker::createComponentData();

        $component = new Component($data + ['ignored' => 'something']);
        foreach ($data as $key => $value) {
            $this->assertSame($value, $component->$key);
        }
    }

    public function testMissing(): void
    {
        $this->expectException(\Throwable::class);

        new Component([]);
    }
}
