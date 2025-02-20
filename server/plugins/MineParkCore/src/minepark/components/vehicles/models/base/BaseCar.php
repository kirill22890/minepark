<?php
namespace minepark\components\vehicles\models\base;

use minepark\Providers;
use pocketmine\math\Vector3;
use pocketmine\entity\Location;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\block\BlockFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\block\BlockLegacyIds;
use pocketmine\entity\EntitySizeInfo;
use minepark\defaults\VehicleConstants;
use minepark\common\player\MineParkPlayer;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\world\World;

abstract class BaseCar extends BaseVehicle
{
    public $gravity = 1.0;

    public $width = 1.0;

    public $height = 1.0;

    public $baseOffset = 0.0;

    public $stepHeight = 1;

    protected ?MineParkPlayer $driver;
    protected ?MineParkPlayer $passenger;
    protected ?MineParkPlayer $rentedBy;

    private float $speed;

    public function __construct(Location $location, CompoundTag $nbt = null)
    {
        parent::__construct($location, $nbt);

        $this->driver = null;
        $this->passenger = null;
        $this->rentedBy = null;
        $this->speed = 0.0;

        if ($this->getVehicleNameTag() !== null) {
            $this->setNameTag($this->getVehicleNameTag());
            $this->setNameTagAlwaysVisible(true);
        }

        $this->setCanSaveWithChunk(true);
        $this->saveNBT();

        $this->setSilent();
    }

    protected function getInitialSizeInfo() : EntitySizeInfo
    {
        return new EntitySizeInfo($this->height, $this->width);
    }

    public function getName() : string
    {
        return "Car";
    }

    abstract public function getLeftSpeed() : float;

    abstract public function getRightSpeed() : float;

    abstract public function getForwardAcceleration() : float;

    abstract public function getBackwardAcceleration() : float;

    abstract public function getMaxSpeed() : float;

    abstract public function getReduceMaxSpeed() : float;

    abstract public function getBrakeSpeed() : float;

    abstract public function getVehicleNameTag() : ?string;

    abstract public function getDriverSeatPosition() : Vector3;

    abstract public function getPassengerSeatPosition() : Vector3;

    abstract public function getCost() : float;

    public function getDriver() : ?MineParkPlayer
    {
        return $this->driver;
    }

    public function getPassenger() : ?MineParkPlayer
    {
        return $this->passenger;
    }

    public function kill() : void
    {
        if (isset($this->driver)) {
            $this->driver->getStatesMap()->ridingVehicle = null;
        }

        if (isset($this->rentedBy)) {
            $this->rentedBy->getStatesMap()->rentedVehicle = null;
        }

        if (isset($this->passenger)) {
            $this->passenger->getStatesMap()->ridingVehicle = null;
        }

        $this->setHealth(0);

        $this->scheduleUpdate();
    }

    public function performAction(MineParkPlayer $player, ?int $data = null)
    {
        if (is_null($data)) {
            return;
        }

        $choice = $data + 1;

        if ($choice === VehicleConstants::ACTION_BE_DRIVER) {
            $this->trySetPlayerDriver($player);
        } else if ($choice === VehicleConstants::ACTION_BE_PASSENGER) {
            $this->trySetPlayerPassenger($player);
        }
    }

    public function buyVehicle(MineParkPlayer $player, ?bool $choice = null)
    {
        if (!isset($choice)) {
            return;
        }

        if (!$choice) {
            return;
        }

        if (isset($this->rentedBy)) {
            return $player->sendMessage("VehicleWasRented1");
        }

        if (Providers::getBankingProvider()->takePlayerMoney($player, $this->getCost())) {
            $this->setRented($player);

            $this->trySetPlayerDriver($player);
        } else {
            $player->sendMessage("VehicleNoMoney");
        }
    }

    public function attack(EntityDamageEvent $event) : void
    {
        // check if this entity is being attacked by player
        if ($event instanceof EntityDamageByEntityEvent) {
            if ($event->getDamager() instanceof MineParkPlayer) {
                $this->handleInteract($event->getDamager());
                return;
            }
        }

        parent::attack($event);
    }

    public function setDriver(MineParkPlayer $player, bool $force = false) : bool
    {
        // $force means if we have to remove current driver
        if ($force) {
            $this->removeDriver();
        }

        if (!is_null($this->getDriver())) {
            return false;
        }

        $this->updateUserFlags($player, true);

        $this->driver = $player;
        $this->getDriver()->getStatesMap()->ridingVehicle = $this;

        $this->broadcastLink($this->getDriver(), EntityLink::TYPE_RIDER);
        return true;
    }

