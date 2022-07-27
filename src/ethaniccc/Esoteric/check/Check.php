<?php


namespace ethaniccc\Esoteric\check;

use DateTime;
use ethaniccc\Esoteric\check\misc\editionfaker\EditionFakerA;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\Settings;
use ethaniccc\Esoteric\tasks\BanTask;
use ethaniccc\Esoteric\tasks\KickTask;
use ethaniccc\Esoteric\webhook\Embed;
use ethaniccc\Esoteric\webhook\Message;
use ethaniccc\Esoteric\webhook\Webhook;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\timings\TimingsHandler;
use function array_keys;
use function count;
use function is_numeric;
use function max;
use function microtime;
use function round;
use function str_replace;
use function var_export;

abstract class Check {

	public static array $settings = [];
	public static array $timings = [];
	public static array $kickOnJoin = [];
	/** @var int[] */
	private static array $TOTAL_VIOLATIONS = [];
	public string $name;
	public string $subType;
	public string $description;
	public bool $experimental;
	public float $violations = 0;
	public float $buffer = 0;

	public function __construct(string $name, string $subType, string $description, bool $experimental = false) {
		$this->name = $name;
		$this->subType = $subType;
		$this->description = $description;
		$this->experimental = $experimental;
		if (!isset(self::$settings["$name:$subType"])) {
			$settings = Esoteric::getInstance()->getSettings()->getCheckSettings($name, $subType);
			if (is_numeric($settings)) {
				$settings = ['enabled' => true, 'punishment_type' => 'none', 'max_vl' => 20];
			}
			self::$settings["$name:$subType"] = $settings;
		}
		if (!isset(self::$timings["$name:$subType"])) {
			self::$timings["$name:$subType"] = new TimingsHandler("Esoteric Check $name($subType)", Esoteric::getInstance()->checkTimings);
		}
	}

	public static function getDataString(array $data): string {
		$dataString = "";
		$n = count($data);
		$i = 1;
		foreach ($data as $name => $value) {
			$dataString .= "$name=$value";
			if ($i !== $n)
				$dataString .= ' ';
			$i++;
		}
		return $dataString;
	}

	public function getData(): array {
		return ['violations' => $this->violations, 'description' => $this->description, 'full_name' => $this->name . " ($this->subType)", 'name' => $this->name, 'subType' => $this->subType];
	}

	public function getTimings(): TimingsHandler {
		return self::$timings["$this->name:$this->subType"];
	}

	public abstract function inbound(ServerboundPacket $packet, PlayerData $data): void;

	public function outbound(ClientboundPacket $packet, PlayerData $data): void {
	}

	public function handleOut(): bool {
		return false;
	}

	public function enabled(): ?bool {
		return $this->option('enabled');
	}

	protected function option(string $option, $default = null) {
		return self::$settings["$this->name:$this->subType"][$option] ?? $default;
	}

	protected function flag(PlayerData $data, array $extraData = []): void {
		$extraData['ping'] = $data->networkSession->getPing();
		$dataString = self::getDataString($extraData);
		if (!$this->experimental) {
			++$this->violations;
			if ($this instanceof EditionFakerA) {
			    self::$kickOnJoin[] = $extraData['name'];
			    return;
            }

			if (!isset(self::$TOTAL_VIOLATIONS[$data->player->getName()])) {
				self::$TOTAL_VIOLATIONS[$data->player->getName()] = 0;
			}
			self::$TOTAL_VIOLATIONS[$data->player->getName()]++;
			$banwaveSettings = Esoteric::getInstance()->getSettings()->getWaveSettings();
			if ($banwaveSettings["enabled"] && self::$TOTAL_VIOLATIONS[$data->player->getName()] >= $banwaveSettings["violations"] && !$data->player->hasPermission("ac.bypass")) {
				$wave = Esoteric::getInstance()->getBanwave();
				$wave->add($data->player->getName(), $this->getCodeName());
			}
			$this->sendAlertWebhook($data->player->getName(), $dataString);
		}
		if($data->player->isOnline()){
			$this->warn($data, $extraData);
			if ($this->violations >= $this->option('max_vl') && $this->canPunish()) {
				$data->player->hasPermission('ac.bypass') ? $this->violations = 0 : $this->punish($data);
			}
		}
	}

	public function getCodeName(): string {
		return $this->option('code', "$this->name($this->subType)");
	}

	protected function warn(PlayerData $data, array $extraData): void {
		$dataString = '';
		$n = count($extraData);
		$i = 1;
		foreach ($extraData as $name => $value) {
			$dataString .= "$name=$value";
			if ($i !== $n)
				$dataString .= ' ';
			$i++;
		}
		$string = str_replace(['{prefix}', '{player}', '{check_name}', '{check_subtype}', '{violations}', '{data}'], [Esoteric::getInstance()->getSettings()->getPrefix(), $data->player->getName(), $this->name, $this->subType, var_export(round($this->violations, 2), true), $dataString], Esoteric::getInstance()->getSettings()->getAlertMessage());
		Esoteric::getInstance()->getLogger()->debug($string);
		foreach (Esoteric::getInstance()->hasAlerts as $other) {
			if (microtime(true) - $other->lastAlertTime >= $other->alertCooldown) {
				$other->lastAlertTime = microtime(true);
				$other->player->sendMessage($string);
			}
		}
	}

