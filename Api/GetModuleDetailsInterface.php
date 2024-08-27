<?php

namespace Katalys\Shop\Api;

/**
 * Interface GetModuleDetailsInterface
 */
interface GetModuleDetailsInterface
{
    /**
     * @return string
     */
    public function getNewVersion(): string;

    /**
     * @return string
     */
    public function getActualVersion(): string;

    /**
     * @return bool
     */
    public function hasNewVersion(): bool;
}