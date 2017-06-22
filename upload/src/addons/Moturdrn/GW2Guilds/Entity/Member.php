<?php

namespace Moturdrn\GW2Guilds\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Member extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_moturdrn_gw2guilds_member';
        $structure->shortName = 'Moturdrn\GW2Guilds:Member';
        $structure->primaryKey = array('guild_id','user_id');
        $structure->columns = [
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'state' => ['type' => self::BINARY, 'allowedValues' => ['pending','accepted'], 'default' => 'pending'],
            'join_date' => ['type' => self::UINT, 'default' => \XF::$time]
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
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true,
            ],
        ];

        return $structure;
    }

    protected function _postSave()
    {
        $state = $this->get('state');
        if($this->isInsert() && $state == 'pending')
        {
            /* @var \Moturdrn\GW2Guilds\Repository\Pending $pendingRepo */
            $pendingRepo = $this->repository('Moturdrn\GW2Guilds:Pending');
            $pendingEntity = $pendingRepo->setupBasePending();
            $pendingEntity->set('guild_id', $this->get('guild_id'));
            $pendingEntity->set('user_id', $this->get('user_id'));
            $pendingEntity->set('pending_type', 'JoinReq');
            $pendingEntity->save();
        }else if($state == 'accepted')
        {
            if($pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', ["guild_id" => $this->get('guild_id'), "user_id" => $this->get('user_id'), "pending_type" => 'JoinReq']))
                $pendingEntity->delete();
        }
    }

    protected function _postDelete()
    {
        $guildId = $this->get('guild_id');
        $userId = $this->get('user_id');
        /* @var \Moturdrn\GW2Guilds\Repository\Pending $pendingRepo */
        $pendingRepo = $this->repository('Moturdrn\GW2Guilds:Pending');
        if($ghostPending = $pendingRepo->getPendingJoinRequestsByGuildIdUserId($guildId, $userId))
        {
            foreach($ghostPending as $pending)
            {
                if($pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', $pending['pending_id']))
                {
                    $pendingEntity->delete();
                }
            }
        }
        $this->_getMemberRepo()->accessAddOrRemove($userId);
    }

    protected function _getMemberRepo()
    {
        /** @var \Moturdrn\GW2Guilds\Repository\Member $repo */
        $repo = $this->repository('Moturdrn\GW2Guilds:Member');
        return $repo;
    }
}