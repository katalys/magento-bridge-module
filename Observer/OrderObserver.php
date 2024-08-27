<?php

namespace Katalys\Shop\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Event\Observer;

/**
 * OrderObserver class
 */
class OrderObserver implements ObserverInterface
{
    const TRACK_COOKIE_NAME = 'revoffers_affil';
    const META_COLUMN = 'katalys_visitor_lookup';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var $o \Magento\Sales\Model\Order */
            $o = $observer->getData('order');

            if (isset($_COOKIE[self::TRACK_COOKIE_NAME])) {
                $data = null;
                $raw = $_COOKIE[self::TRACK_COOKIE_NAME];
                if (strpos($raw, '{') === 0) $data = json_decode($raw, true);
                if (!$data) parse_str($raw, $data);

                if (!$data) {
                    $this->logger->error(__METHOD__ . ": Unable to parse Katalys Advertiser cookie: $raw");
                    return;
                }
                if (!isset($data['vid'])) {
                    $this->logger->error(__METHOD__ . ": Unable to parse vid from Katalys Advertiser cookie: $raw");
                    return;
                }

                $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $data['client_ip'] = $this->_getClientIp();
                $o->setData(self::META_COLUMN, json_encode($data));
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__, [$e]);
        }
    }

    /**
     * @return string|null
     */
    private function _getClientIp()
    {
        $ip = null;
        $filter = function($ip) {
            $ip = trim($ip);
            if (!$ip) return null;
            if (strpos($ip, "127.0.") === 0) return null;
            if (strpos($ip, "192.168.") === 0) return null;
            if (strpos($ip, "169.254.") === 0) return null;
            if (preg_match('#^10\.[0-9]\.#', $ip)) return null;
            if (preg_match('#^172\.(1[6789]|2[0-9]|3[01])\.#', $ip)) return null;
            if (strpos($ip, "fc00:") === 0) return null;
            if (strpos($ip, "fe80:") === 0) return null;
            if (strpos($ip, "::1") === 0) return null;
            return $ip;
        };
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $_ = array_filter(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']), $filter);
            if ($_) {
                $ip = $_[0];
            }
        }
        if (!$ip && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $filter($_SERVER['HTTP_CLIENT_IP']);
        }
        if (!$ip && !empty($_SERVER['SERVER_ADDR'])) {
            $ip = $filter($_SERVER['SERVER_ADDR']);
        }
        return $ip;
    }
}
