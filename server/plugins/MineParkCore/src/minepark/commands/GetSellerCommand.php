<?php
namespace minepark\commands;

use minepark\Providers;
use pocketmine\event\Event;
use minepark\defaults\Sounds;
use pocketmine\world\Position;

use minepark\defaults\Permissions;
use minepark\commands\base\Command;
use minepark\providers\MapProvider;
use minepark\common\player\MineParkPlayer;
use minepark\defaults\OrganisationConstants;
use minepark\components\organisations\Organisations;

class GetSellerCommand extends Command
{
    public const CURRENT_COMMAND = "getseller";

    public const DISTANCE = 10;

    private MapProvider $mapProvider;

    public function __construct()
    {
        $this->mapProvider = Providers::getMapProvider();
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
        $player->sendSound(Sounds::ROLEPLAY);

        $shopPoint = $this->getShop($player->getPosition());

        if($shopPoint == null) {
            $player->sendMessage("CommandGetSellerNoPoint");
            return;
        }

        foreach($this->getServer()->getOnlinePlayers() as $targetPlayer){
            $targetPlayer = MineParkPlayer::cast($targetPlayer);
            if($targetPlayer->getSettings()->organisation == OrganisationConstants::SELLER_WORK) {
                $targetPlayer->sendMessage("CommandGetSellerCall1");
            }
        }

        $player->sendMessage("CommandGetSellerCall2");
        $player->sendMessage("CommandGetSellerCall3");
    }

    private function getShop(Position $position) : ?string
    {
        $shops = $this->mapProvider->getNearPoints($position, self::DISTANCE);
        
        foreach($shops as $point) {
            if($this->mapProvider->getPointGroup($point) == 2) {
                return $point;
            }
        }
        
        return null;
    }
}