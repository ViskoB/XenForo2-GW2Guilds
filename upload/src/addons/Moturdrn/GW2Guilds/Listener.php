<?php

namespace Moturdrn\GW2Guilds;

use XF\Entity\Post;
use XF\Entity\Thread;

class Listener
{
    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater)
    {
        $templater->addFunction('guild_emblem', function($templater, &$escape, $guild, $size = 32)
        {
            $escape = false;
            $guildName = htmlspecialchars($guild['guild_name']);
            $src = "https://guilds.gw2w2w.com/guilds/{$guildName}/{$size}.svg";
            return $templater->renderTemplate('moturdrn_gw2guilds_emblem', [
                'emblem_src' => $src,
            ]);
        });
    }

    public static function getEmblem($content, $guild_name)
    {
        $guildName = htmlspecialchars($guild_name[0]);
        $src = "https://guilds.gw2w2w.com/guilds/{$guildName}/64.svg";
        return $src;
    }

    public static function getBanners($content, $user)
    {
        $output = "";
        $app = \XF::app();
        $guildRepo = $app->repository('Moturdrn\GW2Guilds:Guild');
        $userGuilds = $guildRepo->getActiveGuildsOfUser($user[0]);
        foreach($userGuilds as $userGuild)
        {
            $guildLink = $app->router()->buildLink('canonical:guilds', $userGuild);
            $output .= "<div class=\"userBanner userBanner userBanner--guild message-userBanner\" itemprop=\"jobTitle\"><span class=\"userBanner-before\"></span><strong><a href=\"{$guildLink}\" style=\"color: white;\">{$userGuild['guild_name']} [{$userGuild['guild_tag']}]</a></strong><span class=\"userBanner-after\"></span></div>";
        }
        return $output;
    }
}