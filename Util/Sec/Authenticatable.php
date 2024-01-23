<?php

namespace OneO\Shop\Util\Sec;

use Magento\Framework\Exception\SecurityViolationException;

/**
 * Authenticatable trait
 */
trait Authenticatable
{
    /**
     * @param $request
     * @throws SecurityViolationException
     */
    public function authenticate($request)
    {
        $d = new \DateTime($request->getParam('x-time'));
        if ($d->getTimestamp() < time() - 60 * 60) {
            throw new SecurityViolationException(__('invalid verification time'));
        }

        // Enforce a verification hash on the request itself
        $content = $request->getContent(); // file_get_contents("php://input");
        $sig = $request->getHeader('x-signature');
        if (!$sig) {
            throw new SecurityViolationException(__('no source verification provided'));
        }

        if (function_exists('openssl_verify')) {
            // MORE SECURE!
            $sig = base64_decode($sig);
            $key = file_get_contents(__DIR__ . "/rest_api.pubkey");
            $success = openssl_verify($content, $sig, $key, OPENSSL_ALGO_SHA256);
        } elseif (function_exists('hash_hmac')) {
            // LESS SECURE!
            $hash = $request->getHeader('x-hmac');
            // @-syntax forces UTC timezone
            $secret = 'revoffers' . (new \DateTime("@" . time()))->format('Y-m-d');
            $computedHmac = hash_hmac('sha256', $content, $secret);
            $success = hash_equals($computedHmac, $hash);

        } else {
            throw new SecurityViolationException(__('Must have OpenSSL or hash/hmac functions enabled'));
        }

        if (!$success) {
            throw new SecurityViolationException(__('invalid verification provided'));
        }
    }

}