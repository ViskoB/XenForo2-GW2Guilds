<?php

namespace Moturdrn\GW2Guilds\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;
use XF\Pub\Controller\AbstractController;

class Guild extends AbstractController
{
    const ACCESS_GROUP_ID = 5;
    const GUILD_LEADER_GROUP_ID = 6;

    public function actionIndex(ParameterBag $parameterBag)
    {
        $visitor = \XF::visitor();
        $guildId = $parameterBag->guild_id;

        if($guildId && $guild = $this->assertGuildValid($guildId))
        {
            return $this->actionView($parameterBag);
        }elseif($guildId){
            return $this->redirect($this->buildLink('canonical:guilds'), 'Guild does not exist!');
        }

        $guildRepo = $this->_getGuildRepo();
        /** @var \Moturdrn\GW2Guilds\Entity\Guild $guilds */
        $guilds = $guildRepo->findGuilds();
        //$guilds = $this->prepareGuilds($guilds);
        $pendingGuilds = $guildRepo->getPendingGuilds();
        //$pendingGuilds = $this->prepareGuilds($pendingGuilds);
        if(!$visitor['user_id']) {
            $myGuilds = array("GuildCount" => 0);
        }
        else {
            $myGuilds = $guildRepo->getGuildsOfUserCount($visitor['user_id']);
        }

        $viewParams = [
            'guilds'            => $guilds,
            'canCreate'         => $this->canCreateGuild(),
            'pendingGuilds'     => $pendingGuilds,
            'isGW2GuildsAdmin'  => $this->isGW2GuildsAdmin(),
            'myGuilds'          => $myGuilds['GuildCount'],
        ];
        return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_index', $viewParams);
    }

    public function actionView(ParameterBag $parameterBag)
    {
        $guildRepo = $this->_getGuildRepo();
        /** @var \Moturdrn\GW2Guilds\Entity\Guild $guild */
        $guild = $guildRepo->getGuildByIdOrName($parameterBag->guild_id);
        //$guild = $this->prepareGuild($guild);
        $this->assertCanonicalUrl($this->buildLink('guilds', $guild));

        $viewParams = [
            'guildPage' => '',
            'guild' => $guild,
        ];
        return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_view', $viewParams);
    }

    public function actionMine(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;
        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);
        if($guild)
        {
            return $this->redirect($this->buildLink('guilds', $guild));
        }

        $visitor = \XF::visitor();

        if(!$visitor['user_id'])
        {
            return $this->redirect($this->buildLink('guilds'), 'You must be logged in to see this');
        }

        $guilds = $this->_getGuildRepo()->getGuildsofUser($visitor['user_id']);

        //$guilds = $this->prepareGuilds($guilds);

        $canCreate = $this->canCreateGuild();

        $pendingGuilds = $this->_getGuildRepo()->getPendingGuilds();
        //$pendingGuilds = $this->prepareGuilds($pendingGuilds);

        $viewParams = array(
            'guilds'	=> $guilds,
            'canCreate' => $canCreate,
            'pendingGuilds' => $pendingGuilds,
            'isGW2GuildsAdmin' => $this->isGW2GuildsAdmin(),
        );

