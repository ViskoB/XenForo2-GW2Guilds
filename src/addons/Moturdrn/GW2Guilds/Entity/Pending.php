<?php

namespace Moturdrn\GW2Guilds\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Pending extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_moturdrn_gw2guilds_pending';
        $structure->shortName = 'Moturdrn:GW2GuildPending';
        $structure->primaryKey = 'pending_id';
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
        $this->_alertUser();
    }

    protected function _alertUser()
    {
        $pendingId = $this->get('pending_id');
        $guildId = $this->get('guild_id');
        $pendingType = $this->get('pending_type');

        if($pendingType == 'JoinReq'){
            //Join Request - Guild Leader and Officers Only
        }else if($pendingType == 'NewGuild'){
            //New Guild - GW2GuildAdmins Only
        }else if($pendingType =='ChangeGuild'){
            //New Guild - GW2GuildAdmins Only
        }
    }
}