<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use xSuper\OqexPractice\player\PracticePlayer;

class EsotericTimingsSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if ($sender instanceof PracticePlayer) {
            $sender->canRun(function (bool $canRun) use ($sender, $args): void {
                if (!$canRun) {
                    $sender->sendMessage('§r§cYou do not have permission to run this command!');
                    return;
                }

                $this->process($sender, $args);
            }, PracticePlayer::ADMIN);

            return;
        }

        $this->process($sender, $args);
    }

	public function process(CommandSender $sender, array $args): void {
		$time = $args['time'] ?? 60;
		Server::getInstance()->dispatchCommand(new ConsoleCommandSender($this->plugin->getServer(), $this->plugin->getServer()->getLanguage()), "timings on");
		Esoteric::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
			Server::getInstance()->dispatchCommand(new ConsoleCommandSender($this->plugin->getServer(), $this->plugin->getServer()->getLanguage()), "timings paste");
			Server::getInstance()->dispatchCommand(new ConsoleCommandSender($this->plugin->getServer(), $this->plugin->getServer()->getLanguage()), "timings off");
		}), $time * 20);
	}

	protected function prepare(): void {
		$this->registerArgument(0, new IntegerArgument("time", true));
	}
}





