<?php
namespace minepark\commands;

use pocketmine\event\Event;

use minepark\defaults\Permissions;
use minepark\commands\base\Command;
use minepark\common\player\MineParkPlayer;
use pocketmine\world\World;

class NightCommand extends Command
{
    public const CURRENT_COMMAND = "night";

    public function getCommand() : array
    {
        return [
            self::CURRENT_COMMAND
        ];
    }

    public function getPermissions() : array
    {
        return [
            Permissions::ADMINISTRATOR,
            Permissions::OPERATOR
        ];
    }

    public function execute(MineParkPlayer $player, array $args = array(), Event $event = null)
    {
        $player->getWorld()->setTime(World::TIME_NIGHT);
        $player->sendLocalizedMessage("{CommandNight}" . $player->getWorld()->getDisplayName());
    }
}