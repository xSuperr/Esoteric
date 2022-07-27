<?php

namespace ethaniccc\Esoteric\tasks;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\scheduler\Task;
use xSuper\OqexPractice\player\PlayerSqlHelper;
use xSuper\OqexPractice\player\PracticePlayer;
use function array_filter;

class TickingTask extends Task {

	public int $currentTick = 0;

	public function onRun() : void
    {
        if ($this->currentTick % 40 === 0) {
            if ($this->currentTick % 40 === 0) {
                Esoteric::getInstance()->hasAlerts = array_filter(Esoteric::getInstance()->dataManager->getAll(), static function (PlayerData $data): bool {
                    return $data->player !== null && !$data->player->isClosed() && $data->hasAlerts && $data->player->canReceiveAlerts();
                });
            }
        }
        foreach (Esoteric::getInstance()->dataManager->getAll() as $playerData) {
            $playerData->tickProcessor->execute($playerData);
        }
    }
}