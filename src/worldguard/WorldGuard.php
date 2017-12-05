<?php
namespace worldguard;

use pocketmine\level\{Level, Position};
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

use worldguard\region\{Region, RegionFlags};

class WorldGuard extends PluginBase {

    /** @var Region[] */
    private $regions = [];

    /** @var string[] */
    private $regionCache = [];//for faster region checking.

    /** @var array */
    private $players = [];//realtime player regions

    public function onLoad() : void
    {
        $this->saveResource("regions.yml");
        $this->loadRegions($this->getDataFolder()."regions.yml");
    }

    public function onEnable() : void
    {
        $this->getServer()->getCommandMap()->register("WorldGuard", new CommandHandler($this), "worldguard");
        new EventListener($this, $this->players);
    }

    public function onDisable() : void
    {
        $this->saveRegions($this->getDataFolder()."regions.yml");
    }

    /**
     * Loads regions from YAML file.
     */
    public function loadRegions(string $file) : void
    {
        if (substr($file, -4) === ".yml") {
            foreach (yaml_parse_file($file) as $region => [
                "world" => $world,
                "pos1" => $pos1,
                "pos2" => $pos2,
                "flags" => $flags
            ]) {
                $this->regions[$name = strtolower($region)] = new Region(
                    $name,
                    new Vector3(...array_map("floatval", explode(":", $pos1))),
                    new Vector3(...array_map("floatval", explode(":", $pos2))),
                    $world,
                    $flags
                );
            }
            return;
        }
        throw new \Error("Could not read file ".basename($file).", invalid format.");
    }

    /**
     * Creates a new region.
     *
     * @param string $name
     * @param Vector3 $pos1
     * @param Vector3 $pos2
     * @param Level $level
     *
     * @return Region that has been created.
     */
    public function createRegion(string $name, Vector3 $pos1, Vector3 $pos2, Level $level) : Region
    {
        $this->cacheRegion($region = $this->regions[$name = strtolower($name)] = new Region($name, $pos1, $pos2, $level->getName()));
        return $region;
    }

    /**
     * Saves regions to YAML file.
     */
    public function saveRegions(string $file) : void
    {
        $regions = $this->regions;
        foreach ($regions as &$region) {
            $region = $region->getData();
        }
        yaml_emit_file($file, $regions);
    }

    /**
     * Deletes a region
     *
     * @return bool
     */
    public function deleteRegion(string $region) : bool
    {
        if (isset($this->regions[$region = strtolower($region)])) {
            $this->removeFromCache($region);
            unset($this->regions[$region]);
            return true;
        }
        return false;
    }

    /**
     * Returns all loaded regions.
     *
     * @return Region[]
     */
    public function getRegions() : array
    {
        return $this->regions;
    }

    /**
     * Returns a region by name.
     *
     * @return Region|null
     */
    public function getRegion(string $region) : ?Region
    {
        return $this->regions[strtolower($region)] ?? null;
    }

    /**
     * Returns region at Position.
     *
     * @return Region|null
     */
    public function getRegionFromPos(Position $pos) : ?Region
    {
        if (!empty($this->regionCache[$k = $pos->level->getName().":".(isset($pos->chunk) ? $pos->chunk->getX().":".$pos->chunk->getZ() : ($pos->x >> 4).":".($pos->z >> 4))][$k2 = $pos->y >> 4])) {
            foreach ($this->regionCache[$k][$k2] as $k3 => $region) {
                $region = $this->regions[$region] ?? null;
                if ($region === null) {
                    unset($this->regionCache[$k][$k2][$k3]);
                    continue;
                }
                if ($region->contains($pos)) {
                    return $region;
                }
            }
        }
        return null;
    }

    public function regionsExistInChunk(int $chunkX, int $chunkZ, string $level) : bool
    {
        return !empty($this->regionCache[$level.":".$chunkX.":".$chunkZ]);
    }

    /**
     * Caches a region for faster checking.
     */
    public function cacheRegion(Region $region) : void
    {
        $regionName = $region->getName();
        $level = $region->getLevelname();

        [$posMin, $posMax] = $region->getPositions();
        $posMin->x >>= 4;
        $posMin->y >>= 4;
        $posMin->z >>= 4;
        $posMax->x >>= 4;
        $posMax->y >>= 4;
        $posMax->z >>= 4;

        for ($chunkX = $posMin->x; $chunkX <= $posMax->x; ++$chunkX) {
            for ($chunkZ = $posMin->z; $chunkZ <= $posMax->z; ++$chunkZ) {
                for ($chunkY = $posMin->y; $chunkY <= $posMax->y; ++$chunkY) {
                    if (!isset($this->regionCache[$chunkLevelXZ = $level.":".$chunkX.":".$chunkZ])) {
                        $this->regionCache[$chunkLevelXZ] = [];
                    }
                    if (!isset($this->regionCache[$chunkLevelXZ][$chunkY])) {
                        $this->regionCache[$chunkLevelXZ][$chunkY] = [];
                    }
                    $this->regionCache[$chunkLevelXZ][$chunkY][] = $regionName;//this will narrow down getRegionFromPos() checks
                }
            }
        }
    }

    /**
     * Caches all regions of a specific level.
     */
    public function cacheLevelRegions(string $level) : void
    {
        $regionList = array_keys(array_column($this->regions, "world", "name"), $level, true);//array of region names
        if (!empty($regionList)) {
            foreach ($regionList as $region) {
                $this->cacheRegion($this->getRegion($region));
            }
            $this->getLogger()->notice("Found and loaded ".count($regionList)." from level '".$level."'");
        }
    }

    /**
     * Called when player enters another region.
     *
     * @param string|null $oldRegion
     * @param string|null $newRegion
     *
     * @return bool whether player is allowed to enter the new region / get out of the old region.
     */
    public function onRegionChange(Player $player, ?string $oldRegion, ?string $newRegion) : bool
    {
        if ($oldRegion !== null) {
            $oldRegion = $this->getRegion($oldRegion);
            if ($oldRegion->hasFlag(RegionFlags::CANNOT_LEAVE)) {
                return false;
            }
            if ($oldRegion->hasFlag(RegionFlags::CAN_FLY)) {
                $player->setAllowFlight(false);
                if ($player->isFlying()) {
                    $player->setFlying(false);
                }
            }
            $player->sendMessage("You left $oldRegion.");
        }
        if ($newRegion !== null) {
            $newRegion = $this->getRegion($newRegion);
            if ($newRegion->hasFlag(RegionFlags::CANNOT_ENTER)) {
                return false;
            }
            if ($newRegion->hasFlag(RegionFlags::CAN_FLY)) {
                $player->setAllowFlight(true);
            }
            $player->sendMessage("You entered $newRegion.");
        }
        return true;
    }
}