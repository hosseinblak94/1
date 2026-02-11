<?php
defined('ABSPATH') || exit;

class WPOA_Installer
{
    public static function install(): void
    {
        self::create_tables();
        self::seed_defaults();
        update_option('wpoa_db_version', WPOA_VERSION);
    }

    public static function uninstall(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'wpoa_';

        $tables = [
            'permissions', 'margin_notes', 'referrals', 'activity_log',
            'notification_queue', 'message_tags', 'tags', 'attachments',
            'recipients', 'message_users', 'messages',
            'user_org', 'org_roles', 'org_units', 'user_profiles', 'settings',
        ];

        foreach ($tables as $t) {
            $wpdb->query("DROP TABLE IF EXISTS {$prefix}{$t}");
        }

        delete_option('wpoa_db_version');
        delete_option('wpoa_version');
        delete_option('wpoa_doc_sequence');
    }

    private static function create_tables(): void
    {
        global $wpdb;

        $prefix  = $wpdb->prefix . 'wpoa_';
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        /* =============================================
         * 1) SETTINGS
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}settings (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(191)    NOT NULL,
            setting_val LONGTEXT        NOT NULL DEFAULT '',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_key (setting_key)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 2) USER PROFILES
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}user_profiles (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id             BIGINT UNSIGNED NOT NULL,
            display_name        VARCHAR(200)    NOT NULL DEFAULT '',
            phone               VARCHAR(20)     NOT NULL DEFAULT '',
            avatar_url          VARCHAR(500)    NOT NULL DEFAULT '',
            signature_text      TEXT            NOT NULL DEFAULT '',
            signature_image_url VARCHAR(500)    NOT NULL DEFAULT '',
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user (user_id)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 3) ORG UNITS (hierarchical)
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}org_units (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(200)    NOT NULL,
            slug        VARCHAR(200)    NOT NULL DEFAULT '',
            parent_id   BIGINT UNSIGNED DEFAULT NULL,
            description TEXT            NOT NULL DEFAULT '',
            sort_order  INT             NOT NULL DEFAULT 0,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_parent (parent_id),
            KEY idx_sort   (sort_order)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 4) ORG ROLES
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}org_roles (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(200)    NOT NULL,
            slug        VARCHAR(200)    NOT NULL DEFAULT '',
            description TEXT            NOT NULL DEFAULT '',
            sort_order  INT             NOT NULL DEFAULT 0,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 5) USER ↔ ORG mapping
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}user_org (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            org_unit_id BIGINT UNSIGNED NOT NULL,
            org_role_id BIGINT UNSIGNED NOT NULL,
            is_primary  TINYINT(1)      NOT NULL DEFAULT 1,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_unit (user_id, org_unit_id),
            KEY idx_unit (org_unit_id),
            KEY idx_role (org_role_id)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 6) MESSAGES
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}messages (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id           BIGINT UNSIGNED DEFAULT NULL,
            thread_id           BIGINT UNSIGNED DEFAULT NULL,
            sender_id           BIGINT UNSIGNED NOT NULL,
            title               VARCHAR(500)    NOT NULL DEFAULT '',
            body                LONGTEXT        NOT NULL DEFAULT '',
            priority            ENUM('low','normal','important','instant') NOT NULL DEFAULT 'normal',
            status              ENUM('draft','sent','deleted') NOT NULL DEFAULT 'draft',
            system_doc_number   VARCHAR(50)     DEFAULT NULL,
            internal_doc_number VARCHAR(100)    DEFAULT NULL,
            signature_type      ENUM('none','text','image','both') NOT NULL DEFAULT 'none',
            signature_text      TEXT            NOT NULL DEFAULT '',
            signature_image_url VARCHAR(500)    NOT NULL DEFAULT '',
            internal_note       TEXT            NOT NULL DEFAULT '',
            sent_at             DATETIME        DEFAULT NULL,
            sent_at_jalali      VARCHAR(30)     DEFAULT NULL,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sender   (sender_id),
            KEY idx_status   (status),
            KEY idx_sent     (sent_at),
            KEY idx_doc      (system_doc_number),
            KEY idx_parent   (parent_id),
            KEY idx_thread   (thread_id),
            KEY idx_priority (priority, status)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 7) MESSAGE ↔ USER (per-user state)
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}message_users (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id  BIGINT UNSIGNED NOT NULL,
            user_id     BIGINT UNSIGNED NOT NULL,
            role        ENUM('sender','to','cc') NOT NULL DEFAULT 'to',
            folder      ENUM('inbox','sent','drafts','archive','trash') NOT NULL DEFAULT 'inbox',
            is_read     TINYINT(1)      NOT NULL DEFAULT 0,
            is_starred  TINYINT(1)      NOT NULL DEFAULT 0,
            is_pinned   TINYINT(1)      NOT NULL DEFAULT 0,
            is_deleted  TINYINT(1)      NOT NULL DEFAULT 0,
            read_at     DATETIME        DEFAULT NULL,
            read_ip     VARCHAR(45)     DEFAULT NULL,
            read_device VARCHAR(200)    DEFAULT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_msg_user (message_id, user_id),
            KEY idx_user_folder    (user_id, folder, is_deleted),
            KEY idx_user_read      (user_id, is_read, is_deleted),
            KEY idx_user_starred   (user_id, is_starred, is_deleted)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 8) RECIPIENTS
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}recipients (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id      BIGINT UNSIGNED NOT NULL,
            user_id         BIGINT UNSIGNED NOT NULL,
            type            ENUM('to','cc') NOT NULL DEFAULT 'to',
            notify_email    TINYINT(1)      NOT NULL DEFAULT 0,
            notify_sms      TINYINT(1)      NOT NULL DEFAULT 0,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_message (message_id),
            KEY idx_user    (user_id)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 9) ATTACHMENTS
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}attachments (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id      BIGINT UNSIGNED NOT NULL,
            user_id         BIGINT UNSIGNED NOT NULL,
            file_name       VARCHAR(500)    NOT NULL,
            file_url        VARCHAR(1000)   NOT NULL,
            file_path       VARCHAR(1000)   NOT NULL DEFAULT '',
            file_size       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            file_type       VARCHAR(100)    NOT NULL DEFAULT '',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_message (message_id)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 10) TAGS
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}tags (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(100)    NOT NULL,
            slug       VARCHAR(100)    NOT NULL DEFAULT '',
            color      VARCHAR(7)      NOT NULL DEFAULT '#0073aa',
            created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 11) MESSAGE ↔ TAGS pivot
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}message_tags (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT UNSIGNED NOT NULL,
            tag_id     BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_msg_tag (message_id, tag_id),
            KEY idx_tag (tag_id)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 12) NOTIFICATION QUEUE
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}notification_queue (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            message_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
            channel     ENUM('email','sms') NOT NULL DEFAULT 'email',
            recipient   VARCHAR(200)    NOT NULL DEFAULT '',
            subject     VARCHAR(500)    NOT NULL DEFAULT '',
            body        TEXT            NOT NULL DEFAULT '',
            status      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_error  TEXT            NOT NULL DEFAULT '',
            scheduled_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at     DATETIME        DEFAULT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status    (status, scheduled_at),
            KEY idx_user      (user_id),
            KEY idx_channel   (channel, status)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 13) ACTIVITY LOG
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}activity_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            action      VARCHAR(100)    NOT NULL,
            object_type VARCHAR(50)     NOT NULL DEFAULT '',
            object_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            details     TEXT            NOT NULL DEFAULT '',
            ip_address  VARCHAR(45)     NOT NULL DEFAULT '',
            user_agent  VARCHAR(500)    NOT NULL DEFAULT '',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user    (user_id),
            KEY idx_action  (action),
            KEY idx_object  (object_type, object_id),
            KEY idx_created (created_at)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 14) REFERRALS
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}referrals (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id      BIGINT UNSIGNED NOT NULL,
            from_user_id    BIGINT UNSIGNED NOT NULL,
            to_user_id      BIGINT UNSIGNED NOT NULL,
            type            ENUM('referral','approval','action','info') NOT NULL DEFAULT 'referral',
            status          ENUM('pending','accepted','completed','rejected','expired') NOT NULL DEFAULT 'pending',
            instruction     TEXT            NOT NULL DEFAULT '',
            response        TEXT            NOT NULL DEFAULT '',
            deadline        DATETIME        DEFAULT NULL,
            deadline_jalali VARCHAR(30)     DEFAULT NULL,
            parent_ref_id   BIGINT UNSIGNED DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            responded_at    DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_message   (message_id),
            KEY idx_from      (from_user_id),
            KEY idx_to        (to_user_id),
            KEY idx_to_status (to_user_id, status),
            KEY idx_parent    (parent_ref_id),
            KEY idx_deadline  (deadline, status)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 15) MARGIN NOTES
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}margin_notes (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id  BIGINT UNSIGNED NOT NULL,
            user_id     BIGINT UNSIGNED NOT NULL,
            referral_id BIGINT UNSIGNED DEFAULT NULL,
            note_text   TEXT            NOT NULL,
            is_private  TINYINT(1)      NOT NULL DEFAULT 0,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_message  (message_id),
            KEY idx_user     (user_id),
            KEY idx_referral (referral_id)
        ) {$charset};";
        dbDelta($sql);

        /* =============================================
         * 16) PERMISSIONS
         * ============================================= */
        $sql = "CREATE TABLE {$prefix}permissions (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_role_id BIGINT UNSIGNED NOT NULL,
            permission  VARCHAR(100)    NOT NULL,
            granted     TINYINT(1)      NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY idx_role_perm (org_role_id, permission),
            KEY idx_role (org_role_id)
        ) {$charset};";
        dbDelta($sql);
    }

    private static function seed_defaults(): void
    {
        $settings = new WPOA_Settings_Model();

        $defaults = [
            'org_name'                    => 'سازمان',
            'messages_per_page'           => '20',
            'email_notifications_enabled' => '1',
            'sms_notifications_enabled'   => '0',
            'sms_api_provider'            => '',
            'sms_api_key'                 => '',
            'sms_sender_number'           => '',
            'max_attachment_size_mb'       => '10',
            'allowed_attachment_types'     => 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt',
        ];

        foreach ($defaults as $key => $val) {
            if ($settings->get($key) === null) {
                $settings->set($key, $val);
            }
        }
    }
}