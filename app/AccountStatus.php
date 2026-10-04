<?php

namespace App;

/** Account lifecycle states; only trusted administration may change them. */
enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
