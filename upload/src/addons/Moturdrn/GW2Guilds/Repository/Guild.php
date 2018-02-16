<?php

namespace Moturdrn\GW2Guilds\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Guild extends Repository
{
    /**
     * @return array
     */
    public function getGuilds()
    {
        return $this->db()->fetchAllKeyed("SELECT *
				FROM (SELECT *, 1 AS Rank FROM xf_moturdrn_gw2guilds_guild WHERE status = 'Active' OR status LIKE 'Pending%'
			UNION ALL
			SELECT *, 2 AS Rank
				FROM xf_moturdrn_gw2guilds_guild WHERE status = 'Inactive') guilds
			ORDER BY guilds.Rank, guilds.guild_name, guilds.guild_tag ASC", "guild_id");
    }

    /**
     * @return \Moturdrn\GW2Guilds\Entity\Guild
     */
    public function findGuilds()
    {
        /** @var \Moturdrn\GW2Guilds\Entity\Guild $finder */
        $finder = $this->finder('Moturdrn\GW2Guilds:Guild')
            ->order("guild_name", "asc")->order("guild_tag", "asc")->fetch();
        return $finder;
    }

    /**
     * @param $guildId
     * @return \Moturdrn\GW2Guilds\Entity\Guild
     */
    public function findGuildById($guildId)
    {
        /** @var \Moturdrn\GW2Guilds\Entity\Guild $finder */
        $finder = $this->finder('Moturdrn\GW2Guilds:Guild')
            ->where('guild_id',$guildId)->fetchOne();

        return $finder;
    }

    public function getGuildById($guildId)
    {
        return $this->db()->fetchRow("SELECT * FROM xf_moturdrn_gw2guilds_guild WHERE guild_id = ?", $guildId);
    }

    /**
     * @param $guildName
     * @return \Moturdrn\GW2Guilds\Entity\Guild
     */
    public function findGuildByName($guildName)
    {
        /** @var \Moturdrn\GW2Guilds\Entity\Guild $finder */
        $finder = $this->finder('Moturdrn\GW2Guilds:Guild')
            ->where('guild_name',$guildName)->fetchOne();

        return $finder;
    }

    public function getGuildByName($guildName)
    {
        return $this->db()->fetchRow("SELECT * FROM xf_moturdrn_gw2guilds_guild WHERE guild_name = ?", $guildName);
    }

    public function getPendingGuilds()
    {
        return $this->db()->fetchAllKeyed("SELECT * FROM xf_moturdrn_gw2guilds_guild AS guild WHERE status like 'Pending%' ORDER BY guild_name ASC;", "guild_id");
    }

    public function getGuildsOfUser($userId){
        return $this->db()->fetchAllKeyed("SELECT g.* FROM xf_moturdrn_gw2guilds_member as m JOIN xf_moturdrn_gw2guilds_guild as g on g.guild_id = m.guild_id WHERE m.user_id = ?", "guild_id", $userId);
    }

    public function getGuildsOfUserCount($userId){
        return $this->db()
            ->fetchRow("SELECT count(*) as GuildCount FROM xf_moturdrn_gw2guilds_member as m JOIN xf_moturdrn_gw2guilds_guild as g on g.guild_id = m.guild_id WHERE m.user_id = ?",$userId);
    }

    /**
     * @param $guildIdOrName
     * @return \Moturdrn\GW2Guilds\Entity\Guild
     */
    public function getGuildByIdOrName($guildIdOrName)
    {
        if(is_int($guildIdOrName) || $guildIdOrName === strval(intval($guildIdOrName))) {
            /** @var \Moturdrn\GW2Guilds\Entity\Guild $guild */
            $guild = $this->findGuildById($guildIdOrName);
        }else{
            /** @var \Moturdrn\GW2Guilds\Entity\Guild $guild */
            $guild = $this->findGuildByName($guildIdOrName);
        }

        return $guild;
    }

    public function getActiveGuildsOfUser($userId){
        return $this->db()->fetchAll("SELECT g.* FROM xf_moturdrn_gw2guilds_member as m JOIN xf_moturdrn_gw2guilds_guild as g on g.guild_id = m.guild_id WHERE g.status = 'Active' AND m.user_id = ?", $userId);
    }

    public function getActiveGuildsOfLeader($userId){
        return $this->db()->fetchAll("SELECT g.* FROM xf_moturdrn_gw2guilds_guild as g WHERE g.status = 'Active' AND g.guildleader_userid = ?", $userId);
    }

    /**
     * Ensures that the base fields/relationships are all set to make a "valid" Guild
     * once saved.
     *
     * @param \\Moturdrn\GW2Guilds\Entity\Guild|null $guild An existing Guild to check against or nothing to create a new one
     *
     * @return \Moturdrn\GW2Guilds\Entity\Guild
     */
    public function setupBaseGuild(\Moturdrn\GW2Guilds\Entity\Guild $guild = null)
    {
        if (!$guild)
        {
            $guild = $this->em->create('Moturdrn\GW2Guilds:Guild');
        }

        return $guild;
    }
}