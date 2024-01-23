<?php

namespace OneO\Shop\Model;

use Magento\Framework\Exception\InputException;
use OneO\Shop\Api\RecordDatesInterface;
use OneO\Shop\Util\Sec\Authenticatable;
use Magento\Framework\Exception\SecurityViolationException;
use OneO\Shop\Util\DatesSenderFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

/**
 * RecordDates class
 */
class RecordDates implements RecordDatesInterface
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
     * RecordDates constructor.
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
     * @throws InputException
     * @throws SecurityViolationException
     */
    public function send()
    {
        $this->authenticate($this->request);
        $params = $this->request->getParams();
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;
        $limit = $params['limit'] ?? null;
        $offset = $params['offset'] ?? null;
        $timeout = $params['timeout'] ?? null;
        $datesSender = $this->datesSenderFactory->create();
        try {
            return [$datesSender->send($from, $to, $limit, $offset, $timeout)];
        } catch (\Exception $e) {
            throw new InputException(null, $e);
        }
    }
}