	protected function canPunish(): bool {
		return $this->option('punishment_type') !== 'none' && !$this->experimental;
	}

	protected function punish(PlayerData $data): void {
		$esoteric = Esoteric::getInstance();
		if ($this->option('punishment_type') === 'ban') {
			$data->isDataClosed = true;
			$l = $esoteric->getSettings()->getBanLength();
			$expiration = is_numeric($l) ? (new DateTime('now'))->modify("+$l day") : null;
			$esoteric->getScheduler()->scheduleTask(new BanTask($data->player, $this->getCodeName(), $expiration));
			$this->sendPunishmentWebhook($data->player->getName(), 'ban');
			if(($bc = $esoteric->getSettings()->getBanBroadcast()) !== 'none') {
				$esoteric->getServer()->broadcastMessage(str_replace(['{prefix}', '{player}', '{check_name}', '{code_name}', '{violations}', '{expires}'], [$esoteric->getSettings()->getPrefix(), $data->player->getName(), $this->name, $this->getCodeName(), $this->violations, $expiration !== null ? $expiration->format('m/d/y H:i') : 'Never'], $bc));
			}
		} elseif ($this->option('punishment_type') === 'kick') {
			$data->isDataClosed = true;
			$string = str_replace(['{prefix}', '{code}'], [$esoteric->getSettings()->getPrefix(), $this->getCodeName()], $esoteric->getSettings()->getKickMessage());
			$esoteric->getScheduler()->scheduleTask(new KickTask($data->player, $string));
			$this->sendPunishmentWebhook($data->player->getName(), 'kick');
			if (($bc = $esoteric->getSettings()->getKickBroadcast()) !== 'none') {
				$esoteric->getServer()->broadcastMessage(str_replace(['{prefix}', '{player}', '{check_name}', '{code_name}', '{violations}'], [$esoteric->getSettings()->getPrefix(), $data->player->getName(), $this->name, $this->getCodeName(), $this->violations], $bc));
			}
		} else {
			$this->violations = 0;
		}
	}

	private function sendAlertWebhook(string $player, string $debug): void {
		$webhookSettings = Esoteric::getInstance()->getSettings()->getWebhookSettings();
		$webhookLink = $webhookSettings['link'];
		$canSend = $webhookSettings['alerts'] && $webhookLink !== 'none';

		if (!$canSend) return;

		$message = new Message();
		$message->setContent('');

		$embed = new Embed();
		$embed->setTitle('Anti-cheat alert');
		$embed->setColor(0xFFC300);
		$embed->setFooter((new DateTime('now'))->format("m/d/y @ h:m:s A"));
		$embed->setDescription(
			"Player: **`$player`\n**Violations: **`$this->violations`\n**Codename: **`{$this->getCodeName()}\n" .
			"`**Detection name: **`$this->name ($this->subType)\n`**Debug data: **`$debug`**"
		);

		$message->addEmbed($embed);

		$webhook = new Webhook($webhookLink, $message);
		$webhook->send();
	}

	private function sendPunishmentWebhook(string $player, string $type): void {
		$webhookSettings = Esoteric::getInstance()->getSettings()->getWebhookSettings();
		$webhookLink = $webhookSettings['link'];
		$canSend = $webhookSettings['alerts'] && $webhookLink !== 'none';

		if (!$canSend) {
			return;
		}

		$message = new Message();
		$message->setContent("");

		$embed = new Embed();
		$embed->setTitle('Anti-cheat punishment');
		$embed->setColor(0xFF0000);
		$embed->setFooter((new DateTime('now'))->format("m/d/y @ h:m:s A"));
		$embed->setDescription("Player: **`$player`**\nType: **`$type`**\nCodename: **`{$this->getCodeName()}`**\nDetection name: **`$this->name ($this->subType)`**");
		$message->addEmbed($embed);

		$webhook = new Webhook($webhookLink, $message);
		$webhook->send();
	}

	protected function setback(PlayerData $data): void {
		if (!$data->hasMovementSuppressed && $this->option("setback", false)) {
			$type = Esoteric::getInstance()->getSettings()->getSetbackType();
			switch ($type) {
				case Settings::SETBACK_SMOOTH:
					$delta = ($data->packetDeltas[0] ?? new Vector3(0, -0.08 * 0.98, 0));
					$packet = CorrectPlayerMovePredictionPacket::create(($data->onGround ? $data->lastLocation : $data->lastOnGroundLocation)->add(0, 1.62, 0), $delta, $data->onGround, array_keys($data->packetDeltas)[0] ?? 0);
					$data->networkSession->sendDataPacket($packet);
					break;
				case Settings::SETBACK_INSTANT:
					$position = $data->onGround ? $data->lastLocation : $data->lastOnGroundLocation;
					$data->player->teleport($position, $data->currentYaw, 0);
					break;
			}
			$data->hasMovementSuppressed = true;
		}
	}

	protected function reward(float $sub = 0.01): void {
		$this->violations = max($this->violations - $sub, 0);
	}

}