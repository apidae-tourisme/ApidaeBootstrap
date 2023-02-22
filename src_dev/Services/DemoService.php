<?php

namespace App\Services ;

use Psr\Log\LoggerInterface;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use ApidaeTourisme\ApidaeBundle\Config\TachesCodes;

class DemoService
{
    public function __construct(private LoggerInterface $logger)
    {
    }
    /**
     * Exemple type de tâche lancée par le gestionnaire de tâche :
     * apidae:tache:run X
     */
    public static function demo(Tache $tache): TachesCodes
    {
        $steps = 5 ;
        $logger_context = ['id' => $tache->getId(), 'steps' => $steps] ;
        $step = 1 ;
        do {
            $logger_context['step'] = $step ;
            $tache->log('info', 'Début de l\'étape '.$step.'...');
            /**
             * @todo comme l'exemple est static, on n'a pas accès au logger instancié
             * Montrer ici comment récupérer un logger du container
             */
            //$this->logger->info('Début de l\'étape...', $logger_context) ;
            // Do whatever this task has to do
            sleep(2) ;
            $step++ ;
            $tache->log('info', 'Fin de l\'étape '.$step.'...');
            //$this->logger->info('Etape terminée !', $logger_context) ;
        } while ($step <= $steps) ;

        return TachesCodes::SUCCESS ;
    }

    /**
     * @todo Montrer un exemple de tâche utilisant une méthode non statique
    */
    public function demo2(Tache $tache): TachesCodes
    {
        return TachesCodes::SUCCESS ;
    }
}
