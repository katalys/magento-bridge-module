<?php

namespace OneO\Shop\Util;

/**
 * RollingCurlRequest class
 */
class RollingCurlRequest
{
    const DEFAULT_METHOD = 'GET';

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $method;

    /**
     * @var string|array Will be http_build_query()-encoded if is array
     */
    public $postData;

    /**
     * @var string[] ['Accept: application/json']
     */
    public $headers;

    /**
     * @var array
     */
    public $options;

    /**
     * @var callable
     */
    public $callback;

    // Populated after the request completes
    /**
     * @var double Time started
     */
    public $time;

    /**
     * @var array
     */
    public $info;

    /**
     * @var string
     */
    public $output;

    /**
     * @var array
     */
    public $computedOptions;

    /**
     * @param string       $url
     * @param string       $method
     * @param string|array $postData
     * @param string[]     $headers
     * @param array|null   $options
     */
    public function __construct(
        $url,
        $method = self::DEFAULT_METHOD,
        $postData = null,
        array $headers = null,
        array $options = null
    ) {
        $this->url      = $url;
        $this->method   = strtoupper($method);
        $this->postData = $postData;
        $this->headers  = $headers;
        $this->options  = $options;

        if ($this->method !== self::DEFAULT_METHOD && $this->method !== 'POST') {
            $this->method = empty($postData) ? self::DEFAULT_METHOD : 'POST';
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . '{' . $this->method . ' ' . $this->url
            . ' '
            . (!$this->time ? 'waiting' : (
                $this->output === null ? 'started' : (
                    isset($this->info['http_code']) ? $this->info['http_code'] : 'done'
                )
            ))
            . '}';
    }
}