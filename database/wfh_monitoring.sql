CREATE DATABASE IF NOT EXISTS wfh_monitoring
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE wfh_monitoring;

CREATE TABLE departments (
    id         INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dept_name (name)
) ENGINE=InnoDB;

CREATE TABLE users (
    id          INT          NOT NULL AUTO_INCREMENT,
    username    VARCHAR(50)  NOT NULL,
    password    VARCHAR(255) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    dept_id     INT          NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    CONSTRAINT fk_users_dept
        FOREIGN KEY (dept_id) REFERENCES departments (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE wfh_activities (
    id              VARCHAR(50)  NOT NULL,
    user_id         INT          NOT NULL,
    dept_id         INT          NOT NULL,
    activity        VARCHAR(255) NOT NULL,
    duration        INT          NOT NULL COMMENT 'Durasi dalam menit',
    activity_type   ENUM('Productive','Meeting','Training','Admin','Learning') NOT NULL DEFAULT 'Productive',
    notes           TEXT         NULL,
    date            DATE         NOT NULL,
    timestamp       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blockchain_tx_id VARCHAR(255) NULL COMMENT 'Transaction ID di Hyperledger Fabric',
    PRIMARY KEY (id),
    CONSTRAINT fk_activities_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_activities_dept
        FOREIGN KEY (dept_id) REFERENCES departments (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_history (
    id             INT          NOT NULL AUTO_INCREMENT,
    activity_id    VARCHAR(50)  NOT NULL,
    tx_id          VARCHAR(255) NOT NULL COMMENT 'Transaction ID blockchain',
    is_delete      TINYINT(1)   NOT NULL DEFAULT 0,
    snapshot_data  JSON         NULL COMMENT 'Snapshot data saat perubahan',
    changed_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_history_activity
        FOREIGN KEY (activity_id) REFERENCES wfh_activities (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO departments (name) VALUES
    ('Engineering'),
    ('Marketing'),
    ('Finance'),
    ('Design'),
    ('HR'),
    ('Operations');

INSERT INTO users (username, password, name, role, dept_id) VALUES
    ('admin',    'admin123', 'Administrator', 'admin', NULL),
    ('employee1',    'user123',  'Employee 1',          'user',  1),
    ('employee2',     'user123',  'Employee 2',           'user',  2),
    ('employee3',  'user123',  'Employee 3',        'user',  1),
    ('employee4',   'user123',  'Employee 4',         'user',  3),
    ('employee5', 'user123',  'Employee 5',       'user',  4);
