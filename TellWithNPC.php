<?php
/**
 * @name TellWithNPC
 * @author alvin0319
 * @main alvin0319\TellWithNPC
 * @version 1.0.0
 * @api 4.0.0
 */
namespace alvin0319;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Human;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\nbt\tag\{
    CompoundTag, ListTag, DoubleTag, StringTag, FloatTag
};
use pocketmine\command\PluginCommand;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
//한글깨짐방지

class TellWithNPC extends PluginBase implements Listener{
	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->npcDB = new Config($this->getDataFolder() . "Config.yml", Config::YAML);
		$this->db = $this->npcDB->getAll();
		Entity::registerEntity(ANPC::class, true);
		$cmd = new PluginCommand("엔피시", $this);
		$cmd->setDescription("엔피시 관련 명령어");
		$this->getServer()->getCommandMap()->register("엔피시", $cmd);
	}
	public function MainData() {
		$encode = [
		"type" => "custom_form",
		"title" => "NPC",
		"content" => [
		[
		"type" => "input",
		"text" => "엔피시의 이름",
		],
		[
		"type" => "input",
		"text" => "엔피시의 고유 번호",
		],
		[
		"type" => "input",
		"text" => "엔피시가 띄울 UI 의 말",
		]
		]
		];
		return json_encode($encode);
	}
	public function NpcData($info) {
		$encode = [
		"type" => "form",
		"title" => "NPC",
		"content" => "{$info}",
		"buttons" => [
		[
		"text" => "확인",
		]
		]
		];
		return json_encode($encode);
	}
	public function onDataPacket(DataPacketReceiveEvent $event) {
		$player = $event->getPlayer();
		$packet = $event->getPacket();
		if ($packet instanceof ModalFormResponsePacket) {
			$id = $packet->formId;
			$data = json_decode($packet->formData, true);
			if ($id === 109) {
				if (! is_numeric($data[1]) or ! isset($data[0]) or ! isset($data[1]) or ! isset($data[2])) {
					$player->sendMessage("고유 번호는 숫자여야 하며, 모든 칸을 다 입력해주세요");
					return;
				}
				if (isset($this->db[$data[0]])) {
					$player->sendMessage("해당 엔피시는 이미 있습니다");
					return;
				}
				$this->addNpc($data[0], $player, $data[2], $data[1]);
			} else if ($id === 120) {
				if (! isset($data[0])) {
					$player->sendMessage("칸이 비어 있습니다");
					return;
				}
				if (! isset($this->db[$data[0]])) {
					$player->sendMessage("그런 엔피시는 없습니다");
					return;
				}
				unset($this->db[$data[0]]);
				$this->save();
				$player->sendMessage("제거되었습니다.\n엔피시를 죽여주세요");
			}
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent) {
			$player = $event->getDamager();
			$npc = $event->getEntity();
			if ($npc instanceof ANPC) {
				$Nname = $npc->getNameTag();
				if (isset($this->db[$Nname])) {
					$event->setCancelled(true);
					$a = explode(":", $this->db[$Nname]);
					$info = str_replace(["(줄바꿈)"], ["\n"], $a[1]);
					$this->sendUI($player, 108, $this->NpcData($info));
				} else {
					if ($event->isCancelled()) {
						$event->setCancelled(false);
					} else {
						$event->setCancelled(false);
					}
				}
			}
		}
	}
	public function RemoveData() {
		$encode = [
		"type" => "custom_form",
		"title" => "NPC",
		"content" => [
		[
		"type" => "input",
		"text" => "제거할 엔피시의 이름을 넣어주세요",
		]
		]
		];
		return json_encode($encode);
	}
	public function sendUI(Player $player, $code, $data) {
		$pk = new ModalFormRequestPacket();
		$pk->formId = $code;
		$pk->formData = $data;
		$player->dataPacket($pk);
	}
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if ($command->getName() === "엔피시") {
			if (! $sender->isOp()) {
				return true;
			}
			if (! isset($args[0])) {
				$this->sendUI($sender, 109, $this->MainData());
			} else {
				if ($args[0] === "제거") {
					$this->sendUI($sender, 120, $this->RemoveData());
				}
			}
		}
		return true;
	}
	public function save() {
		$this->npcDB->setAll($this->db);
		$this->npcDB->save();
	}
	public function addNpc($name, $player, $msg, $num) {
		$inv = $player->getInventory();
        $arinv = $player->getArmorInventory();
        $nbt = new CompoundTag("", [
            new ListTag("Pos", [
                new DoubleTag("", $player->x),
                new DoubleTag("", $player->y),
                new DoubleTag("", $player->z) ]),
            new ListTag("Motion", [
                new DoubleTag("", 0),
                new DoubleTag("", 0),
                new DoubleTag("", 0) ]),
            new ListTag("Rotation",[
                new FloatTag(0, $player->getYaw()),
                new FloatTag(0, $player->getPitch())]),
            new CompoundTag("Skin", [
                "Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
                "Name" => new StringTag("Name", $player->getSkin()->getSkinId()),
            ]),
        ]);
        $entity = Entity::createEntity("ANPC", $player->getLevel(), $nbt);
        $entity->setNameTag($name);
        $entity->setMaxHealth(100);
        $entity->setHealth(100);
        $einv = $entity->getInventory();
        $earinv = $entity->getArmorInventory();
        $einv->setItemInHand($inv->getItemInHand());
        $earinv->setHelmet($arinv->getHelmet());
        $earinv->setChestplate($arinv->getChestplate());
        $earinv->setLeggings($arinv->getLeggings());
        $earinv->setBoots($arinv->getBoots());
        $entity->setNameTagVisible(true);
        $entity->setNameTagAlwaysVisible(true);
        $entity->spawnToAll();
        $this->db[$name] = $num . ":" . $msg;
        $player->sendMessage("생성되었습니다");
        $this->save();
    }
}
class ANPC extends Human{
}