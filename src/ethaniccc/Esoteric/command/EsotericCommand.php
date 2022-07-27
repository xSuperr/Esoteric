<?php

namespace ethaniccc\Esoteric\command;

use CortexPE\Commando\BaseCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericAlertsSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericDelaySubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericExemptSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericHelpSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericLogsSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericTimingsSubCommand;
use pocketmine\command\CommandSender;
use xSuper\OqexPractice\player\PracticePlayer;

class EsotericCommand extends BaseCommand {

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

        $this->process($sender, $args);
    }

	public function process(CommandSender $sender, array $args): void {
		$sender->sendMessage('§r§l§4BaC §r§8» §4Better Anti Cheat §7- A rewrite of §4Esoteric');
	}

	protected function prepare(): void {
		$this->registerSubCommand(new EsotericHelpSubCommand($this->plugin, "help", "A help message with all the commands"));
		$this->registerSubCommand(new EsotericLogsSubCommand($this->plugin, "logs", "Retrieve user anti-cheat logs"));
		$this->registerSubCommand(new EsotericDelaySubCommand($this->plugin, "delay", "Set your anti-cheat alert delay cooldown"));
		$this->registerSubCommand(new EsotericAlertsSubCommand($this->plugin, "alerts", "Toggle alerts in-game on/off"));
		$this->registerSubCommand(new EsotericTimingsSubCommand($this->plugin, "timings", "Measure performance with timings"));
		$this->registerSubCommand(new EsotericExemptSubCommand($this->plugin, "exempt", "Handle exemption settings"));
	}

}



