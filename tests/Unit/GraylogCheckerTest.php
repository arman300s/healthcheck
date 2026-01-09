<?php

namespace Adata\HealthChecker\Tests\Unit;

use Adata\HealthChecker\Checkers\GraylogChecker;
use Adata\HealthChecker\Entities\HealthEntity;
use Adata\HealthChecker\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as StatusCode;

/**
 * @covers GraylogChecker
 */
class GraylogCheckerTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		Config::shouldReceive('get')
			->with('logging.channels', [])
			->andReturn([
				'gelf' => [
					'driver' => 'monolog',
					'handler' => 'Hedii\LaravelGelfLogger\GelfLoggerHandler',
					'level' => 'debug',
				],
			]);

		Log::shouldReceive('channel')
			->with('gelf')
			->andReturnSelf();

		Log::shouldReceive('info')
			->andReturn(true);

		Log::shouldReceive('warning')
			->andReturn(true);
	}

	/**
	 * @covers       GraylogChecker::check
	 * @dataProvider getData
	 */
	public function test(string $expectedHealthStatus, array $config, array $graylogResponse)
	{
		$response = new Response(
			$graylogResponse['status_code'],
			[],
			data_get($graylogResponse, 'body')
		);

		$this->guzzleClientStub->method('get')->willReturn($response);

		$graylog = new GraylogChecker($this->guzzleClientStub, $config);
		$status = $graylog->check();

		$this->assertEquals($expectedHealthStatus, $status);
	}

	public function getData(): array
	{
		return [
			[
				'expected_health_status' => HealthEntity::STATUS_SUCCESSFUL,
				'config'                 => [
					'type'    => 'graylog',
					'host'    => '127.0.0.1',
					'port'    => 12201,
					'channel' => 'gelf',
					'source'  => 'example',
					'timeout' => 3,
				],
				'graylog_response'       => [
					'status_code' => StatusCode::HTTP_OK,
					'body'        => 'ALIVE',
				],
			],
			[
				'expected_health_status' => HealthEntity::STATUS_FAIL,
				'config'                 => [
					'type'    => 'graylog',
					'host'    => '127.0.0.1',
					'port'    => 12201,
					'channel' => 'gelf',
					'source'  => 'example',
					'timeout' => 3,
				],
				'graylog_response'       => [
					'status_code' => StatusCode::HTTP_INTERNAL_SERVER_ERROR,
					'body'        => 'ERROR',
				],
			],
			[
				'expected_health_status' => HealthEntity::STATUS_FAIL,
				'config'                 => [
					'type'    => 'graylog',
					'host'    => '127.0.0.1',
					'port'    => 12201,
					'channel' => 'gelf',
					'source'  => 'example',
					'timeout' => 3,
				],
				'graylog_response'       => [
					'status_code' => StatusCode::HTTP_OK,
					'body'        => 'DEAD',
				],
			],
			[
				'expected_health_status' => HealthEntity::STATUS_SUCCESSFUL,
				'config'                 => [
					'type'    => 'graylog',
					'host'    => '127.0.0.1',
					'port'    => 256,
					'channel' => 'gelf',
					'source'  => 'example',
					'timeout' => 3,
				],
				'graylog_response'       => [
					'status_code' => StatusCode::HTTP_OK,
					'body'        => 'ALIVE',
				],
			],
			[
				'expected_health_status' => HealthEntity::STATUS_FAIL,
				'config'                 => [
					'type'    => 'graylog',
					'port'    => 12201,
					'channel' => 'gelf',
					'timeout' => 3,
				],
				'graylog_response'       => [
					'status_code' => StatusCode::HTTP_INTERNAL_SERVER_ERROR,
					'body'        => 'ERROR',
				],
			],
		];
	}
}