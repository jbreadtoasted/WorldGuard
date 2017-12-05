<?php
namespace worldguard;

use pocketmine\command\{CommandSender, PluginCommand};
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;

use worldguard\region\Region;

class CommandHandler extends PluginCommand {

    const HELP_MESSAGE = [
        "pos1" => TF::BLUE."/{CMD} pos1:".TF::YELLOW." Sets position 1",
        "pos2" => TF::BLUE."/{CMD} pos2:".TF::YELLOW." Sets position 2",
        "wand" => TF::BLUE."/{CMD} wand:".TF::YELLOW." Gives you worldguard wand",
        "create" => TF::BLUE."/{CMD} create <region>:".TF::YELLOW." Creates a new region.",
        "setflag" => TF::BLUE."/{CMD} setflag <region> <flag> <true/false>:".TF::YELLOW." Sets/removes a flag from a region.",
        "delete" => TF::BLUE."/{CMD} delete <region>:".TF::YELLOW." Deletes a region.",
        "list" => TF::BLUE."/{CMD} list <page>:".TF::YELLOW." Lists all regions.",
        "info" => TF::BLUE."/{CMD} info <region>:".TF::YELLOW." Get information of a region."
    ];

    private $creator = [];

    private $cache = [];

    public function __construct(WorldGuard $plugin)
    {
        parent::__construct("worldguard", $plugin);
        $this->setAliases(["wg"]);
        $this->setPermissionMessage(TF::RED."You are not authorized to use this command.");
        $this->setUsage("/worldguard help");
        $this->setPermission("worldguard.command");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        switch ($args[0] ?? null) {
            case "pos1":
                $this->setPosition($sender, 1);
                return;
            case "pos2":
                $this->setPosition($sender, 2);
                return;
            case "create":
                if (!isset($this->creator[$k = $sender->getId()]) || count($this->creator[$k]) !== 2) {
                    $sender->sendMessage(TF::RED."Please select two points using /$commandLabel pos1 and /$commandLabel pos2 before creating a new region.");
                    return;
                }
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED."Usage: ".str_replace("{CMD}", $commandLabel, self::HELP_MESSAGE["create"]));
                    return;
                }
                if (!ctype_alnum($args[1])) {
                    $sender->sendMessage(TF::RED."Region name must be alpha-numeric.");
                    return;
                }
                if ($this->getPlugin()->getRegion($args[1]) !== null) {
                    $sender->sendMessage(TF::RED."A region by the name ".$args[1]." already exists. Choose a different name or delete the current region named ".$args[1].".");
                    return;
                }
                $this->creator[$k][] = $sender->getLevel();

                $region = $this->getPlugin()->createRegion($args[1], ...$this->creator[$k]);
                unset($this->creator[$k]);

                $message = TF::GREEN."Created region ".$region->getName()." ";
                foreach ($region->getPositions() as $pos) {
                    $message .= $pos." ";
                }
                $sender->sendMessage($message);
                return;
            case "delete":
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED."Usage: ".str_replace("{CMD}", $commandLabel, self::HELP_MESSAGE["delete"]));
                    return;
                }
                if ($this->getPlugin()->deleteRegion($args[1])) {
                    $sender->sendMessage(TF::GREEN."Region '".$args[1]."' has been deleted.");
                } else {
                    $sender->sendMessage(TF::RED."Region '".$args[1]."' does not exist.");
                }
                return;
            case "setflag":
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED."Usage: ".str_replace("{CMD}", $commandLabel, self::HELP_MESSAGE["setflag"]));
                    return;
                }

                if (!isset($args[2])) {
                    $sender->sendMessage(TF::BLUE."Available flags: ".($this->cache["flags"] ?? $this->cache["flags"] = TF::YELLOW.implode(TF::BLUE.", ".TF::YELLOW, array_keys(Region::FLAG2STRING))));
                    return;
                }

                if (!isset($args[3])) {
                    $sender->sendMessage(TF::RED."Usage: ".str_replace("{CMD}", $commandLabel, self::HELP_MESSAGE["setflag"]));
                    return;
                }

                $flag = Region::FLAG2STRING[$args[2] = strtolower($args[2])] ?? null;
                if ($flag === null) {
                    $sender->sendMessage(TF::RED."Invalid flag '".$args[2]."'.");
                    return;
                }

                $region = $this->getPlugin()->getRegion($args[1]);
                if ($region === null) {
                    $sender->sendMessage(TF::RED."No region with the name '".$args[1]."' exists.");
                    return;
                }

                $args[3] = $args[3] ?? "true";

                if ($args[3] === "true") {
                    if ($region->hasFlag($flag)) {
                        $sender->sendMessage(TF::RED."'".$region->getName()."' already has this flag set.");
                    } else {
                        $region->setFlag($flag);
                        $sender->sendMessage(TF::GREEN."Flag '".$args[2]."' has been set to region '".$region->getName()."'.");
                    }
                } elseif ($args[3] === "false") {
                    if (!$region->hasFlag($flag)) {
                        $sender->sendMessage(TF::RED."'".$region->getName()."' does not have this flag set.");
                    } else {
                        $region->removeFlag($flag);
                        $sender->sendMessage(TF::GREEN."Flag '".$args[2]."' has been removed from region '".$region->getName()."'.");
                    }
                } else {
                    $sender->sendMessage(TF::RED."Invalid argument '".$args[3]."', you can set a flag to either 'true' or 'false'.");
                }
                return;
            case "help":
            default:
                $sender->sendMessage(implode("\n", str_replace("{CMD}", $commandLabel, self::HELP_MESSAGE)));
                return;
        }
    }

    private function setPosition(Player $player, int $pos) : void
    {
        --$pos;
        $player->sendMessage(TF::LIGHT_PURPLE.($pos === 0 ? "First" : "Second")." position set to (".$player->x.", ".$player->y.", ".$player->z.", ".$player->level->getName().")");
        if (!isset($this->creator[$k = $player->getId()])) {
            $this->creator[$k] = [];
        }
        $this->creator[$k][$pos] = $player->asVector3();
    }
}