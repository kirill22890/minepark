<?php
namespace minepark\commands\organisations;

use minepark\Providers;
use minepark\Components;
use pocketmine\event\Event;

use minepark\components\chat\Chat;
use minepark\defaults\Permissions;
use minepark\providers\MapProvider;
use minepark\providers\BankingProvider;
use minepark\providers\ProfileProvider;
use minepark\common\player\MineParkPlayer;
use minepark\defaults\OrganisationConstants;
use minepark\commands\base\OrganisationsCommand;
use minepark\components\organisations\Organisations;

class ChangeNameCommand extends OrganisationsCommand
{
    public const CURRENT_COMMAND = "changename";

    public const POINT_NAME = "Мэрия";

    private BankingProvider $bankingProvider;

    private ProfileProvider $profileProvider;

    private MapProvider $mapProvider;

    private Chat $chat;

    public function __construct()
    {
        $this->bankingProvider = Providers::getBankingProvider();

        $this->profileProvider = Providers::getProfileProvider();

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
            $player->sendMessage("CommandChangeNameNoCanGive");
            return;
        }

        if (!$this->isNearPoint($player)) {
            $player->sendMessage("CommandChangeNameNoGov");
            return;
        }

        $plrs = $this->getPlayersNear($player);

        if (self::argumentsNo($plrs)) {
            $player->sendMessage("CommandChangeNameNoPlayer");
            return;
        }

        if (self::argumentsMin(2, $plrs)) {
            $this->moveThemOut($plrs, $player);
            return;
        }

        if (!self::argumentsMin(2, $args)) {
            $player->sendMessage("CommandChangeNameHelp");
            return;
        }

        $this->tryChangeName($plrs[0], $player, $args[0], $args[1]);
    }

    private function tryChangeName(MineParkPlayer $toPlayer, MineParkPlayer $government, string $name, string $surname)
    {
        
        $oldname = $toPlayer->getProfile()->fullName;
        $toPlayer->getProfile()->fullName = $name . ' ' . $surname;

        $this->profileProvider->saveProfile($toPlayer);
        $toPlayer->sendTitle("§aпоздравляем!","§9$oldname §7>>> §e".$toPlayer->getProfile()->fullName, 5);

        $this->bankingProvider->givePlayerMoney($government, 10);
        $government->sendLocalizedMessage("{CommandChangeName}".$toPlayer->getProfile()->fullName);
    }

    private function moveThemOut(array $plrs, MineParkPlayer $government)
    {
        $this->chat->sendLocalMessage($government, "{CommandChangeNameManyPlayers1}");
        foreach($plrs as $id => $player) {
            if($id > 1) {
                $player->sendMessage("CommandChangeNameManyPlayers2");
            }
        }
        $government->sendMessage("CommandChangeNameManyPlayers3");
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
        $allPlayers = $this->getCore()->getRegionPlayers($player->getPosition(), 5);

        $players = array();
        foreach ($allPlayers as $currp) {
            if ($currp->getName() !== $player->getName()) {
                $players[] = $currp;
            }
        }

        return $players;
    }
}