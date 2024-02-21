<?php

use Monolog\Level;
use Monolog\Logger;
use IpapiProxy\Ipapi;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use IpapiProxy\Exception\IpapiException;
use IpapiProxy\Exception\InvalidIpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\ErrorHandler\ErrorHandler;
use IpapiProxy\Exception\LimitExceededException;
use IpapiProxy\IpapiRateLimiter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\ErrorHandler\Debug;

require_once __DIR__ . '/../vendor/autoload.php';
$config = require_once __DIR__ . '/../config.php';

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $config['log_dir'] . '/php_error.log');

if(true == $config['debug']) {
    Debug::enable();
}

ErrorHandler::register();

$handler = new StreamHandler($config['log_dir'] . '/lookup.log', Level::Info);
$handler->setFormatter(new JsonFormatter());
$logger = new Logger('ip_lookup');
$logger->setHandlers([ $handler ]);

$request = Request::createFromGlobals();
$ip = $request->query->get('ip');

$cache = new FilesystemAdapter('ipapi_lookups', 2629800, $config['cache_dir']);
$ipapi = new Ipapi($config['ipapi_token'], $logger, $cache, new IpapiRateLimiter($logger, $cache, $config['ipapi_request_limit']));

$logger->info('LOOKUP_START', [
    'ip' => $ip
]);

$ipapi_response = null;

try {
    $ipapi_response = $ipapi->lookup($ip);
} catch(InvalidIpException $e) {
    $logger->error('INVALID_IP_ERROR', [
        'ip' => $ip,
        'message' => $e->getMessage()
    ]);

    $http_response = new Response(json_encode([ 'message' => 'Invalid IP address' ]), 400, [ 'Content-Type' => 'application/json' ]);
    $http_response->send();
    die();
} catch(IpapiException $e) {
    /**
     * @var BadResponseException
     */
    $previous = $e->getPrevious();

    $logger->error('IPAPI_ERROR', [
        'message' => $e->getMessage(),
        'response' => $previous->getResponse()->getBody(),
        'status_code' => $previous->getResponse()->getStatusCode(),
    ]);

    $http_response = new Response(json_encode([ 'message' => 'ipapi error: ' . $e->getMessage() ]), 503, [ 'Content-Type' => 'application/json' ]);
    $http_response->send();
    die();
} catch(LimitExceededException $e) {
    $logger->error('IPAPI_LIMIT_EXCEEDED_ERROR', [
        'message' => $e->getMessage()
    ]);

    $http_response = new Response(json_encode([ 'message' => 'ipapi request limit exceeded' ]), 503, [ 'Content-Type' => 'application/json' ]);
    $http_response->send();
    die();
}

$logger->info('LOOKUP_END', [
    'ip' => $ip
]);

$http_response = new Response($ipapi_response, 200, [ 'Content-Type' => 'application/json' ]);
$http_response->send();
