<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use xSuper\OqexPractice\commands\arguments\PlayerArgument;
use xSuper\OqexPractice\player\PracticePlayer;

class ExemptAddSubCommand extends BaseSubCommand {
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if ($sender instanceof PracticePlayer) {
            $sender->canRun(function (bool $canRun) use ($sender, $args): void {
                if (!$canRun) {
                    $sender->sendMessage('§r§cYou do not have permission to run this command!');
                    return;
                }

                $this->process($sender, $args);
            }, PracticePlayer::MOD);

            return;
        }

        $this->process($sender, $args);
    }

    public function process(CommandSender $sender, array $args): void {
		$selected = $args['player']->getName();
		Esoteric::getInstance()->exemptList[] = $selected;
		$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::GREEN . " $selected was exempted from Esoteric");
	}

	protected function prepare(): void {
		$this->registerArgument(0, new PlayerArgument("player"));
	}
}







