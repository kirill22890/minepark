<?php
namespace minepark\components;

use minepark\defaults\Defaults;
use minepark\Tasks;
use minepark\Events;
use minepark\Providers;
use minepark\defaults\EventList;
use minepark\defaults\TimeConstants;
use minepark\models\dtos\PasswordDto;
use minepark\components\base\Component;
use minepark\common\player\MineParkPlayer;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerJoinEvent;
use minepark\providers\data\UsersDataProvider;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

class Auth extends Component
{
    private const STATE_REGISTER = 0;
    private const STATE_NEED_AUTH = 1;
    private const STATE_AUTO = 2;

    private $ips = [];

    private UsersDataProvider $usersDataProvider;

    public function initialize()
    {
        Events::registerEvent(EventList::PLAYER_JOIN_EVENT, [$this, "afterJoin"]);
        Events::registerEvent(EventList::PLAYER_INTERACT_EVENT, [$this, "handleInteract"]);
        Events::registerEvent(EventList::BLOCK_BREAK_EVENT, [$this, "handleBlockBreak"]);
        Events::registerEvent(EventList::BLOCK_PLACE_EVENT, [$this, "handleBlockPlace"]);
        Events::registerEvent(EventList::INVENTORY_TRANSACTION_EVENT, [$this, "handleInventoryTransaction"]);
        Events::registerEvent(EventList::PLAYER_COMMAND_PREPROCESS_EVENT, [$this, "executeInputData"]);

        $this->usersDataProvider = Providers::getUsersDataProvider();
    }

    public function getAttributes() : array
    {
        return [
        ];
    }

    public function afterJoin(PlayerJoinEvent $event)
    {
        $player = MineParkPlayer::cast($event->getPlayer());

        $state = $this->checkState($player);

        $this->setMovement($player, false);

        switch($state) {
            case self::STATE_REGISTER:
                $player->getStatesMap()->bar = "AuthPasswordRegister"; 
            break;
            case self::STATE_NEED_AUTH:
                $player->getStatesMap()->bar = "AuthPasswordLogin"; 
            break;
            case self::STATE_AUTO:
                $this->autoLogInUser($player);
            break;
            default:
                $player->getStatesMap()->bar = "AuthError"; 
            break;
        }
    }

    public function handleInteract(PlayerInteractEvent $event)
    {
        if (!$event->getPlayer()->isAuthorized()) {
            $event->cancel();
        }
    }

    public function handleBlockBreak(BlockBreakEvent $event)
    {
        if (!$event->getPlayer()->isAuthorized()) {
            $event->cancel();
        }
    }

    public function handleInventoryTransaction(InventoryTransactionEvent $event)
    {
        foreach($event->getTransaction()->getInventories() as $inventory) {
            $holder = $inventory->getHolder();
            if($holder instanceof MineParkPlayer and !$holder->getStatesMap()->authorized) {
                $event->cancel();
            }
        }
    }

    public function handleBlockPlace(BlockPlaceEvent $event)
    {
        if (!$event->getPlayer()->isAuthorized()) {
            $event->cancel();
        }
    }

    public function executeInputData(PlayerCommandPreprocessEvent $event)
    {
        $player = MineParkPlayer::cast($event->getPlayer());

        if(!$player->isAuthorized()) {
            $this->login($player, $event->getMessage());
            $event->cancel();
            return;
        }
    }
    
    public function checkState(MineParkPlayer $player) : int
    {
        if(!$this->usersDataProvider->isUserPasswordExist($player->getName())) {
            return self::STATE_REGISTER;
        } else {
            if(isset($this->ips[$player->getName()]) and $this->ips[$player->getName()] == $player->getNetworkSession()->getIp()) {
                return self::STATE_AUTO;
            }
            else {
                return self::STATE_NEED_AUTH;
            }
        }
    }
    
    public function login(MineParkPlayer $player, string $password)
    {
        $state = $this->checkState($player);

        if($state == self::STATE_REGISTER) {
            if(strlen($password) < 6) {
                $player->sendMessage("AuthLen");
            } else {
                $this->registerUser($player, $password);
            }
        } elseif($state == self::STATE_NEED_AUTH) {
            $passwordWithSalt = $password . Defaults::SALT;

            if(strlen($password) < 6) {
                $player->kick("AuthLen");
            } elseif(password_verify($passwordWithSalt, $this->usersDataProvider->getUserPassword($player->getName()))) {
                $this->logInUser($player);
            } else {
                $player->kick("AuthInvalid");
            }
        }
    }
    
    public function setMovement($player, bool $status)
    {
        $player->setImmobile(!$status);
    }

    public function sendWelcomeText(MineParkPlayer $player)
    {	
        $player->addTitle("WelcomeTitle1","WelcomeTitle2", 5);
        $this->sendWelcomeChatText($player);
    }
    
    public function sendWelcomeChatText(MineParkPlayer $player)
    {
        $player->sendMessage("WelcomeTextMessage1");
        $player->sendMessage("WelcomeTextMessage2");
        $player->sendMessage("WelcomeTextMessage3");
        $player->sendMessage("WelcomeTextMessage4");
        $player->sendMessage("WelcomeTextMessage5");
    }

    private function logInUser(MineParkPlayer $player)
    {
        $player->getStatesMap()->authorized = true;
        $player->getStatesMap()->bar = null;

        $this->ips[$player->getName()] = $player->getNetworkSession()->getIp();
        
        $this->sendWelcomeText($player);
        
        $this->setMovement($player, true);
    }

    private function registerUser(MineParkPlayer $player, string $password)
    {
        $this->updatePassword($player, $password);
        $this->ips[$player->getName()] = $player->getNetworkSession()->getIp();

        $player->getStatesMap()->authorized = true;
        $player->getStatesMap()->bar = null; 

        $this->sendWelcomeText($player);
        $player->sendLocalizedMessage("{AuthStart}" . $password);

        $this->setMovement($player, true);
    }

    private function updatePassword(MineParkPlayer $player, string $password) 
    {
        $passwordWithSalt = $password . Defaults::SALT;

        $passwordDto = new PasswordDto();
        $passwordDto->name = $player->getName();
        $passwordDto->password = password_hash($passwordWithSalt, PASSWORD_DEFAULT);

        $this->usersDataProvider->setUserPassword($passwordDto);
    }

    private function autoLogInUser(MineParkPlayer $player)
    {
        $player->getStatesMap()->authorized = true; 
        $player->getStatesMap()->bar = null;

        $this->setMovement($player, true);

        $timeoutTicks = TimeConstants::ONE_SECOND_TICKS * TimeConstants::WELCOME_MESSAGE_TIMEOUT;
        Tasks::registerDelayedAction($timeoutTicks, [$this, "sendWelcomeText"], [$player]);
    }
}