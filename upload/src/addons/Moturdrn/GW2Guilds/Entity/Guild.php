<?php

namespace Moturdrn\GW2Guilds\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Guild extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_moturdrn_gw2guilds_guild';
        $structure->shortName = 'Moturdrn\GW2Guilds:Guild';
        $structure->primaryKey = 'guild_id';
        $structure->columns = [
            'guild_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_name' => ['type' => self::STR, 'maxLength' => 50, 'required' => true,
                'verification' => ['$this', '_verifyGuildName'],],
            'guild_tag' => ['type' => self::STR, 'maxLength' => 4, 'required' => true],
            'guildleader_userid' => ['type' => self::UINT, 'required' => true],
            'guildofficer_userids' => ['type' => self::STR, 'maxLength' => 250, 'default' => ''],
            'members' => ['type' => self::STR, 'maxLength' => 20, 'default' => '1-50'],
            'WvW' => ['type' => self::STR, 'default' => 'N'],
            'PvE' => ['type' => self::STR, 'default' => 'N'],
            'PvP' => ['type' => self::STR, 'default' => 'N'],
            'Casual' => ['type' => self::STR, 'default' => 'N'],
            'guild_recruitment' => ['type' => self::STR, 'maxLength' => 6777215,
                'default' => 'No Guild Information Entered'],
            'guild_website' => ['type' => self::STR, 'maxLength' => 500, 'default' => ''],
            'status' => ['type' => self::STR, 'maxLength' => 20, 'default' => 'Pending (New]'],
            'last_modified' => ['type' => self::UINT]
        ];
        $structure->behaviors = [
            //'XF:ChangeLoggable' => [] // will pick up content type automatically
        ];
        $structure->relations = [
            'Member' => [
                'entity' => 'Moturdrn\GW2Guilds:Member',
                'type' => self::TO_MANY,
                'conditions' => 'guild_id',
                'primary' => true,
            ],
            'Pending' => [
                'entity' => 'Moturdrn\GW2Guilds:Pending',
                'type' => self::TO_MANY,
                'conditions' => 'guild_id',
                'primary' => true,
            ],
        ];
        $structure->getters = [
            'canEdit' => true,
            'canJoin' => true,
            'canLeave' => true,
            'canDelete' => true,
            'canTransfer' => true,
            'accessLevel' => true,
            'memberCount' => true,
            'pendingCount' => true
        ];

        return $structure;
    }

    /**
     * @return bool
     */
    public function getCanEdit()
    {
        $visitor = \XF::visitor();
        if($visitor['is_banned'])
        {
            return false;
        }

        if(!$visitor['user_id'])
        {
            return false;
        }

        if ($visitor->hasPermission('moturdrn_gw2guilds', 'admin')) {
            return true;
        }

        if($this->guildleader_userid == $visitor['user_id'])
        {
            return true;
        }

        $guildOfficers = explode(',', $this->guildofficer_userids);

        foreach($guildOfficers as $guildOfficer){
            if($guildOfficer == $visitor['user_id'])
                return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function getCanJoin()
    {
        $visitor = \XF::visitor();
        if($visitor['is_banned'])
        {
            return false;
        }

        if(!$visitor['user_id'])
        {
            return false;
        }

        if($this->_getMemberRepo()->getGuildMember($this->guild_id, $visitor['user_id']))
            return false;

        if ($visitor->hasPermission('moturdrn_gw2guilds', 'joinguild')) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function getCanLeave()
    {
        $visitor = \XF::visitor();
        if($visitor['is_banned'])
        {
            return false;
        }

        if(!$visitor['user_id'])
        {
            return false;
        }

        if($this->guildleader_userid == $visitor['user_id'])
        {
            return false;
        }

        if($this->_getMemberRepo()->getGuildMember($this->guild_id, $visitor['user_id']))
            return true;

        return false;
    }

    /**
     * @return bool
     */
    public function getCanDelete()
    {
        $visitor = \XF::visitor();
        if($visitor['is_banned'])
        {
            return false;
        }

        if(!$visitor['user_id'])
        {
            return false;
        }

        if ($visitor->hasPermission('moturdrn_gw2guilds', 'admin')) {
            return true;
        }

        if($this->guildleader_userid == $visitor['user_id'])
        {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getCanTransfer()
    {
        $visitor = \XF::visitor();
        if($visitor['is_banned'])
        {
            return false;
        }

        if(!$visitor['user_id'])
        {
            return false;
        }

        if ($visitor->hasPermission('moturdrn_gw2guilds', 'admin')) {
            return true;
        }

        if($this->guildleader_userid == $visitor['user_id'])
        {
            return true;
        }

        return false;
    }

    /**
     * @return bool|int
     */
    public function getAccessLevel()
    {
        $visitor = \XF::visitor();
        if($visitor['is_banned'])
        {
            return false;
        }

        if(!$visitor['user_id'])
        {
            return 0;
        }

        if(\XF::visitor()->hasPermission('moturdrn_gw2guilds','admin'))
        {
            return 50;
        }

        if($this->guildleader_userid == $visitor['user_id'])
        {
            return 40;
        }

        $guildOfficers = explode(',', $this->guildofficer_userids);

        foreach($guildOfficers as $guildOfficer){
            if($guildOfficer == $visitor['user_id'])
                return 30;
        }


        if($this->_getMemberRepo()->getActiveGuildMember($this->guild_id, $visitor['user_id']))
            return 20;

        if($this->_getMemberRepo()->getPendingRequestByUserGuild($this->guild_id, $visitor['user_id']))
            return 10;

        return 0;
    }


    /**
     * @return int
     */
    public function getMemberCount()
    {
        /** @var \Moturdrn\GW2Guilds\Entity\Member $guildMembers */
        $guildMembers = $this->_getMemberRepo()->getActiveGuildMemberCountByGuildId($this->guild_id);
        return $guildMembers['GuildMembers'];
    }

    /**
     * @return int
     */
    public function getPendingCount()
    {
        /** @var \Moturdrn\GW2Guilds\Entity\Member $pendingMembers */
        $pendingMembers = $this->_getMemberRepo()->getPendingJoinRequestsCountByGuildId($this->guild_id);
        return $pendingMembers['PendingMembers'];
    }

    protected function _postSave()
    {
        $guildId = $this->get('guild_id');
        $leaderId = $this->get('guildleader_userid');
        $guildStatus = $this->get('status');
        /* @var \Moturdrn\GW2Guilds\Repository\Pending $pendingRepo */
        $pendingRepo = $this->repository('Moturdrn\GW2Guilds:Pending');

        if($this->isInsert()) {
            /* @var \Moturdrn\GW2Guilds\Repository\Pending $pendingRepo */
            $pendingEntity = $pendingRepo->setupBasePending();
            $pendingEntity->set('guild_id', $guildId);
            $pendingEntity->set('user_id', $leaderId);
            $pendingEntity->set('pending_type', 'NewGuild');
            $pendingEntity->save();
        }else if($guildStatus != 'Active' && $guildStatus != 'Inactive'){
            if($pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', ["guild_id" => $guildId, "pending_type" => "NewGuild"])) {
                $pendingEntity->delete();
            }

            if(!$pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', ["guild_id" => $guildId, "pending_type" => "ChangeGuild"])) {
                $pendingEntity = $pendingRepo->setupBasePending();
                $pendingEntity->set('guild_id', $guildId);
                $pendingEntity->set('user_id', $leaderId);
                $pendingEntity->set('pending_type', 'ChangeGuild');
                $pendingEntity->save();
            }
        }else if($guildStatus == 'Active' || $guildStatus == 'Inactive'){
            /* @var \Moturdrn\GW2Guilds\Entity\Pending $pendingEntities */
            $pendingEntities = $pendingRepo->findPendingRequestsByGuildId($guildId);
            foreach($pendingEntities as $pendingEntity) {
                $pendingEntity->delete();
            }

            if($guildStatus != $this->getPreviousValue('status')){
                //Add/Remove current members to group
                $members = $this->_getMemberRepo()->findGuildMembers($guildId);
                foreach($members as $member){
                    $this->_getMemberRepo()->accessAddOrRemove($member['user_id']);
                }

                //Add/Remove Guild Leader to group
                $this->_getMemberRepo()->leaderAddOrRemove($leaderId);
            }

            if($guildStatus == 'Inactive' && $this->getPreviousValue('status') == 'Active'){
                //Remove pending users
                if($pendingMembers = $pendingRepo->findPendingJoinRequestsByGuildId($guildId)){
                    foreach($pendingMembers as $pendingMember){
                        $pendingMember->delete();
                    }
                }
            }
        }
    }

    protected function _postDelete()
    {
        $guildId = $this->get('guild_id');
        $leaderId = $this->get('guildleader_userid');
        if($pendingEntities = $this->_getPendingRepo()->findPendingRequestsByGuildId($guildId)){
            foreach($pendingEntities as $pendingEntity){
                $pendingEntity->delete();
            }
        }

        if($memberEntities = $this->_getMemberRepo()->findGuildMembers($guildId))
        {
            foreach($memberEntities as $memberEntity){
                $memberEntity->delete();
            }
        }
        $this->_getMemberRepo()->leaderAddOrRemove($leaderId);
    }

    protected function _getGuildRepo()
    {
        /** @var \Moturdrn\GW2Guilds\Repository\Guild $repo */
        $repo = $this->repository('Moturdrn\GW2Guilds:Guild');
        return $repo;
    }

    protected function _getMemberRepo()
    {
        /** @var \Moturdrn\GW2Guilds\Repository\Member $repo */
        $repo = $this->repository('Moturdrn\GW2Guilds:Member');
        return $repo;
    }

    protected function _getPendingRepo()
    {
        /** @var \Moturdrn\GW2Guilds\Repository\Member $repo */
        $repo = $this->repository('Moturdrn\GW2Guilds:Member');
        return $repo;
    }
}