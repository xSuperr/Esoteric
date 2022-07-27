<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\BooleanArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use xSuper\OqexPractice\player\PracticePlayer;

class EsotericAlertsSubCommand extends BaseSubCommand {
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if ($sender instanceof PracticePlayer) {
            $sender->canRun(function (bool $canRun) use ($sender, $args): void {
                if (!$canRun) {
                    $sender->sendMessage('§r§cYou do not have permission to run this command!');
                    return;
                }

                $this->process($sender, $args);
            }, PracticePlayer::HELPER);

            return;
        }

        $sender->sendMessage('§r§cYou have to be in-game to use this command!');
    }

	public function process(CommandSender $sender, array $args): void {
		$playerData = Esoteric::getInstance()->dataManager->get($sender->getNetworkSession());
		$toggle = $args['toggle'] ?? !$playerData->hasAlerts;
		$playerData->hasAlerts = $toggle;
		$sender->sendMessage($playerData->hasAlerts ? TextFormat::GREEN . "Your alerts have been turned on" : TextFormat::RED . "Your alerts have been disabled");
	}

	protected function prepare(): void {
		$this->registerArgument(0, new BooleanArgument("toggle", true));
	}
}





