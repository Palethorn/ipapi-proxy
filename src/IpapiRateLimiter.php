<?php

namespace IpapiProxy;

use DateInterval;
use DateTime;
use DateTimeZone;
use Monolog\Logger;
use IpapiProxy\Exception\LimitExceededException;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class IpapiRateLimiter {
    private int $limit;
    private Logger $logger;
    private AdapterInterface $cache;

    public function __construct(
        Logger $logger,
        AdapterInterface $cache,
        int $limit
    ) {
        $this->limit = $limit;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function check() {
        $counter = 0;
        $count = $this->cache->getItem('api_request_count');

        $dateTime = new DateTime('now', new DateTimeZone('UTC'));
        $dateTime->add(new DateInterval('P1D'));
        $count->expiresAt(DateTime::createFromFormat('Y-m-d H:i:s', $dateTime->format('Y-m-d 00:00:00'), new DateTimeZone('UTC')));

        if($count->isHit()) {
            $counter = $count->get();
    
            if($counter >= $this->limit) {
                throw new LimitExceededException('ip api limit reached', 503);
            }
    
            $count->set(++$counter);
            $this->cache->save($count);
        } else {
            $count->set(1);
            $this->cache->save($count);
        }

        $this->logger->info('IPAPI_REQUEST_COUTER', [
            'count' => $counter
        ]);
    }
}
