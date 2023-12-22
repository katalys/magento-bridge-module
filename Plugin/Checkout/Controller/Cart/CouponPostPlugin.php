<?php

namespace OneO\Shop\Plugin\Checkout\Controller\Cart;

use Magento\Checkout\Controller\Cart\CouponPost;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;

/**
 * Class CouponPostPlugin
 */
class CouponPostPlugin
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @param RequestInterface $request
     * @param MessageManagerInterface $messageManager
     * @param RedirectFactory $resultRedirectFactory
     * @param Escaper $escaper
     */
    public function __construct(
        RequestInterface $request,
        MessageManagerInterface $messageManager,
        RedirectFactory $resultRedirectFactory,
        Escaper $escaper,
        UrlInterface $url
    ) {
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->escaper = $escaper;
        $this->url = $url;
    }

    /**
     * Retrieve request object
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param CouponPost $subject
     * @param callable $proceed
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function aroundExecute(CouponPost $subject, callable $proceed)
    {
        $couponCode = $this->getRequest()->getParam('remove') == 1
            ? ''
            : trim($this->getRequest()->getParam('coupon_code'));
        if (substr($couponCode, 0, 3) === 'KS_') {
            $this->messageManager->addErrorMessage(
                __(
                    'The coupon code "%1" is not valid.',
                    $this->escaper->escapeHtml($couponCode)
                )
            );
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl(
                $this->url->getUrl('checkout/cart')
            );
            return $resultRedirect;
        }
        return $proceed();
    }
}
