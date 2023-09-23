<?php

 namespace spokkernetwork\maxips;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    private $language;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->copyLanguageFiles();
        $this->loadLanguage();
        $this->createPlayerFolders();
    }

    public function onPlayerLoginEvent(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();
        $ip = $player->getAddress();
        $playerName = strtolower($player->getName());

        $ipFolder = $this->getDataFolder() . "ips/";
        if (!is_dir($ipFolder)) {
            mkdir($ipFolder, 0777, true);
        }

        $ipFileName = $this->getIpFileName($ip);
        $ipFilePath = $ipFolder . $ipFileName;

        if (file_exists($ipFilePath)) {
            $ipConfig = new Config($ipFilePath, Config::YAML);
            $playerList = $ipConfig->get("players", []);

            $maxAccounts = $this->getConfig()->get("MaxAccounts");
            if (count($playerList) >= $maxAccounts && !in_array($playerName, $playerList)) {
                $player->kick($this->getMessage("max_accounts_kick_message"), false);
                $this->getLogger()->warning($this->getMessage("max_accounts_warning", ["player" => $playerName]));
                return;
            }

            if (!in_array($playerName, $playerList)) {
                $playerList[] = $playerName;
                $ipConfig->set("players", $playerList);
                $ipConfig->save();
            }
        } else {
            $ipConfig = new Config($ipFilePath, Config::YAML);
            $ipConfig->set("players", [$playerName]);
            $ipConfig->set("ip", $ip);
            $ipConfig->save();
        }
     }



    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender->isOp()) {
            $sender->sendMessage($this->getMessage("no_permission"));
            return false;
        }

        switch ($command->getName()) {
            case "iplist":
    if (empty($args)) {
        $ipList = [];
        $ipFolder = $this->getDataFolder() . "ips/";
        if (is_dir($ipFolder)) {
            $ipFiles = scandir($ipFolder);
            foreach ($ipFiles as $ipFile) {
                if ($ipFile !== "." && $ipFile !== "..") {
                    $ipConfig = new Config($ipFolder . $ipFile, Config::YAML);
                    $firstPlayer = $ipConfig->get("players", [])[0] ?? "";
                    $ipList[$firstPlayer] = count($ipConfig->get("players", []));
                }
            }
        }
        foreach ($ipList as $player => $count) {
            $sender->sendMessage("$player: $count");
        }
        return true;
    } else {
        $targetPlayerName = strtolower($args[0]);
        $targetPlayerFileName = $this->getPlayerFileName($targetPlayerName);
        $targetPlayerFilePath = $this->getDataFolder() . "players/" . $targetPlayerFileName;

        if (!file_exists($targetPlayerFilePath)) {
            $sender->sendMessage($this->getMessage("player_not_found"));
            return false;
        }

        $targetPlayerConfig = new Config($targetPlayerFilePath, Config::YAML);
        $ip = $targetPlayerConfig->get("ip");
        $playerList = $targetPlayerConfig->get("players", []);

        $sender->sendMessage($this->getMessage("registered_names_for_ip") . " $ip:");
        foreach ($playerList as $registeredName) {
            $sender->sendMessage("- $registeredName");
        }
        return true;
    }


            case "updateips":
                // no optimization 
                
                $ipFolder = $this->getDataFolder() . "ips/";
                if (is_dir($ipFolder)) {
                    $ipFiles = scandir($ipFolder);
                    foreach ($ipFiles as $ipFile) {
                        if ($ipFile !== "." && $ipFile !== "..") {
                            $ipConfig = new Config($ipFolder . $ipFile, Config::YAML);
                            $firstPlayer = $ipConfig->get("players", [])[0] ?? "";
                            $newFileName = $this->getPlayerFileName($firstPlayer);
                            $newFilePath = $this->getDataFolder() . "players/" . $newFileName;

                            if (!file_exists($newFilePath)) {
                                copy($ipFolder . $ipFile, $newFilePath);
                            }
                        }
                    }
                }
                $sender->sendMessage($this->getMessage("players_updated_based_on_ips"));
                return true;

            default:
                return false;
        }
    }

    private function getIpFileName(string $ip): string {
        return str_replace(".", "_", $ip) . ".yml";
    }
    
    private function getPlayerFileName(string $playerName): string {
    return strtolower($playerName) . ".yml";
}





    private function copyLanguageFiles() {
        $languages = ["en", "pt", "es", "ru"];
        $pluginDataFolder = $this->getDataFolder();

        foreach ($languages as $language) {
            $resourcePath = $this->getFile() . "resources/language/{$language}.yml";
            $dataPath = $pluginDataFolder . "language/{$language}.yml";

            if (!file_exists($dataPath)) {
                $this->saveResource("language/{$language}.yml", false);
            }
        }
    }

    private function loadLanguage() {
        $language = $this->getConfig()->get("Language", "en");
        $languageFile = $this->getDataFolder() . "language/{$language}.yml";

        if (file_exists($languageFile)) {
            $this->language = new Config($languageFile, Config::YAML);
        } else {
            $this->getLogger()->warning($this->getMessage("language_file_not_found", ["language" => $language]));
            $this->language = new Config($this->getFile() . "resources/language/en.yml", Config::YAML);
        }
    }

    private function getMessage(string $key, array $placeholders = []): string {
        $message = $this->language->get($key, $key);

        foreach ($placeholders as $placeholder => $value) {
            $message = str_replace("{{$placeholder}}", $value, $message);
        }

        return $message;
    }

    private function createPlayerFolders() {
        $playersFolder = $this->getDataFolder() . "players/";
        if (!is_dir($playersFolder)) {
            mkdir($playersFolder, 0777, true);
        }
    }
}

