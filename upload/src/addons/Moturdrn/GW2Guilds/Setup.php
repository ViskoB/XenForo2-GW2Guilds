<?php

namespace Moturdrn\GW2Guilds;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{

    use \XF\AddOn\StepRunnerInstallTrait;
    use \XF\AddOn\StepRunnerUpgradeTrait;
    use \XF\AddOn\StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->schemaManager()->createTable('xf_moturdrn_gw2guilds_guild', function (Create $table) {
            $table->addColumn('guild_id', 'int')->autoIncrement();
            $table->addColumn('guild_name', 'varchar', 50);
            $table->addColumn('guild_tag', 'varchar', 4);
            $table->addColumn('founded', 'int')->setDefault(0);
            $table->addColumn('guild_website', 'varchar', 500);
            $table->addColumn('members', 'varchar', 20);
            $table->addColumn('guildleader_userid', 'int');
            $table->addColumn('guildofficer_userids', 'char', 250);
            $table->addColumn('guild_recruitment', 'longtext');
            $table->addColumn('WvW', 'varchar', 1)->setDefault('N');
            $table->addColumn('PvE', 'varchar', 1)->setDefault('N');
            $table->addColumn('PvP', 'varchar', 1)->setDefault('N');
            $table->addColumn('Casual', 'varchar', 1)->setDefault('N');
            $table->addColumn('status', 'varchar', 20)->setDefault('Pending (New)');
            $table->addColumn('last_modified', 'int');
            $table->addUniqueKey('guild_name');
        });

        $this->schemaManager()->createTable('xf_moturdrn_gw2guilds_member', function (Create $table) {
            $table->addColumn('guild_id', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('username', 'varchar', 50);
            $table->addColumn('state', 'varbinary', 25);
            $table->addColumn('join_date', 'int');
            $table->addPrimaryKey(array('guild_id','user_id'));
        });

        $this->schemaManager()->createTable('xf_moturdrn_gw2guilds_pending', function (Create $table) {
            $table->addColumn('pending_id', 'int')->autoIncrement();
            $table->addColumn('guild_id', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('pending_type', 'varchar', 50);
        });
    }

    public function uninstallStep1()
    {
        $this->schemaManager()->dropTable('xf_moturdrn_gw2guilds_guild');
        $this->schemaManager()->dropTable('xf_moturdrn_gw2guilds_member');
        $this->schemaManager()->dropTable('xf_moturdrn_gw2guilds_pending');
    }

    public function upgrade2000070Step1()
    {
        if ($this->schemaManager()->getTableStatus('xf_moturdrn_gw2guilds_guilds')) {
            $this->schemaManager()->renameTable('xf_moturdrn_gw2guilds_guilds', 'xf_moturdrn_gw2guilds_guild');
            $this->schemaManager()->alterTable('xf_moturdrn_gw2guilds_guild', function (Alter $table) {
                $table->renameColumn('guildid', 'guild_id');
                $table->renameColumn('guildname', 'guild_name');
                $table->renameColumn('guildtag', 'guild_tag');
                $table->renameColumn('guildwebsite', 'guild_website');
                $table->renameColumn('guildrecruitment', 'guild_recruitment');
                $table->dropColumns(array('user_group_id'));
            });
        }

        if ($this->schemaManager()->getTableStatus('xf_moturdrn_gw2guilds_members')) {
            $this->schemaManager()->renameTable('xf_moturdrn_gw2guilds_members', 'xf_moturdrn_gw2guilds_member');
            $this->schemaManager()->alterTable('xf_moturdrn_gw2guilds_member', function (Alter $table) {
                $table->renameColumn('guildid', 'guild_id');
                $table->addColumn('username', 'varchar', 50);
            });
        }

        if ($this->schemaManager()->getTableStatus('xf_moturdrn_gw2guilds_members')) {
            $this->schemaManager()->alterTable('xf_moturdrn_gw2guilds_pending', function (Alter $table) {
                $table->renameColumn('guildid', 'guild_id');
                $table->dropColumns(array("username"));
            });
        }
    }
}