<?php

if (count($argv) < 2) {
    echo "Usage: SITE=<site_id> php {$argv[0]} <command> [...args]\n";
    echo "  send_order_id <order_id>\n";
    echo "  send_order_dates <from> <to> [<timeout>]\n";
    echo "  send_order_iterate <from> <to> <timeout>\n";
    echo "  queue_order_iterate <from> <to> <timeout>\n";
    echo "  send_yesterday\n";
    echo "  update_order_status <order_id>\n";
    echo "  send_product_catalog [<timeout> [<offset>]]\n";
    exit(2);
}

$slug = arg(1);

switch ($slug) {
    case 'send_order_id':
        send("record/order/" . arg(2), []);
        break;
    case 'send_order_dates':
        send("record/dates", [
            'from' => arg(2),
            'to' => arg(3),
            'offset' => arg(4, 0),
            'limit' => arg(5, 1000000),
            'timeout' => 55,
        ]);
        break;
    case 'send_yesterday':
        $today = new \DateTime('-12 hours');
        $from = $today->format('Y-m-d');
        $today->add(new \DateInterval('P1D'));
        $to = $today->format('Y-m-d');
        $argv[2] = $from;
        $argv[3] = $to;
        $argv[4] = 1000;
        // break; << fallthrough...
    case 'send_order_iterate':
        $offset = 0;
        while (true) {
            $out = send('record/dates', [
                'from' => arg(2),
                'to' => arg(3, ''),
                'limit' => arg(4, ''),
                'offset' => $offset,
                'timeout' => 55,
            ]);
            $out = json_decode($out);
            if (!$out) return;
            list($out) = $out; // Magento2 always returns an array
            $sent = $out->queued ?: $out->sent;
            if (!$sent) return;
            $cnt = count((array) ($out->queued ? $out->ids : $out->map));
            if (!$cnt) return;
            $offset += $cnt;
        }
        break;
    case 'queue_order_iterate':
        $offset = 0;
        while (true) {
            $out = send('queue/dates', [
                'from' => arg(2),
                'to' => arg(3, ''),
                'limit' => arg(4, ''),
                'offset' => $offset,
                'timeout' => 55,
            ]);
            $out = json_decode($out);
            if (!$out) return;
            list($out) = $out; // Magento2 always returns an array
            $sent = $out->queued ?: $out->sent;
            if (!$sent) return;
            $cnt = count((array) ($out->queued ? $out->ids : $out->map));
            if (!$cnt) return;
            $offset += $cnt;
        }
        break;
    case 'update_order_status':
        send("update/order_status/" . arg(2), [
            'conversion_status' => 'converted',
            'conversion_message' => 'This order matches a click from a Katalys affiliate.',
        ]);
        break;
    case 'send_product_catalog':
        send("send/product_catalog", [
            'timeout' => arg(2, 28),
            'offset' => arg(3, 0),
        ]);
        break;
    case 'send_product_rules':
        send("send/catalog_rules", [
            'timeout' => arg(2, 28),
            'offset' => arg(3, 0),
        ]);
        break;
    default:
        echo "Bad args\n";
        exit(1);
}

function arg($i, $default = null) {
    global $argv;
    if (isset($argv[$i])) return $argv[$i];
    if ($default === null) {
        throw new RuntimeException("Missing offset $i");
    }
    return $default;
}

function send($slug, $data) {
    $host = getenv('SITE');
    if (!$host) {
        throw new \RuntimeException("Missing ENV:SITE");
    }
    if (strpos($host, "://") === false) {
        $host = "https://$host";
    }

    $qs = null;
    if (!is_string($data)) {
        $data['x-time'] = "@" . time();
        $qs = http_build_query($data);
        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    $url = "$host/rest/V1/revoffers/$slug?$qs";

    $secret = 'revoffers' . (new DateTime("@" . time()))->format('Y-m-d');
    $computedHmac = hash_hmac('sha256', $data, $secret);
    $keyContents = file_get_contents(__DIR__ . '/rest_api.privkey');
    openssl_sign($data, $sig, $keyContents, OPENSSL_ALGO_SHA256);

    echo "Sending to $url\n$data\n\n";
    $startTime = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,// safe-guard against bad SSL
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            "X-Forwarded-Proto: https",
            "Content-Type: application/json",
            "X-Signature: " . base64_encode($sig),
            "X-Hmac: $computedHmac",
        ],
    ]);
    $out = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    echo $out;
    echo "\n[{$info['http_code']}] in ", number_format(microtime(true) - $startTime, 2), "s\n";
    return $out;
}

/*
define('SAVEQUERIES', true);
add_action('shutdown', function () {
    global $wpdb;
    $log_file = fopen('/tmp/sql_log.txt', 'a');
    fwrite($log_file, "//////////////////////////////////////////\n\n" . date("F j, Y, g:i:s a")."\n");
    foreach($wpdb->queries as $q) {
        fwrite($log_file, $q[0] . " - ($q[1] s)" . "\n\n");
    }
    fclose($log_file);
});
*/
