<?php

namespace ApidaeTourisme\ApidaeBundle\Config;

use Symfony\Component\Console\Command\Command;

/**
 * @see https://symfony.com/doc/current/reference/forms/types/enum.html
 *
 * TachesCode est un enum des const de Command :
 * On l'utilise surtout pour s'assurer que les codes renvoyés par les différentes fonctions sont bien des valeurs de Command::?
 * Les enum étant récents, on peut imaginer que Command finira par s'en servir, mais pour l'instant c'est plus sûr que d'utiliser un retour "int" à la valeur incertaine.
 * Le fait de forcer les fonction à renvoyer un TachesCode nous assure que le développeur a bien renvoyé un code correspondant à Command::?
 * C'est d'autant plus important que le code Command::SUCCESS est 0, ce qui peut être mal compris (on peut avoir le réflexe de renvoyer 1 pour OK et 0 pour KO)
 */

enum TachesCode: int
{
    case SUCCESS = 0 ; //Command::SUCCESS ;
    case FAILURE = 1 ; //Command::FAILURE ;
    case INVALID = 2 ; //Command::INVALID ;
}
