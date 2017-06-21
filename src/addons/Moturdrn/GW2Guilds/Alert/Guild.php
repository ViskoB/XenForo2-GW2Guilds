<?php

namespace Moturdrn\GW2Guilds\Alert;

use XF\Alert\AbstractHandler;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;

class Guild extends AbstractHandler
{
    public function getEntityWith()
    {
        return ['Pending'];
    }

    public function canViewAlert(UserAlert $alert, &$error = null)
    {
        if ($alert->action == 'trophy' && !\XF::options()->enableTrophies)
        {
            return false;
        }

        return true;
    }

    public function getOptOutActions()
    {
        return [
            'joinreq',
            'newguild',
            'changeguild',
        ];
    }

    public function getOptOutDisplayOrder()
    {
        return 999;
    }
}