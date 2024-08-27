<?php

namespace Katalys\Shop\Util;

/**
 * Container for a rolling queue of cURL requests.
 *
 * Authored by Josh Fraser (www.joshfraser.com)
 * Released under Apache License 2.0
 * Maintained by Alexander Makarov, http://rmcreative.ru/
 * Heavily edited by Jesse Decker <me@jessedecker.com>
 * Including changes from multiple GitHub repos.
 *
 * @author Josh Fraser (www.joshfraser.com)
 * @author Jesse Decker <me@jessedecker.com>
 * @requires PHP 5.6+
 */
class RollingCurl
{
    /**
     * Window size is the max number of simultaneous connections allowed.
     *
     * REMEMBER TO RESPECT THE SERVERS:
     * Sending too many requests at one time can easily be perceived
     * as a DOS attack. Increase this window_size if you are making requests
     * to multiple servers or have permission from the receving server admins.
     *
     * @var int
     */
    public $windowSize = 5;

    /**
     * Timeout is the timeout used for curl_multi_select.
     * @var float
     * @see curl_multi_select()
     */
    protected $multiTimeout = 1;

    /**
     * Use HTTP/1.1 pipelining where possible.
     * @var bool
     * @see curl_multi_setopt()
     */
    public $multiPipeline;

    /**
     * Callback function to be applied to each result.
     * Can be specified as 'my_callback_function'
     * or array($object, 'my_callback_method').
     *
     * Function should take three parameters: $response, $info, $request.
     * $response is response body, $info is additional curl info.
     * $request is the original request
     * @var string|array
     */
    public $callback;

    /**
     * Default cURL options for EVERY request before applying per-request options.
     * @var array
     */
    public $options = [CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_RETURNTRANSFER => true];

    /**
     * Default list of cURL headers to pass as CURLOPT_HTTPHEADER.
     * @var string[]
     */
    public $headers = [];

    /**
     * Private request queue. Can override by passing custom \Iterable to ->iterate().
     * @var RollingCurlRequest[]
     */
    protected $requests = [];

    /**
     * An active session, if one has started.
     * @var \Generator
     */
    private $session;
    /**
     * Whether \Generator::next() must been called on the session to get our next value.
     * Important, because callbacks might call wait() recursively.
     * @var bool
     */
    private $sessionIsWaiting;
    /**
     * Flag to warn if multiple iterators are running from this instance.
     * @var bool
     */
    private $isRunning;

    /**
     * Constructor
     */
    public function __construct()
    {
        // left here so sub-class calls to parent::__construct() do not fail
    }

    /**
     * Add a request to the request queue
     *
     * @param RollingCurlRequest $request
     */
    public function add(RollingCurlRequest $request)
    {
        $this->requests[] = $request;
    }

    /**
     * Create new Request and add it to the request queue
     *
     * @param string       $url
     * @param string       $method
     * @param array|string $postData
     * @param string[]     $headers
     * @param array|null   $options
     */
    public function request(
        $url,
        $method = "GET",
        $postData = null,
        array $headers = null,
        array $options = null
    ) {
        $this->add(new RollingCurlRequest($url, $method, $postData, $headers, $options));
    }

    /**
     * Perform GET request
     *
     * @param string   $url
     * @param string[] $headers
     * @param array    $options
     */
    public function get($url, array $headers = null, array $options = null)
    {
        $this->add(new RollingCurlRequest($url, "GET", null, $headers, $options));
    }

    /**
     * Start a new session and own the Generator instance with default queue.
     */
    public function start()
    {
        if ($this->session) {
            return;
        }
        if (!function_exists('curl_multi_init')) {
            trigger_error("curl_multi_init() is not available, please install the curl_multi package!", E_USER_WARNING);

            // execute all requests in blocking fashion
            while (true) {
                if (!$this->single_curl()) {
                    return;
                }
            }
        }

        $this->session = $this->iterate();

        // Start first requests
        $lastTimeout        = $this->multiTimeout;
        $this->multiTimeout = 0;
        $this->session->valid(); // run to first "yield" statement

        // reset to original value
        $this->multiTimeout = $lastTimeout;
    }

