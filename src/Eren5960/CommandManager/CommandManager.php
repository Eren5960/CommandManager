<?php
/**
 *  _____                    ____   ___    __     ___  
 * | ____| _ __  ___  _ __  | ___| / _ \  / /_   / _ \ 
 * |  _|  | '__|/ _ \| '_ \ |___ \| (_) || '_ \ | | | |
 * | |___ | |  |  __/| | | | ___) |\__, || (_) || |_| |
 * |_____||_|   \___||_| |_||____/   /_/  \___/  \___/ 
 * 
 * @author Eren5960
 * @link https://github.com/Eren5960
 */
declare(strict_types=1);

namespace Eren5960\CommandManager;

use Eren5960\CommandManager\expections\CommandNotFoundExpection;
use Eren5960\CommandManager\expections\SubcommandNotFoundExpection;
use Eren5960\CommandManager\illegal_dedect\IllegalDedector;
use Eren5960\CommandManager\providers\Provider;
use pocketmine\command\Command;
use pocketmine\command\SimpleCommandMap;
use pocketmine\permission\Permission;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class CommandManager extends PluginBase{
    /** @var Provider */
    private $provider;
    /** @var CommandManager */
    private static $instance;
    /** @var string  */
    const PREFIX = "§e│ §bCommandManager §e│§r ";
    /** @var int  */
    const COMMAND_NOT_FOUND = 0;
    const COMMAND_ALREADY_ENABLED = 1;
    const COMMAND_ENABLED = 2;

    protected function onLoad(): void{
        $this->provider = new Provider($this);
        self::$instance = $this;
    }

    protected function onEnable(): void{
        $state = !(new IllegalDedector($this))->check();
        $sub_commands = [];
        $permissions = [];

        if($state){
            $this->setEnabled(false);
            return;
        }

        $this->provider->start();

        foreach ($this->getConfig()->getToRemoves() as $key => $name) {
            $this->disableCommandByName($name);
            $this->getLogger()->info(self::PREFIX . TextFormat::GOLD . $name . TextFormat::GREEN . " command disabled!");
        }

        /** @var BaseCommand $command */
        foreach ($this->provider->getDefaultSubCommands() as $command) {
            try {
                BaseCommand::registerSubcommand($command);
                $sub_commands[] = $command->getSubName();
            } catch (SubcommandNotFoundExpection $e) {
                $this->getLogger()->critical($e);
            }
        }

        $this->getCommandMap()->register("comandmanager", new BaseCommand());

        foreach ($sub_commands as $sub_command_name){
            $permissions["use.commandmanager." . $sub_command_name]["default"] = "op";
        }

        $permissions["use.commandmanager.allcommands"]["default"] = "op";

        Permission::loadPermissions($permissions);
    }

    /**
     * @return CommandManager
     */
    public static function getInstance(): CommandManager{
        return self::$instance;
    }

    /**
     * @return Provider
     */
    public function getConfig(): Config{
        return $this->provider;
    }

    /**
     * @return SimpleCommandMap
     */
    public function getCommandMap(): SimpleCommandMap{
        return $this->getServer()->getCommandMap();
    }

    /**
     * @param Command $command
     * @return bool
     */
    public function disableCommandByCommand(Command $command): bool{
        $this->getCommandMap()->unregister($command);
        return true;
    }

    /**
     * @param string $name
     * @return bool
     * @throws CommandNotFoundExpection
     */
    public function disableCommandByName(string $name): bool{
        $command = $this->getCommandMap()->getCommand($name);
        if(is_null($command)){
            throw new CommandNotFoundExpection($name);
        }

        $this->getCommandMap()->unregister($command);
        return true;
    }

    /**
     * @param string $name
     * @return int
     */
    public function enableCommandByName(string $name): int{
        if($this->getCommandMap()->getCommand($name) !== null){
            return self::COMMAND_ALREADY_ENABLED;
        }

        if(!isset($this->provider->getCommands()[$name])){
            return self::COMMAND_NOT_FOUND;
        }

        $this->getCommandMap()->register("commandmanager", $this->provider->getCommands()[$name]);
        return self::COMMAND_ENABLED;
    }
}