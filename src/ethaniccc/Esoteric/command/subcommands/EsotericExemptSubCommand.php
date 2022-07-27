<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\command\subcommands\exempt\ExemptAddSubCommand;
use ethaniccc\Esoteric\command\subcommands\exempt\ExemptAllSubCommand;
use ethaniccc\Esoteric\command\subcommands\exempt\ExemptGetSubCommand;
use ethaniccc\Esoteric\command\subcommands\exempt\ExemptRemoveSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use xSuper\OqexPractice\player\PracticePlayer;

class EsotericExemptSubCommand extends BaseSubCommand {
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
		$sender->sendMessage(TextFormat::RED . "Available sub commands: all, get, add, remove");
	}

	protected function prepare(): void {
		$this->registerSubCommand(new ExemptAllSubCommand($this->plugin, "all", "Get all the currently exempted players"));
		$this->registerSubCommand(new ExemptGetSubCommand($this->plugin, "get", "Check if a target player is exempt or not"));
		$this->registerSubCommand(new ExemptAddSubCommand($this->plugin, "add", "Add a player to an exempt list"));
		$this->registerSubCommand(new ExemptRemoveSubCommand($this->plugin, "remove", "Remove a player to an exempt list"));
	}
}





