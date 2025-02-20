<?php
namespace minepark\commands\organisations;

use minepark\Components;
use pocketmine\event\Event;
use minepark\components\chat\Chat;

use minepark\defaults\Permissions;
use minepark\common\player\MineParkPlayer;
use minepark\defaults\OrganisationConstants;
use minepark\commands\base\OrganisationsCommand;
use minepark\components\organisations\Organisations;

class ArestCommand extends OrganisationsCommand
{
    public const CURRENT_COMMAND = "arest";

    private Chat $chat;

    public function __construct()
    {
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
        if (!$this->canArrest($player)) {
            $player->sendMessage("CommandArestCan");
            return;
        }

        $this->chat->sendLocalMessage($player, "{CommandArestCuff}", "§d : ", 10);

        $plrs = $this->getPlayersNear($player);

        if (self::argumentsNo($plrs)) {
            $player->sendMessage("CommandArestNoPlayers");
            return;
        }

        foreach ($plrs as $plr) {
            $this->arrestPlayer($plr, $player);
        }
    }

    private function canArrest(MineParkPlayer $player) : bool
    {
        return $player->getSettings()->organisation === OrganisationConstants::GOVERNMENT_WORK or $player->getSettings()->organisation === OrganisationConstants::SECURITY_WORK;
    }

    private function getPlayersNear(MineParkPlayer $player) : array
    {
        $allplayers = $this->getCore()->getRegionPlayers($player->getPosition(), 5);

        $players = array();

        foreach ($allplayers as $currp) {
            if ($currp->getName() !== $player->getName()) {
                $players[] = $currp;
            }
        }

        return $players;
    }

    private function arrestPlayer(MineParkPlayer $playerToArrest, MineParkPlayer $arrester)
    {
        $playerToArrest->arest();
        $playerToArrest->setImmobile(false);

        $playerToArrest->sendLocalizedMessage("{CommandArestPrisoner}".$arrester->getProfile()->fullName);
        $arrester->sendLocalizedMessage("{CommandArestPolice}".$playerToArrest->getProfile()->fullName);
    }
}