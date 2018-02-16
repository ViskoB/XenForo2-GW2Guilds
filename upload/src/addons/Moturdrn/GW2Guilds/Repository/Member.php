<?php

namespace Moturdrn\GW2Guilds\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Member extends Repository
{
    /**
     * @return array
     */
    public function getGuildMembers($guildId)
    {
        return $this->db()->fetchAll("SELECT * FROM xf_moturdrn_gw2guilds_member WHERE state = 'accepted' AND guild_id = ?;", $guildId);
    }

    /**
     * @return finder
     */
    public function findGuildMembers($guildId)
    {
        $finder = $this->finder('Moturdrn\GW2Guilds:Member');
        $finder
            ->where('guild_id',$guildId)
            ->with('User')
            ->with('User.Profile');

        return $finder;
    }

    /**
     * @return array
     */
    public function getActiveGuildMember($guildId, $userId)
    {
        return $this->db()->fetchRow("SELECT * FROM xf_moturdrn_gw2guilds_member WHERE state = 'accepted' AND guild_id = ? AND user_id = ?;", array($guildId, $userId));
    }

    /**
     * @return array
     */
    public function getActiveGuildMemberCountByGuildId($guildId)
    {
        return $this->db()->fetchRow("SELECT count(*) as `GuildMembers` FROM xf_moturdrn_gw2guilds_member WHERE state = 'accepted' AND guild_id = ?;", $guildId);
    }

    /**
     * @return array
     */
    public function getPendingRequestByUserGuild($guildId, $userId)
    {
        return $this->db()->fetchRow("SELECT * FROM xf_moturdrn_gw2guilds_member WHERE guild_id = ? and user_id = ? and state = 'pending';", array($guildId, $userId));
    }

    /**
     * @return array
     */
    public function getGuildMember($guildId, $userId)
    {
        return $this->db()->fetchRow("SELECT * FROM xf_moturdrn_gw2guilds_member WHERE guild_id = ? AND user_id = ?;", array($guildId, $userId));
    }

    /**
     * @return array
     */
    public function getPendingJoinRequestsByGuildId($guildId)
    {
        return $this->db()->fetchAll("SELECT * FROM xf_moturdrn_gw2guilds_member WHERE state = 'pending' and guild_id = ?", $guildId);
    }

    /**
     * @return array
     */
    public function getPendingJoinRequestsCountByGuildId($guildId)
    {
        return $this->db()->fetchRow("SELECT count(*) as `PendingMembers` FROM xf_moturdrn_gw2guilds_member WHERE state = 'pending' and guild_id = ?", $guildId);
    }

    /**
     * @return finder
     */
    public function findPendingRequestsByGuildId($guildId)
    {
        $finder = $this->finder('Moturdrn\GW2Guilds:Member');
        $finder
            ->where('guild_id',$guildId)
            ->where('state', 'pending')
            ->with('User')
            ->with('User.Profile');

        return $finder;
    }

    public function accessAddOrRemove($userId)
    {
        if($this->options()->moturdrn_gw2guilds_apply_member_group) {
            /** @var \XF\Entity\User $user */
            $user = \XF::em()->find('XF:User', ["user_id" => $userId]);
            if ($user) {
                if ($userGroup = \XF::em()->find('XF:UserGroup', $this->options()->moturdrn_gw2guilds_member_group)) {
                    if ($this->_getGuildRepo()->getActiveGuildsOfUser($user['user_id'])) {
                        $this->addSecondaryGroup($user, $userGroup['user_group_id']);
                    } else {
                        $this->removeSecondaryGroup($user, $userGroup['user_group_id']);
                    }
                }
            }
        }
    }

    public function leaderAddOrRemove($userId)
    {
        if($this->options()->moturdrn_gw2guilds_apply_leader_group) {
            /** @var \XF\Entity\User $user */
            $user = \XF::em()->find('XF:User', ["user_id" => $userId]);

            if ($user) {
                if ($userGroup = \XF::em()->find('XF:UserGroup', $this->options()->moturdrn_gw2guilds_leader_group)) {
                    if ($this->_getGuildRepo()->getActiveGuildsOfUser($user['user_id'])) {
                        $this->addSecondaryGroup($user, $userGroup['user_group_id']);
                    } else {
                        $this->removeSecondaryGroup($user, $userGroup['user_group_id']);
                    }
                }
            }
        }
    }

    public function addSecondaryGroup(\XF\Entity\User $user, $groupId)
    {
        $group = \XF::em()->find('XF:UserGroup', $groupId);
        if($user && $group)
        {
            if(!$user->isMemberOf($groupId))
            {
                $secondaryGroupIds = $user->secondary_group_ids;
                $secondaryGroupIds[] = $groupId;
                $user->set('secondary_group_ids', $secondaryGroupIds);
                $user->save();
            }
        }
    }

    public function removeSecondaryGroup(\XF\Entity\User $user, $groupId)
    {
        $group = \XF::em()->find('XF:UserGroup', $groupId);
        if($user && $group)
        {
            if($user->isMemberOf($groupId))
            {
                $secondaryGroupIds = $user->secondary_group_ids;
                if(($key = array_search($groupId, $secondaryGroupIds)) !== false)
                {
                    unset($secondaryGroupIds[$key]);
                }
                $user->set('secondary_group_ids', $secondaryGroupIds);
                $user->save();
            }
        }
    }

    protected function _getGuildRepo()
    {
        /** @var \Moturdrn\GW2Guilds\Repository\Guild $repo */
        $repo = $this->repository('Moturdrn\GW2Guilds:Guild');
        return $repo;
    }
}