<?php

namespace Adata\HealthChecker\Checkers;

use Adata\HealthChecker\Entities\HealthEntity;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * GraylogChecker class
 *
 * type = graylog
 * @uses \Adata\HealthChecker\Tests\Unit\GraylogCheckerTest
 */
class GraylogChecker implements CheckerInterface, HealthEntity
{
    /** @var Client */
    private $guzzleClient;

    /** @var array */
    private $config;

    public function __construct(Client $guzzleClient, array $config)
    {
        $this->guzzleClient = $guzzleClient;
        $this->config       = $config;
    }

    /**
     * @inheritdoc
     * @uses \Adata\HealthChecker\Tests\Unit\GraylogCheckerTest::test()
     */
    public function check(): string
    {
        $status = self::STATUS_SUCCESSFUL;

        try {
            $protocol = self::DEFAULT_PROTOCOL;
            $timeout  = self::DEFAULT_TIMEOUT;
            $port     = self::DEFAULT_GRAYLOG_PORT;

            if (isset($this->config['https']) && $this->config['https']) {
                $protocol = 'https';
            }

            if (isset($this->config['timeout']) && !empty($this->config['timeout'])) {
                $timeout = $this->config['timeout'];
            }

            if (isset($this->config['port']) && !empty($this->config['port'])) {
                $port = $this->config['port'];
            }

            $channel         = $this->config['channel'] ?? self::DEFAULT_GRAYLOG_CHANNEL;
            $loggingChannels = Config::get('logging.channels', []);

            if (!isset($loggingChannels[$channel])) {
                Log::warning('HEALTHCHECK: GraylogChecker - GELF channel not found in logging config', [
                    'channel' => $channel,
                ]);

                return self::STATUS_FAIL;
            }

            $url      = sprintf('%s://%s:%s/api/system/lbstatus',
                $protocol,
                $this->config['host'],
                $port,
            );
            $response = $this->guzzleClient->get($url, ['timeout' => $timeout]);

            if ($response->getStatusCode() !== Response::HTTP_OK
                || stripos($response->getBody()->getContents(), 'ALIVE') === false
            ) {
                return self::STATUS_FAIL;
            }

            Log::channel($channel)->info('Health check test message', [
                'health_check' => true,
                'source'       => $this->config['source'] ?? 'health-checker',
            ]);
        } catch (\Exception $e) {
            Log::warning('HEALTHCHECK: GraylogChecker have catch', ['error' => $e->getMessage()]);
            $status = self::STATUS_FAIL;
        }

        return $status;
    }
}
