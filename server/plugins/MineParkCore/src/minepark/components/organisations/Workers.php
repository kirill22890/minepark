<?php
namespace minepark\components\organisations;

use minepark\defaults\TimeConstants;
use minepark\Providers;

use pocketmine\data\bedrock\EffectIds;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\world\Position;
use pocketmine\entity\effect\EffectInstance;
use minepark\common\player\MineParkPlayer;
use minepark\Components;
use minepark\components\base\Component;
use minepark\components\chat\Chat;
use minepark\defaults\ComponentAttributes;
use minepark\defaults\EventList;
use minepark\defaults\MapConstants;
use minepark\Events;
use minepark\providers\BankingProvider;
use minepark\providers\MapProvider;
use pocketmine\block\utils\SignText;
use pocketmine\event\block\SignChangeEvent;

class Workers extends Component
{
    public array $words;

    private MapProvider $mapProvider;

    private BankingProvider $bankingProvider;

    private Chat $chat;
    
    public function initialize()
    {
        Events::registerEvent(EventList::SIGN_CHANGE_EVENT, [$this, "onSignChange"]);

        $this->words = [
            "Сельдь *Московская*","Картофель *Беларус*","Боярышник","*Contex Classic*",
            "*Contex с пупырышками*","Шоколад *Алёнка*","Трубы водопроводные","Пойманные морские обитатели",
            "Сало","Коньяк SHABO","Кактус декоративный","Стекло","Гельдуш *Аноним*","Падаль","Очищенная питьевая вода",
            "Морковь","Вода Бонаква","Сигареты *Prima*","Диски *WannaCry*","Спец. корм для Летающих особей",
            "Складной велосипед","Плюшевые мишки","Куклы *БАРБИ*","Земляника","Колбаса","Обои","Сыр","Пиво",
            "Шлакоблоки молотые","Ракушки","Тушки лосося","Известь","Стеклопакет","Морские водоросли","Семечки *Гоп*",
            "Хлеб *Ладушки*","Булочки *Повариха*","Морские свинки","Одежда", "Патифоны","Матрас *Моряк*","Спиннеры",
            "Питьевая вода","Порох","Кирпич","Пластик","Песок","Сельдерей","Творог *Коровка*","Йогурты *Данон*", 
            "Синтезатор *Casio*","Скрипка *Страдивари*","Лопаты", "Железная руда","Пищевая соя","Творог *Ростишка*",
            "Гречка *Мир*","Хотдоги","Масло","Туалетная бумага *Нежность*","Игрушечное яйцо динозавра","Рис","Перец чили",
            "Макароны","Торт *Наполеон*","Яблоки","Респераторы","Бумага","Школьный мел","Сок из оливок","Лимонный сок",
            "Сок из кактусов","Ликер","Коньяк","Специи","Журналы *Черепашки Ниньдзя*","Глина","Учебник по алгебре",
            "Вазоны","Шаверма","Журнал *Мирный Мир*","Маршрутизаторы","Флешкарты","Спец. корм для Травоядных",
            "Комплектующие для ноутбука","*Play Station 5*","Топовый ПК","Микроскоп","Книжная полка","Рачки"
        ];//90

        $this->mapProvider = Providers::getMapProvider();

        $this->bankingProvider = Providers::getBankingProvider();

        $this->chat = Components::getComponent(Chat::class);
    }

    public function getAttributes() : array
    {
        return [
            ComponentAttributes::STANDALONE
        ];
    }

    public function onSignChange(SignChangeEvent $event)
    {
        $player = MineParkPlayer::cast($event->getPlayer());
        $lines = $event->getNewText()->getLines();

        if ($lines[0] === "[workers1]" and $player->isOperator()) {
            $this->handleTakeBoxSign($event);
        } elseif ($lines[0] === "[workers2]" and $player->isOperator()) {
            $this->handlePutBoxSign($event);
        }
    }
    
    private function handleTakeBoxSign(SignChangeEvent $event)
    {
        $text = new SignText([
            "§eЗдесь можно",
            "§eподзаработать",
            "§f(грузчики)",
            "§b/takebox"
        ]);

        $event->setNewText($text);
    }
    
    private function handlePutBoxSign(SignChangeEvent $event)
    {
        $text = new SignText([
            "§aЗдесь находится",
            "§aточка разгрузки",
            "§f(грузчики)",
            "§6Разгрузиться: §b/putbox"
        ]);
        
        $event->setNewText($text);
    }

    public function takeBox(MineParkPlayer $player)
    {
        if(!$this->isNearAreaWithBoxes($player)) {
            $player->sendMessage("WorkersNoNear1");
            return;
        }

        if(!is_null($player->getStatesMap()->loadWeight)) {
            $player->sendMessage("WorkersBoxNoPut");
            return;
        }

        $this->handleBoxTake($player);
    }
    
    private function handleBoxTake(MineParkPlayer $player)
    {
        $this->giveSlownessEffect($player);

        $box = $this->words[mt_rand(0, count($this->words))]; 
        $player->getStatesMap()->loadWeight = mt_rand(1, 12); 
        
        $player->sendMessage("WorkersSearchPalace");
        
        $this->chat->sendLocalMessage($player, "{WorkersBoxInArm}", "§d : ", 12);
    
        $player->getStatesMap()->bar = "§aВ руках ящик около " . $player->getStatesMap()->loadWeight . " кг";
    }

    public function putBox(MineParkPlayer $player)
    {
        if(!$this->isNearUnloadingPoint($player)) {
            $player->sendMessage("WorkersNoNear2");
            return;
        }

        if(is_null($player->getStatesMap()->loadWeight)) {
            $player->sendMessage("WorkersNoBoxInArm");
            return;
        }

        $this->handlePutBox($player);
    }
    
    private function handlePutBox(MineParkPlayer $player)
    {
        $player->getEffects()->clear();

        $this->chat->sendLocalMessage($player, "WorkersBoxPut", "§d : ", 12);
        $this->bankingProvider->givePlayerMoney($player, 20 * $player->getStatesMap()->loadWeight);

        $player->getStatesMap()->loadWeight = null; 
        $player->getStatesMap()->bar = null;
    }

    private function giveSlownessEffect(MineParkPlayer $player)
    {
        $effect = VanillaEffects::fromString("slowness");
        $instance = new EffectInstance($effect, TimeConstants::ONE_SECOND_TICKS * 9999, 3, true);
        $player->getEffects()->add($instance);
    }

    private function isPlayerNearPoint(Position $position, int $group) : bool
    {
        $points = $this->mapProvider->getNearPoints($position, 6);

        foreach($points as $point) {
            if($this->mapProvider->getPointGroup($point) === $group) {
                return true;
            }
        }

        return false;
    }

    private function isNearAreaWithBoxes(MineParkPlayer $player) : bool
    {
        return $this->isPlayerNearPoint($player->getPosition(), MapConstants::POINT_GROUP_WORK1);
    }

    private function isNearUnloadingPoint(MineParkPlayer $player) : bool
    {
        return $this->isPlayerNearPoint($player->getPosition(), MapConstants::POINT_GROUP_WORK2);
    }
}