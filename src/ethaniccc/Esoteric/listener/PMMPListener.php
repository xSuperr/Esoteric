<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\KickTask;
use ethaniccc\Esoteric\tasks\TickingTask;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\TextFormat;
use function is_null;
use function round;
use function str_replace;
use function strtolower;
use function var_export;
use const PHP_EOL;

class PMMPListener implements Listener {

	public TimingsHandler $sendTimings;
	public TimingsHandler $decodingTimings;

	public function __construct() {
		$this->sendTimings = new TimingsHandler("Esoteric Listener Outbound");
		$this->decodingTimings = new TimingsHandler("Esoteric Batch Decoding");
	}

	/**
	 * @param PlayerPreLoginEvent $event
	 * @priority LOWEST
	 */
	public function log(PlayerPreLoginEvent $event): void {
		foreach (Server::getInstance()->getNameBans()->getEntries() as $entry) {
			if ($entry->getSource() === 'Esoteric AC' && $entry->getName() === strtolower($event->getPlayerInfo()->getUsername())) {
				$event->setKickReason(PlayerPreLoginEvent::KICK_REASON_PLUGIN, str_replace(['{prefix}', '{code}', '{expires}'], [Esoteric::getInstance()->getSettings()->getPrefix(), $entry->getReason(), $entry->getExpires() !== null ? $entry->getExpires()->format("m/d/y h:i A T") : 'Never'], Esoteric::getInstance()->getSettings()->getBanMessage()));
				break;
			}
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority LOWEST
	 */
	public function join(PlayerJoinEvent $event): void {
		$data = Esoteric::getInstance()->dataManager->get($event->getPlayer()->getNetworkSession());
        if (in_array($event->getPlayer()->getName(), Check::$kickOnJoin)) {
            $esoteric = Esoteric::getInstance();

            $data->isDataClosed = true;
            $check = $data->checks[9];
            $string = str_replace(['{prefix}', '{code}'], [$esoteric->getSettings()->getPrefix(), $check->getCodeName()], $esoteric->getSettings()->getKickMessage());
            $esoteric->getScheduler()->scheduleTask(new KickTask($data->player, $string));
            if (($bc = $esoteric->getSettings()->getKickBroadcast()) !== 'none') {
                $esoteric->getServer()->broadcastMessage(str_replace(['{prefix}', '{player}', '{check_name}', '{code_name}', '{violations}'], [$esoteric->getSettings()->getPrefix(), $data->player->getName(), $check->name, $check->getCodeName(), $check->violations], $bc));
            }

            unset(Check::$kickOnJoin[array_search($event->getPlayer()->getName(), Check::$kickOnJoin)]);
        }

		if ($data !== null) {
			$data->hasAlerts = $data->player->hasPermission('ac.alerts');
		}
	}


	/**
	 * @param PlayerQuitEvent $event
	 * @priority LOWEST
	 */
	public function quit(PlayerQuitEvent $event): void {
		$data = Esoteric::getInstance()->dataManager->get($event->getPlayer()->getNetworkSession());
		$message = null;
		foreach ($data->checks as $check) {
			$checkData = $check->getData();
			if ($checkData['violations'] >= 1) {
				if (is_null($message)) {
					$message = '';
				}
				$message .= TextFormat::YELLOW . $checkData['full_name'] . TextFormat::WHITE . ' - ' . $checkData['description'] . TextFormat::GRAY . ' (' . TextFormat::RED . 'x' . var_export(round($checkData['violations'], 3), true) . TextFormat::GRAY . ')' . PHP_EOL;
			}
		}
		Esoteric::getInstance()->logCache[strtolower($event->getPlayer()->getName())] = is_null($message) ? TextFormat::GREEN . 'This player has no logs' : $message;
		Esoteric::getInstance()->dataManager->remove($event->getPlayer()->getNetworkSession());
	}

	public function login(PlayerLoginEvent $event): void {
		$data = Esoteric::getInstance()->dataManager->get($event->getPlayer()->getNetworkSession());
        $data->player = $event->getPlayer();
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 * @handleCancelled
	 */
	public function receive(DataPacketReceiveEvent $event): void {
		$packet = $event->getPacket();
		$session = $event->getOrigin();
		$playerData = Esoteric::getInstance()->dataManager->get($session) ?? Esoteric::getInstance()->dataManager->add($session);
		if($playerData->isDataClosed || $session->getProtocolId() <= ProtocolInfo::PROTOCOL_1_16_210) return;
		$playerData->inboundProcessor->execute($packet, $playerData);
		if($playerData->playerOS === DeviceOS::PLAYSTATION) return;
		foreach ($playerData->checks as $check) {
			if ($check->enabled()) {
				$check->getTimings()->startTiming();
				$check->inbound($packet, $playerData);
				$check->getTimings()->stopTiming();
			}
		}
	}

	public function send(DataPacketSendEvent $event): void {
		foreach ($event->getTargets() as $target) {
			$playerData = Esoteric::getInstance()->dataManager->get($target);
			if (is_null($playerData)) continue;
			foreach ($event->getPackets() as $packet) {
				if ($packet instanceof StartGamePacket) {
					$packet->playerMovementSettings = new PlayerMovementSettings(PlayerMovementType::SERVER_AUTHORITATIVE_V2_REWIND, 20, false);
				}
				$playerData->outboundProcessor->execute($packet, $playerData);
			}
		}
	}

	public function teleport(EntityTeleportEvent $event) : void {
		if($event->getFrom()->getWorld()->getFolderName() !== $event->getTo()->getWorld()->getFolderName()){
			$entity = $event->getEntity();
			if ($entity instanceof Player) {
				$data = Esoteric::getInstance()->dataManager->get($entity->getNetworkSession());
				if ($data !== null) $data->inLoadedChunk = false;
			}
		}
	}

}
