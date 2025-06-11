-- Create Database
create database if not exists noteapp character set utf8mb4 collate utf8mb4_general_ci;
use noteapp;


-- Table: users

create table users (
  id int auto_increment primary key,
  email varchar(255) not null unique,
  password varchar(255) not null,
  display_name varchar(100) not null,
  is_verified tinyint(1) not null default 0,
  activation_token varchar(255) not null
);


-- Table: labels

create table labels (
  id int auto_increment primary key,
  name varchar(100) not null,
  user_id int not null,
  created_at timestamp default current_timestamp,
  foreign key (user_id) references users(id) on delete cascade
);


-- Table: notes

create table notes (
  id int auto_increment primary key,
  user_id int not null,
  title varchar(255) not null,
  content text,
  image varchar(255),
  is_pinned tinyint(1) default 0,
  password varchar(255),
  password_hash varchar(255) null,
  created_at timestamp default current_timestamp,
  updated_at timestamp default current_timestamp on update current_timestamp,
  foreign key (user_id) references users(id) on delete cascade
);


-- Table: attachment

create table attachment (
  id int auto_increment primary key,
  note_id int not null,
  filename varchar(255) not null,
  uploaded_at timestamp default current_timestamp,
  foreign key (note_id) references notes(id) on delete cascade
);


-- Table: label_note (junction table)

create table label_note (
  id int auto_increment primary key,
  label_id int not null,
  note_id int not null,
  unique (label_id, note_id),
  foreign key (label_id) references labels(id) on delete cascade,
  foreign key (note_id) references notes(id) on delete cascade
);

-- Table: user_preferences

create table if not exists user_preferences (
    id int auto_increment primary key,
    user_id int not null,
    theme varchar(10) default 'light',
    font_size int default 16,
    note_color varchar(20) default 'default',
    auto_save tinyint(1) default 1,
    auto_save_delay int default 800,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (user_id) references users(id) on delete cascade,
    unique key unique_user_preferences (user_id)
);

-- Table: password_resets

create table if not exists password_resets (
    id int auto_increment primary key,
    user_id int not null,
    reset_token varchar(255) not null,
    expires_at timestamp not null,
    created_at timestamp default current_timestamp,
    foreign key (user_id) references users(id) on delete cascade,
    index idx_reset_token (reset_token),
    index idx_expires_at (expires_at)
);

-- Table: snote_shares

create table shared_notes (
    id int auto_increment primary key,
    note_id int not null,
    owner_id int not null,
    shared_with_email varchar(255) not null,
    shared_with_id int default null,
    permission enum('read', 'edit') not null default 'read',
    shared_at timestamp default current_timestamp,
    foreign key (note_id) references notes(id) on delete cascade,
    foreign key (owner_id) references users(id) on delete cascade,
    foreign key (shared_with_id) references users(id) on delete set null,
    unique key unique_share (note_id, shared_with_email)
);

-- Table: note_collaborations

create table note_collaborations (
    id int auto_increment primary key,
    note_id int not null,
    user_id int not null,
    cursor_position int default 0,
    last_active timestamp default current_timestamp on update current_timestamp,
    foreign key (note_id) references notes(id) on delete cascade,
    foreign key (user_id) references users(id) on delete cascade,
    unique key unique_collaboration (note_id, user_id)
);

-- Add collaboration tracking tables

CREATE TABLE IF NOT EXISTS collaboration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    change_type ENUM('update', 'insert', 'delete') NOT NULL,
    field_name VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    version INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_note_version (note_id, version),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS active_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_typing BOOLEAN DEFAULT FALSE,
    cursor_position JSON,
    UNIQUE KEY unique_user_note (note_id, user_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS typing_indicators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    is_typing BOOLEAN DEFAULT TRUE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_typing (note_id, user_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add is_editing column to active_users table
ALTER TABLE active_users ADD COLUMN is_editing BOOLEAN DEFAULT FALSE;

-- Add index for better performance
CREATE INDEX idx_active_users_editing ON active_users(note_id, is_editing, last_seen);