    public function setPassenger(MineParkPlayer $player, bool $force = false) : bool
    {
        if ($force) {
            $this->removePassenger();
        }

        if (!is_null($this->getPassenger())) {
            return false;
        }

        $this->updateUserFlags($player, true, false);

        $this->passenger = $player;
        $this->getPassenger()->getStatesMap()->ridingVehicle = $this;

        $this->broadcastLink($this->getPassenger(), EntityLink::TYPE_PASSENGER);
        return true;
    }

    public function setVillagerProfession(int $profession)
    {
        
    }

    public function removeRentedStatus()
    {
        $this->rentedBy = null;
    }

    public function tryToRemovePlayer(MineParkPlayer $player) : bool
    {
        if ($this->passenger?->getName() === $player->getName()) {
            return $this->removePassenger();
        } else if ($this->driver?->getName() === $player->getName()) {
            return $this->removeDriver();
        }

        return false;
    }

    public function removeDriver() : bool
    {
        if (is_null($this->driver)) {
            return false;
        }

        if ($this->driver?->isOnline()) {
            $this->updateUserFlags($this->driver, false);

            $this->broadcastLink($this->driver, EntityLink::TYPE_REMOVE);

            $this->driver->getStatesMap()->ridingVehicle = null;

            $this->driver->getStatesMap()->bar = null;
        }

        $this->driver = null;

        return true;
    }

    public function removePassenger() : bool
    {
        if (is_null($this->passenger)) {
            return false;
        }

        if ($this->passenger?->isOnline()) {
            $this->updateUserFlags($this->passenger, false, false);

            $this->broadcastLink($this->passenger, EntityLink::TYPE_REMOVE);

            $this->passenger->getStatesMap()->ridingVehicle = null;
        }

        $this->passenger = null;

        return true;
    }

    public function updateSpeed(float $x, float $y)
    {
        if ($x > 0.0) {
            $this->location->yaw -= $x * $this->getLeftSpeed();
        } else {
            $this->location->yaw -= $x * $this->getRightSpeed();
        }

        if ($y === 0.0) {
            return;
        }

        if ($y > 0 and $this->speed <= $this->getMaxSpeed()) {
            $this->addSpeed($this->getForwardAcceleration());
        } else if ($this->speed > $this->getReduceMaxSpeed()) {
            if ($this->speed > 0) {
                $this->reduceSpeed($this->getBrakeSpeed());
            } else {
                $this->reduceSpeed($this->getBackwardAcceleration());
            }
        }
    }

    public function addSpeed(float $speed)
    {
        $calculatedSpeed = $this->speed + $speed;

        if ($calculatedSpeed <= $this->getMaxSpeed()) {
            $this->speed = $calculatedSpeed;
        } else {
            $this->speed = $this->getMaxSpeed();
        }
    }

    public function reduceSpeed(float $speed)
    {
        $calculatedSpeed = $this->speed - $speed;

        if ($calculatedSpeed >= $this->getReduceMaxSpeed()) {
            $this->speed = $calculatedSpeed;
        } else {
            $this->speed = $this->getReduceMaxSpeed();
        }
    }
    
    // special for kirill: its being called every tick
    public function onUpdate(int $currentTick) : bool
    {
        if ($this->speed === 0) {
            return parent::onUpdate($currentTick);
        }

        if ($this->speed <= 0.003 and $this->speed >= -0.003) {
            $this->speed = 0;
            return parent::onUpdate($currentTick);
        }
        
        if ($this->speed > 0) {
            $this->reduceSpeed(0.002);
            $this->performForwardAcceleration();
        } else {
            $this->addSpeed(0.002);
            $this->performBackwardAcceleration();
        }

        if (isset($this->driver)) {
            $this->updateSpeedMeter();
        }

        parent::onUpdate($currentTick);

        return true;
    }

    public function setRentedBy(MineParkPlayer $player)
    {
        $this->rentedBy = $player;

        if (isset($player->getStatesMap()->rentedVehicle)) {
            $player->getStatesMap()->rentedVehicle->removeRentedStatus();
        }

        $player->getStatesMap()->rentedVehicle = $this;
    }

    protected function trySetPlayerDriver(MineParkPlayer $player)
    {
        if (!$this->performRentCheck($player)) {
            return;
        }

        if ($this->setDriver($player)) {
            $player->sendTip("Вы успешно сели за руль!");
        } else {
            $player->sendMessage("VehicleWheelOccupied");
        }
    }

