<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use xSuper\OqexPractice\commands\arguments\PlayerArgument;
use xSuper\OqexPractice\player\PracticePlayer;

class EsotericLogsSubCommand extends BaseSubCommand {
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
		$selectedUser = $args['player'];
		$data = Esoteric::getInstance()->dataManager->getFromName($selectedUser->getName());
		if ($data === null) {
			// try the log cache
			$cached = Esoteric::getInstance()->logCache[strtolower($selectedUser->getName())] ?? null;
			$sender->sendMessage($cached === null ? TextFormat::RED . "The specified player was not found." : $cached);
			return;
		}

		$message = null;
		foreach ($data->checks as $check) {
			$checkData = $check->getData();
			if ($checkData["violations"] >= 1) {
				if ($message === null) {
					$message = "";
				}
				$message .= TextFormat::YELLOW . $checkData["full_name"] . TextFormat::WHITE . " - " . $checkData["description"] . TextFormat::GRAY . " (" . TextFormat::RED . "x" . var_export(round($checkData["violations"], 3), true) . TextFormat::GRAY . ")" . PHP_EOL;
			}
		}
		$sender->sendMessage($message === null ? TextFormat::GREEN . "{$selectedUser->getName()} has logs" : TextFormat::RED . TextFormat::BOLD . $selectedUser->getName() . "'s logs:\n" . TextFormat::RESET . $message);
	}

	protected function prepare(): void {
		$this->registerArgument(0, new PlayerArgument("player"));
	}
}





