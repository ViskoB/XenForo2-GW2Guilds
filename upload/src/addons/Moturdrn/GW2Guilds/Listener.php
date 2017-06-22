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
}