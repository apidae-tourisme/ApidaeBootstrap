<?php

namespace ApidaeTourisme\ApidaeBundle\Config;

use Symfony\Component\Console\Command\Command;

/**
 * @see https://symfony.com/doc/current/reference/forms/types/enum.html
 */

enum TachesCodes: int
{
    // public const SUCCESS = 0;
    // public const FAILURE = 1;
    // public const INVALID = 2;
    case SUCCESS = 0 ; //Command::SUCCESS ;
    case FAILURE = 1 ; //Command::FAILURE ;
    case INVALID = 2 ; //Command::INVALID ;
}
