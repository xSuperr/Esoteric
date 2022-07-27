<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use function min;
use function round;

class VelocityA extends Check {

	private float $yMotion = 0.0;

	public function __construct() {
		parent::__construct("Velocity", "A", "Checks if the user is taking an abnormal amount of vertical knockback.", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			//var_dump('motion: ' . $data->ticksSinceMotion, 'jump: ' . $data->ticksSinceJump);
			if ($data->ticksSinceMotion === 1 && $data->ticksSinceJump !== 1) {
				$this->yMotion = $data->motion->y;
			}

			if ($this->yMotion > 0.005) {
				if ($data->hasBlockAbove || $data->immobile || !$data->isAlive || $data->teleported || $data->isInVoid || $data->ticksSinceGlide < 3) {
					$this->yMotion = 0.0;
					$this->buffer = 0;
					return;
				}

				$percentage = ($data->currentMoveDelta->y / $this->yMotion) * 100;
				// var_dump("Esoteric -> (Y: $this->yMotion | DELTA: {$data->currentMoveDelta->y} | PCT: $percentage)");
				if ($percentage < $this->option("pct", 99.9) && $data->inLoadedChunk && !$data->hasBlockAbove && $data->ticksSinceInCobweb >= 5 && $data->ticksSinceFlight >= 10 && $data->ticksSinceInLiquid >= 5 && $data->ticksSinceInClimbable >= 5) {
					//var_dump('Esoteric -> ' . $percentage);
					if (++$this->buffer >= 8) {
						$this->flag($data, ["pct" => round($percentage, 5) . "%",]);
					}
					$this->buffer = min($this->buffer, 16);
				} else {
					$this->buffer = 0;
					$this->reward();
				}

				$this->yMotion = ($this->yMotion - 0.08) * MovementConstants::GRAVITY_MULTIPLICATION;
			}
		}
	}

}