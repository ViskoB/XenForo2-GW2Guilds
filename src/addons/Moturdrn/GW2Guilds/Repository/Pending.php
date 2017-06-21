<?php

namespace Moturdrn\GW2Guilds\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Pending extends Repository
{
    /**
     * @return array
     */
    public function getPendingJoinRequestsByGuildId($guildId)
    {
        return $this->db()->fetchAllKeyed("SELECT * FROM xf_moturdrn_gw2guilds_pending WHERE pending_type = 'JoinReq' and guild_id = ?", "pending_id", $guildId);
    }

    public function getPendingRequestActivateByGuildId($guildId)
    {
        return $this->db()->fetchAllKeyed("SELECT * FROM xf_moturdrn_gw2guilds_pending WHERE guild_id = ? and pending_type in ('NewGuild','ChangeGuild')", "pending_id", $guildId);
    }

    public function findPendingRequestsByGuildId($guildId)
    {
        $finder = $this->finder('Moturdrn\GW2Guilds:Pending');
        $finder
            ->where('guild_id',$guildId)
            ->where(['pending_type','!=','JoinReq']);

        return $finder->fetch();
    }

    public function getPendingJoinRequestsByGuildIdUserId($guildId, $userId)
    {
        return $this->db()->fetchAllKeyed("SELECT * FROM xf_moturdrn_gw2guilds_pending WHERE guild_id = ? and user_id = ? and pending_type = 'JoinReq'", "pending_id", array($guildId, $userId));
    }

    /**
     * Ensures that the base fields/relationships are all set to make a "valid" Pending
     * once saved.
     *
     * @param \\Moturdrn\GW2Guilds\Entity\Pending|null $pending An existing Pending to check against or nothing to create a new one
     *
     * @return \Moturdrn\GW2Guilds\Entity\Pending
     */
    public function setupBasePending(\Moturdrn\GW2Guilds\Entity\Pending $pending = null)
    {
        if (!$pending)
        {
            $pending = $this->em->create('Moturdrn\GW2Guilds:Pending');
        }

        return $pending;
    }
}