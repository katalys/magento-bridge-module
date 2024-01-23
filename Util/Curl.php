<?php

namespace OneO\Shop\Util;

/**
 * Curl class
 */
class Curl
{
    /** @var RollingCurl */
    private static $rollingCurlInstance;

    /** @var string */
    private static $siteUrl;

    /** @var string */
    private static $siteId;

    /** @var string */
    private static $userAgent;

    const ENDPOINT = 'https://db.revoffers.com/v2/_tr';

    /**
     * @return string
     */
    public static function getUserAgent()
    {
        if (!self::$userAgent) {
            $ver = '<unknown>';
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if ($objectManager) {
                $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
                if ($productMetadata) {
                    $ver = $productMetadata->getVersion();
                }
            }

            $pluginVersion = '1.0.0';
            self::$userAgent = "Magento/$ver (Katalys Advertiser PHP plugin $pluginVersion)";
        }

        return self::$userAgent;
    }

    /**
     * @return RollingCurl
     */
    public static function getDefault()
    {
        if (!self::$rollingCurlInstance) {
            self::$rollingCurlInstance = new RollingCurl();

            // RevOffers defaults
            self::$rollingCurlInstance->options[CURLOPT_FOLLOWLOCATION] = true;
            self::$rollingCurlInstance->options[CURLOPT_MAXREDIRS] = 2;
            self::$rollingCurlInstance->options[CURLOPT_TIMEOUT] = 15;
            self::$rollingCurlInstance->options[CURLOPT_USERAGENT] = self::getUserAgent();

            // ensure waiting requests are handled before shutdown
            register_shutdown_function(function () {
                /** @var RollingCurl $rollingCurlInstance */
                $rollingCurlInstance = self::getDefault();

                if (function_exists('fastcgi_finish_request')) {
                    // tell FPM to release the request context
                    fastcgi_finish_request();
                }

                // wait for any in-flight requests to complete
                $rollingCurlInstance->finish();
            });
        }

        return self::$rollingCurlInstance;
    }

    /**
     * POST data via cURL to collector endpoint.
     *
     * @param $params
     * @return \OneO\Shop\Util\RollingCurlRequest
     */
    public static function post($params)
    {
        if (!$params) return null;

        // Determine server state
        $isHttps = !empty($_SERVER['HTTPS']) || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0);
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']
            : parse_url(self::_getSiteUrl(), PHP_URL_HOST);
        $uri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/<offline_processing>';

        // Populate params with session info
        $params['request_uri'] = ($isHttps ? 'https' : 'http') . "://" . $host . $uri;
        $params['site_id'] = self::_getSiteId();
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $params['referrer'] = $_SERVER['HTTP_REFERER'];
        }
        $params['document_title'] = '<Magento Offline Tracking>';

        $rollingCurlInstance = self::getDefault();
        $request = new RollingCurlRequest(self::ENDPOINT, 'POST', http_build_query($params, "", '&'));
        $rollingCurlInstance->add($request);
        $rollingCurlInstance->start();

        return $request;
    }

    /**
     * @param $event
     * @return RollingCurlRequest
     */
    public static function sendNotification($event)
    {
        $params = [];

        // Determine server state
        $isHttps = !empty($_SERVER['HTTPS']) || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0);
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']
            : parse_url(self::_getSiteUrl(), PHP_URL_HOST);
        $uri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/<offline_processing>';

        // Populate params with reporting data
        $params['integration'] = 'Magento';
        $params['user'] = self::_getClientIp();
        $params['domain'] = $host;
        $params['url'] = ($isHttps ? 'https' : 'http') . "://" . $host . $uri;
        $params['site_id'] = self::_getSiteId();


        $rollingCurlInstance = self::getDefault();
        $request = new RollingCurlRequest("https://db.revoffers.com/v3/event/$event", 'POST', http_build_query($params, "", '&'));
        $rollingCurlInstance->add($request);
        $rollingCurlInstance->start();

        return $request;
    }

    /**
     * @return string
     */
    private static function _getSiteUrl()
    {
        if (!self::$siteUrl) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if ($objectManager) {
                $urlMetadata = $objectManager->get('Magento\Framework\UrlInterface');
                if ($urlMetadata) {
                    self::$siteUrl = $urlMetadata->getBaseUrl();
                }
            }
        }

        return self::$siteUrl;
    }

    /**
     * @return string
     */
    private static function _getSiteId()
    {
        if (!self::$siteId) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if ($objectManager) {
                $scopeMetadata = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
                if ($scopeMetadata) {
                    self::$siteId = $scopeMetadata->getValue(\OneO\Shop\Helper\Data::SITEID_CONFIG_PATH);
                }
            }
        }

        return self::$siteId;
    }

    /**
     * @return string|null
     */
    private static function _getClientIp()
    {
        $ip = null;
        $filter = function ($ip) {
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