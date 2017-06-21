<?php

namespace Moturdrn\GW2Guilds\Alert;

use XF\Alert\AbstractHandler;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;

class Pending extends AbstractHandler
{
    public function getEntityWith()
    {
        return [];
    }

    public function canViewAlert(UserAlert $alert, &$error = null)
    {
        return true;
    }

    public function canViewContent(Entity $entity, &$error = null)
    {
        return true;
    }

    public function getOptOutActions()
    {
        return [
            //'joinreq',
            //'newguild',
            //'changeguild',
        ];
    }

    public function getOptOutDisplayOrder()
    {
        return 999;
    }
}