    /**
     * Helper function to ALWAYS process the response.
     *
     * @return RollingCurlRequest|null
     */
    private function next()
    {
        $req = null;
        // IMPORTANT: callbacks can call wait() again!!
        if ($this->session && $this->sessionIsWaiting) {
            $this->sessionIsWaiting = false;
            $this->session->next();// might clear the session
        }
        if ($this->session && $this->session->valid()) {
            $req = $this->session->current();
            $this->sessionIsWaiting = true;
            if ($req) {// might be null
                $this->processResponse($req);
            }
        }
        return $req;
    }

    /**
     * Allow RollingCurl to process results, waiting at maximum $waitFor seconds for work to do.
     * Run a no-wait cycle with ->tick(0).
     *
     * @param float $waitFor Seconds to wait for incoming new requests before returning
     * @return boolean True if there is still a waiting session
     */
    public function tick($waitFor = null)
    {
        $lastTimeout = $waitFor !== null ? $waitFor : $this->multiTimeout;
        while ($this->next());// loop until NULL response
        $this->multiTimeout = $lastTimeout;
        return !!$this->session;
    }

    /**
     * Complete any waiting tasks in the current (or new) session.
     */
    public function finish()
    {
        $this->start();
        while ($this->session) {
            $this->next();
        }
    }

    /**
     * Complete any waiting tasks in the current (or new) session.
     *
     * @param RollingCurlRequest $req Specific request to wait for
     * @return bool Success; always TRUE if $req is empty, else if $req was never found
     */
    public function waitFor(RollingCurlRequest $req)
    {
        if ($req->info !== null) {
            return true;// This request has already been received
        }
        // move to beginning of queue
        $i = array_search($req, $this->requests, true);
        if ($i !== false && 0 < $i) {
            array_splice($this->requests, $i, 1);
            array_unshift($this->requests, $req);
        }
        $this->start();
        while ($this->session) {
            $cur = $this->next();
            if ($cur === $req) {
                return true;
            }
            unset($cur);// clear memory
        }
        return false;
    }

