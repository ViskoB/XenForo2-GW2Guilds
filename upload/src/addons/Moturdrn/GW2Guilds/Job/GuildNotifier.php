<?php

namespace Moturdrn\GW2Guilds;

use XF\Job\AbstractJob;

class GuildNotifier extends AbstractJob
{
    protected $defaultData = [
        'pendingId' => 0,
        'action' => '',

        'admins' => [],
        'officers' => [],

        'alerted' => [],
        'emailed' => []
    ];

    public function run($maxRunTime)
    {
        $pending = $this->app->find('Moturdrn\GW2Guilds:Pending', $this->data['pendingId']);
        if (!$pending)
        {
            return $this->complete();
        }

        /** @var \Moturdrn\GW2Guilds\Service\Notifier $notifier */
        $notifier = $this->app->service('Moturdrn\GW2Guilds:Notifier', $pending, $this->data['action']);
        $notifier->setupFromJobData($this->data);
        $notifier->notify($maxRunTime);
        if (!$notifier->hasMore())
        {
            return $this->complete();
        }

        $this->data = $notifier->getDataForJob();
        return $this->resume();
    }

    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('running');
        $typePhrase = 'Notifications'; // never seen
        return sprintf('%s... %s %s', $actionPhrase, $typePhrase, '...');
    }

    public function canCancel()
    {
        return false;
    }

    public function canTriggerByChoice()
    {
        return false;
    }
}