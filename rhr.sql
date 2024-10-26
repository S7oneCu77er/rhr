SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `users` (
                        `user_guid`            int(16)         NOT NULL         AUTO_INCREMENT PRIMARY KEY,
                        `first_name`           varchar(99)     NOT NULL,
                        `last_name`            varchar(99)     NOT NULL,
                        `passport_id`          varchar(30)     NOT NULL,
                        `password`             varchar(30)     NOT NULL         DEFAULT '123456789',
                        `email`                varchar(50)     DEFAULT          '',
                        `phone_number`         varchar(20)     NOT NULL         DEFAULT '0',
                        `country`              varchar(30)     NOT NULL         DEFAULT 'Israel',
                        `description`          varchar(99)     NOT NULL         DEFAULT '',
                        `group`                enum('disabled','workers','drivers','site_managers','admins') NOT NULL DEFAULT 'workers'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `users`
                        ADD UNIQUE KEY         `user_guid`     (`user_guid`),
                        ADD UNIQUE KEY         `passport_id`   (`passport_id`);
INSERT INTO `users` VALUES
                        (0, '', '', '0', '0', '0', '0', '0', '', 'disabled');
UPDATE `users` SET user_guid = 0;




CREATE TABLE `sites` (
                        `site_guid`            int(16)          NOT NULL        AUTO_INCREMENT PRIMARY KEY,
                        `site_name`            varchar(99)      NOT NULL,
                        `site_address`         varchar(99)      NOT NULL,
                        `phone_number`         varchar(20)      NULL,
                        `shiftStart_time`      TIME             NOT NULL        DEFAULT '07:00:00',
                        `shiftEnd_time`        TIME             NOT NULL        DEFAULT '18:00:00',
                        `site_owner_guid`      int(16)          NOT NULL        DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `sites`
                        ADD UNIQUE KEY         `site_name`     (`site_name`),
                        ADD UNIQUE KEY         `site_guid`     (`site_guid`);
INSERT INTO `sites` VALUES
                        (0, '', '', '0', '00:00:00', '00:00:00', 0);
UPDATE `sites` SET site_guid = 0, site_owner_guid = 0;




CREATE TABLE `cars` (
                        `car_guid`             int(16)          NOT NULL        AUTO_INCREMENT PRIMARY KEY,
                        `car_model`            varchar(30)      NOT NULL        DEFAULT 'Unknown',
                        `car_model_year`       int(8)           NOT NULL        DEFAULT 2000,
                        `car_number_plate`     varchar(12)      NOT NULL        DEFAULT '00-000-00',
                        `max_passengers`       int(8)           NOT NULL        DEFAULT 7,
                        `insurance_end`        datetime         NOT NULL        DEFAULT NOW(),
                        `license_end`          datetime         NOT NULL        DEFAULT NOW(),
                        `driver_guid`          int(16)          NOT NULL        DEFAULT 0,
                        `assignment_guid`      int(16)          NOT NULL        DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `cars` VALUES
                        (0, '', 0, '0', 0, NOW(), NOW(), 0, 0);
UPDATE `cars` SET car_guid = 0, assignment_guid = 0;




CREATE TABLE `houses` (
                        `house_guid`           int(16)         NOT NULL         AUTO_INCREMENT PRIMARY KEY,
                        `house_address`        varchar(99)     NOT NULL,
                        `address_description`  varchar(255)    NULL,
                        `house_size_sqm`       int(8)          NULL,
                        `number_of_rooms`      int(8)          NULL,
                        `number_of_toilets`    int(8)          NULL,
                        `contract_number`      varchar(50)     NOT NULL,
                        `contract_start`       DATE            NOT NULL,
                        `contract_end`         DATE            NOT NULL,
                        `security_deed`        varchar(50)     NOT NULL,
                        `monthly_rent`         int(8)          NOT NULL,
                        `monthly_arnona`       int(8)          NOT NULL,
                        `monthly_water`        int(8)          NULL,
                        `monthly_electric`     int(8)          NULL,
                        `monthly_gas`          int(8)          NOT NULL,
                        `monthly_vaad`         int(8)          NOT NULL,
                        `landlord_name`        varchar(99)     NOT NULL,
                        `landlord_id`          varchar(16)     NOT NULL,
                        `landlord_phone`       varchar(20)     NOT NULL,
                        `landlord_email`       varchar(50)     NOT NULL,
                        `vaad_name`            varchar(99)     NOT NULL,
                        `vaad_phone`           varchar(20)     NOT NULL,
                        `max_tenants`          int(4)          NOT NULL         DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `houses`
                        ADD UNIQUE             (house_guid);
ALTER TABLE `houses`
                        ADD UNIQUE             (house_address);
INSERT INTO `houses`    VALUES
                        (0,'No Assigned House','Default house location','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0','0' );
UPDATE `houses` SET house_guid = 0;




CREATE TABLE `site_managers` (
                        `user_guid`            int(16)         NOT NULL        DEFAULT 0,
                        `site_guid`            int(16)         NOT NULL        DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `site_managers`
                        ADD UNIQUE KEY         `site_user_guid`     (`user_guid`, `site_guid`);




CREATE TABLE `documents` (
                        `doc_guid`             int(16)         NOT NULL         AUTO_INCREMENT PRIMARY KEY,
                        `uploaded_by`          int(16)         DEFAULT NULL,
                        `uploaded_for`         int(16)         DEFAULT NULL,
                        `document_name`        varchar(99)     DEFAULT NULL,
                        `document_type`        varchar(50)     DEFAULT NULL,
                        `document_file`        LONGBLOB        DEFAULT NULL,
                        `date_uploaded`        DATETIME        DEFAULT          current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `shifts` (
                        `shift_guid`           int(16)         NOT NULL         AUTO_INCREMENT PRIMARY KEY,
                        `user_guid`            int(16)         NOT NULL         DEFAULT 0,
                        `site_guid`            int(16)         NOT NULL         DEFAULT 0,
                        `shift_start`          DATETIME        NOT NULL         DEFAULT current_timestamp(),
                        `shift_end`            DATETIME        DEFAULT          NULL,
                        `total_time`           TIME            GENERATED        ALWAYS AS (timediff(`shift_end`,`shift_start`)) STORED,
                        `status`               enum('approved','pending')       NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `shifts` VALUES
    (0, 0, 0, current_timestamp(), current_timestamp(), NULL, 'approved');
UPDATE `shifts` SET shift_guid = 0, user_guid = 0;




CREATE TABLE `workers` (
                        `user_guid`            int(16)         NOT NULL         PRIMARY KEY,
                        `andromeda_guid`       int(16)         NOT NULL         DEFAULT 0,
                        `worker_id`            int(16)         NOT NULL,
                        `profession`           varchar(50)     NOT NULL,
                        `hourly_rate`          decimal(10,2)   NOT NULL,
                        `account`              int(16)         NOT NULL,
                        `foreign_phone`        varchar(20)     DEFAULT          '0',
                        `height_training`      BOOL            NOT NULL         DEFAULT FALSE,
                        `house_guid`           int(16)         NOT NULL         DEFAULT 0,
                        `health_insurance`     DATE            DEFAULT          CURDATE(),
                        `drivers_license`      DATE            NULL             DEFAULT NULL,
                        `description`          varchar(99)     NOT NULL         DEFAULT '',
                        `on_relief`            BOOL            NOT NULL         DEFAULT FALSE,
                        `relief_end_date`      DATE            NULL             DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `workers`
                        ADD UNIQUE KEY         `user_guid`     (`user_guid`),
                        ADD UNIQUE KEY         `worker_id`     (`worker_id`);




CREATE TABLE `worker_languages` (
                        `user_guid`            int(16)         NOT NULL         DEFAULT 0,
                        `language`             varchar(50)     NOT NULL         DEFAULT  'English',
                        PRIMARY KEY            (`user_guid`, `language`),
                        FOREIGN KEY            (`user_guid`) REFERENCES `users`(`user_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `worker_professions` (
                        `profession_guid`      int(16)         NOT NULL         AUTO_INCREMENT PRIMARY KEY,
                        `profession`           varchar(50)     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `support` (
                        `support_guid`         int(16)         NOT NULL         AUTO_INCREMENT PRIMARY KEY,
                        `user_guid`            int(16)         NOT NULL         DEFAULT 0,
                        `site_guid`            int(16)         NOT NULL         DEFAULT 0,
                        `shift_guid`           int(16)         NOT NULL         DEFAULT 0,
                        `assignment_guid`      int(16)         NOT NULL         DEFAULT 0,
                        `car_guid`             int(16)         NOT NULL         DEFAULT 0,
                        `support_type`         varchar(30)     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `support`
                        ADD UNIQUE KEY         `support_guid`     (`support_guid`);




CREATE TABLE shift_assignments (
                        `assignment_guid`      int(16)         AUTO_INCREMENT   PRIMARY KEY,
                        `site_guid`            int(16)         NOT NULL         DEFAULT 0,
                        `workers`              varchar(99)     NOT NULL         DEFAULT '1',
                        `description`          varchar(99)     NOT NULL         DEFAULT '',
                        `shift_start_date`     DATE            NOT NULL         DEFAULT CURDATE(),
                        `shift_end_date`       DATE            NOT NULL         DEFAULT CURDATE(),
                        `shift_created_date`   DATETIME        NOT NULL         DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `shift_assignments` VALUES
                        (0, 0, 0, 'No assignment', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), NOW());
UPDATE `shift_assignments` SET assignment_guid = 0, site_guid = 0;




CREATE TABLE shift_assignment_workers (
                        `assignment_guid`      int(16)         NOT NULL,
                        `user_guid`            int(16)         NOT NULL         DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE shift_assignment_workers
                        ADD UNIQUE KEY         `assign_user`   (`assignment_guid`, `user_guid`);




CREATE TABLE `action_logs` (
                         `user_guid`            int(16)         NOT NULL         PRIMARY KEY,
                         `date`                 DATETIME        DEFAULT          current_timestamp(),
                         `action`               varchar(99)     NOT NULL         DEFAULT '',
                         `target_guid`          int(16)         NOT NULL         DEFAULT 0,
                         `details`              varchar(255)    NOT NULL         DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




ALTER TABLE `site_managers`
                ADD CONSTRAINT      `user_guid_1`                    FOREIGN KEY (`user_guid`)                   REFERENCES `users` (`user_guid`);
ALTER TABLE `site_managers`
                ADD CONSTRAINT      `site_guid_1`                    FOREIGN KEY (`site_guid`)                   REFERENCES `sites` (`site_guid`);

ALTER TABLE `sites`
                ADD CONSTRAINT      `site_owner_guid_1`              FOREIGN KEY (`site_owner_guid`)             REFERENCES `users` (`user_guid`);

ALTER TABLE `documents`
                ADD CONSTRAINT      `uploaded_by_1`                  FOREIGN KEY (`uploaded_by`)                 REFERENCES `users` (`user_guid`);
ALTER TABLE `documents`
                ADD CONSTRAINT      `uploaded_for_1`                 FOREIGN KEY (`uploaded_for`)                REFERENCES `users` (`user_guid`);

ALTER TABLE `shifts`
                ADD CONSTRAINT      `user_guid_2`                    FOREIGN KEY (`user_guid`)                   REFERENCES `users` (`user_guid`);
ALTER TABLE `shifts`
                ADD CONSTRAINT      `site_guid_2`                    FOREIGN KEY (`site_guid`)                   REFERENCES `sites` (`site_guid`);

ALTER TABLE `workers`
                ADD CONSTRAINT      `user_guid_3`                    FOREIGN KEY (`user_guid`)                   REFERENCES `users` (`user_guid`);
ALTER TABLE `workers`
                ADD CONSTRAINT      `house_guid_1`                   FOREIGN KEY (`house_guid`)                  REFERENCES `houses` (`house_guid`);

ALTER TABLE `shift_assignments`
                ADD CONSTRAINT      `site_guid_3`                    FOREIGN KEY (`site_guid`)                   REFERENCES `sites` (`site_guid`);

ALTER TABLE `shift_assignment_workers`
                ADD CONSTRAINT      `assignment_guid_1`              FOREIGN KEY (`assignment_guid`)             REFERENCES `shift_assignments` (`assignment_guid`);
ALTER TABLE `shift_assignment_workers`
                ADD CONSTRAINT      `user_guid_5`                    FOREIGN KEY (`user_guid`)                   REFERENCES `users` (`user_guid`);

ALTER TABLE `support`
                ADD CONSTRAINT      `assignment_guid_2`              FOREIGN KEY (`assignment_guid`)             REFERENCES `shift_assignments` (`assignment_guid`);
ALTER TABLE `support`
                ADD CONSTRAINT      `user_guid_6`                    FOREIGN KEY (`user_guid`)                   REFERENCES `users` (`user_guid`);
ALTER TABLE `support`
                ADD CONSTRAINT      `site_guid_4`                    FOREIGN KEY (`site_guid`)                   REFERENCES `sites` (`site_guid`);
ALTER TABLE `support`
                ADD CONSTRAINT      `car_guid_2`                     FOREIGN KEY (`car_guid`)                    REFERENCES `cars` (`car_guid`);
ALTER TABLE `support`
                ADD CONSTRAINT      `shift_guid_1`                   FOREIGN KEY (`shift_guid`)                  REFERENCES `shifts` (`shift_guid`);

ALTER TABLE `cars`
                ADD CONSTRAINT      `assignment_guid_3`              FOREIGN KEY (`assignment_guid`)             REFERENCES `shift_assignments` (`assignment_guid`);

ALTER TABLE `worker_languages`
                ADD CONSTRAINT      `user_guid_7`                    FOREIGN KEY (`user_guid`)                   REFERENCES `users` (`user_guid`);

ALTER TABLE `action_logs`
                ADD CONSTRAINT      `user_guid_8`                    FOREIGN KEY (`user_guid`)                   REFERENCES `users` (`user_guid`);

INSERT INTO `users` (first_name, last_name, passport_id, password, email, phone_number, country, description, `group`) VALUES
    ('Matan-el', 'Yamin', '203022215', '1234', 'stone.gaming@gmail.com', '0502695333', 'Israel', 'Web Administrator', 'admins');
INSERT INTO `sites` (site_name, site_address, phone_number, shiftStart_time, shiftEnd_time, site_owner_guid)  VALUES
    ('Stone PHP', 'דב שילנסקי 4 נתניה', '0502695333', '06:00:00', '02:00:00', 1);
INSERT INTO `shift_assignments` (site_guid, workers, description, shift_start_date, shift_end_date, shift_created_date) VALUES
    (1, '["1"]', '["Programming"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), NOW());
INSERT INTO `houses` (house_address, address_description, house_size_sqm, number_of_rooms, number_of_toilets, contract_number, contract_start, contract_end, security_deed, monthly_rent, monthly_arnona, monthly_water, monthly_electric, monthly_gas, monthly_vaad, landlord_name, landlord_id, landlord_phone, landlord_email, vaad_name, vaad_phone, max_tenants)    VALUES
    ('דב שילנסקי 4 נתניה','בניין לוטוס קריית נורדאו','210','6','3','0','	2022-04-01','2028-04-02','0','8000','650','250','1800','0','450','Moshe','123123123','0511111111','000@000.000','Yossi','0522222222','4' );
INSERT INTO `workers` (user_guid, andromeda_guid, worker_id, profession, hourly_rate, account, foreign_phone, height_training, house_guid, health_insurance, on_relief, relief_end_date) VALUES
    (1, 0, 0, 'Webmaster',45,18391992,'0523277976',false,1,DATE_ADD(CURDATE(), INTERVAL 1 YEAR),FALSE,'');
INSERT INTO `shift_assignment_workers` (assignment_guid, user_guid) VALUES
    (1,1);
INSERT INTO `cars` (car_model, car_model_year, car_number_plate, max_passengers, insurance_end, license_end, driver_guid, assignment_guid) VALUES
    ('Skoda Fabia', 2012, '82-895-76', 5, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 0, 0);