    /**
     * @param RollingCurlRequest[] $reqs
     * @return bool Success
     */
    public function waitForAll(array $reqs)
    {
        // move all to beginning of queue
        foreach ($reqs as $req) {
            $i = array_search($req, $this->requests, true);
            if ($i !== false && 0 < $i) {
                array_splice($this->requests, $i, 1);
                array_unshift($this->requests, $req);
            }
        }
        $success = true;
        foreach ($reqs as $req) {
            if (!$this->waitFor($req)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Process the response.
     *
     * @param RollingCurlRequest $request
     */
    protected function processResponse(RollingCurlRequest $request)
    {
        // send the return values to the callback function.
        if (is_callable($request->callback)) {
            $cb = $request->callback;
            $cb($request->output, $request->info, $request);
        }

        // it's not neccesary to set a callback for one-off requests
        if (is_callable($this->callback)) {
            $cb = $this->callback;
            $cb($request->output, $request->info, $request);
        }
    }

    /**
     * Performs a single curl request
     *
     * @access private
     * @return boolean Success
     */
    private function single_curl()
    {
        $request = array_shift($this->requests);
        if (!$request) {
            return false;
        }

        $ch = curl_init();
        $o = $this->getOptions($request);
        curl_setopt_array($ch, $o);
        $output = curl_exec($ch);
        $info = (array) curl_getinfo($ch);
        $info += ['errno' => curl_errno($ch), 'error' => curl_error($ch)];

        $request->info = $info;
        if ($output !== true) {// this just means "success". If CURLOPT_FILE is set, we want to use output for this.
            $request->output = $output;
        }
        $this->processResponse($request);
        return true;
    }

    /**
     * @return \Generator
     */
    private function useLocalQueue()
    {
        while (true) {
            yield array_shift($this->requests);
        }
    }

    /**
     * @param callable $callable
     * @return \Generator
     */
    private function wrapCallable(callable $callable)
    {
        while (true) {
            yield $callable();
        }
    }

    /**
     * Iterate until all requests in the queue have been executed.
     *
     * @param \Iterator|callable $iterator Will fallback to using $this->getNextRequest() if missing
     * @return \Generator <RollingCurlRequest|null> Will yield NULL every $this->multi_timeout seconds if no responses
     */
    public function iterate($iterator = null)
    {
        if ($this->isRunning) {
            throw new \RuntimeException("Already running");
        }

        $requestMap = [];// map of [(string) curl_handle] = [curl_handle, RollingCurlRequest]
        $master = $cookieShare = null;// master resource handle
        if (!($iterator instanceof \Iterator)) {
            $iterator = is_callable($iterator)
                ? $this->wrapCallable($iterator)
                : $this->useLocalQueue();
        }

        try {
            if (!$iterator->valid()) {
                return;// fast-fail, will also run generators up to the first yield statement
            }

            $this->isRunning = true;
            $curlHasNext = null;// whether curl_multi_exec reports is-still-running

            while (true) {

                // Fill the queue if any available
                for ($i = count($requestMap); $i < $this->windowSize; $i++) {
                    if (!$master) {
                        // First call MUST have a request, else all will stop
                        if (!($iterator->current() instanceof RollingCurlRequest)) {
                            return;// fast-fail
                        }

                        // Initialize, now that we know queue isn't empty
                        $master = curl_multi_init();
                        if ($this->multiPipeline) {
                            curl_multi_setopt($master, CURLMOPT_PIPELINING, 1);
                        }
                        $cookieShare = curl_share_init();
                        curl_share_setopt($cookieShare, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE);

                    } else {
                        // Generators that rely on current-state will be awakened by ->next()
                        $iterator->next();
                        if (!$iterator->valid()) {
                            break;
                        }
                    }

                    $request = $iterator->current();
                    if (!($request instanceof RollingCurlRequest)) {
                        break;
                    }

                    $ch = curl_init();
                    curl_setopt_array($ch, $this->getOptions($request));
                    curl_setopt($ch, CURLOPT_SHARE, $cookieShare);
                    curl_multi_add_handle($master, $ch);

                    // save so can access callback later
                    // key contains unique resource ID
                    if (is_resource($ch)) {
                        // PHP7 curl_init() returns a resource
                        $requestMap[(string) $ch] = [$ch, $request];
                    } else {
                        // PHP8 curl_init() returns a CurlHandle class object
                        $requestMap[spl_object_hash($ch)] = [$ch, $request];
                    }
                    $curlHasNext = true;
                }
                unset($ch, $request);// be explicit

                if (!$requestMap && !$curlHasNext) {
                    break;// multiCurl isn't running and we didn't add anything new
                }

                // start up any waiting requests
                while (($execrun = curl_multi_exec($master, $curlHasNext)) == CURLM_CALL_MULTI_PERFORM) ;

                // check that curl is ok (this will rarely ever fail)
                if ($execrun !== CURLM_OK) {
                    trigger_error('MultiCurl puked', E_USER_ERROR);
                    break;
                }

                // Block for data in / output; error handling is done by curl_multi_exec
                if ($curlHasNext) {
                    // Timeout must not be null!
                    if (curl_multi_select($master, floatval($this->multiTimeout)) === -1) {
                        // Perform a usleep if a select returns -1.
                        // See: https://bugs.php.net/bug.php?id=61141
                        usleep(100);
                    }
                }

                // Process any waiting messages
                $numRecv = 0;
                while (($done = curl_multi_info_read($master))) {
                    // maybe the message is a progress indicator?
                    if ($done['msg'] !== CURLMSG_DONE) {
                        continue;
                    }

                    // trust the resource ID to be unique
                    $ch  = $done['handle'];
                    if (is_resource($ch)) {
                        // PHP7 curl_init() returns a resource
                        $key = (string) $ch;
                    } else {
                        // PHP8 curl_init() returns a CurlHandle class object
                        $key = spl_object_hash($ch);
                    }
                    unset($done); // be explicit

                    if (empty($requestMap[$key])) {
                        trigger_error("Received cURL handle for unknown resource $key", E_USER_WARNING);
                        $request = null;
                    } else {
                        /** @var RollingCurlRequest $request */
                        $request = $requestMap[$key][1];
                        unset($requestMap[$key]);
                        // get the info and content returned on the request
                        $request->info   = ((array) curl_getinfo($ch))
                            + ['errno' => curl_errno($ch), 'error' => curl_error($ch)];
                        // Used for debugging: $request->info['COOKIES'] = curl_getinfo($ch, CURLINFO_COOKIELIST);
                        $out = curl_multi_getcontent($ch);
                        if (is_string($out)) {
                            $request->output = $out;
                        }
                        unset($out);
                    }

                    // remove the curl handle (required, even if re-adding later)
                    curl_multi_remove_handle($master, $ch);
                    // also close it (FYI: re-using handles requires PHPv5.5+)
                    curl_close($ch);
                    unset($ch); // be explicit, release memory

                    if ($request) {
                        $numRecv++;
                        yield $request;
                    }
                    unset($request); // be explicit, release memory
                }

                if ($curlHasNext && !$numRecv) {
                    yield; // allow caller to take over while we're still waiting
                    // This will have the effect of yield'ing NULL every $this->multi_timeout seconds if no
                    // response has been received.
                }
            }

        } finally {
            if ($requestMap) {
                // cleanup
                $domains = [];
                foreach ($requestMap as $_) {
                    /** @var RollingCurlRequest $req */
                    $req  = $_[1];
                    $host = parse_url($req->url, PHP_URL_HOST)
                        ?: (parse_url(curl_getinfo($_[0])['url'], PHP_URL_HOST) ?: $req->url);
                    if (!isset($domains[$host])) $domains[$host] = 1;
                    else $domains[$host]++;

                    curl_multi_remove_handle($master, $_[0]);
                    curl_close($_[0]);
                }
                // convert to message
                foreach ($domains as $k => $_) {
                    $domains[$k] = "$k: $_";
                }
                $domains = implode(', ', $domains);
                $cnt = count($requestMap);
                trigger_error("Map contains $cnt unfinished requests ($domains)", E_USER_WARNING);
            }

            if ($master) {// might not have had any requests
                curl_multi_close($master);
                curl_share_close($cookieShare);
            }

            $this->isRunning = false;
            $this->session = null;
        }
    }

    /**
     * Helper function to set up a new request by setting the appropriate options
     *
     * @param RollingCurlRequest $request
     * @return array
     */
    protected function getOptions(RollingCurlRequest $request)
    {
        // options for this entire curl object
        $options = (array) $this->options; // implicit array copy
        $options[CURLOPT_FOLLOWLOCATION] = 1;
        $options[CURLOPT_MAXREDIRS] = 5;

        if ($this->headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $this->headers;
        }

        if ($request->headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = !empty($options[CURLOPT_HTTPHEADER])
                ? array_merge($options[CURLOPT_HTTPHEADER], $request->headers)
                : $request->headers;
        }

        // posting data w/ this request?
        if ($request->postData) {
            $options[CURLOPT_POST] = 1;
            // Passing an array as POSTFIELDS will auto-encode as application/x-www-form-urlencoded
            // @see http://php.net/manual/en/function.curl-setopt.php#refsect1-function.curl-setopt-notes
            $options[CURLOPT_POSTFIELDS] = $request->postData;// array or string
        }

        if (!isset($options[CURLOPT_CUSTOMREQUEST]) && $request->method) {
            $method = strtoupper($request->method);
            if ($method !== 'GET' && $method !== 'POST') {
                $options[CURLOPT_CUSTOMREQUEST] = $method;
            }
        }

        // append custom options for this specific request
        if ($request->options) {
            // addition operator with non-array is Fatal, so explicitly cast
            $options = ((array) $request->options) + $options;
        }

        // set the request URL
        $options[CURLOPT_URL] = $request->url;

        // add the computed options hash back to the object
        // this is here for easy debugging only; please don't rely on it
        $request->computedOptions = $options;
        $request->time = microtime(true);

        return $options;
    }
}