<?php

namespace Katalys\Shop\Api;

interface ConfigInterface
{
    public const _KEY_DEBUG_MODE = 'katalys_ad/configs/debug';
    public const _KEY_TRIGGER_ALL_STATUS = 'katalys_ad/configs/trigger_all_status';

    /**
     * @return bool
     */
    public function isDebugMode(): bool;

    /**
     * @return bool
     */
    public function isTriggerAllStatus(): bool;
}