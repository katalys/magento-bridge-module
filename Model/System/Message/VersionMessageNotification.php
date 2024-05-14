<?php

namespace Katalys\Shop\Model\System\Message;

use Katalys\Shop\Api\GetModuleDetailsInterface;
use Magento\Framework\Notification\MessageInterface;

/**
 * Class VersionMessageNotification
 */
class VersionMessageNotification implements MessageInterface
{
    /**
     * Message identity
     */
    const MESSAGE_IDENTITY = 'version_message_notification';

    /**
     * @var GetModuleDetailsInterface
     */
    protected $getModuleDetails;

    /**
     * @param GetModuleDetailsInterface $getModuleDetails
     */
    public function __construct(GetModuleDetailsInterface $getModuleDetails)
    {
        $this->getModuleDetails = $getModuleDetails;
    }

    /**
     * Retrieve unique system message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether the system message should be shown
     *
     * @return bool
     */
    public function isDisplayed()
    {
        if ($this->getModuleDetails->hasNewVersion()) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return __(
            'The Katalys module has a new version. Please, update the module Katalys_Shop. The new version is %1.',
            $this->getModuleDetails->getNewVersion()
        );
    }

    /**
     * Retrieve system message severity
     * Possible default system message types:
     * - MessageInterface::SEVERITY_CRITICAL
     * - MessageInterface::SEVERITY_MAJOR
     * - MessageInterface::SEVERITY_MINOR
     * - MessageInterface::SEVERITY_NOTICE
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;

    }
}