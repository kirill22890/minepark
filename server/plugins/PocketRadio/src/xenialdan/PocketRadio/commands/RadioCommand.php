<?php

declare(strict_types=1);

namespace xenialdan\PocketRadio\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use xenialdan\PocketRadio\Loader;

class RadioCommand extends Command implements PluginOwned
{
    public function __construct(Plugin $plugin)
    {
        parent::__construct("radio");
        $this->setPermission("pocketradio.command.radio");
        $this->setDescription("Manage radio");
        $this->setUsage("/radio");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
		if($sender->getServer()->isOp($sender->getName()) and isset($args[0]) and $args[0] == "next") {
			Loader::playNext();
		} else {
			$sender->radioMode = !$sender->radioMode;
			$sender->sendMessage($sender->radioMode ? "RadioOn" : "RadioOff");
		}
		
        return true;
    }

    public function getOwningPlugin(): Plugin
    {
        // TODO: Implement getOwningPlugin() method.
    }
}
