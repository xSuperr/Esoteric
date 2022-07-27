<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use xSuper\OqexPractice\player\PracticePlayer;

class EsotericDelaySubCommand extends BaseSubCommand {
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
		$delay = $args['delay'] ?? Esoteric::getInstance()->getSettings()->getAlertCooldown();
		$playerData = Esoteric::getInstance()->dataManager->get($sender->getNetworkSession());
		$playerData->alertCooldown = $delay;
		$sender->sendMessage(TextFormat::GREEN . "Your alert cooldown was set to $delay seconds");
	}

	protected function prepare(): void {
		$this->registerArgument(0, new IntegerArgument("delay", true));
	}
}





