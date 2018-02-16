<?php

namespace Moturdrn\GW2Guilds\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Pending extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_moturdrn_gw2guilds_pending';
        $structure->shortName = 'Moturdrn\GW2Guilds:Pending';
        $structure->primaryKey = 'pending_id';
        $structure->contentType = 'moturdrn_gw2guilds';
        $structure->columns = [
            'pending_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'pending_type' => ['type' => self::STR, 'required' => true]
        ];
        $structure->relations = [
            'Guild' => [
                'entity' => 'Moturdrn\GW2Guilds:Guild',
                'type' => Entity::TO_MANY,
                'conditions' => 'guild_id',
                'primary' => true,
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => Entity::TO_MANY,
                'conditions' => 'user_id',
                'primary' => true,
            ],
        ];

        return $structure;
    }

    protected function _postSave()
    {
        //$this->sendNotifications($this);
    }

    protected function _postDelete()
    {
        //$this->deletePendingAlert($this);
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Pending $pending
     */
    public function sendNotifications(\Moturdrn\GW2Guilds\Entity\Pending $pending)
    {
        /** @var \Moturdrn\GW2Guilds\Service\Notifier $notifier */
        $notifier = $this->app()->service('Moturdrn\GW2Guilds:Notifier', $pending);
        $notifier->setNotifyAdmins($this->getAdminUserIds($this));
        $notifier->setNotifyOfficers($this->getOfficerUserIds($this));
        $notifier->notify(3);
        $notifier->enqueueJobIfNeeded();
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Pending $pending
     *
     * @return array
     */
    public function getAdminUserIds(\Moturdrn\GW2Guilds\Entity\Pending $pending)
    {
        if ($pending->pending_type != 'NewGuild' && $pending->pending_type != 'ChangeGuild')
        {
            return [];
        }

        $adminUserIds = [];

        /** @var \XF\Entity\User $users */
        $users = \XF::finder('XF:User')->fetch()->filter(function(\XF\Entity\User $user){
            return $user->hasPermission('moturdrn_gw2guilds', 'admin');
        });

        $users = $users->toArray();
        if($users)
        {
            foreach($users as $user)
            {
                if($user['user_id'] != $pending['user_id'])
                    $adminUserIds[] = $user['user_id'];
            }
        }

        return $adminUserIds;
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Pending $pending
     *
     * @return array
     */
    public function getOfficerUserIds(\Moturdrn\GW2Guilds\Entity\Pending $pending)
    {
        if ($pending->pending_type != 'JoinReq')
        {
            return [];
        }

        $officerUserIds = [];

        /** @var \Moturdrn\GW2Guilds\Entity\Guild $guild */
        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $pending['guild_id']);

        if($guild)
        {
            $officerUserIds[] = $guild->guildleader_userid;

            $guildOfficers = explode(',', $guild->guildofficer_userids);
            foreach($guildOfficers as $guildOfficer)
            {
                if($guildOfficer != $pending->user_id)
                    $officerUserIds[] = $guildOfficer;
            }
        }

        return $officerUserIds;
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Pending $pending
     */
    protected function deletePendingAlert(\Moturdrn\GW2Guilds\Entity\Pending $pending)
    {
        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->fastDeleteAlertsFromUser(
            $pending->user_id, 'moturdrn_gw2guilds', $pending->pending_id, strtolower($pending->pending_type)
        );
    }
}