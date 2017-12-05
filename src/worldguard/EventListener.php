<?php
namespace worldguard;

use pocketmine\event\block\{BlockBreakEvent, BlockPlaceEvent};
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerDropItemEvent, PlayerExhaustEvent, PlayerInteractEvent, PlayerMoveEvent};

use worldguard\region\RegionFlags;

class EventListener implements Listener {

    /** @var WorldGuard */
    private $plugin;

    /** @var string[] */
    private $playerRegions;//realtime player region

    public function __construct(WorldGuard $plugin, &$realtimePlayerRegions)
    {
        $this->playerRegions = &$realtimePlayerRegions;

        $this->plugin = $plugin;
        $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);

        foreach ($plugin->getServer()->getLevels() as $level) {
            $this->plugin->cacheLevelRegions($level->getName());
        }
    }

    public function onLevelLoad(LevelLoadEvent $event) : void
    {
        $this->plugin->cacheLevelRegions($event->getLevel()->getName());
    }

    public function onPlayerMove(PlayerMoveEvent $event) : void
    {
        $player = $event->getPlayer();
        $region = $this->plugin->getRegionFromPos($player);

        if ($region !== ($this->playerRegions[$k = $player->getId()] ?? null)) {
            if (!$this->plugin->onRegionChange($player, $this->playerRegions[$k] ?? null, $region)) {
                $from = $event->getFrom();
                $to = $event->getTo();
                $from->x -= ($to->x - $from->x) * 0.5;
                $from->y += ($to->y - $from->y) * 0.25;
                $from->z -= ($to->z - $from->z) * 0.5;
                $event->setTo($from);
                $event->setCancelled();
                return;
            }
            if ($region !== null) {
                $this->playerRegions[$k] = $region;
            } else {
                unset($this->playerRegions[$k]);
            }
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event) : void
    {
        $region = $this->plugin->getRegionFromPos($event->getBlock());
        if ($region !== null) {
            if (!$region->canEdit($event->getPlayer())) {
                $event->setCancelled();
            }
        }
    }

    public function onPlayerExhaust(PlayerExhaustEvent $event) : void
    {
        $player = $event->getPlayer();
        if (isset($this->playerRegions[$k = $player->getId()]) && $this->playerRegions[$k]->hasFlag(RegionFlags::NO_HUNGER)) {
            $event->setCancelled();
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event) : void
    {
        $region = $this->plugin->getRegionFromPos($event->getBlock());
        if ($region !== null) {
            if (!$region->canEdit($event->getPlayer())) {
                $event->setCancelled();
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event) : void
    {
        $region = $this->plugin->getRegionFromPos($event->getBlock());
        if ($region !== null) {
            if (!$region->canEdit($event->getPlayer())) {
                $event->setCancelled();
            }
        }
    }

    public function onEntityDamage(EntityDamageEvent $event) : void
    {
        $entity = $event->getEntity();
        if (isset($this->playerRegions[$k = $entity->getId()]) && $this->playerRegions[$k]->hasFlag(RegionFlags::NO_PVP)) {
            $event->setCancelled();
        }
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event) : void
    {
        $player = $event->getPlayer();
        if (isset($this->playerRegions[$k = $player->getId()]) && $this->playerRegions[$k]->hasFlag(RegionFlags::NO_DROP_ITEM)) {
            $event->setCancelled();
        }
    }
}