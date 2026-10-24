package main

import (
	"database/sql"
	"fmt"
	"log"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

// User 用户模型
type User struct {
	ID        int       `json:"id"`
	Username  string    `json:"username"`
	Email     string    `json:"email"`
	Age       int       `json:"age"`
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

// Database 数据库连接管理
type Database struct {
	DB *sql.DB
}

// NewDatabase 创建新的数据库连接
func NewDatabase() (*Database, error) {
	// MySQL 9 连接配置
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		"root",           // 用户名
		"123456",         // 密码
		"mysql-9",        // MySQL 9 容器名
		3306,             // 端口
		"test_db",        // 数据库名
	)

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, fmt.Errorf("failed to open database: %w", err)
	}

	// 设置连接池参数
	db.SetMaxOpenConns(25)
	db.SetMaxIdleConns(25)
	db.SetConnMaxLifetime(5 * time.Minute)

	// 测试连接
	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	log.Println("Successfully connected to MySQL 9")
	return &Database{DB: db}, nil
}

// InitDatabase 初始化数据库和表
func (d *Database) InitDatabase() error {
	// 创建数据库（如果不存在）
	createDB := "CREATE DATABASE IF NOT EXISTS test_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
	if _, err := d.DB.Exec(createDB); err != nil {
		return fmt.Errorf("failed to create database: %w", err)
	}

	// 切换到 test_db
	if _, err := d.DB.Exec("USE test_db"); err != nil {
		return fmt.Errorf("failed to use database: %w", err)
	}

	// 创建 users 表
	createTable := `
	CREATE TABLE IF NOT EXISTS users (
		id INT AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(50) NOT NULL UNIQUE,
		email VARCHAR(100) NOT NULL UNIQUE,
		age INT DEFAULT 0,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		INDEX idx_username (username),
		INDEX idx_email (email)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	`

	if _, err := d.DB.Exec(createTable); err != nil {
		return fmt.Errorf("failed to create table: %w", err)
	}

	log.Println("Database and tables initialized successfully")
	return nil
}

// CreateUser 创建用户
func (d *Database) CreateUser(username, email string, age int) (int64, error) {
	query := "INSERT INTO users (username, email, age) VALUES (?, ?, ?)"
	result, err := d.DB.Exec(query, username, email, age)
	if err != nil {
		return 0, fmt.Errorf("failed to create user: %w", err)
	}

	id, err := result.LastInsertId()
	if err != nil {
		return 0, fmt.Errorf("failed to get last insert id: %w", err)
	}

	return id, nil
}

// GetUserByID 根据ID获取用户
func (d *Database) GetUserByID(id int) (*User, error) {
	query := "SELECT id, username, email, age, created_at, updated_at FROM users WHERE id = ?"
	user := &User{}

	err := d.DB.QueryRow(query, id).Scan(
		&user.ID,
		&user.Username,
		&user.Email,
		&user.Age,
		&user.CreatedAt,
		&user.UpdatedAt,
	)

	if err != nil {
		if err == sql.ErrNoRows {
			return nil, fmt.Errorf("user not found")
		}
		return nil, fmt.Errorf("failed to get user: %w", err)
	}

	return user, nil
}

// GetAllUsers 获取所有用户
func (d *Database) GetAllUsers() ([]*User, error) {
	query := "SELECT id, username, email, age, created_at, updated_at FROM users ORDER BY id DESC"
	rows, err := d.DB.Query(query)
	if err != nil {
		return nil, fmt.Errorf("failed to query users: %w", err)
	}
	defer rows.Close()

	var users []*User
	for rows.Next() {
		user := &User{}
		err := rows.Scan(
			&user.ID,
			&user.Username,
			&user.Email,
			&user.Age,
			&user.CreatedAt,
			&user.UpdatedAt,
		)
		if err != nil {
			return nil, fmt.Errorf("failed to scan user: %w", err)
		}
		users = append(users, user)
	}

	return users, nil
}

// UpdateUser 更新用户
func (d *Database) UpdateUser(id int, username, email string, age int) error {
	query := "UPDATE users SET username = ?, email = ?, age = ? WHERE id = ?"
	result, err := d.DB.Exec(query, username, email, age, id)
	if err != nil {
		return fmt.Errorf("failed to update user: %w", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %w", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("user not found")
	}

	return nil
}

// DeleteUser 删除用户
func (d *Database) DeleteUser(id int) error {
	query := "DELETE FROM users WHERE id = ?"
	result, err := d.DB.Exec(query, id)
	if err != nil {
		return fmt.Errorf("failed to delete user: %w", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %w", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("user not found")
	}

	return nil
}

// Close 关闭数据库连接
func (d *Database) Close() error {
	if d.DB != nil {
		return d.DB.Close()
	}
	return nil
}
