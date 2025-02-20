<?php
namespace minepark\components\settings;

use minepark\components\administrative\BanSystem;
use minepark\defaults\StringConstants;
use minepark\Events;
use minepark\Providers;
use minepark\Components;
use minepark\components\BossBar;
use minepark\defaults\EventList;
use pocketmine\item\ItemFactory;
use minepark\defaults\Permissions;
use minepark\defaults\ItemConstants;
use minepark\defaults\PaymentMethods;
use minepark\models\player\StatesMap;
use minepark\defaults\PlayerConstants;
use minepark\components\base\Component;
use minepark\providers\ProfileProvider;
use minepark\common\player\MineParkPlayer;
use minepark\defaults\OrganisationConstants;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerLoginEvent;
use minepark\components\administrative\Tracking;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerInteractEvent;
use minepark\components\organisations\Organisations;

class PlayerSettings extends Component
{
    private Tracking $tracking;

    public function initialize()
    {
        Events::registerEvent(EventList::PLAYER_CREATION_EVENT, [$this, "setDefaultPlayerClass"]);
        Events::registerEvent(EventList::PLAYER_LOGIN_EVENT, [$this, "initializePlayer"]);
        Events::registerEvent(EventList::PLAYER_JOIN_EVENT, [$this, "applyJoinSettings"]);
        Events::registerEvent(EventList::PLAYER_QUIT_EVENT, [$this, "applyQuitSettings"]);
        Events::registerEvent(EventList::PLAYER_INTERACT_EVENT, [$this, "applyInteractSettings"]);

        $this->tracking = Components::getComponent(Tracking::class);
    }

    public function getAttributes() : array
    {
        return [
        ];
    }

    public function getProfileProvider() : ProfileProvider
    {
        return Providers::getProfileProvider();
    }

    public function setDefaultPlayerClass(PlayerCreationEvent $event)
    {
        $event->setPlayerClass(MineParkPlayer::class);
    }
    
    public function initializePlayer(PlayerLoginEvent $event)
    {
        $player = MineParkPlayer::cast($event->getPlayer());

        $this->setupDefaults($player);

        $this->updateNewPlayerStatus($player);

        $this->getProfileProvider()->initializeProfile($player);

        if($this->checkPlayerForBan($player)) {
            return;
        }

        $this->updateBegginerStatus($player);

        $this->setPermissions($player);
        
        $this->showLang($player);
    }

    public function applyJoinSettings(PlayerJoinEvent $event)
    {
        $player = MineParkPlayer::cast($event->getPlayer());

        $event->setJoinMessage(StringConstants::EMPTY_STRING);

        $player->getEffects()->clear();
        $player->setNameTag(StringConstants::EMPTY_STRING);

        if($player->getStatesMap()->isNew) {
            $this->handleNewPlayer($player);
        }

        Providers::getBankingProvider()->initializePlayerPaymentMethod($player);

        $this->showDonaterStatus($player);
        
        $this->addInventoryItems($player);

        Providers::getUsersDataProvider()->updateUserJoinStatus($player->getName());
    }

    public function applyQuitSettings(PlayerQuitEvent $event)
    {
        $player = MineParkPlayer::cast($event->getPlayer());

        $event->setQuitMessage(StringConstants::EMPTY_STRING);

        Providers::getUsersDataProvider()->updateUserQuitStatus($player->getName());
    }

    public function applyInteractSettings(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();

        if($this->filterItemsAndBlocks($player)) {
            $event->cancel();
        }

        if (!$this->isCanActivate($player)) {
            return;
        }

        if (!$event->isCancelled()) {
            $this->checkInventoryItems($player);
        }
    }

    public function checkInventoryItems(MineParkPlayer $player)
    {
        $itemId = $player->getInventory()->getItemInHand()->getId();

        //CHECK ITEMS > DEFAULT KIT
        if($itemId === 336) { //336 - phone
            $player->sendCommand("/c");
        } else if($itemId === 340) { //340 - passport
            $player->sendCommand("/doc");
        } else if($itemId === 405) { //405 - gps
            $player->sendCommand("/gps");
        }
    }

