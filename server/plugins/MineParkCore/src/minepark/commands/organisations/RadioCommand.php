<?php
namespace minepark\commands\organisations;

use pocketmine\event\Event;
use minepark\defaults\Permissions;
use minepark\common\player\MineParkPlayer;

use minepark\defaults\OrganisationConstants;
use minepark\commands\base\OrganisationsCommand;
use minepark\components\organisations\Organisations;

class RadioCommand extends OrganisationsCommand
{
    public const CURRENT_COMMAND = "r";

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
        if (self::argumentsNo($args)) {
            return $player->sendMessage("§eПравильное использование этой команды: /o r [ТЕКСТ]");
        }

        $organisationId = $player->getSettings()->organisation;

        if ($organisationId === OrganisationConstants::NO_WORK) {
            $player->sendMessage("§6У вас нет рации!");
            return;
        }

        $implodedMessage = implode(self::ARGUMENTS_SEPERATOR, $args);

        $generatedRadioMessage = "§d[РАЦИЯ] §7" . $player->getProfile()->fullName . " §4> §7" . $implodedMessage;

        foreach($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer = MineParkPlayer::cast($onlinePlayer);
            if ($onlinePlayer->getSettings()->organisation === $organisationId) {
                $onlinePlayer->sendMessage($generatedRadioMessage);
            }
        }
    }
}