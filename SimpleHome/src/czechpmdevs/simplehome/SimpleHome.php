<?php

/**
 * Copyright (C) 2018-2019  CzechPMDevs
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace czechpmdevs\simplehome;

use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use czechpmdevs\simplehome\commands\HomeCommand;
use czechpmdevs\simplehome\commands\RemovehomeCommand;
use czechpmdevs\simplehome\commands\SethomeCommand;
use mydeacy\moneylevel\service\MoneyLevelAPI;

/**
 * Class SimpleHome
 * @package simplehome
 */
class SimpleHome extends PluginBase implements Listener{

    /** @var SimpleHome $instance */
    private static $instance;

    /** @var array $messages */
    public $messages = [];

    /** @var array $homes */
    public $homes = [];

    /** @var Command[] $commands */
    private $commands = [];

    public function onEnable() {
        $this->getserver()->getPluginManager()->registerEvents($this, $this);
        self::$instance = $this;
        $this->registerCommands();
        $this->loadData();
    }

    public function onDisable() {
        $this->saveData();
    }

    /**
     * @api
     *
     * @param Player $player
     *
     * @return array
     */
    public function getHomeList(Player $player): array {
        $list = [];

        if(!isset($this->homes[$player->getName()])) {
            $this->homes[$player->getName()] = [];
        }

        foreach ($this->homes[$player->getName()] as $homeName => $homeData) {
            $list[] = $homeName;
        }

        return $list;
    }

    /**
     * @api
     *
     * @param Player $player
     *
     * @return string
     */
    public function getDisplayHomeList(Player $player): string {
        $list = $this->getHomeList($player);
        if(count($list) == 0) {
            return $this->messages["no-home"];
        }

        $msg = $this->messages["home-list"];
        $msg = str_replace("%1", (string)count($list), $msg);
        $msg = str_replace("%2", implode(", ", $list), $msg);

        return $msg;
    }

    /**
     * @api
     *
     * @param Player $player
     * @param Home $home
     */
    public function removeHome(Player $player, Home $home) {
        unset($this->homes[$player->getName()][$home->getName()]);
    }

    /**
     * @api
     *
     * @param Player $player
     * @param Home $home
     */
    public function setPlayerHome(Player $player, Home $home) {
        $lv = MoneyLevelAPI::getInstance()->getLv($player->getName());
        $max_lv = $this->messages["limit"] + (int)($lv / 10);
        if($max_lv != -1) {
            if(count($this->getHomeList($player)) >= $max_lv) {
                $msg = $this->messages["sethome-max"];
                $msg = str_replace("%1", $home->getName(), $msg);
                $msg = str_replace("%2", (string)$max_lv, $msg);
                $player->sendMessage(str_replace("%1", $home->getName(), $msg));
                return;
            }
        }
        $ban_worlds = explode(",",$this->messages["ban-world"]);
        foreach($ban_worlds as $ban_world){
            if($player->getlevel()->getName() == $ban_world){
                $player->sendMessage($this->messages["ban-world-message"]);
                return;
            }
        }
        $this->homes[$player->getName()][$home->getName()] = [$home->getX(), $home->getY(), $home->getZ(), $home->getLevel()->getName()];
    }

    /**
     * @api
     *
     * @param Player $player
     * @param string $home
     *
     * @return Home|bool
     */
    public function getPlayerHome(Player $player, string $home) {
        if(isset($this->homes[$player->getName()][$home])) {
            return new Home($player, $this->homes[$player->getName()][$home], $home);
        }
        else {
            return false;
        }
    }

    public function registerCommands() {
        $this->commands["delhome"] = new RemovehomeCommand($this);
        $this->commands["home"] = new HomeCommand($this);
        $this->commands["sethome"] = new SethomeCommand($this);
        foreach ($this->commands as $command) {
            $this->getServer()->getCommandMap()->register("simplehome", $command);
        }
    }


    public function saveData() {
        foreach ($this->homes as $name => $data) {
            $config = new Config($this->getDataFolder()."players/$name.yml", Config::YAML);
            $config->set("homes", $data);
            $config->save();
        }
    }

    public function loadData() {
        if(!is_dir($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if(!is_dir($this->getDataFolder()."players")) {
            @mkdir($this->getDataFolder()."players");
        }
        else {
            foreach (glob($this->getDataFolder()."players/*.yml") as $file) {
                $config = new Config($file, Config::YAML);
                $this->homes[basename($file, ".yml")] = $config->get("homes");
            }
        }
        if(!is_file($this->getDataFolder()."/config.yml")) {
            $this->saveResource("/config.yml");
        }
        $this->messages = $this->getConfig()->getAll();
    }

    /**
     * @return string $prefix
     */
    public function getPrefix(): string {
        return $this->messages["prefix"]." ";
    }

    /**
     * @api
     *
     * @return SimpleHome $instance
     */
    public static function getInstance(): SimpleHome {
        return self::$instance;
    }
}
