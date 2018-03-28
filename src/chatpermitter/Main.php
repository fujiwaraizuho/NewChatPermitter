<?php

namespace chatpermitter;

/* base */
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\Server;

/* event */
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\server\DataPacketReceiveEvent;

/* scheduler */
use pocketmine\scheduler\AsyncTask;

/* utils */
use pocketmine\utils\Config;
use pocketmine\utils\MainLogger;

/* packet */
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class Main extends PluginBase implements Listener
{
	const PLUGIN_NAME = "ChatPermitter";
	const SELECT_URL = "https://mirm.info/viewkey.php";
	const DELETE_URL = "https://mirm.info/chat/removekey.php";
	const DELETE_DAY = 3;
	const FORM_KEY = 0;

	public $users;
	public $config;
	public $logger;
	public $chatUrl;
	public $deleteUrl;
	public $deleteDay;
	public $chatPlayers = [];
	public $configEnable = false;

	protected static $instance = null;


	public static function getInstance()
	{
		return self::$instance;
	}


	public function onEnable()
	{
		Server::getInstance()->getPluginManager()->registerEvents($this, $this);

		if (!file_exists($this->getDataFolder())) {
			mkdir($this->getDataFolder(), 0744, true);
		}

		$this->users = new DB($this->getDataFolder());

		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML,
			[
				"config_enable" => false,
				"select_url" => self::SELECT_URL,
				"delete_url" => self::DELETE_URL,
				"delete_day" => self::DELETE_DAY
			]);

		if ($this->config->exists("config_enable")) {
			if (!$this->config->get("config_enable")) {
				$this->configEnable = false;
			} else {
				$this->configEnable = true;
			}
		} else {
			$this->configEnable = false;
		}

		$this->chatUrl   = $this->configEnable ? $this->config->get("select_url")   : self::SELECT_URL;
		$this->deleteUrl = $this->configEnable ? $this->config->get("delete_url") : self::DELETE_URL;
		$this->deleteDay = $this->configEnable ? $this->config->get("delete_day") : self::DELETE_DAY;

		self::$instance = $this;

		$this->logger = $this->getLogger();
		$this->logger->info("§a> Loading...");
	}


	public function onJoin(PlayerJoinEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();

		if ($this->users->isDate($name)) {
			if (time() - $this->users->getDate($name)["date"] <= $this->deleteDay * 3600 * 24) {
				$this->chatPlayers[strtolower($name)] = true;
			} else {
				$this->chatPlayers[strtolower($name)] = false;
			}
		} else {
			$this->chatPlayers[strtolower($name)] = false;
		}
	}


	public function onChat(PlayerChatEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();

		if (!$this->chatPlayers[strtolower($name)]) {
			$event->setCancelled(true);
			$chat = $this->getServer()->getLanguage()->translateString($event->getFormat(), [$player->getDisplayName(), $event->getMessage()]);
			MainLogger::getLogger()->info($chat);
			if (strlen($event->getMessage()) === 5) {
				if (!preg_match("/^[a-zA-Z0-9]+$/", $event->getMessage())) return;
				$this->getServer()->getScheduler()->scheduleAsyncTask(new Authentication($event->getMessage(), strtolower($name)));
			} else {
				$player->sendMessage("§c認証されていないためチャットが利用できません！\n".
								 	 "§c五文字の認証キーをチャットかダイアログに入力してください！"
									);
			}
		}
	}


	public function onCmd(PlayerCommandPreprocessEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$message = $event->getMessage();
		$command = substr($message, 1);
		$args = explode(" ", $command);

		switch ($args[0]) {

			case "me":

				if (!$this->chatPlayers[$name]) {
					$event->setCancelled(true);
					$this->sendForm($player);
				}

				break;

			case "say":

				if (!$this->chatPlayers[$name]) {
					$event->setCancelled(true);
					$this->sendForm($player);
				}

				break;
		}		
	}


	public function onReceivePacket(DataPacketReceiveEvent $event)
	{
		$packet = $event->getPacket();
		if ($packet instanceof ModalFormResponsePacket) {
			$player = $event->getPlayer();
			$name = $player->getName();
			$formId = (int) $packet->formId;
			$formData = json_decode($packet->formData);

			if (!isset($player->formId)) return;
			if (!array_key_exists(self::PLUGIN_NAME, $player->formId)) return;
			if ($formId === $player->formId[self::PLUGIN_NAME][self::FORM_KEY]) {
				if (!preg_match("/^[a-zA-Z0-9]+$/", $formData[1])) {
					unset($player->formId[self::PLUGIN_NAME][self::FORM_KEY]);
					return;
				}
				$this->getServer()->getScheduler()->scheduleAsyncTask(new Authentication($formData[1], strtolower($name)));
			}
		}
	}


	/**   API   **/
	public function sendForm(Player $player)
	{
		$data = [
			"type" => "custom_form",
			"title" => "§lMiRmチャット認証",
			"content" => [
				[
					"type" => "label",
					"text" => "§cチャットをするためには認証キーが必要です。\n".
							  "§c認証キーは ". $this->chatUrl ." で取得できます！",
				],
				[
					"type" => "input",
					"text" => "認証キー",
					"placeholder" => "Authentication key"
				]
			]
		];

		$pk = new ModalFormRequestPacket();

		$pk->formId = mt_rand(1, 9999999);
		$pk->formData = json_encode($data);

		$player->formId[self::PLUGIN_NAME][self::FORM_KEY] = $pk->formId;

		$player->dataPacket($pk);		
	}
}