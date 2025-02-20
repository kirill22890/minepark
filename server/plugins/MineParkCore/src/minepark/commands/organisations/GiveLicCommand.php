<?php
namespace minepark\commands\organisations;

use minepark\Providers;
use minepark\Components;
use pocketmine\event\Event;

use minepark\components\chat\Chat;
use minepark\defaults\Permissions;
use minepark\providers\MapProvider;
use minepark\common\player\MineParkPlayer;
use minepark\defaults\OrganisationConstants;
use minepark\commands\base\OrganisationsCommand;
use minepark\components\organisations\Organisations;

class GiveLicCommand extends OrganisationsCommand
{
    public const CURRENT_COMMAND = "givelic";

    public const POINT_NAME = "Мэрия";

    private MapProvider $mapProvider;

    private Chat $chat;

    public function __construct()
    {
        $this->mapProvider = Providers::getMapProvider();

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
        if (!$this->canGiveDocuments($player)) {
            $player->sendMessage("CommandGiveLicNoCanGive");
            return;
        }

        if (!$this->isNearPoint($player)) {
            $player->sendMessage("CommandGiveLicNoGov");
            return;
        }

        $plrs = $this->getPlayersNear($player);

        if (self::argumentsNo($plrs)) {
            $player->sendMessage("CommandGiveLicNoPlayer");
            return;
        }

        if (self::argumentsMin(2, $plrs)) {
            $this->moveThemOut($plrs, $player);
            return;
        }

        $this->tryGiveLicense($plrs[0], $player);
    }

    private function tryGiveLicense(MineParkPlayer $toPlayer, MineParkPlayer $government)
    {
        $this->chat->sendLocalMessage($government, "{CommandGiveLicKeys}", "§d : ", 10);

        $government->sendMessage("CommandGiveLicNoLic1");
        $toPlayer->sendMessage("CommandGiveLicNoLic2");
    }

    private function moveThemOut(array $plrs, MineParkPlayer $government)
    {
        $this->chat->sendLocalMessage($government, "{CommandGiveLicManyPlayers1}");

        foreach($plrs as $id => $p) {
            if($id > 1) {
                $p->sendMessage("CommandGiveLicManyPlayers2");
            }
        }

        $government->sendMessage("CommandGiveLicManyPlayers3");
    }

    private function canGiveDocuments(MineParkPlayer $player) : bool
    {
        return $player->getSettings()->organisation === OrganisationConstants::GOVERNMENT_WORK or $player->getSettings()->organisation === OrganisationConstants::LAWYER_WORK;
    }

    private function isNearPoint(MineParkPlayer $player) : bool
    {
        $plist = $this->mapProvider->getNearPoints($player->getPosition(), 32);
        return in_array(self::POINT_NAME, $plist);
    }

    private function getPlayersNear(MineParkPlayer $player) : array
    {
        $allplayers = $this->getCore()->getRegionPlayers($player->getPosition(), 5);

        $players = array();
        foreach ($allplayers as $currp) {
            if ($currp->getName() != $player->getName()) {
                $players[] = $currp;
            }
        }

        return $players;
    }
}