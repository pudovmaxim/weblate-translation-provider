<?php
/*
 * This file is part of the weblate-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace M2MTech\WeblateTranslationProvider\Api\DTO;

class Translation extends DTO
{
    public string $language_code;

    public string $filename;

    public string $file_url;

    public string $units_list_url;

    public bool $created = false;
}
