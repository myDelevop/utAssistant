<?php

namespace UserFrosting;

use \Illuminate\Database\Capsule\Manager as Capsule;
//use Illuminate\Contracts\Redis\Database;

/**
 * Represents the UserFrosting database.
 *
 * Serves as a global static repository for table information, such as table names and columns. Also, provides information about the database.
 * Finally, this class is responsible for initializing the database during installation.
 *
 * @package UserFrosting
 * @author Alex Weissman
 * @see http://www.userfrosting.com/tutorials/lesson-3-data-model/
 */
abstract class Database {
	/**
	 *
	 * @var Slim The Slim app, containing configuration info
	 */
	public static $app;
	
	/**
	 *
	 * @var array[DatabaseTable] An array of DatabaseTable objects representing the configuration of the database tables.
	 */
	protected static $tables;
	
	/**
	 * Retrieve a DatabaseTable object based on its handle.
	 *
	 * @param string $id
	 *        	the handle (id) of the table, as it was defined in the call to `setSchemaTable`.
	 * @throws Exception there is no table associated with the specified handle.
	 * @return DatabaseTable the DatabaseTable registered to this handle.
	 */
	public static function getSchemaTable($id) {
		if (isset ( static::$tables [$id] ))
			return static::$tables [$id];
		else
			throw new \Exception ( "There is no table with id '$id'." );
	}
	
	/**
	 * Register a DatabaseTable object with the Database, assigning it the specified handle.
	 *
	 * @param string $id
	 *        	the handle (id) of the table, which you may choose.
	 * @param DatabaseTable $table
	 *        	the DatabaseTable to associate with this handle.
	 * @return void
	 */
	public static function setSchemaTable($id, $table) {
		static::$tables [$id] = $table;
	}
	
	/**
	 * Set the name for a DatabaseTable that has been registered with the database.
	 *
	 * @param string $id
	 *        	the handle (id) of the table, as it was defined in the call to `setSchemaTable`.
	 * @param string $name
	 *        	the new name for the DatabaseTable.
	 * @throws Exception there is no table associated with the specified handle.
	 * @return void
	 */
	public static function setTableName($id, $name) {
		if (isset ( static::$tables [$id] )) {
			call_user_func_array ( [ 
					static::$tables [$id],
					"setName" 
			], $name );
		} else
			throw new \Exception ( "There is no table with id '$id'." );
	}
	
	/**
	 * Add columns to a DatabaseTable that has been registered with the database.
	 *
	 * @param string $id
	 *        	the handle (id) of the table, as it was defined in the call to `setSchemaTable`.
	 * @param string $column,...
	 *        	the new columns to add to the DatabaseTable.
	 * @throws Exception there is no table associated with the specified handle.
	 * @return void
	 */
	public static function addTableColumns($id) {
		if (isset ( static::$tables [$id] )) {
			$columns = array_slice ( func_get_args (), 1 );
			call_user_func_array ( [ 
					static::$tables [$id],
					"addColumns" 
			], $columns );
		} else
			throw new \Exception ( "There is no table with id '$id'." );
	}
	
	/**
	 * Test whether a DB connection can be established.
	 *
	 * @return bool true if the connection can be established, false otherwise.
	 */
	public static function testConnection() {
		try {
			Capsule::connection ();
		} catch ( \PDOException $e ) {
			error_log ( "Error in " . $e->getFile () . " on line " . $e->getLine () . ": " . $e->getMessage () );
			error_log ( $e->getTraceAsString () );
			return false;
		}
		return true;
	}
	
	/**
	 * Get an array of key-value pairs containing basic information about this database.
	 *
	 * The site settings module expects the following key-value pairs:
	 * db_type, db_version, db_name, table_prefix
	 *
	 * @return array[string] the properties of this database.
	 */
	public static function getInfo() {
		$pdo = Capsule::connection ()->getPdo ();
		$results = [ ];
		try {
			$results ['db_type'] = $pdo->getAttribute ( \PDO::ATTR_DRIVER_NAME );
		} catch ( Exception $e ) {
			$results ['db_type'] = "Unknown";
		}
		try {
			$results ['db_version'] = $pdo->getAttribute ( \PDO::ATTR_SERVER_VERSION );
		} catch ( Exception $e ) {
			$results ['db_type'] = "";
		}
		$results ['db_name'] = static::$app->config ( 'db' ) ['db_name'];
		$results ['table_prefix'] = static::$app->config ( 'db' ) ['db_prefix'];
		return $results;
	}
	
