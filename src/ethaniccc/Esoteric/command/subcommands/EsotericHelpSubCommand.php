<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use xSuper\OqexPractice\player\PracticePlayer;

class EsotericHelpSubCommand extends BaseSubCommand {
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
		$helpMessage = TextFormat::GRAY . str_repeat("-", 8) . " " . TextFormat::BOLD . TextFormat::GRAY . "[" . "§l§4BaC" . TextFormat::GRAY . "] " . TextFormat::RESET . TextFormat::GRAY . str_repeat("-", 8) . PHP_EOL . TextFormat::YELLOW . "/ac logs <player> - Get the anti-cheat logs of the specified player (permission=ac.command.logs)" . PHP_EOL . TextFormat::GOLD . "/ac delay <delay> - Set your alert cooldown delay (permission=ac.command.delay)" . PHP_EOL . TextFormat::YELLOW . "/ac alerts <on/off> - Toggle alerts on or off (permission=ac.alerts)" . PHP_EOL . TextFormat::GOLD . "/ac banwave <subcommand> - Do actions with banwaves (permission=ac.command.banwave)" . PHP_EOL . TextFormat::YELLOW . "/ac timings <seconds> - Enable timings for a certain amount of seconds to see performance (permission=ac.command.timings)";
		$sender->sendMessage($helpMessage);
	}

	protected function prepare(): void {
	}
}




