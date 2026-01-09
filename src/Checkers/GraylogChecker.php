<?php

namespace Adata\HealthChecker\Checkers;

use Adata\HealthChecker\Entities\HealthEntity;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Adata\HealthChecker\Tests\Unit\HttpCheckerTest;

class GraylogChecker implements CheckerInterface, HealthEntity
{
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
     * @uses HttpCheckerTest::test()
     */
    public function check(): string
    {
		$status = self::STATUS_SUCCESSFUL;

		try {
			$timeout = self::DEFAULT_TIMEOUT;
			$port    = self::DEFAULT_GRAYLOG_PORT;

			if (isset($this->config['timeout']) && !empty($this->config['timeout'])) {
				$timeout = $this->config['timeout'];
			}

			if (isset($this->config['port']) && !empty($this->config['port'])) {
				$port = $this->config['port'];
			}

			$channel = $this->config['channel'] ?? 'gelf';
			$loggingChannels = Config::get('logging.channels', []);

			if (!isset($loggingChannels[$channel])) {
				Log::warning('HEALTHCHECK: GraylogChecker - GELF channel not found in logging config', [
					'channel' => $channel
				]);
				return self::STATUS_FAIL;
			}

			$url = sprintf('http://%s:%s/api/system/lbstatus', $this->config['host'], $port);
			$request = $this->guzzleClient->get($url, ['timeout' => $timeout]);
			$response = $request->getBody()->getContents();

			if (stripos($response, 'ALIVE') === false) {
				$status = self::STATUS_FAIL;
			}

			if ($status === self::STATUS_SUCCESSFUL) {
				Log::channel($channel)->info('Health check test message', [
					'health_check' => true,
					'source' => $this->config['source'] ?? 'health-checker'
				]);
			}

		} catch (\Exception $e) {
			Log::warning('HEALTHCHECK: GraylogChecker have catch', ['error' => $e->getMessage()]);
			$status = self::STATUS_FAIL;
		}

		return $status;
    }
}