	/**
	 * Get an array of the names of tables that exist in the database.
	 *
	 * Looks for tables with the following handles: user, group, group_user, authorize_group, authorize_user
	 *
	 * @return array[string] the names of the UF tables that actually exist.
	 */
	public static function getCreatedTables() {
		if (! static::testConnection ())
			return [ ];
		
		$connection = Capsule::connection ();
		$results = [ ];
		
		$test_list = [ 
				static::getSchemaTable ( 'user' )->name,
				static::getSchemaTable ( 'user_event' )->name,
				static::getSchemaTable ( 'group' )->name,
				static::getSchemaTable ( 'group_user' )->name,
				static::getSchemaTable ( 'authorize_user' )->name,
				static::getSchemaTable ( 'authorize_group' )->name,
				static::$app->remember_me_table ['tableName'] 
		];
		
		foreach ( $test_list as $table ) {
			try {
				$stmt = $connection->select ( "SELECT 1 FROM `$table` LIMIT 1;" );
			} catch ( \PDOException $e ) {
				continue;
			}
			$results [] = $table;
		}
		
		return $results;
	}
	
	/**
	 * Set up the initial tables for the database.
	 *
	 * Creates all tables, and loads the configuration table with the default config data. Also, sets install_status to `pending`.
	 */
	public static function install() {
		$connection = Capsule::connection ();
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::getSchemaTable ( 'configuration' )->name . "` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `plugin` varchar(50) NOT NULL COMMENT 'The name of the plugin that manages this setting (set to ''userfrosting'' for core settings)',
            `name` varchar(150) NOT NULL COMMENT 'The name of the setting.',
            `value` longtext NOT NULL COMMENT 'The current value of the setting.',
            `description` text NOT NULL COMMENT 'A brief description of this setting.',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='A configuration table, mapping global configuration options to their values.' AUTO_INCREMENT=1 ;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::getSchemaTable ( 'authorize_group' )->name . "` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `group_id` int(10) unsigned NOT NULL,
            `hook` varchar(200) NOT NULL COMMENT 'A code that references a specific action or URI that the group has access to.',
            `conditions` text NOT NULL COMMENT 'The conditions under which members of this group have access to this hook.',
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::getSchemaTable ( 'authorize_user' )->name . "` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(10) unsigned NOT NULL,
            `hook` varchar(200) NOT NULL COMMENT 'A code that references a specific action or URI that the user has access to.',
            `conditions` text NOT NULL COMMENT 'The conditions under which the user has access to this action.',
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::getSchemaTable ( 'group' )->name . "` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(150) NOT NULL,
            `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Specifies whether this permission is a default setting for new accounts.',
            `can_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Specifies whether this permission can be deleted from the control panel.',
            `theme` varchar(100) NOT NULL DEFAULT 'default' COMMENT 'The theme assigned to primary users in this group.',
            `landing_page` varchar(200) NOT NULL DEFAULT 'dashboard' COMMENT 'The page to take primary members to when they first log in.',
            `new_user_title` varchar(200) NOT NULL DEFAULT 'New User' COMMENT 'The default title to assign to new primary users.',
            `icon` varchar(100) NOT NULL DEFAULT 'fa fa-user' COMMENT 'The icon representing primary users in this group.',
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::getSchemaTable ( 'group_user' )->name . "` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(10) unsigned NOT NULL,
            `group_id` int(10) unsigned NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Maps users to their group(s)' AUTO_INCREMENT=1 ;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::getSchemaTable ( 'user' )->name . "` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `user_name` varchar(50) NOT NULL,
            `display_name` varchar(50) NOT NULL,
            `email` varchar(150) NOT NULL,
            `title` varchar(150) NOT NULL,
            `locale` varchar(10) NOT NULL DEFAULT 'en_US' COMMENT 'The language and locale to use for this user.',
            `primary_group_id` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'The id of this user''s primary group.',
            `secret_token` varchar(32) NOT NULL DEFAULT '' COMMENT 'The current one-time use token for various user activities confirmed via email.',
            `flag_verified` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Set to ''1'' if the user has verified their account via email, ''0'' otherwise.',
            `flag_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Set to ''1'' if the user''s account is currently enabled, ''0'' otherwise.  Disabled accounts cannot be logged in to, but they retain all of their data and settings.',
            `flag_password_reset` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Set to ''1'' if the user has an outstanding password reset request, ''0'' otherwise.',
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            `password` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::getSchemaTable ( 'user_event' )->name . "` ( 
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(10) unsigned NOT NULL,
            `event_type` varchar(255) NOT NULL COMMENT 'An identifier used to track the type of event.',
            `occurred_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `description` text NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `" . static::$app->remember_me_table ['tableName'] . "` (
            `user_id` int(11) NOT NULL,
            `token` varchar(40) NOT NULL,
            `persistent_token` varchar(40) NOT NULL,
            `expires` datetime NOT NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		// EXTERNAL TABLLES
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `file_audio` (
        	`id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
       		`task_id` int(11) DEFAULT NULL,
       		`user_id` int(10) UNSIGNED DEFAULT NULL,
       		`path` text
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE IF NOT EXISTS `file_video` (
         `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
         `task_id` int(11) DEFAULT NULL,
         `user_id` int(10) UNSIGNED DEFAULT NULL,
         `path` text
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `q_attrakdiff` (
         `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
         `user_id` int(10) UNSIGNED DEFAULT NULL,
         `studio_id` int(11) DEFAULT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=latin1;" );
		
		$connection->statement ( "
          
         CREATE TABLE `q_sus` (
         `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
         `user_id` int(10) UNSIGNED DEFAULT NULL,
         `studio_id` int(11) DEFAULT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=latin1;" );
		
		$connection->statement ( "CREATE TABLE `smt2_browsers` (
         `id` tinyint(3) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT, 
         `name` varchar(128) NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `smt2_cache` (
		`id` bigint(20) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `file` varchar(255) NOT NULL,
        `url` text NOT NULL,
        `layout` enum('left','center','right','liquid') NOT NULL DEFAULT 'liquid',
        `title` varchar(255) NOT NULL,
        `saved` datetime NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `smt2_cms` (
        `id` tinyint(3) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `type` tinyint(4) NOT NULL,
        `name` varchar(100) NOT NULL,
        `value` varchar(255) NOT NULL,
        `description` text NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `smt2_domains` (
        `id` smallint(5) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `domain` varchar(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `smt2_exts` (
        `id` tinyint(3) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `dir` varchar(20) NOT NULL,
        `priority` tinyint(3) UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `smt2_hypernotes` (
        `id` int(10) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `record_id` bigint(20) UNSIGNED NOT NULL,
        `cuepoint` char(5) NOT NULL,
        `user_id` int(10) UNSIGNED NOT NULL,
        `hypernote` mediumtext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `smt2_jsopt` (
        `id` tinyint(3) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `type` tinyint(4) NOT NULL,
        `name` varchar(100) NOT NULL,
        `value` varchar(255) NOT NULL,
        `description` text NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `smt2_os` (
		`id` tinyint(3) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
		`name` varchar(20) NOT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
				
		$connection->statement ( "CREATE TABLE `smt2_records` (
        `id` bigint(20) UNSIGNED  PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `client_id` varchar(20) NOT NULL,
        `cache_id` bigint(20) UNSIGNED NOT NULL,
        `domain_id` smallint(5) UNSIGNED NOT NULL,
         `os_id` tinyint(3) UNSIGNED NOT NULL,
         `browser_id` tinyint(3) UNSIGNED NOT NULL,
         `browser_ver` float(3,1) UNSIGNED NOT NULL,
         `user_agent` varchar(255) NOT NULL,
         `ftu` tinyint(1) NOT NULL,
         `ip` varchar(15) NOT NULL,
         `scr_width` smallint(5) UNSIGNED NOT NULL,
         `scr_height` smallint(5) UNSIGNED NOT NULL,
         `vp_width` smallint(5) UNSIGNED NOT NULL,
         `vp_height` smallint(5) UNSIGNED NOT NULL,
         `sess_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
         `sess_time` float(7,2) UNSIGNED NOT NULL,
         `fps` tinyint(3) UNSIGNED NOT NULL,
         `coords_x` mediumtext NOT NULL,
         `coords_y` mediumtext NOT NULL,
         `clicks` mediumtext NOT NULL,
         `hovered` longtext NOT NULL,
         `clicked` longtext NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `staff_event` (
        `id` int(10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `date` datetime NOT NULL,
        `location` text,
        `description` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;" );
		
		$connection->statement ( "CREATE TABLE `staff_event_user` (
        `id` int(10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `user_id` int(10) UNSIGNED NOT NULL,
        `staff_event_id` int(10) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `utAss_as_record_user_task` (
        `id` int(10) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `user_id` int(10) UNSIGNED NOT NULL,
        `task_id` int(11) NOT NULL,
        `record_id` bigint(20) UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		$connection->statement ( "CREATE TABLE `utAss_as_studio_user` (
        `id` int(10) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        `studio_id` int(11) NOT NULL,
        `user_id` int(10) UNSIGNED NOT NULL,
        `flag_completato` tinyint(1) DEFAULT NULL,
        `flag_valutato` tinyint(1) DEFAULT NULL,
        `data_completato` datetime DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;" );
		
		
		
		$connection->insert ( "INSERT INTO `utAss_as_studio_user` (`id`, `studio_id`, `user_id`, `flag_completato`, `flag_valutato`, `data_completato`) VALUES
		(64, 164, 13, 0, 0, NULL);" );
		
		
		$connection->statement ("CREATE TABLE `utAss_studio` (
		`id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
		`obiettivo` longtext,
		`istruzioni` longtext,
		`commenti` longtext NOT NULL,
		`url` text,
		`user_id` int(10) UNSIGNED DEFAULT NULL,
		`registra_audio` tinyint(1) NOT NULL DEFAULT '0',
		`registra_video` tinyint(1) NOT NULL DEFAULT '0',
		`registra_comportamento` tinyint(1) NOT NULL DEFAULT '0',
		`somministra_sus` tinyint(1) NOT NULL DEFAULT '0',
		`somministra_attrakdiff` tinyint(1) NOT NULL DEFAULT '0',
		`flag_completato` tinyint(1) NOT NULL DEFAULT '0'
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
		
		$connection->insert("INSERT INTO `utAss_studio` (`id`, `obiettivo`, `istruzioni`, `commenti`, `url`, `user_id`, `registra_audio`, `registra_video`, `registra_comportamento`, `somministra_sus`, `somministra_attrakdiff`, `flag_completato`) VALUES
		(164, 'studio completato', '', 'lo studio è stato completato con successo', 'http://studiocompletato.org', 12, 0, 0, 0, 0, 0, 1);");
		
		$connection->statement("CREATE TABLE `utAss_task` (
		`id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
		`titolo` text,
		`descrizione` text,
		`durataMax_ss` int(11) DEFAULT NULL,
		`url` text,
		`studio_id` int(11) NOT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
				
		$connection->insert("INSERT INTO `utAss_task` (`id`, `titolo`, `descrizione`, `durataMax_ss`, `url`, `studio_id`) VALUES
		(30, 'completatoT1', '', 111, 'http://task1completato.org', 164),
		(31, 'ultimo task', '', 2, 'http://TaskUltimoCompletato.org', 164);");
		
		
		// EXTERNAL TABLES

		
		// Setup initial configuration settings
		static::$app->site->install_status = "new";
		static::$app->site->root_account_config_token = "";
		static::$app->site->store ();
		
		// Setup default groups. TODO: finish Group API so they can be created through objects
		$connection->insert ( "INSERT INTO `" . static::getSchemaTable ( 'group' )->name . "` (`id`,`name`, `is_default`, `can_delete`, `theme`, `landing_page`, `new_user_title`, `icon`) VALUES
          (1, 'User', " . GROUP_DEFAULT_PRIMARY . ", 0, 'default', 'utente', 'New User', 'fa fa-user'),
          (2, 'Administrator', " . GROUP_NOT_DEFAULT . ", 0, 'nyx', 'dashboard', 'Brood Spawn', 'fa fa-flag'),
          (4, 'Valutatore', 0, 1, 'default', 'valutatore', 'Nuovo Valutatore', 'fa fa-flag');" );
		
		// Setup default authorizations
		$connection->insert ( "INSERT INTO `" . static::getSchemaTable ( 'authorize_group' )->name . "` (`group_id`, `hook`, `conditions`) VALUES
          (1, 'uri_dashboard', 'always()'),
          (2, 'uri_dashboard', 'always()'),
          (2, 'uri_users', 'always()'),
          (1, 'uri_account_settings', 'always()'),
          (1, 'update_account_setting', 'equals(self.id, user.id)&&in(property,[\"email\",\"locale\",\"password\"])'),
          (2, 'update_account_setting', '!in_group(user.id,2)&&in(property,[\"email\",\"display_name\",\"title\",\"locale\",\"flag_password_reset\",\"flag_enabled\"])'),
          (2, 'view_account_setting', 'in(property,[\"user_name\",\"email\",\"display_name\",\"title\",\"locale\",\"flag_enabled\",\"groups\",\"primary_group_id\"])'),
          (2, 'delete_account', '!in_group(user.id,2)'),
          (2, 'create_account', 'always()'),
          (4, 'create_account', 'always()'),
          (4, 'uri_analist', 'always()'),
          (4, 'uri_group_titles', 'always()'),
          (1, 'uri_utente', 'always()'),
          (4, 'delete_account', '!in_group(user.id,2)'),
          (2, 'update_account_setting', 'always()'),
          (4, 'view_account_setting', 'always()'),
          (4, 'uri_account_settings', 'always()'),
          (4, 'uri_dashboard', 'always()'),
          (4, 'uri_account_setting', 'always()'),
          (4, 'update_account_setting', 'always()'),
          (4, 'uri_users', 'always()');" );
		
		$connection->insert ( "INSERT INTO `" . static::getSchemaTable ( 'user' )->name . "` (`id`, `user_name`, `display_name`, `email`, `title`, `locale`, `primary_group_id`, `secret_token`, `flag_verified`, `flag_enabled`, `flag_password_reset`, `created_at`, `updated_at`, `password`) VALUES
         (1, 'Admin', 'admin', 'admin@admin.ad', 'New User', 'en_US', 2, '', 1, 1, 0, '2015-12-03 06:58:03', '2016-01-31 08:14:59', '$2y$10\$tXm3CI8KlmPhA8j4o9KstOo9xuuZg8/AyUqPaV.3WnOi6KpegXZ6.'),
         (12, 'valutatore', 'valutatore', 'valutatore@email.com', 'New User', 'it_IT', 4, '8a39512323920e6ab0fbd13784477a9e', 1, 1, 1, '2016-02-12 15:50:13', '2016-02-12 15:51:25', '$2y$10\$rD4pPGitbbNY8Sv/T9osiee5FKaCgtfCkoV084cojl9Pwq0ptjbpu'),
         (13, 'utente', 'utente', 'utente@mail.com', 'New User', 'it_IT', 1, 'a9554af2970528d61a973fa1f96720c1', 1, 1, 1, '2016-02-12 15:58:24', '2016-02-12 15:58:46', '$2y$10\$Lj6BR7NHwabYhcYFsmH77uTAXjeMZg5dEbV.HLzQPQJ42jgGL0zTa');" );
		
		$connection->insert ( "INSERT INTO `" . static::getSchemaTable ( 'group_user' )->name . "` (`id`,`user_id`, `group_id`) VALUES
		(9, 1, 2),
		(15, 12, 4),
		(16, 13, 1);" );
		
		$connection->statement("ALTER TABLE `file_audio`
		ADD CONSTRAINT `file_audio_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `utAss_task` (`id`),
		ADD CONSTRAINT `file_audio_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`);");
		
		$connection->statement("ALTER TABLE `file_video`
		ADD CONSTRAINT `file_video_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `utAss_task` (`id`),
		ADD CONSTRAINT `file_video_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`);");
		
		$connection->statement("ALTER TABLE `q_attrakdiff`
		ADD CONSTRAINT `q_attrakdiff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`),
		ADD CONSTRAINT `q_attrakdiff_ibfk_2` FOREIGN KEY (`studio_id`) REFERENCES `utAss_studio` (`id`);");
		
		$connection->statement("ALTER TABLE `q_sus`
		ADD CONSTRAINT `q_sus_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`),
		ADD CONSTRAINT `q_sus_ibfk_2` FOREIGN KEY (`studio_id`) REFERENCES `utAss_studio` (`id`);");
		
		$connection->statement("ALTER TABLE `smt2_hypernotes`
		ADD CONSTRAINT `smt2_hypernotes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`),
		ADD CONSTRAINT `smt2_hypernotes_ibfk_2` FOREIGN KEY (`record_id`) REFERENCES `smt2_records` (`id`);");
		
		$connection->statement("ALTER TABLE `smt2_records`
		ADD CONSTRAINT `smt2_records_ibfk_1` FOREIGN KEY (`cache_id`) REFERENCES `smt2_cache` (`id`),
		ADD CONSTRAINT `smt2_records_ibfk_2` FOREIGN KEY (`domain_id`) REFERENCES `smt2_domains` (`id`),
		ADD CONSTRAINT `smt2_records_ibfk_3` FOREIGN KEY (`os_id`) REFERENCES `smt2_os` (`id`),
		ADD CONSTRAINT `smt2_records_ibfk_4` FOREIGN KEY (`browser_id`) REFERENCES `smt2_browsers` (`id`);");
		
		$connection->statement("ALTER TABLE `utAss_as_record_user_task`
		ADD CONSTRAINT `utAss_as_record_user_task_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`),
		ADD CONSTRAINT `utAss_as_record_user_task_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `utAss_task` (`id`),
		ADD CONSTRAINT `utAss_as_record_user_task_ibfk_3` FOREIGN KEY (`record_id`) REFERENCES `smt2_records` (`id`);");
		
		$connection->statement("ALTER TABLE `utAss_as_studio_user`
		ADD CONSTRAINT `utAss_as_studio_user_ibfk_1` FOREIGN KEY (`studio_id`) REFERENCES `utAss_studio` (`id`),
		ADD CONSTRAINT `utAss_as_studio_user_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`);");
		
		$connection->statement("ALTER TABLE `utAss_studio`
		ADD CONSTRAINT `utAss_studio_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`);");
		
		$connection->statement("ALTER TABLE `utAss_task`
		ADD CONSTRAINT `utAss_task_ibfk_1` FOREIGN KEY (`studio_id`) REFERENCES `utAss_studio` (`id`);");
		
		
		$connection->statement("ALTER TABLE `uf_authorize_group`
		ADD CONSTRAINT `uf_authorize_group_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `uf_group` (`id`);");
		
		$connection->statement("ALTER TABLE `uf_authorize_user`
		ADD CONSTRAINT `uf_authorize_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`);");
		
		$connection->statement("ALTER TABLE `uf_group_user`
		ADD CONSTRAINT `uf_group_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`),
		ADD CONSTRAINT `uf_group_user_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `uf_group` (`id`);");
		
		$connection->statement("ALTER TABLE `uf_user_event`
		ADD CONSTRAINT `uf_user_event_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `uf_user` (`id`);");
		
	}
}
