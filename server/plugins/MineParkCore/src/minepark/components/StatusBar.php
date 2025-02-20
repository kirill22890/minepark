<?php
namespace minepark\components;

use minepark\Tasks;
use minepark\components\base\Component;
use minepark\defaults\TimeConstants;

class StatusBar extends Component
{
    public function initialize()
    {
        Tasks::executeActionWithTicksInterval(TimeConstants::ONE_SECOND_TICKS, [$this, "updateAll"]);
    }

    public function getAttributes() : array
    {
        return [
        ];
    }

    public function updateAll()
    {
        foreach($this->getServer()->getOnlinePlayers() as $player) {
            if(isset($player->getStatesMap()->bar)) {
                $player->sendLocalizedTip($player->getStatesMap()->bar);
            }
        }
    }
}