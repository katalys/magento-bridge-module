<?php

declare(strict_types=1);

namespace OneO\Shop\Api\Data;

interface ProcessDirectiveInterface
{
    const ARGS_KEY = 'args';

    public function processDirective($jsonDirective): array;
}