    private function checkPlayerForBan(MineParkPlayer $player) : bool
    {
        $banInfo = $player->getProfile()->banRecord;

        if($banInfo === null) {
            return false;
        }

        $releaseDate = $banInfo->releaseDate;
        $issuerName = $banInfo->issuerName;
        $reason = $banInfo->reason;

        $kickMessage = "§eВы заблокированы на сервере до§b $releaseDate. §eВас заблокировал игрок§b $issuerName §eпо причине§b $reason";

        $player->kick($kickMessage);

        return true;
    }

    private function addInventoryItems(MineParkPlayer $player)
    {
        //GIVING ITEMS > DEFAULT KIT
        $phone = ItemFactory::getInstance()->get(336);
        $phone->setCustomName("Телефон");
        
        $passport = ItemFactory::getInstance()->get(340);
        $passport->setCustomName("Паспорт");
        
        $gps = ItemFactory::getInstance()->get(405);
        $gps->setCustomName("Навигатор");
        
        if(!$player->getInventory()->contains($phone)) {
            $player->getInventory()->setItem(2, $phone);
        }

        $player->getInventory()->setHeldItemIndex(3);
        
        if(!$player->getInventory()->contains($passport)) {
            $player->getInventory()->setItem(3, $passport);
        }
        
        if(!$player->getInventory()->contains($gps)) {
            $player->getInventory()->setItem(4, $gps);
        }

        if($player->getSettings()->organisation == OrganisationConstants::SECURITY_WORK) {
            $item = ItemFactory::getInstance()->get(280);
            $player->getInventory()->addItem($item);
        }
    }

    private function setupDefaults(MineParkPlayer $player)
    {
        $statesMap = new StatesMap();

        $statesMap->authorized = false;

        $statesMap->isNew = false;
        $statesMap->isBeginner = false;

        $statesMap->gpsLightsVisible = false;
        
        $statesMap->gps = null;
        $statesMap->bar = null;

        $statesMap->phoneCompanion = null;
        $statesMap->phoneIncomingCall = null;
        $statesMap->phoneOutcomingCall = null;

        $statesMap->goods = array();

        $statesMap->loadWeight = null;

        $statesMap->damageDisabled = false;

        $statesMap->lastTap = time();

        $statesMap->paymentMethod = PaymentMethods::CASH;

        $statesMap->ridingVehicle = null;
        $statesMap->rentedVehicle = null;

        $statesMap->bossBarSession = null;

        $player->setStatesMap($statesMap);
    }

    private function updateNewPlayerStatus(MineParkPlayer $player) 
    {
        $status = $this->getProfileProvider()->isNewPlayer($player);
        $player->getStatesMap()->isNew = $status;
    }

    private function updateBegginerStatus(MineParkPlayer $player) 
    {
        $status = $player->getStatesMap()->isNew or 
            $player->getProfile()->minutesPlayed < PlayerConstants::MINIMAL_SKILL_MINUTES_PLAYED;
        $player->getStatesMap()->isBeginner = $status;
    }
    
    private function showLang(MineParkPlayer $player)
    {
        $message = "Selected locale: " . $player->getLocale();
        $this->getServer()->getLogger()->info($message);
    }

    private function handleNewPlayer(MineParkPlayer $player)
    {
        Providers::getBankingProvider()->givePlayerMoney($player, PlayerConstants::DEFAULT_MONEY_PRESENT);
        $this->tracking->enableTrack($player);
        $this->presentNewPlayer($player);
    }

    private function presentNewPlayer(MineParkPlayer $newPlayer)
    {
        foreach($this->getServer()->getOnlinePlayers() as $player) {
            $player->sendTitle("§6" . $newPlayer->getName(), "§aВ парке новый посетитель!", 5);
        }
    }

    private function isCanActivate(MineParkPlayer $player) : bool
    {
        $currentTime = time();

        if ($currentTime - $player->getStatesMap()->lastTap > 2) {
            $player->getStatesMap()->lastTap = $currentTime;

            return true;
        }
        
        return false;
    }

