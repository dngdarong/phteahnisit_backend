<?php

namespace App\Enums;

enum RoomTypeEnum: string
{
    case SingleRoom = 'single_room';
    case SharedRoom = 'shared_room';
    case Studio = 'studio';
    case Apartment = 'apartment';
}
