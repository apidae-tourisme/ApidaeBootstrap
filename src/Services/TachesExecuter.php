<?php

namespace ApidaeTourisme\ApidaeBundle\Services;

use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;

// use App\Service\Traitements\DescriptifsThematisesService;
// use App\Service\Traitements\SITUtilisateurs;

class TachesExecuter
{
    protected $tacheRepository;
    // protected $descriptifsThematisesService;
    // protected $sITUtilisateurs;

    public function __construct(
        TacheRepository $tacheRepository,
        // DescriptifsThematisesService $descriptifsThematisesService,
        // SITUtilisateurs $sITUtilisateurs
    ) {
        $this->tacheRepository = $tacheRepository;
        // $this->descriptifsThematisesService = $descriptifsThematisesService;
        // $this->sITUtilisateurs = $sITUtilisateurs;
    }

    /**
     * Exécute la tâche
     */
    public function exec(int $tacheId)
    {
        $tache = $this->tacheRepository->getTacheById($tacheId);

        if (preg_match("#^([a-zA-Z]+):([a-zA-Z]+)$#", $tache->getTache(), $match)) {
            if (!$tache->getVerbose()) {
                ob_start();
            }
            return $this->{lcfirst($match[1])}->{$match[2]}($tache->getId());
            if (!$tache->getVerbose()) {
                ob_end_clean();
            }
        }

        return false;
    }
}
