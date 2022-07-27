<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\check\combat\aim\AimA;
use ethaniccc\Esoteric\check\combat\aim\AimB;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerA;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerB;
use ethaniccc\Esoteric\check\combat\killaura\KillAuraA;
use ethaniccc\Esoteric\check\combat\killaura\KillAuraB;
use ethaniccc\Esoteric\check\combat\range\RangeA;
use ethaniccc\Esoteric\check\misc\editionfaker\EditionFakerA;
use ethaniccc\Esoteric\check\misc\timer\TimerA;
use ethaniccc\Esoteric\check\movement\velocity\VelocityA;
use ethaniccc\Esoteric\check\movement\velocity\VelocityB;
use ethaniccc\Esoteric\data\process\NetworkStackLatencyHandler;
use ethaniccc\Esoteric\data\process\ProcessInbound;
use ethaniccc\Esoteric\data\process\ProcessOutbound;
use ethaniccc\Esoteric\data\process\ProcessTick;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use ethaniccc\Esoteric\data\sub\location\LocationMap;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function microtime;
use function spl_object_hash;

final class PlayerData {

	public ?Player $player = null;
	public NetworkSession $networkSession;
	/** @var string - The spl_object_hash identifier of the player. */
	public string $hash;
	/** @var string - Identifier used in network interface */
	public string $networkIdentifier;
	/** @var bool - Boolean value for if the player is logged in. */
	public bool $loggedIn = false;
	/** @var bool - The boolean value for if the player has alerts enabled. This will always be false for players without alert permissions. */
	public bool $hasAlerts = false;
	/** @var int - The alert cooldown the player has set. */
	public int $alertCooldown = 0;
	/** @var float - The last time the player has received an alert message. */
	public float $lastAlertTime;
	/** @var Check[] - An array of checks */
	public array $checks = [];
	/** @var ProcessInbound - A class to process packet data sent by the client. */
	public ProcessInbound $inboundProcessor;
	/** @var ProcessOutbound - A class to process packet data sent by the server. */
	public ProcessOutbound $outboundProcessor;
	/** @var ProcessTick - A class to execute every tick. Mainly will be used for NetworkStackLatency timeouts, and */
	public ProcessTick $tickProcessor;
	public LocationMap $entityLocationMap;
	public NetworkStackLatencyHandler $networkStackLatencyHandler;
	public int $latency = 0;
	/** @var int - ID of the current target entity */
	public int $target = -1;
	/** @var ?Vector3 - Attack position of the player */
	public ?Vector3 $attackPos;
	/** @var EffectData[] */
	public array $effects = [];
	public int $currentTick = 0;
	/** @var Vector3[] */
	public array $packetDeltas = [];
	/** @var ?Vector3 - The current and previous locations of the player */
	public ?Vector3 $lastLocation = null;
	public ?Vector3 $currentLocation = null;
	public ?Vector3 $lastOnGroundLocation = null;
	/** @var ?Vector3 - Movement deltas of the player */
	public ?Vector3 $currentMoveDelta = null;
	/** @var float - Rotation values of the player */
	public float $previousPitch = 0.0;
	public float $currentYaw = 0.0;
	public float $previousYaw = 0.0;
	public float $currentPitch = 0.0;
	/** @var float - Rotation deltas of the player */
	public float $currentYawDelta = 0.0;
	public float $currentPitchDelta = 0.0;
	/** @var bool - The boolean value for if the player is on the ground. The client on-ground value is used for this. */
	public bool $onGround = true;
	/** @var bool - An expected value for the client's on ground. */
	public bool $expectedOnGround = true;
	public int $offGroundTicks = 0;
	public ?AxisAlignedBB $boundingBox = null;
	public ?Vector3 $directionVector = null;
	/** @var int - Ticks since the player has taken motion. */
	public int $ticksSinceMotion = 0;
	public ?Vector3 $motion = null;
	public bool $isCollidedVertically = false;
	public bool $isCollidedHorizontally = false;
	public bool $hasBlockAbove = false;
	public int $ticksSinceInLiquid = 0;
	public int $ticksSinceInClimbable = 0;
	public int $ticksSinceInCobweb = 0;
	/** @var int - Movements passed since the user teleported. */
	public int $ticksSinceTeleport = 0;
	public bool $isGliding = false;
	public int $ticksSinceGlide = 0;
	/** @var bool - Boolean value for if the player is in the void. */
	public bool $isInVoid = false;
	public bool $teleported = false;
	/** @var int - The amount of movements that have passed since the player has disabled flight. */
	public int $ticksSinceFlight = 0;
	/** @var bool - Boolean value for if the player is flying. */
	public bool $isFlying = false;
	/** @var int - Movements that have passed since the user has jumped. */
	public int $ticksSinceJump = 0;
	public bool $hasMovementSuppressed = false;
	/** @var bool - Boolean value for if the player is in a chunk they've received */
	public bool $inLoadedChunk = false;
	/** @var Vector3 - Position sent in NetworkChunkPublisherUpdatePacket */
	public Vector3 $chunkSendPosition;
	public bool $immobile = false;
	public bool $canPlaceBlocks = true;
	public float $hitboxWidth = 0.0;
	public float $hitboxHeight = 0.0;
	public bool $isAlive = true;
	/** @var int - Amount of client ticks that have passed since the player has spawned. */
	public int $ticksSinceSpawn = 0;
	/** @var int - Device OS of the player */
	public int $playerOS = DeviceOS::UNKNOWN;
	public GameMode $gamemode;
	public float $jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
	public float $jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;
	public float $moveStrafe = 0.0;
	public float $moveForward = 0.0;
	/** @var int[] */
	public array $clickSamples = [];
	public bool $runClickChecks = false;
	public float $cps = 0.0, $kurtosis = 0.0, $skewness = 0.0, $deviation = 0.0, $outliers = 0.0, $variance = 0.0;
	/** @var int - Last tick the client clicked. */
	public int $lastClickTick = 0;
	public bool $isDataClosed = false;
	public bool $isFullKeyboardGameplay = true;

	public function __construct(NetworkSession $session) {
		$this->gamemode = GameMode::SURVIVAL();
		$this->hash = spl_object_hash($session);
		$this->networkIdentifier = "{$session->getPort()} {$session->getIp()}";
		$this->networkStackLatencyHandler = NetworkStackLatencyHandler::getInstance();
		$this->networkSession = $session;
		$zeroVec = clone MathUtils::$ZERO_VECTOR;

		// AIDS START
		$this->currentLocation = $this->lastLocation = $this->currentMoveDelta = $this->lastOnGroundLocation = $this->directionVector = $this->motion = $zeroVec;
		// AIDS END

		$this->inboundProcessor = new ProcessInbound();
		$this->outboundProcessor = new ProcessOutbound();
		$this->tickProcessor = new ProcessTick();

		$this->entityLocationMap = new LocationMap();

		$this->alertCooldown = Esoteric::getInstance()->getSettings()->getAlertCooldown();
		$this->lastAlertTime = microtime(true);

		$this->checks = [
			new AutoClickerA, new AutoClickerB, # AutoClicker checks
			new AimA, new AimB, # Aim Checks
			new KillAuraA, new KillAuraB, # Killaura checks
			new RangeA, # Range checks
			new VelocityA, new VelocityB, # Velocity checks
			new EditionFakerA, # EditionFaker checks
			new TimerA, # Timer checks
		];
	}

	public function tick(): void {
		$this->currentTick++;
		$this->entityLocationMap->executeTick();
	}

}