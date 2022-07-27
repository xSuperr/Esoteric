<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use pocketmine\entity\Attribute;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\WorldManager;
use function is_null;

class ProcessOutbound {

	public static ?TimingsHandler $baseTimings = null;
	public WorldManager $worldManager;

	public function __construct() {
		if (is_null(self::$baseTimings)) {
			self::$baseTimings = new TimingsHandler("Esoteric Outbound Handling");
		}
		$this->worldManager = Server::getInstance()->getWorldManager();
	}

	public function execute(ClientboundPacket $packet, PlayerData $data): void { // todo make sure player is not null
		self::$baseTimings->startTiming();
		switch($packet::class){
			case MovePlayerPacket::class:
				if(is_null($data->player)) break;
				/** @var MovePlayerPacket $packet */
				if ($packet->actorRuntimeId === $data->player->getId() && ($packet->mode === MovePlayerPacket::MODE_TELEPORT || $packet->mode === MovePlayerPacket::MODE_RESET)) {
					$data->networkStackLatencyHandler->queue($data, static function () use (&$data): void {
						$data->ticksSinceTeleport = 0;
					});
				} elseif ($packet->actorRuntimeId !== $data->player->getId()) {
					$data->entityLocationMap->addEntity($packet);
				}
				break;
			case MoveActorAbsolutePacket::class:
				/** @var MoveActorAbsolutePacket $packet */
				$data->entityLocationMap->addEntity($packet);
				break;
			case UpdateBlockPacket::class:
				/** @var UpdateBlockPacket $packet */
				$blockVector = new Vector3($packet->blockPosition->getX(),$packet->blockPosition->getY(), $packet->blockPosition->getZ());
				foreach ($data->inboundProcessor->placedBlocks as $key => $block) {
					// check if the block's position sent in UpdateBlockPacket is the same as the placed block
					// and if the block runtime ID sent in the packet equals the
					if ($blockVector->equals($block->getPosition()) && $block->getFullId() === RuntimeBlockMapping::getInstance()->fromRuntimeId($packet->blockRuntimeId, $data->player->getNetworkSession()->getProtocolId())) {
						unset($data->inboundProcessor->placedBlocks[$key]);
					}
				}
				break;
			case SetActorMotionPacket::class:
				/** @var SetActorMotionPacket $packet */
				if(!is_null($data->player) && $packet->actorRuntimeId === $data->player->getId()){
					$data->networkStackLatencyHandler->queue($data, static function () use (&$data, $packet) : void {
						$data->motion = $packet->motion;
						$data->ticksSinceMotion = 0;
					});
				}
				break;
			case MobEffectPacket::class:
				/** @var MobEffectPacket $packet */
				if(!is_null($data->player) && $packet->actorRuntimeId === $data->player->getId()){
					switch ($packet->eventId) {
						case MobEffectPacket::EVENT_ADD:
							$effectData = new EffectData();
							$effectData->effectId = $packet->effectId;
							$effectData->ticks = $packet->duration;
							$effectData->amplifier = $packet->amplifier + 1;
							$data->networkStackLatencyHandler->queue($data, static function () use (&$data, $effectData) : void {
								$data->effects[$effectData->effectId] = $effectData;
							});
							break;
						case MobEffectPacket::EVENT_MODIFY:
							$effectData = $data->effects[$packet->effectId] ?? null;
							if (is_null($effectData))
								return;
							$data->networkStackLatencyHandler->queue($data, static function () use (&$effectData, $packet) : void {
								$effectData->amplifier = $packet->amplifier + 1;
								$effectData->ticks = $packet->duration;
							});
							break;
						case MobEffectPacket::EVENT_REMOVE:
							if (isset($data->effects[$packet->effectId])) {
								// removed before the effect duration has wore off client-side
								$data->networkStackLatencyHandler->queue($data, static function () use (&$data, $packet) : void {
									unset($data->effects[$packet->effectId]);
								});
							}
							break;
					}
				}
				break;
			case SetPlayerGameTypePacket::class:
				/** @var SetPlayerGameTypePacket $packet */
				if(is_null($data->player)) return;
				$mode = $data->player->getGamemode();
				$data->networkStackLatencyHandler->queue($data, static function () use (&$data, $mode) : void {
					$data->gamemode = $mode;
				});
				break;
			case SetActorDataPacket::class:
				/** @var SetActorDataPacket $packet */
				if(!is_null($data->player) && $packet->actorRuntimeId === $data->player->getId()){
					if ($data->immobile !== ($currentImmobile = $data->player->isImmobile())) {
						if ($data->loggedIn) {
							$data->networkStackLatencyHandler->queue($data, static function () use (&$data, $currentImmobile) : void {
								$data->immobile = $currentImmobile;
							});
						} else {
							$data->immobile = $currentImmobile;
						}
					}
					$AABB = $data->player->getBoundingBox();
					$hitboxWidth = ($AABB->maxX - $AABB->minX) * 0.5;
					$hitboxHeight = $AABB->maxY - $AABB->minY;
					if ($hitboxWidth !== $data->hitboxWidth) {
						$data->loggedIn ? $data->networkStackLatencyHandler->queue($data, static function () use (&$data, $hitboxWidth) : void {
							$data->hitboxWidth = $hitboxWidth;
						}) : $data->hitboxWidth = $hitboxWidth;
					}
					if ($hitboxHeight !== $data->hitboxWidth) {
						$data->loggedIn ? $data->networkStackLatencyHandler->queue($data, static function () use (&$data, $hitboxHeight) : void {
							$data->hitboxHeight = $hitboxHeight;
						}) : $data->hitboxHeight = $hitboxHeight;
					}
				}
				break;
			case NetworkChunkPublisherUpdatePacket::class:
				/** @var NetworkChunkPublisherUpdatePacket $packet */
				if (!$data->loggedIn) {
					$data->inLoadedChunk = true;
					$data->chunkSendPosition = new Vector3($packet->blockPosition->getX(), $packet->blockPosition->getY(), $packet->blockPosition->getZ());
				} else {
					if (!is_null($data->player) && $data->chunkSendPosition->distance($data->currentLocation->floor()) > $data->player->getViewDistance() * 16) {
						$data->inLoadedChunk = false;
						$data->networkStackLatencyHandler->queue($data, static function () use ($packet, &$data) : void {
							$data->inLoadedChunk = true;
							$data->chunkSendPosition = new Vector3($packet->blockPosition->getX(), $packet->blockPosition->getY(), $packet->blockPosition->getZ());
						});
					}
				}
				break;
			case AdventureSettingsPacket::class:
				/** @var AdventureSettingsPacket $packet */
				$data->networkStackLatencyHandler->queue($data, static function () use ($packet, &$data) : void {
					$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING) || $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
				});
				break;
			case ActorEventPacket::class:
				/** @var ActorEventPacket $packet */
				if(!is_null($data->player) && $packet->actorRuntimeId === $data->player->getId()){
					switch ($packet->eventId) {
                        case ActorEvent::RESPAWN:
							$data->networkStackLatencyHandler->queue($data, static function () use (&$data) : void {
								$data->isAlive = true;
							});
							break;
					}
				}
				break;
			case UpdateAttributesPacket::class:
				/** @var UpdateAttributesPacket $packet */
				if(!is_null($data->player) && $packet->actorRuntimeId === $data->player->getId()){
					foreach ($packet->entries as $attribute) {
						if ($attribute->getId() === Attribute::HEALTH) {
							if ($attribute->getCurrent() <= 0) {
								$data->networkStackLatencyHandler->queue($data, static function () use (&$data): void {
									$data->isAlive = false;
								});
							} elseif ($attribute->getCurrent() > 0 && !$data->isAlive) {
								$data->networkStackLatencyHandler->queue($data, static function () use (&$data): void {
									$data->isAlive = true;
								});
							}
						}
					}
				}
				break;
			case CorrectPlayerMovePredictionPacket::class:
				/** @var CorrectPlayerMovePredictionPacket $packet */
				$data->networkStackLatencyHandler->queue($data, static function () use (&$data) : void {
					$data->ticksSinceTeleport = 0;
				});
				break;
			case RemoveActorPacket::class:
				/** @var RemoveActorPacket $packet */
				$data->networkStackLatencyHandler->queue($data, static function () use (&$data, $packet) : void {
					$data->entityLocationMap->removeEntity($packet->actorUniqueId);
				});
				break;
		}
		self::$baseTimings->stopTiming();
	}

}