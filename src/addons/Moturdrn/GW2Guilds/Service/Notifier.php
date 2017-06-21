<?php

namespace Moturdrn\GW2Guilds\Service;

use Moturdrn\GW2Guilds\Entity\Pending;
use XF\Service\AbstractService;

class Notifier extends AbstractService
{
    protected $pending;
    protected $actionType = 'reply';

    protected $notifyAdmins = [];
    protected $notifyOfficers = [];

    protected $usersAlerted = [];
    protected $usersEmailed = [];

    protected $previousPosts = null;

    public function __construct(\XF\App $app, Pending $pending)
    {
        parent::__construct($app);
        $this->pending = $pending;
        $actionType = $this->pending->pending_type;

        switch ($actionType)
        {
            case 'NewGuild':
            case 'ChangeGuild':
            case 'JoinReq':
                break;

            default:
                throw new \InvalidArgumentException("Unknown action type '$actionType'");
        }
        $this->actionType = $actionType;
    }

    public function setNotifyAdmins(array $admins)
    {
        $this->notifyAdmins = array_unique($admins);
    }

    public function getNotifyAdmins()
    {
        return $this->notifyAdmins;
    }

    public function setNotifyOfficers(array $officers)
    {
        $this->notifyOfficers = array_unique($officers);
    }

    public function getNotifyOfficers()
    {
        return $this->notifyOfficers;
    }

    public function setUsersAlerted(array $users)
    {
        $this->usersAlerted = $users;
    }

    public function getUsersAlerted()
    {
        return $this->usersAlerted;
    }

    public function setUsersEmailed(array $users)
    {
        $this->usersEmailed = $users;
    }

    public function getUsersEmailed()
    {
        return $this->usersEmailed;
    }

    public function notify($timeLimit = null)
    {
        $endTime = $timeLimit > 0 ? microtime(true) + $timeLimit : null;

        $notifiableUsers = $this->getUsersForNotification();

        $adminUsers = $this->getNotifyAdmins();
        foreach ($adminUsers AS $k => $userId)
        {
            if (isset($notifiableUsers[$userId]))
            {
                $this->sendGuildNotification($notifiableUsers[$userId]);
            }
            unset($adminUsers[$k]);

            if ($endTime && microtime(true) >= $endTime)
            {
                break;
            }
        }
        $this->notifyAdmins = $adminUsers;

        if ($endTime && microtime(true) >= $endTime)
        {
            return;
        }

        $officerUsers = $this->getNotifyOfficers();
        foreach ($officerUsers AS $k => $userId)
        {
            if (isset($notifiableUsers[$userId]))
            {
                $this->sendJoinNotification($notifiableUsers[$userId]);
            }
            unset($officerUsers[$k]);

            if ($endTime && microtime(true) >= $endTime)
            {
                break;
            }
        }
        $this->notifyOfficers = $officerUsers;

        if ($endTime && microtime(true) >= $endTime)
        {
            return;
        }
    }

    public function hasMore()
    {
        return count($this->getUsersForNotification()) > 0;
    }

    public function enqueueJobIfNeeded($forceCalculate = false)
    {
        // calling hasMore will populate the thread/forum watchers which we want to avoid by default.
        // if the values are null, they haven't been calculated so we have to insert the job
        $needed = (
            $this->hasMore()
        );
        if (!$needed)
        {
            return false;
        }

        $this->enqueueJob();

        return true;
    }

    public function enqueueJob()
    {
        $this->app->jobManager()->enqueue('Moturdrn\GW2Guilds:GuildNotifier', $this->getDataForJob());
    }

    public function getDataForJob()
    {
        return [
            'pendingId' => $this->pending->pending_id,
            'action' => $this->actionType,

            'admins' => $this->notifyAdmins,
            'officers' => $this->notifyOfficers,

            'alerted' => $this->usersAlerted,
            'emailed' => $this->usersEmailed
        ];
    }

    public function setupFromJobData(array $data)
    {
        $this->setNotifyAdmins($data['admins']);
        $this->setNotifyOfficers($data['officers']);

        $this->setUsersAlerted($data['alerted']);
        $this->setUsersEmailed($data['emailed']);
    }

    protected function getUsersForNotification()
    {
        $userIds = array_merge($this->getNotifyAdmins(), $this->getNotifyOfficers());

        $users = $this->app->em()->findByIds('XF:User', $userIds, ['Profile', 'Option']);
        if (!$users->count())
        {
            return [];
        }

        $users = $users->toArray();

        return $users;
    }

    protected function sendGuildNotification(\XF\Entity\User $user)
    {
        $pending = $this->pending;

        if ($user->user_id == $pending->user_id)
        {
            return false;
        }

        if($pending->pending_type != 'NewGuild' && $pending->pending_type != 'ChangeGuild') {
            return false;
        }

        /** @var \Moturdrn\GW2Guilds\Entity\Guild $guild */
        $guild = $this->app->em()->find('Moturdrn\GW2Guilds:Guild', $pending->guild_id);

        $extraData = ["guild" => $guild];

        if (empty($this->usersAlerted[$user->user_id]))
        {
            /** @var \XF\Entity\User $pendingUser */
            $pendingUser = $this->app->find('XF:User', $pending->user_id);

            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->app->repository('XF:UserAlert');
            if ($alertRepo->alert($user, $pending->user_id, $pendingUser['username'], 'moturdrn_gw2guilds', $pending->pending_id, strtolower($pending->pending_type), $extraData))
            {
                $this->usersAlerted[$user->user_id] = true;
                return true;
            }
        }

        return false;
    }

    protected function sendJoinNotification(\XF\Entity\User $user)
    {
        $pending = $this->pending;

        if ($user->user_id == $pending->user_id)
        {
            return false;
        }

        if($pending->pending_type != 'JoinReq') {
            return false;
        }

        /** @var \Moturdrn\GW2Guilds\Entity\Guild $guild */
        $guild = $this->app->em()->find('Moturdrn\GW2Guilds:Guild', $pending->guild_id);

        $extraData = ["guild" => $guild];

        if (empty($this->usersAlerted[$user->user_id]))
        {
            /** @var \XF\Entity\User $pendingUser */
            $pendingUser = $this->app->find('XF:User', $pending->user_id);

            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->app->repository('XF:UserAlert');
            if ($alertRepo->alert($user, $pending->user_id, $pendingUser['username'], 'moturdrn_gw2guilds', $pending->pending_id, 'joinreq', $extraData))
            {
                $this->usersAlerted[$user->user_id] = true;
                return true;
            }
        }

        return false;
    }
}