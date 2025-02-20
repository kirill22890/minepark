<?php
namespace minepark\commands\workers;

use minepark\Components;

use pocketmine\event\Event;
use minepark\defaults\Sounds;

use minepark\defaults\Permissions;
use minepark\commands\base\Command;
use minepark\common\player\MineParkPlayer;
use minepark\components\organisations\Organisations;

class TakeBoxCommand extends Command
{
    public const CURRENT_COMMAND = "takebox";

    private Organisations $organisations;

    public function __construct()
    {
        $this->organisations = Components::getComponent(Organisations::class);
    }

    public function getCommand() : array
    {
        return [
            self::CURRENT_COMMAND
        ];
    }

    public function getPermissions() : array
    {
        return [
            Permissions::ANYBODY
        ];
    }

    public function execute(MineParkPlayer $player, array $args = array(), Event $event = null)
    {
        $this->organisations->getWorkers()->takeBox($player);
        
        $player->sendSound(Sounds::ROLEPLAY);
    }
}