<?php

namespace ethaniccc\Esoteric\utils;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use function max;
use function sqrt;

class AABB {
	public static function from(PlayerData $data): AxisAlignedBB {
		$pos = $data->currentLocation;
		return new AxisAlignedBB($pos->x - $data->hitboxWidth, $pos->y, $pos->z - $data->hitboxWidth, $pos->x + $data->hitboxWidth, $pos->y + $data->hitboxHeight, $pos->z + $data->hitboxWidth);
	}

	public static function fromPosition(Vector3 $pos, float $width = 0.3, float $height = 1.8): AxisAlignedBB {
		return new AxisAlignedBB($pos->x - $width, $pos->y, $pos->z - $width, $pos->x + $width, $pos->y + $height, $pos->z + $width);
	}

	public static function fromBlock(Block $block): AxisAlignedBB {
		$b = $block->getCollisionBoxes()[0] ?? null;
		if ($b !== null || count($block->getCollisionBoxes()) > 0) {
			return new AxisAlignedBB($b->minX, $b->minY, $b->minZ, $b->maxX, $b->maxY, $b->maxZ);
		} else {
			$pos = $block->getPosition();
			return new AxisAlignedBB($pos->getX(), $pos->getY(), $pos->getZ(), $pos->getX() + 1, $pos->getY() + 1, $pos->getZ() + 1);
		}
	}

	public static function clone(AxisAlignedBB $bb): AxisAlignedBB {
		return clone $bb;
	}

	public static function distanceFromVector(AxisAlignedBB $bb, Vector3 $vector): float {
		$distX = max($bb->minX - $vector->x, max(0, $vector->x - $bb->maxX));
		$distY = max($bb->minY - $vector->y, max(0, $vector->y - $bb->maxY));
		$distZ = max($bb->minZ - $vector->z, max(0, $vector->z - $bb->maxZ));
		return sqrt(($distX ** 2) + ($distY ** 2) + ($distZ ** 2));
	}

	public static function calculateIntercept(AxisAlignedBB $bb, Vector3 $pos1, Vector3 $pos2): ?RayTraceResult {
		return $bb->isVectorInside($pos1) ? new RayTraceResult($bb, 0, clone MathUtils::$ZERO_VECTOR) : $bb->calculateIntercept($pos1, $pos2);
	}

}