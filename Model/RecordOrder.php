<?php

namespace Katalys\Shop\Model;

use Katalys\Shop\Api\RecordOrderInterface;
use Katalys\Shop\Util\Sec\Authenticatable;
use Magento\Framework\Exception\SecurityViolationException;
use Katalys\Shop\Util\OrderPackagerFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

/**
 * RecordOrder class
 */
class RecordOrder implements RecordOrderInterface
{
    use Authenticatable;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var OrderPackagerFactory
     */
    protected $orderPackagerFactory;

    /**
     * RecordOrder constructor.
     * @param LoggerInterface $logger
     * @param OrderPackagerFactory $orderPackagerFactory
     * @param RequestInterface $request
     */
    public function __construct(
        LoggerInterface $logger,
        OrderPackagerFactory $orderPackagerFactory,
        RequestInterface $request
    ) {
        $this->logger = $logger;
        $this->orderPackagerFactory = $orderPackagerFactory;
        $this->request = $request;
    }

    /**
     * @api
     * @param string $id
     * @return array
     * @throws SecurityViolationException
     */
    public function send($id)
    {
        $this->authenticate($this->request);
        $params = $this->request->getParams();
        $key = (!empty($params['key'])) ? $params['key'] : null;
        $label = $key ? '_id' : ' increment_id';
        $packager = $this->orderPackagerFactory->create();
        $params = $packager->getParams($id, $key);
        if (!$params) {
            return [
                [
                    'success' => false,
                    'error' => "order$label $id is not found"
                ]
            ];
        }
        $params['action'] = 'restapi_conv';
        $req = \Katalys\Shop\Util\Curl::post($params);
        \Katalys\Shop\Util\Curl::getDefault()->waitFor($req);
        $info = $req->info;
        $success = in_array($info['http_code'], [200,204]);
        return [
            [
                'success' => $success,
                'info' => $info
            ]
        ];
    }
}