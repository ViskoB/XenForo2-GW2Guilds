<?php

namespace Moturdrn\GW2Guilds\Sitemap;

use XF\Sitemap\AbstractHandler;
use XF\Sitemap\Entry;

class Guild extends AbstractHandler
{
    public function getRecords($start)
    {
        $app = $this->app;
        $user = \XF::visitor();

        $ids = $this->getIds('xf_moturdrn_gw2guilds_guild', 'guild_id', $start);

        $guildFinder = $app->finder('Moturdrn\GW2Guilds:Guild');
        $guilds = $guildFinder
            ->where('guild_id', $ids)
            ->fetch();

        return $guilds;
    }

    public function getEntry($record)
    {
        $url = $this->app->router('public')->buildLink('canonical:guilds', $record);
        return Entry::create($url, [
            'lastmod' => $record->last_modified
        ]);
    }

    public function isIncluded($record)
    {
        /** @var $record \Moturdrn\GW2Guilds\Entity\Guild */
        if ($record->status == 'Active')
        {
            return true;
        }
        return false;
    }
}