    protected function trySetPlayerPassenger(MineParkPlayer $player)
    {
        if ($this->setPassenger($player)) {
            return $player->sendTip("Вы успешно сели в машину!");
        } else {
            return $player->sendMessage("VehiclePassengerOccupied");
        }
    }

    protected function broadcastLink(MineParkPlayer $player, int $type = EntityLink::TYPE_RIDER)
    {
        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->getId(), $player->getId(), $type, true, true);

        foreach($this->getViewers() as $viewer) {
            $viewer->getNetworkSession()->sendDataPacket($pk);
        }
    }

    private function performRentCheck(MineParkPlayer $player) : bool
    {
        if ($player->isAdministrator()) {
            return true;
        }

        if (isset($this->rentedBy)) {
            if ($this->rentedBy->getName() !== $player->getName()) {
                $player->sendMessage("VehicleWasRented2");
                return false;
            }

            return true;
        }

        if ($this->getCost() === 0.0) {
            $this->setRentedBy($player);

            return true;
        }

        $this->showRentForm($player);

        return false;
    }

    private function setRented(MineParkPlayer $player)
    {
        $player->sendMessage("VehicleRentedSucces");

        $this->setRentedBy($player);
    }

    private function performForwardAcceleration()
    {
        $motion = $this->getDirectionVector();

        $motionX = $motion->getX() * $this->speed;
        $motionY = $motion->getY();
        $motionZ = $motion->getZ() * $this->speed;

        $this->motion = new Vector3($motionX, $motionY, $motionZ);
    }

    private function updateSpeedMeter()
    {
        $speed = $this->speed;

        if ($speed > 0.02) {
            if ($speed + 0.004 > $this->getMaxSpeed()) {
                $speed = $this->getMaxSpeed();
            }

            $repeats = $speed / ($this->getMaxSpeed() / 10);
        } else if ($speed < -0.02) {
            if ($speed - 0.004 < $this->getReduceMaxSpeed()) {
                $speed = $this->getReduceMaxSpeed();
            }

            $repeats = $speed / ($this->getReduceMaxSpeed() / 10);

            $speed = abs($speed);
        } else {
            $speed = 0;
            $repeats = 0;
        }

        $speed /= 2;

        $speedKmh = $speed * 100;

        $bar = round($speedKmh) . "km/h\n";
        $bar .= $this->generateProgressBar($repeats);

        $this->driver->getStatesMap()->bar = $bar;
    }

    private function generateProgressBar(int $repeats) : string
    {
        $generated = "§a";

        for ($i = 0; $i < 10; $i++) {
            if ($i === $repeats) {
                $generated .= "§f";
            }

            $generated .= "▎";
        }

        return $generated;
    }

    private function performBackwardAcceleration()
    {
        $motion = $this->getDirectionVector();

        $motionX = $motion->getX() * $this->speed;
        $motionY = $motion->getY();
        $motionZ = $motion->getZ() * $this->speed;

        $this->motion = new Vector3($motionX, $motionY, $motionZ);
    }

    private function updateUserFlags(MineParkPlayer $player, bool $status, bool $driver = true)
    {
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, $status);
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, $status);

        if($driver) {
            $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, $status);

            if($status) {
                $player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $this->getDriverSeatPosition());
            }
        } elseif($status) {
            $player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $this->getPassengerSeatPosition());
        }
    
        $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, $status);
    }

    private function handleInteract(MineParkPlayer $player)
    {
        if($player->getStatesMap()->ridingVehicle) {
            return $player->sendMessage("VehicleNoMoveAnotherCar");
        }

        if(isset($this->driver) and isset($this->passenger)) {
            return $player->sendMessage("VehisleOccupied");
        }

        $form = new SimpleForm([$this, "performAction"]);
        $form->setTitle("Машина");

        $form->setContent("Выберите, что вы хотите сделать с машиной!");
        $form->addButton("Водить машиной");
        $form->addButton("Стать пассажиром");

        $player->sendForm($form);
    }

    private function showRentForm(MineParkPlayer $player)
    {
        $form = new ModalForm([$this, "buyVehicle"]);

        $form->setContent("Данную машину нужно арендовать за " . $this->getCost() . " рублей! Желаете ли Вы ее арендовать?");
        $form->setButton1("Да");
        $form->setButton2("Нет");

        $player->sendForm($form);
    }
}