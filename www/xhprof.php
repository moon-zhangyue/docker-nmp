<?php
// Initialize profiling
if (extension_loaded('tideways_xhprof')) {
    tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
}

// Your application code here
// ...

// End profiling and save data
if (extension_loaded('tideways_xhprof')) {
    $data = tideways_xhprof_disable();

    // Connect to MongoDB
    $mongo = new MongoDB\Client('mongodb://xhgui:27017');
    $mongo->xhgui->results->insertOne([
        'profile' => $data,
        'meta' => [
            'url' => $_SERVER['REQUEST_URI'],
            'SERVER' => $_SERVER,
            'get' => $_GET,
            'env' => $_ENV,
            'simple_url' => preg_replace('/\=\d+/', '', $_SERVER['REQUEST_URI']),
            'request_ts' => new MongoDB\BSON\UTCDateTime(microtime(true) * 1000),
            'request_date' => date('Y-m-d'),
        ]
    ]);
}
