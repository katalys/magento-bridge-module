<?php

declare(strict_types=1);

namespace OneO\Shop\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Index implements HttpPostActionInterface
{
    private JsonFactory $jsonFactory;

    public function __construct(JsonFactory $jsonFactory)
    {
        $this->jsonFactory = $jsonFactory;
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();
        $data = ['message' => 'Hello world!'];

        return $result->setData($data);
    }
}