        return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_index', $viewParams);
    }

    public function actionAdd()
    {
        $guild = null;

        if(!$this->canCreateGuild())
        {
            throw $this->exception($this->noPermission("You cannot create Guilds"));
        }

        return $this->_getGuildAddOrEditResponse($guild);
    }

    public function actionEdit(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;
        $guild = $this->_getGuildRepo()->getGuildByIdOrName($guildId);
        $this->assertCanonicalUrl($this->buildLink('guilds/edit', $guild));
        return $this->_getGuildAddOrEditResponse($guild);
    }

    public function actionDelete(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;

        /** @var \Moturdrn\GW2Guilds\Entity\Guild $guild */
        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

        if(!$guild)
        {
            throw $this->exception($this->message("Guild does not exist", 400));
        }

        $this->assertCanonicalUrl($this->buildLink('guilds/delete', $guild));

        if (!$this->canDeleteGuild($guild))
        {
            throw $this->exception($this->noPermission('You cannot delete this Guild'));
        }

        $visitor = \XF::visitor();

        if ($this->isPost())
        {
            $guildLeader = \XF::em()->find('XF:User', $guild->guildleader_userid);

            /*
             * Remove the Guild
             */
            $guild->delete();

            if($guild->status == 'Pending (New)' && $guild->guildleader_userid != $visitor['user_id'])
            {
                $messageText = <<<HTML
					Hi {$guildLeader['username']},
					Your registration of the Guild {$guild->guild_name} [{$guild->guild_tag}] has been rejected.

					This may be due to the Guild not being active on the server, or for other reasons.

					{$visitor['username']}
HTML;

                $creator = $this->setupConversationCreate($guildLeader['username'], 'Guild Rejected', $messageText);
                $creator->save();
                $this->finalizeConversationCreate($creator);
            }
            elseif($guild->guildleader_userid != $visitor['user_id'])
            {
                $messageText = <<<HTML
                    Hi {$guildLeader['username']},
                    The Guild {$guild->guild_name} [{$guild->guild_tag}] has been deleted from our database.

                    This may be due to the Guild no longer being active on the server, or for other reasons.

                    {$visitor['username']}
HTML;

                $creator = $this->setupConversationCreate($guildLeader['username'], 'Guild Deleted', $messageText);
                $creator->save();
                $this->finalizeConversationCreate($creator);
            }

            return $this->redirect($this->buildLink('guilds'), 'Guild Deleted');
        }
        else
        {
            $viewParams = array(
                'guild' => $guild,
            );

            return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_delete', $viewParams);
        }
    }

    public function actionTransfer(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;

        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

        if(!$guild)
        {
            throw $this->exception($this->message("Guild does not exist", 400));
        }

        $this->assertCanonicalUrl($this->buildLink('guilds/transfer', $guild));

        if (!$this->canTransferGuild($guild))
        {
            throw $this->exception($this->noPermission('You cannot transfer this Guild'));
        }

        $visitor = \XF::visitor();

        if ($this->isPost())
        {
            $oldLeaderId = $guild['guildleader_userid'];
            $username = $this->filter('username', 'str');
            $actionToDo = $this->filter('xferaction', 'str');

            $user = $this->_getUserRepo()->getUserByNameOrEmail($username);
            if(!$user)
            {
                throw $this->exception($this->notFound("User does not exist"));
            }

            if($user['user_id'] == $oldLeaderId)
                throw $this->exception($this->message('You cannot transfer a Guild to the existing Guild Leader', 400));

            /*
             * Add new leader to the Guild if not already in, or ensure accepted it pending
             */
            if(!$this->_getMemberRepo()->getGuildMember($guild['guild_id'], $user['user_id']))
            {
                $writer = \XF::em()->create('Moturdrn\GW2Guilds:Member');
                $writer->set('guild_id', $guild['guild_id']);
                $writer->set('user_id', $user['user_id']);
                $writer->set('state', 'accepted');
                $writer->save();
            }else{
                $writer = \XF::em()->find('Moturdrn\GW2Guilds:Member', ["guild_id" => $guild['guild_id'], "user_id" => $user['user_id']]);
                $writer->set('state', 'accepted');
                $writer->save();
                if($writer = \XF::em()->find('Moturdrn\GW2Guilds:Pending', ["guild_id" => $guild['guild_id'], "user_id" => $user['user_id'], "pending_type" => "JoinReq"]))
                {
                    $writer->delete();
                }
            }
            $guild->set('guildleader_userid', $user['user_id']);

            $guildOfficers = explode(',', $guild['guildofficer_userids']);

            /*
             * Remove new leader from officers list if present
             */
            foreach($guildOfficers as $key => $guildOfficer)
            {
                if($guildOfficer == $user['user_id'])
                    unset($guildOfficers[$key]);
            }

            /*
             * What is happening to the old leader?
             */
            if($actionToDo == 'Officer')
            {
                /*
                 * Add old leader to the officer list
                 */
                $guildOfficers[] = $oldLeaderId;
            }
            elseif($actionToDo == 'Remove')
            {
                /*
                 * Remove old leader from the usergroup
                 */
                if($memberEntity = \XF::em()->find('Moturdrn\GW2Guilds:Member', ["guild_id" => $guild['guild_id'], "user_id" => $oldLeaderId]))
                {
                    $memberEntity->delete();
                }
            }

            /*
             * Set the new Guild Leader
             */
            $guildOfficers = implode(",", $guildOfficers);

            $guild->set('guildofficer_userids', $guildOfficers);
            $guild->save();

            $this->_getMemberRepo()->leaderAddOrRemove($oldLeaderId);
            $this->_getMemberRepo()->accessAddOrRemove($oldLeaderId);

            $this->_getMemberRepo()->leaderAddOrRemove($user['user_id']);
            $this->_getMemberRepo()->accessAddOrRemove($user['user_id']);

            if($user['user_id'] != $visitor['user_id'])
            {
                $messageText = <<<HTML
					Hi {$user['username']},
					Leadership of the Guild {$guild['guild_name']} [{$guild['guild_tag']}] has been transferred to you.

					If any of your Guild members wish to join, they may do so by first registering on this forum, then joining via the Guild system.

					By joining the Guild, your members will be listed underneath the Guild Roster.

					{$visitor['username']}
HTML;
                $creator = $this->setupConversationCreate($user['username'], 'Guild Transferred', $messageText);
                $creator->save();
                $this->finalizeConversationCreate($creator);
            }

            return $this->redirect($this->buildLink('guilds/roster', $guild), 'Guild Transferred');
        }
        else
        {
            $viewParams = array(
                'guild' => $guild,
            );

            return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_transfer', $viewParams);
        }
    }

    public function actionSave()
    {
        $this->assertPostOnly();

        $guildId = $this->filter('guild_id', 'int');

        $visitor = \XF::visitor();

        if ($guildId)
        {
            $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

            if (!$this->canEditGuild($guild))
            {
                // throw error if user try to editing category but don't have permission :-/
                throw $this->exception($this->noPermission("You cannot edit this Guild"));
            }
        }
        else {
            $guild = null;
        }

        $guild_recruitment = $this->plugin('XF:Editor')->fromInput('guild_recruitment');

        $members = $this->filter('members', 'str');
        if(!in_array($members, array('1-50','51-100','101-150','151-200','201-250','251-300','301-350','351-400','401-450','451-500','500+')))
            throw $this->exception($this->message("You must select a valid number of members from the dropdown list.",400));


        $writer = $this->_getGuildRepo()->setupBaseGuild($guild);
        if ($guildId)
        {
            $oldStatus = $writer->get('status');
        }
        else
        {
            $writer = \XF::em()->create("Moturdrn\GW2Guilds:Guild");
            $oldStatus = '';
            $writer->set('guild_name', $this->filter('guild_name', 'str'));
            $writer->set('guild_tag', $this->filter('guild_tag', 'str'));
            $writer->set('status', "Pending (New)");
            $writer->set('guildleader_userid', $visitor['user_id']);
        }

        $modified_date = strtotime("now");

        $writer->set('guild_recruitment', $guild_recruitment);
        $writer->set('guild_website', $this->filter('guild_website', 'str'));
        $writer->set('WvW', $this->filter('WvW', 'str'));
        $writer->set('PvE', $this->filter('PvE', 'str'));
        $writer->set('PvP', $this->filter('PvP', 'str'));
        $writer->set('Casual', $this->filter('Casual', 'str'));
        $writer->set('members', $this->filter('members', 'str'));
        $writer->set('last_modified', $modified_date);
        $writer->save();

        $guild = $writer;

        if(!$guildId)
        {
            $writer = \XF::em()->create("Moturdrn\GW2Guilds:Member");
            $writer->set('guild_id', $guild['guild_id']);
            $writer->set('user_id', $visitor['user_id']);
            $writer->set('state', 'accepted');
            $writer->save();
        }

        $this->_getMemberRepo()->leaderAddOrRemove($guild['guildleader_userid']);

        return $this->redirect($this->buildLink('guilds', $guild), 'Guild Added');
    }

    public function actionJoin(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;
        if(!$guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId))
            throw $this->exception($this->notFound('Guild not found'));

        if(!$this->canJoinGuild($guild))
            throw $this->exception($this->noPermission('You cannot join this Guild'));

        $visitor = \XF::visitor();

        $memberEntity = \XF::em()->create('Moturdrn\GW2Guilds:Member');
        $memberEntity->set('guild_id', $guild['guild_id']);
        $memberEntity->set('user_id', $visitor['user_id']);
        $memberEntity->set('state', 'pending');
        $memberEntity->save();

        return $this->redirect($this->buildLink('guilds', $guild), 'You have requested to join the Guild');
    }

    public function actionLeave(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;
        if(!$guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId))
            throw $this->exception($this->notFound('Guild not found'));

        if(!$this->canLeaveGuild($guild))
            throw $this->exception($this->noPermission('You cannot leave this Guild'));

        $visitor = \XF::visitor();

        if($this->_getMemberRepo()->getGuildMember($guildId, $visitor['user_id'])) {
            if($memberEntity = \XF::em()->find('Moturdrn\GW2Guilds:Member', ["guild_id" => $guild['guild_id'], "user_id" => $visitor['user_id']])) {
                $memberEntity->delete();
            }
        }

        $guildOfficers = explode(',', $guild['guildofficer_userids']);

        foreach($guildOfficers as $key => $guildOfficer)
        {
            if($guildOfficer == $visitor['user_id'])
                unset($guildOfficers[$key]);
        }

        $guildOfficers = implode(",", $guildOfficers);
        $guild->set('guildofficer_userids', $guildOfficers);
        $guild->save();

        $this->_getMemberRepo()->accessAddOrRemove($visitor['user_id']);

        return $this->redirect($this->buildLink('guilds', $guild), 'You have left the Guild');
    }

    public function actionRoster(ParameterBag $parameterBag)
    {
        $guildRepo = $this->_getGuildRepo();
        $guildId = $parameterBag->guild_id;

        if(!$guildId && !$guild = $this->assertGuildValid($guildId))
        {
            return $this->redirect($this->buildLink('canonical:guilds'), 'Guild does not exist!');
        }

        $guild = $guildRepo->getGuildByIdOrName($parameterBag->guild_id);
        //$guild = $this->prepareGuild($guild);
        $visitor = \XF::visitor();
        if(!$visitor['user_id']) {
            $showAccNames = false;
        }else{
            $showAccNames = true;
        }

        $memberRepo = $this->_getMemberRepo();
        $guildMembersList = $memberRepo->getGuildMembers($guild['guild_id']);
        $guildOfficers = $guildMembers = array();

        foreach($guildMembersList as $guildMember)
        {
            /*
             * @return \XF\Entity\User
             */
            $guildUser = $this->assertViewableUser($guildMember['user_id'], [], false);

            //$customFields = unserialize($guildUser['custom_fields']);
            $displayName = $guildUser['username'];
            if($guildUser['user_id'] == $guild['guildleader_userid'])
            {
                $guildLeaderSingle = array(
                    "user" => $guildUser,
                    //"GW2AccName" => $customFields['guild_wars_2_id']
                );
                $guildLeader[] = $guildLeaderSingle;
            }
            elseif(in_array($guildUser['user_id'],explode(',',$guild['guildofficer_userids'])))
            {
                $guildOfficer = array(
                    "user" => $guildUser,
                    //"GW2AccName" => $customFields['guild_wars_2_id']
                );
                $guildOfficers[$displayName] = $guildOfficer;
            }
            else
            {
                $guildMember = array(
                    "user" => $guildUser,
                    //"GW2AccName" => $customFields['guild_wars_2_id']
                );
                $guildMembers[$displayName] = $guildMember;
            }
        }

        $array_lowercase = array_map('strtolower', array_keys($guildOfficers));
        array_multisort(array_keys($array_lowercase), SORT_ASC, SORT_STRING, $guildOfficers);
        $array_lowercase = array_map('strtolower', array_keys($guildMembers));
        array_multisort($array_lowercase, SORT_ASC, SORT_STRING, $guildMembers);
        $viewParams = array(
            'guildPage' => 'roster',
            'guild'	=> $guild,
            'leader' => $guildLeader,
            'officers' => $guildOfficers,
            'members' => $guildMembers,
            'showAccNames' => $showAccNames,
            'Mini' => false,
        );

        return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_view', $viewParams);
    }

    public function actionRequests(ParameterBag $parameterBag)
    {
        $guildRepo = $this->_getGuildRepo();
        $guildId = $parameterBag->guild_id;

        if(!$guildId && !$guild = $this->assertGuildValid($guildId))
        {
            return $this->redirect($this->buildLink('canonical:guilds'), 'Guild does not exist!');
        }

        $guild = $guildRepo->getGuildByIdOrName($parameterBag->guild_id);
        //$guild = $this->prepareGuild($guild);
        $visitor = \XF::visitor();
        if(!$visitor['user_id']) {
            $showAccNames = false;
        }else{
            $showAccNames = true;
        }

        $memberRepo = $this->_getMemberRepo();

        $pendingRequests = $this->_getMemberRepo()->getPendingJoinRequestsByGuildId($guild['guild_id']);
        $pendingMembers = array();
        foreach($pendingRequests as $pendingRequest)
        {
            /*
             * @return \XF\Entity\User
             */
            $guildUser = $this->assertViewableUser($pendingRequest['user_id'], [], false);

            //$customFields = unserialize($guildUser['custom_fields']);
            $displayName = $guildUser['username'];
            $pendingMember = array(
                "user" => $guildUser,
                //"GW2AccName" => $customFields['guild_wars_2_id']
            );
            $pendingMembers[$displayName] = $pendingMember;
        }

        $array_lowercase = array_map('strtolower', array_keys($pendingMembers));
        array_multisort($array_lowercase, SORT_ASC, SORT_STRING, $pendingMembers);
        $viewParams = array(
            'guildPage' => 'requests',
            'guild'	=> $guild,
            'pending' => $pendingMembers,
            'showAccNames' => $showAccNames,
            'Mini' => false,
        );

        return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_view', $viewParams);
    }

    public function actionActivate(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;

        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

        if(!$guild)
            throw $this->exception($this->notFound('Guild does not exist'));

        if(!$this->canEditGuild($guild))
        {
            throw $this->exception($this->noPermission('You cannot edit this Guild'));
        }

        $visitor = \XF::visitor();

        if(!$this->isGW2Guildsadmin() && $guild['status'] == 'Inactive')
        {
            $guild->set('status', 'Pending (Change)');
            $guild->save();
        }else if(!$this->isGW2Guildsadmin()){
            throw $this->exception($this->noPermission('You cannot set this Guild active from Pending'));
        }else {
            $guild->set('status', 'Active');
            $guild->save();

            if($existingPending = $this->_getPendingRepo()->getPendingRequestActivateByGuildId($guild['guild_id']))
            {
                foreach($existingPending as $pending)
                {
                    if($pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', $pending['pending_id'])) {
                        $pendingEntity->delete();
                    }
                }
            }

            if($guild['status'] == 'Pending (New)' && $guild['guildleader_userid'] != $visitor['user_id'])
            {
                $guildLeader = \XF::em()->find('XF:User', $guild['guildleader_userid']);

                $messageText = <<<HTML
					Hi {$guildLeader['username']},
					Your registration of the Guild {$guild['guild_name']} [{$guild['guild_tag']}] has been approved.

					If any of your Guild members wish to join, they may do so by first registering on this forum, then joining via the Guild system.

					By joining the Guild, your members will be listed underneath the Guild Roster.

					{$visitor['username']}
HTML;

                $creator = $this->setupConversationCreate($guildLeader['username'], 'Guild Approved', $messageText);
                $creator->save();
                $this->finalizeConversationCreate($creator);
            }
            elseif($guild['status'] == 'Pending (Changed)' && $guild['guildleader_userid'] != $visitor['user_id'])
            {
                $guildLeader = \XF::em()->find('User:XF', $guild['guildleader_userid']);

                $messageText = <<<HTML
					Hi {$guildLeader['username']},
					The request to mark the Guild {$guild['guild_name']} [{$guild['guild_tag']}] as active has been approved.

					If any of your Guild members wish to join, they may do so by first registering on this forum, then joining via the Guild system.

					By joining the Guild, your members will be listed underneath the Guild Roster.

					{$visitor['username']}
HTML;

                $creator = $this->setupConversationCreate($guildLeader['username'], 'Guild Request Approved', $messageText);
                $creator->save();
                $this->finalizeConversationCreate($creator);
            }
            elseif($guild['guildleader_userid'] != $visitor['user_id'])
            {
                $guildLeader = \XF::em()->find('XF:User', $guild['guildleader_userid']);

                $messageText = <<<HTML
					Hi {$guildLeader['username']},
					The Guild {$guild['guild_name']} [{$guild['guild_tag']}] has been marked as Active.

					If any of your Guild members wish to join, they may do so by first registering on this forum, then joining via the Guild system.

					By joining the Guild, your members will be listed underneath the Guild Roster.

					{$visitor['username']}
HTML;

                $creator = $this->setupConversationCreate($guildLeader['username'], 'Guild Approved', $messageText);
                $creator->save();
                $this->finalizeConversationCreate($creator);
            }
        }

        return $this->redirect($this->buildLink('guilds'), 'Guild status changed');
    }

    public function actionDeactivate(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;
        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);
        if(!$guild)
        {
            throw $this->exception($this->notFound('Guild does not exist'));
        }

        if(!$this->canEditGuild($guild))
        {
            throw $this->exception($this->noPermission('You cannot edit this Guild'));
        }

        $visitor = \XF::visitor();

        $guild->set('status', 'Inactive');
        $guild->save();

        /*
         * Remove any pending new or change Guild requests for this Guild
         */
        if($existingPending = $this->_getPendingRepo()->getPendingRequestActivateByGuildId($guild->guild_id))
        {
            foreach($existingPending as $pending)
            {
                if($pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', $pending['pending_id'])) {
                    $pendingEntity->delete();
                }
            }
        }

        $guildLeader = \XF::em()->find('XF:User',$guild['guildleader_userid']);

        if($guild['status'] == 'Pending (Changed)' && $guild['guildleader_userid'] != $visitor['user_id'])
        {
            $messageText = <<<HTML
					Hi {$guildLeader['username']},
					Your request to make the Guild {$guild['guild_name']} [{$guild['guild_tag']}] active has been rejected.

					This may be due to the Guild not being active on the server, or for other reasons.

					{$visitor['username']}
HTML;

            $creator = $this->setupConversationCreate($guildLeader['username'], 'Guild Request Rejected', $messageText);
            $creator->save();
            $this->finalizeConversationCreate($creator);
        }
        elseif($guild['guildleader_userid'] != $visitor['user_id'])
        {
            $messageText = <<<HTML
                    Hi {$guildLeader['username']},
                    The Guild {$guild['guild_name']} [{$guild['guild_tag']}] has been marked as Inactive.

                    This may be due to the Guild not being active on the server, or for other reasons.

                    {$visitor['username']}
HTML;

            $creator = $this->setupConversationCreate($guildLeader['username'], 'Guild Marked Inactive', $messageText);
            $creator->save();
            $this->finalizeConversationCreate($creator);
        }

        return $this->redirect($this->buildLink('guilds'), 'Guild status changed');
    }

    public function actionInvite(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;

        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

        if(!$guild)
        {
            throw $this->exception($this->message("Guild does not exist", 400));
        }

        $this->assertCanonicalUrl($this->buildLink('guilds/transfer', $guild));

        if (!$this->canTransferGuild($guild))
        {
            throw $this->exception($this->noPermission('You cannot transfer this Guild'));
        }

        $visitor = \XF::visitor();

        if ($this->isPost()) {
            $username = $this->filter('username', 'str');

            if (!$guild) {
                throw $this->exception($this->notFound("Guild does not exist"));
            }

            $user = $this->_getUserRepo()->getUserByNameOrEmail($username);
            if (!$user) {
                throw $this->exception($this->notFound("User does not exist"));
            }
            $userId = $user->user_id;

            //$guild = $this->prepareGuild($guild);

            if (!$guild->getCanEdit())
                throw $this->exception($this->noPermission("You cannot add members to the roster"));

            if ($this->_getMemberRepo()->getGuildMember($guildId, $userId))
                throw $this->exception($this->message("Member already in Guild", 400));

            if ($this->_getMemberRepo()->getPendingRequestByUserGuild($guildId, $userId))
                throw $this->exception($this->message("Member already applied to Guild, reject or approve their request", 400));

            $writer = \XF::em()->create('Moturdrn\GW2Guilds:Member');
            $writer->set('guild_id', $guild['guild_id']);
            $writer->set('user_id', $user['user_id']);
            $writer->set('state', 'accepted');
            $writer->save();

            $this->_getMemberRepo()->accessAddOrRemove($userId);

            return $this->redirect($this->buildLink('canonical:guilds/roster', $guild), 'User added to the Guild');
        }
        else
        {
            $viewParams = array(
                'guild' => $guild,
            );

            return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_invite', $viewParams);
        }
    }

    public function actionMembersRemove(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;

        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

        if(!$guild)
            throw $this->exception($this->notFound('Guild does not exist'));

        $userId = $this->filter('user_id', 'int');
        if (!$user = \XF::em()->find('XF:User', $userId))
            throw $this->exception($this->notFound('User does not exist'));

        if(!$member = \XF::em()->find('Moturdrn\GW2Guilds:Member', array($guild['guild_id'], $userId)))
            throw $this->exception($this->notFound('User not member of this Guild'));

        $guildAccessLevel = $this->guildAccessLevel($guild);

        if($guild['guildleader_userid'] == $userId)
            throw $this->exception($this->message('You cannot remove the Guild Leader. The Guild Leader or Admin must transfer or delete the Guild', 400));

        $guildOfficers = explode(',', $guild['guildofficer_userids']);

        if(in_array($userId, $guildOfficers))
            $requiredAccessLevel = 40;
        else
            $requiredAccessLevel = 30;

        if($guildAccessLevel < $requiredAccessLevel)
            throw $this->exception($this->message('You have insufficient permissions to remove this member.', 400));

        if($memberExists = $this->_getMemberRepo()->getGuildMember($guild['guild_id'], $userId)) {
            foreach($memberExists as $member)
            {
                if($memberEntity = \XF::em()->find('Moturdrn\GW2Guilds:Member', ["guild_id" => $guild['guild_id'], "user_id" => $userId])) {
                    $memberEntity->delete();
                }
            }
        }

        if($pendingExists = $this->_getPendingRepo()->getPendingJoinRequestsByGuildId($guild['guild_id']))
        {
            foreach($pendingExists as $pending)
            {
                if($pending['user_id'] == $userId)
                {
                    if($pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', $pending['pending_id'])) {
                        $pendingEntity->delete();
                    }
                }
            }
        }

        foreach($guildOfficers as $key => $guildOfficer)
        {
            if($guildOfficer == $userId)
                unset($guildOfficers[$key]);
        }

        $guildOfficers = implode(",", $guildOfficers);
        $guild->set('guildofficer_userids', $guildOfficers);
        $guild->save();

        $this->_getMemberRepo()->accessAddOrRemove($userId);

        return $this->redirect($this->buildLink('guilds/roster', $guild), 'Guild Member Removed');
    }

    public function actionMembersPromote(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;

        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

        if(!$guild)
            throw $this->exception($this->notFound('Guild does not exist'));

        $userId = $this->filter('user_id', 'int');
        if (!$user = \XF::em()->find('XF:User', $userId))
            throw $this->exception($this->notFound('User does not exist'));

        if(!$member = \XF::em()->find('Moturdrn\GW2Guilds:Member', array($guild['guild_id'], $userId)))
            throw $this->exception($this->notFound('User not member of this Guild'));

        $guildAccessLevel = $this->guildAccessLevel($guild);

        if($guild['guildleader_userid'] == $userId)
            throw $this->exception($this->message('You cannot promote a Guild Leader further', 400));

        $guildOfficers = explode(',', $guild['guildofficer_userids']);

        if(in_array($userId, $guildOfficers))
            throw $this->exception($this->message('You cannot promote an Officer, to do please transfer Guild Leadership', 400));
        else
            $requiredAccessLevel = 30;

        if($guildAccessLevel < $requiredAccessLevel)
            throw $this->exception($this->message('You have insufficient permissions to promote this member.', 400));

        if($member = $this->_getMemberRepo()->getPendingRequestByUserGuild($guild['guild_id'], $userId)) {
            if ($memberEntity = \XF::em()->find('Moturdrn\GW2Guilds:Member', ["guild_id" => $guild['guild_id'], "user_id" => $member['user_id']])) {
                $memberEntity->set('state', 'accepted');
                $memberEntity->save();
            }

            if($pendingRequests = $this->_getPendingRepo()->getPendingJoinRequestsByGuildId($guildId))
            {
                foreach($pendingRequests as $pendingRequest)
                {
                    if($pendingRequest['user_id'] == $userId)
                    {
                        if($pendingEntity = \XF::em()->find('Moturdrn\GW2Guilds:Pending', $pendingRequest['pending_id'])) {
                            $pendingEntity->delete();
                        }
                    }
                }
            }
        }else{
            $guildOfficers[] = $userId;
            $guildOfficers = implode(",", $guildOfficers);

            $guild->set('guildofficer_userids', $guildOfficers);
            $guild->save();
        }

        $this->_getMemberRepo()->accessAddOrRemove($userId);

        return $this->redirect($this->buildLink('guilds/roster', $guild), 'Guild Member Promoted');
    }

    public function actionMembersDemote(ParameterBag $parameterBag)
    {
        $guildId = $parameterBag->guild_id;

        $guild = \XF::em()->find('Moturdrn\GW2Guilds:Guild', $guildId);

        if(!$guild)
            throw $this->exception($this->notFound('Guild does not exist'));

        $userId = $this->filter('user_id', 'int');
        if (!$user = \XF::em()->find('XF:User', $userId))
            throw $this->exception($this->notFound('User does not exist'));

        if(!$member = \XF::em()->find('Moturdrn\GW2Guilds:Member', ["guild_id" => $guild['guild_id'], "user_id" => $userId]))
            throw $this->exception($this->notFound('User not member of this Guild'));

        $guildAccessLevel = $this->guildAccessLevel($guild);

        if($userId == $guild['guildleader_userid'])
            throw $this->exception($this->message('A Guild Leader cannot be demoted. The Guild Leader or an Admin must transfer the Guild', 400));

        $guildOfficers = explode(',', $guild['guildofficer_userids']);

        if(!in_array($userId, $guildOfficers))
            throw $this->exception($this->message('You cannot demote a member to pending, please remove them instead', 400));
        else
            $requiredAccessLevel = 40;

        if($guildAccessLevel < $requiredAccessLevel)
            throw $this->exception($this->message('You have insufficient permissions to demote this officer.', 400));

        foreach($guildOfficers as $key => $guildOfficer)
        {
            if($guildOfficer == $userId)
                unset($guildOfficers[$key]);
        }
        print_r($guildOfficers);

        $guildOfficers = implode(",", $guildOfficers);
        $guild->set('guildofficer_userids', $guildOfficers);
        $guild->save();

        $this->_getMemberRepo()->accessAddOrRemove($userId);

        return $this->redirect($this->buildLink('guilds/roster', $guild), 'Guild Member Demoted');
    }

    public function actionError(ParameterBag $parameterBag)
    {
        throw $this->exception($this->error($parameterBag->error));
    }

    protected function assertGuildValid($guildIdOrName = null)
    {
        /** @var \Moturdrn\GW2Guilds\Repository\Guild $repo */
        $repo = $this->repository('Moturdrn\GW2Guilds:Guild');
        $guild = $repo->getGuildByIdOrName($guildIdOrName);
        if(!$guild)
        {
            return false;
        }
        return true;
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Guild $guilds
     * @return \Moturdrn\GW2Guilds\Entity\Guild
     */
    protected function prepareGuilds(\Moturdrn\GW2Guilds\Entity\Guild $guilds)
    {
        foreach($guilds as &$guild)
        {
            $guild = $this->prepareGuild($guild);
        }
        return $guilds;
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Guild $guild
     * @return \Moturdrn\GW2Guilds\Entity\Guild
     */
    protected function prepareGuild(\Moturdrn\GW2Guilds\Entity\Guild $guild)
    {
        $memberRepo = $this->_getMemberRepo();

        if($guild["guild_website"] != "")
        {
            if(substr($guild["guild_website"],0,7) !== "http://" && substr($guild["guild_website"],0,7) !== "https:/")
            {
                $guild["guild_website"] = "http://" . $guild["guild_website"];
            }
        }

        $guild['canEdit'] = $this->canEditGuild($guild);
        $guild['canJoin'] = $this->canJoinGuild($guild);
        $guild['canLeave'] = $this->canLeaveGuild($guild);
        $guild['canDelete'] = $this->canDeleteGuild($guild);
        $guild['canTransfer'] = $this->canTransferGuild($guild);
        $guild['accessLevel'] = $this->guildAccessLevel($guild);

        $guildMembers = $memberRepo->getGuildMembers($guild['guild_id']);
        if(count($guildMembers) > 0)
            $guild['member_count'] = count($guildMembers);
        else
            $guild['member_count'] = 0;

        $guild['pending_count'] = 0;

        return $guild;
    }

    protected function isGW2GuildsAdmin()
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

        if($visitor->hasPermission('moturdrn_gw2guilds','admin')) {
            return true;
        }

        return false;
    }

    public function guildAccessLevel(\Moturdrn\GW2Guilds\Entity\Guild $guild)
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

        if($this->isGW2GuildsAdmin())
            return 50;

        if($guild['guildleader_userid'] == $visitor['user_id'])
        {
            return 40;
        }

        $guildOfficers = explode(',', $guild['guildofficer_userids']);

        foreach($guildOfficers as $guildOfficer){
            if($guildOfficer == $visitor['user_id'])
                return 30;
        }


        if($this->_getMemberRepo()->getActiveGuildMember($guild['guild_id'], $visitor['user_id']))
            return 20;

        if($this->_getMemberRepo()->getPendingRequestByUserGuild($guild['guild_id'], $visitor['user_id']))
            return 10;

        return 0;
    }

    protected function canCreateGuild()
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

        if(\XF::visitor()->hasPermission('moturdrn_gw2guilds','createguild'))
        {
            return true;
        }

        return false;
    }

    protected function canJoinGuild(\Moturdrn\GW2Guilds\Entity\Guild $guild)
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

        if($this->_getMemberRepo()->getGuildMember($guild['guild_id'], $visitor['user_id']))
            return false;

        if(\XF::visitor()->hasPermission('moturdrn_gw2guilds','joinguild'))
        {
            return true;
        }

        return false;
    }

    protected function canLeaveGuild(\Moturdrn\GW2Guilds\Entity\Guild $guild)
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

        if($guild['guildleader_userid'] == $visitor['user_id'])
        {
            return false;
        }

        if($this->_getMemberRepo()->getGuildMember($guild['guild_id'], $visitor['user_id']))
            return true;

        return false;
    }

    protected function canEditGuild(\Moturdrn\GW2Guilds\Entity\Guild $guild)
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

        if($this->isGW2GuildsAdmin())
            return true;

        if($guild['guildleader_userid'] == $visitor['user_id'])
        {
            return true;
        }

        $guildOfficers = explode(',', $guild['guildofficer_userids']);

        foreach($guildOfficers as $guildOfficer){
            if($guildOfficer == $visitor['user_id'])
                return true;
        }

        return false;
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Guild $guild
     * @return bool
     */
    protected function canDeleteGuild(\Moturdrn\GW2Guilds\Entity\Guild $guild)
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

        if($this->isGW2GuildsAdmin())
            return true;

        if($guild['guildleader_userid'] == $visitor['user_id'])
        {
            return true;
        }

        return false;
    }

    protected function canTransferGuild(\Moturdrn\GW2Guilds\Entity\Guild $guild)
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

        if(in_array($guild['status'], array('Inactive','Pending (New)', 'Pending (Change)', 'Pending')))
        {
            return false;
        }

        if($this->isGW2GuildsAdmin())
            return true;

        if($guild['guildleader_userid'] == $visitor['user_id'])
        {
            return true;
        }

        return false;
    }

    /**
     * @param \Moturdrn\GW2Guilds\Entity\Guild $guild
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function _getGuildAddOrEditResponse(\Moturdrn\GW2Guilds\Entity\Guild $guild = null)
    {

        if($guild && !$guild->getCanEdit())
        {
            throw $this->exception($this->noPermission('You cannot edit this Guild'));
        }
        elseif(!$this->canCreateGuild())
        {
            throw $this->exception($this->noPermission('You cannot create a Guild'));
        }

        $viewParams = array(
            'guild' => $guild
        );

        return $this->view('Moturdrn\GW2Guilds:View', 'moturdrn_gw2guilds_add', $viewParams);
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
        /** @var \Moturdrn\GW2Guilds\Repository\Pending $repo */
        $repo = $this->repository('Moturdrn\GW2Guilds:Pending');
        return $repo;
    }

    protected function _getUserRepo()
    {
        /** @var \XF\Repository\User $repo */
        $repo = $this->repository('XF:User');
        return $repo;
    }

    /**
     * @param int $userId
     * @param array $extraWith
     * @param bool $basicProfileOnly
     *
     * @return \XF\Entity\User
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableUser($userId, array $extraWith = [], $basicProfileOnly = false)
    {
        $extraWith[] = 'Option';
        $extraWith[] = 'Privacy';
        $extraWith[] = 'Profile';
        array_unique($extraWith);

        /** @var \XF\Entity\User $user */
        $user = $this->em()->find('XF:User', $userId, $extraWith);
        if (!$user)
        {
            throw $this->exception($this->notFound(\XF::phrase('requested_user_not_found')));
        }

        $canView = $basicProfileOnly ? $user->canViewBasicProfile($error) : $user->canViewFullProfile($error);
        if (!$canView)
        {
            throw $this->exception($this->noPermission($error));
        }

        return $user;
    }

    /**
     * @param string $recipient
     * @param string $title
     * @param string $message
     *
     * @return \XF\Service\Conversation\Creator
     */
    protected function setupConversationCreate($recipient, $title, $message)
    {

        $conversationLocked = $this->filter('conversation_locked', 'bool');
        $options = $this->filter([
            'open_invite' => 'bool'
        ]);
        $options['conversation_open'] = !$conversationLocked;

        $visitor = \XF::visitor();

        /** @var \XF\Service\Conversation\Creator $creator */
        $creator = $this->service('XF:Conversation\Creator', $visitor);
        $creator->setOptions($options);
        $creator->setRecipients($recipient);
        $creator->setContent($title, $message);

        return $creator;
    }

    protected function finalizeConversationCreate(\XF\Service\Conversation\Creator $creator)
    {
        \XF\Draft::createFromKey('conversation')->delete();
    }
}