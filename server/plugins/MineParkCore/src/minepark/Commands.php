<?php
namespace minepark;

use minepark\commands\admin\BanCommand;
use minepark\commands\admin\UnbanCommand;
use pocketmine\event\Event;
use minepark\defaults\EventList;
use minepark\commands\DayCommand;
use minepark\defaults\Permissions;
use minepark\commands\base\Command;
use minepark\commands\LevelCommand;

use minepark\commands\NightCommand;
use minepark\commands\CasinoCommand;
use minepark\commands\DonateCommand;
use minepark\commands\OnlineCommand;
use minepark\defaults\ChatConstants;
use minepark\commands\map\ATMCommand;
use minepark\commands\map\GPSCommand;
use minepark\commands\JailExitCommand;
use minepark\commands\PassportCommand;
use minepark\commands\AnimationCommand;
use minepark\commands\GetSellerCommand;
use minepark\commands\phone\SmsCommand;
use minepark\commands\TransportCommand;
use minepark\commands\phone\CallCommand;
use minepark\commands\admin\AdminCommand;
use minepark\commands\economy\PayCommand;
use minepark\commands\map\GPSNearCommand;
use minepark\commands\map\ToPointCommand;
use minepark\commands\roleplay\DoCommand;
use minepark\commands\roleplay\MeCommand;
use minepark\commands\economy\BankCommand;
use minepark\commands\map\AddPointCommand;
use minepark\commands\report\CloseCommand;
use minepark\commands\report\ReplyCommand;
use minepark\commands\roleplay\TryCommand;
use minepark\common\player\MineParkPlayer;
use minepark\commands\economy\MoneyCommand;
use minepark\commands\report\ReportCommand;
use minepark\commands\ResetPasswordCommand;
use minepark\commands\roleplay\ShoutCommand;
use minepark\commands\workers\PutBoxCommand;
use minepark\commands\GetOrganisationCommand;
use minepark\commands\map\RemovePointCommand;
use minepark\commands\map\ToNearPointCommand;
use minepark\commands\workers\GetFarmCommand;
use minepark\commands\workers\PutFarmCommand;
use minepark\commands\workers\TakeBoxCommand;
use minepark\commands\roleplay\WhisperCommand;
use minepark\commands\map\FloatingTextsCommand;
use minepark\commands\organisations\AddCommand;
use minepark\commands\base\OrganisationsCommand;
use minepark\commands\economy\MoneyGiftCommand;
use minepark\commands\organisations\HealCommand;
use minepark\commands\organisations\InfoCommand;
use minepark\commands\organisations\SellCommand;
use minepark\commands\organisations\ShowCommand;
use minepark\commands\permissions\SwitchCommand;
use minepark\commands\organisations\ArestCommand;
use minepark\commands\organisations\RadioCommand;
use minepark\commands\organisations\NoFireCommand;
use minepark\commands\organisations\RemoveCommand;
use minepark\commands\organisations\GiveLicCommand;
use minepark\commands\organisations\ChangeNameCommand;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

class Commands
{
    private $commands;
    private $organisationsCommands;

    public function __construct()
    {
        $this->initializeCommands();
        $this->initializeOrganisationsCommands();

        Events::registerEvent(EventList::PLAYER_COMMAND_PREPROCESS_EVENT, [$this, "executeInputData"]);
    }

    public function getCommands() : array
    {
        return $this->commands;
    }

    public function getOrganisationsCommands() : array
    {
        return $this->organisationsCommands;
    }

    private function initializeCommands()
    {
        $this->commands = [
            new AdminCommand,
            new AddPointCommand,
            new GPSCommand,
            new GPSNearCommand,
            new RemovePointCommand,
            new ToNearPointCommand,
            new ToPointCommand,
            new CallCommand,
            new SmsCommand,
            new DoCommand,
            new MeCommand,
            new ShoutCommand,
            new TryCommand,
            new WhisperCommand,
            new GetFarmCommand,
            new PutBoxCommand,
            new PutFarmCommand,
            new TakeBoxCommand,
            new AnimationCommand,
            new CasinoCommand,
            new DonateCommand,
            new GetOrganisationCommand,
            new GetSellerCommand,
            new JailExitCommand,
            new LevelCommand,
            new MoneyCommand,
            new OnlineCommand,
            new PassportCommand,
            new PayCommand,
            new ResetPasswordCommand,
            new ReportCommand,
            new ReplyCommand,
            new CloseCommand,
            new BankCommand,
            new MoneyGiftCommand,
            new DayCommand,
            new NightCommand,
            new TransportCommand,
            new SwitchCommand,
            new FloatingTextsCommand,
            new ATMCommand,
            new BanCommand,
            new UnbanCommand
        ];
    }

    private function initializeOrganisationsCommands()
    {
        $this->organisationsCommands = [
            new AddCommand,
            new ArestCommand,
            new ChangeNameCommand,
            new GiveLicCommand,
            new HealCommand,
            new InfoCommand,
            new NoFireCommand,
            new RadioCommand,
            new RemoveCommand,
            new SellCommand,
            new ShowCommand
        ];
    }

    public function executeInputData(PlayerCommandPreprocessEvent $event)
    {
        $player = MineParkPlayer::cast($event->getPlayer());

        if(!$player->isAuthorized()) {
            return;
        }

        if($event->getMessage()[0] !== ChatConstants::COMMAND_PREFIX) {
            return;
        }

        $rawCommand = substr($event->getMessage(), 1);
        $arguments = explode(Command::ARGUMENTS_SEPERATOR, $rawCommand);

        if($arguments[0] === ChatConstants::ORGANISATIONS_COMMANDS_PREFIX) {
            return $this->executeOrganisationsCommand($player, array_slice($arguments, 1), $event);
        }

        $command = $this->getCommand($arguments[0]);

        if($command === null) {
            return;
        }

        $arguments = array_slice($arguments, 1);

        $event->cancel();

        if(!$this->checkPermissions($player, $command, $event)) {
            return;
        }

        $command->execute($player, $arguments, $event);
    }

    private function executeOrganisationsCommand(MineParkPlayer $player, array $arguments, PlayerCommandPreprocessEvent $event)
    {
        if(!isset($arguments[0])) {
            return;
        }

        $command = $this->getOrganisationsCommand($arguments[0]);

        if(!isset($command)) {
            return;
        }

        $arguments = array_slice($arguments, 1);

        $event->cancel();

        if(!$this->checkPermissions($player, $command, $event)) {
            return;
        }

        $command->execute($player, $arguments, $event);
    }

    private function getCommand(string $commandName) : ?Command
    {
        foreach($this->commands as $command) {
            if(in_array($commandName, $command->getCommand())) {
                return $command;
            }
        }

        return null;
    }

    private function getOrganisationsCommand(string $commandName) : ?OrganisationsCommand
    {
        foreach($this->organisationsCommands as $command) {
            if(in_array($commandName, $command->getCommand())) {
                return $command;
            }
        }

        return null;
    }

    private function checkPermissions(MineParkPlayer $player, Command $command, ?Event $event = null) : bool
    {
        if($this->hasPermissions($player, $command)) {
            return true;
        }

        $player->sendMessage("NoPermission1");
        $player->sendMessage("NoPermission2");

        return false;
    }

    private function hasPermissions(MineParkPlayer $player, Command $command) : bool
    {
        $permissions = $command->getPermissions();

        if(in_array(Permissions::ANYBODY, $permissions)) {
            return true;
        }

        if(in_array(Permissions::OPERATOR, $permissions) or $player->isOperator()) {
            return true;
        }

        if($player->hasPermissions($permissions)) {
            return true;
        }

        return false;
    }
}