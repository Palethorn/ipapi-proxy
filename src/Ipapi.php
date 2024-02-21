<?php

namespace IpapiProxy;

use Monolog\Logger;
use GuzzleHttp\Client;
use IpapiProxy\Exception\IpapiException;
use Symfony\Contracts\Cache\CacheInterface;
use IpapiProxy\Exception\InvalidIpException;
use GuzzleHttp\Exception\BadResponseException;

class Ipapi {
    private string $token;
    private Logger $logger;
    private Client $client;
    private CacheInterface $cache;
    private IpapiRateLimiter $ipapiRateLimiter;

    public function __construct(
        string $token,
        Logger $logger,
        CacheInterface $cache,
        IpapiRateLimiter $ipapiRateLimiter
    ) {
        $this->token = $token;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->client = new Client();
        $this->ipapiRateLimiter = $ipapiRateLimiter;
    }

    public function lookup(string $ip) {
        if(!preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $ip)) {
            throw new InvalidIpException('Not a valid IP', 400);
        }

        return $this->cache->get(hash('sha512', $ip), function() use($ip) {
            $this->logger->info('CACHE_MISS', [
                'ip' => $ip
            ]);
        
            $this->ipapiRateLimiter->check();

            try {
                $ipapi_response = $this->client->get(sprintf('https://api.ipapi.is?q=%s&key=%s', $ip, $this->token));
            } catch(BadResponseException $e) {
                throw new IpapiException($e->getMessage(), $e->getResponse()->getStatusCode(), $e);
            }
        
            return (string)$ipapi_response->getBody();
        });
    }
}
