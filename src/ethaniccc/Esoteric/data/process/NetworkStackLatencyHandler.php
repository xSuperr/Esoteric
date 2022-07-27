<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use function is_null;

final class NetworkStackLatencyHandler {

	private array $queue = [];

	private static ?NetworkStackLatencyHandler $instance = null;

	public static function getInstance(): self{
		if(is_null(self::$instance)) self::$instance = new self;
		return self::$instance;
	}

	public function queue(PlayerData $data, callable $onResponse) {
		$timestamp = $data->tickProcessor->getLatencyTimestamp();
		$this->send($data);
		$this->queue[$data->hash][$timestamp] = $onResponse;
	}

	public function execute(PlayerData $data, int $timestamp): void {
		$callable = $this->queue[$data->hash][$timestamp] ?? null;
		if ($callable !== null) {
			($callable)($timestamp);
		}
		$data->tickProcessor->response($timestamp);
		unset($this->queue[$data->hash][$timestamp]);
	}

	public function remove(string $hash): void {
		unset($this->queue[$hash]);
	}

	private function send(PlayerData $data): void {
		$pk = new NetworkStackLatencyPacket();
		$pk->timestamp = $data->tickProcessor->currentTimestamp ?? 0;
		$pk->needResponse = true;
		$data->networkSession->addToSendBuffer($pk);
		$data->tickProcessor->waiting[$pk->timestamp] = $data->currentTick;
		$data->tickProcessor->randomizeTimestamps();
	}

}