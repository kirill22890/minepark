<?php
namespace minepark\commands\roleplay;

use minepark\Components;

use pocketmine\event\Event;
use minepark\defaults\Sounds;

use minepark\components\chat\Chat;
use minepark\components\administrative\Tracking;
use minepark\defaults\Permissions;
use minepark\commands\base\Command;
use minepark\common\player\MineParkPlayer;

class WhisperCommand extends Command
{
    public const CURRENT_COMMAND = "w";

    public const DISTANCE = 4;

    private Tracking $tracking;

    private Chat $chat;

    public function __construct()
    {
        $this->tracking = Components::getComponent(Tracking::class);

        $this->chat = Components::getComponent(Chat::class);
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
        $event->cancel();

        if(self::argumentsNo($args)) {
            $player->sendMessage("CommandRolePlayWhisperUse");
            return;
        }

        $message = implode(self::ARGUMENTS_SEPERATOR, $args);
        
        $this->chat->sendLocalMessage($player, $message, "{CommandRolePlayWhisperDo}", self::DISTANCE);
        $player->sendSound(Sounds::ROLEPLAY);

        $this->tracking->actionRP($player, $message, self::DISTANCE, "[WHISPER]");
    }
}