    private function filterItemsAndBlocks(MineParkPlayer $player) : bool
    {
        $itemId = $player->getInventory()->getItemInHand()->getId();

        if(!$player->canBuild() and in_array($itemId, ItemConstants::getRestrictedItemsNonBuilder())) {
            return true;
        }

        if(in_array($itemId, ItemConstants::getGunItemIds())) {
            return true;
        }

        return false;
    }
    
    private function setPermissions(MineParkPlayer $player)
    {
        $player->addAttachment($this->getCore(), Permissions::ANYBODY, true);
        $this->addCustomPermissions($player);
    }
    
    private function showDonaterStatus(MineParkPlayer $donater)
    {
        if(!$donater->hasPermission("group.custom")){
            return;
        }
        
        $label = $this->getDonaterLabel($donater);

        foreach($this->getServer()->getOnlinePlayers() as $player) {
            $player = MineParkPlayer::cast($player);
            $player->addLocalizedTitle("§e" . $donater->getName(), $label . " " . $donater->getName() . " {UserOnline}", 5);
        }
    }
    
    private function getDonaterLabel(MineParkPlayer $donater)
    {
        if($donater->isOperator()) {
            return "§7⚑РУКОВОДСТВО ПАРКА";
        }

        $profile = $donater->getProfile();

        if($profile->administrator) {
            if($profile->builder) {
                return "§bСтроитель парка";
            } elseif($profile->realtor) {
                return "§cРиэлтор недвижимости";
            } else {
                return "§aАдминистратор";
            }
        }

        if($profile->vip) {
            return "§e§0-=§9V.I.P§0=-§e";
        }

        if($profile->bonus > PlayerConstants::DONATER_STATUS_BONUS_COUNT) {
            return "§7~§6§e-=ДоНаТеР=-§6§7~";
        }
    }

    private function addCustomPermissions(MineParkPlayer $player)
    {
        $profile = $player->getProfile();

        $hasCustomPermissions = false;

        if(!is_null($profile->group)) {
            $permission = "group." . strtolower($profile->group);
            $player->addAttachment($this->getCore(), $permission, true);
            $hasCustomPermissions = true;
        }

        if($profile->vip) {
            $player->addAttachment($this->getCore(), Permissions::VIP, true);
            $this->addVipPermissions($player);
            $hasCustomPermissions = true;
        }

        if($profile->administrator) {
            $player->addAttachment($this->getCore(), Permissions::ADMINISTRATOR, true);
            $this->addAdministratorPermissions($player);
            $hasCustomPermissions = true;
        }

        if($profile->builder) {
            $player->addAttachment($this->getCore(), Permissions::BUILDER, true);
            $this->addBuilderPermissions($player);
            $hasCustomPermissions = true;
        }

        if($profile->realtor) {
            $player->addAttachment($this->getCore(), Permissions::REALTOR, true);
            $this->addRealtorPermissions($player);
            $hasCustomPermissions = true;
        }

        if($player->isOperator()) {
            $player->addAttachment($this->getCore(), Permissions::OPERATOR, true);
            $hasCustomPermissions = true;
        }

        if($hasCustomPermissions) {
            $player->addAttachment($this->getCore(), Permissions::CUSTOM, true);
        }
    }
    
    private function addAdministratorPermissions(MineParkPlayer $player)
    {
        $permissions = Permissions::getCustomAdministratorPermissions();
        $this->applyPermissions($player, $permissions);
    }

    private function addBuilderPermissions(MineParkPlayer $player)
    {
        $permissions = Permissions::getCustomBuilderPermissions();
        $this->applyPermissions($player, $permissions);
    }

    private function addRealtorPermissions(MineParkPlayer $player)
    {
        $permissions = Permissions::getCustomRealtorPermissions();
        $this->applyPermissions($player, $permissions);
    }

    private function addVipPermissions(MineParkPlayer $player)
    {
        $permissions = Permissions::getCustomVipPermissions();
        $this->applyPermissions($player, $permissions);
    }

    private function applyPermissions(MineParkPlayer $player, array $permissions)
    {
        foreach($permissions as $permission) {
            $player->addAttachment($this->getCore(), $permission, true);
        }
    }
}
