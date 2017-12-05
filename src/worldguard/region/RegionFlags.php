<?php
namespace worldguard\region;

interface RegionFlags {

    const NO_PVP = 0b10000000000000;
    const CAN_FLY = 0b01000000000000;
    const NO_EDIT = 0b00100000000000;
    const NO_DROP_ITEM = 0b00010000000000;
    const CANNOT_ENTER = 0b00001000000000;
    const CANNOT_LEAVE = 0b00000100000000;
    const NO_HUNGER = 0b00000010000000;
}