<?php

namespace chatpermitter;

/* base */
use pocketmine\Server;

/* scheduler */
use pocketmine\scheduler\AsyncTask;

class Authentication extends AsyncTask
{
	public function __construct(string $key, string $name)
	{
		$this->key = $key;
		$this->name = $name;
	}


	public function onRun()
	{
		$data = [];
		$error = [];

		$data["name"] = $this->name;

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, Main::DELETE_URL ."?key=". $this->key);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);

		$response_before = curl_exec($curl);
		$response_after = trim($response_before);

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (is_numeric($response_after)) {
			if ($response_after === "0") {
				$data["result"] = false;
				$data["check"] = false;
			} else if ($response_after === "1"){
				$data["result"] = true;
				$data["check"] = false;
			}
		} else {
			$data["result"] = false;
			$data["check"] = true;
			$data["response"] = $response_after;
			$data["code"] = $code;
		}

		curl_close($curl);

		$this->setResult($data);
	}


	public function onCompletion(Server $server)
	{
		$data = $this->getResult();

		if (isset($data["check"])) {
			if ($data["check"]) {
				$this->logger->warning("Error Occured : ". PHP_EOL);
				$this->logger->warning($data["response"] . PHP_EOL);
				$this->logger->warning($data["code"] . PHP_EOL);
				return;				
			}
		}

		$name = $data["name"];

		if (!is_null($player = $server->getPlayer($name))) {
			if ($data["result"]) {
				Main::getInstance()->chatPlayers[$name] = true;
				if (Main::getInstance()->users->isDate($name)) {
					Main::getInstance()->users->rmDate($name);
				}
				Main::getInstance()->users->setDate($name);
				$player->addTitle("§aSuccess！", "§fコードが認証されました");
				$player->sendMessage("§a>> コードが認証されました！");
			} else {
				$player->addTitle("§cFailure...", "§fコードが認証されませんでした");
				$player->sendMessage("§c>> コードが認証されませんでした！");
			}
		}
	}
}