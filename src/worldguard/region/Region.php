<?php
namespace worldguard\region;

use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;

class Region {

    const FLAG2STRING = [
        "no-pvp" => RegionFlags::NO_PVP,
        "flight" => RegionFlags::CAN_FLY,
        "no-edit" => RegionFlags::NO_EDIT,
        "no-drop-item" => RegionFlags::NO_DROP_ITEM,
        "no-enter" => RegionFlags::CANNOT_ENTER,
        "no-leave" => RegionFlags::CANNOT_LEAVE,
        "no-hunger" => RegionFlags::NO_HUNGER
    ];

    const DEFAULT_VALUES = [
        RegionFlags::NO_EDIT => []
    ];

    /** @var string */
    public $name;

    /** @var Vector3 */
    public $pos1;

    /** @var Vector3 */
    public $pos2;

    /** @var string */
    public $world;

    /** @var int */
    public $flags;

    /** @var array */
    public $options = [];

    public function __construct(string $name, Vector3 $pos1, Vector3 $pos2, string $world, int $flags = 0, array $options = [])
    {
        $this->name = $name;

        //sorting: pos1 is always < pos2 so we don't need to call min() and max() in isInRegion()
        if ($pos1->x > $pos2->x) {
            $temp = $pos1->x;
            [$pos1->x, $pos2->x] = [$pos2->x, $temp];
        }
        if ($pos1->y > $pos2->y) {
            $temp = $pos1->y;
            [$pos1->y, $pos2->y] = [$pos2->y, $temp];
        }
        if ($pos1->z > $pos2->z) {
            $temp = $pos1->z;
            [$pos1->z, $pos2->z] = [$pos2->z, $temp];
        }

        $this->pos1 = $pos1;
        $this->pos2 = $pos2;

        $this->world = $world;
        $this->flags = $flags;
        $this->options = array_intersect_key($options, self::DEFAULT_VALUES);
    }

    public function getPositions() : array
    {
        return [clone $this->pos1, clone $this->pos2];
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getLevelname() : string
    {
        return $this->world;
    }

    public function contains(Position $pos) : bool
    {
        return $pos->level !== null &&
            $pos->level->getName() === $this->world &&//not sure if level names are unique, probably use level ids in the future
            $pos->x >= $this->pos1->x && $pos->x <= $this->pos2->x &&
            $pos->y >= $this->pos1->y && $pos->y <= $this->pos2->y &&
            $pos->z >= $this->pos1->z && $pos->z <= $this->pos2->z;
    }

    public function getData() : array
    {
        return [
            "world" => $this->world,
            "pos1" => $this->pos1->x.":".$this->pos1->y.":".$this->pos1->z,
            "pos2" => $this->pos2->x.":".$this->pos2->y.":".$this->pos2->z,
            "flags" => $this->flags,
            "options" => $this->options
        ];
    }

    public function hasFlag(int $flag) : bool
    {
        return ($this->flags & $flag) === $flag;
    }

    public function getFlag(int $flag)
    {
        return $this->options[$flag] ?? null;
    }

    public function setFlag(int $flag) : void
    {
        $this->flags |= $flag;
        if (isset(self::DEFAULT_VALUES[$flag])) {
            $this->options[$flag] = self::DEFAULT_VALUES[$flag];
        }
    }

    public function removeFlag(int $flag) : void
    {
        $this->flags &= ~$flag;
        unset($this->options[$flag]);
    }

    public function canEdit(Player $player) : bool
    {
        return $this->hasFlag(RegionFlags::NO_EDIT) && in_array($player->getLowerCaseName(), $this->options[RegionFlags::NO_EDIT]);
    }

    public function __toString() : string
    {
        return $this->name;
    }
}