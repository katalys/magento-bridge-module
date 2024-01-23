<?php

namespace OneO\Shop\Model;

use OneO\Shop\Api\QueueDatesInterface;
use OneO\Shop\Util\Sec\Authenticatable;
use Magento\Framework\Exception\SecurityViolationException;
use Magento\Framework\Exception\InputException;
use OneO\Shop\Util\DatesSenderFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

/**
 * QueueDates class
 */
class QueueDates implements QueueDatesInterface
{
    use Authenticatable;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DatesSenderFactory
     */
    protected $datesSenderFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param LoggerInterface $logger
     * @param DatesSenderFactory $datesSenderFactory
     * @param RequestInterface $request
     */
    public function __construct(
        LoggerInterface $logger,
        DatesSenderFactory $datesSenderFactory,
        RequestInterface $request
    ) {
        $this->logger = $logger;
        $this->datesSenderFactory = $datesSenderFactory;
        $this->request = $request;
    }

    /**
     * @api
     * @return array
     * @throws SecurityViolationException
     * @throws InputException
     */
    public function queue()
    {
        $this->authenticate($this->request);
        $params = $this->request->getParams();
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;
        $limit = $params['limit'] ?? null;
        $offset = $params['offset'] ?? null;
        $datesSender = $this->datesSenderFactory->create();
        try {
            return [$datesSender->queue($from, $to, $limit, $offset)];
        } catch (\Exception $e) {
            throw new InputException(null, $e);
        }
    }
}