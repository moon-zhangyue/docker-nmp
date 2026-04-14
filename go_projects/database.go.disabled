//go:build ignore

package main

import (
	"database/sql"
	"fmt"
	"log"
	"os"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

var DB *sql.DB

// DatabaseConfig 数据库配置结构
type DatabaseConfig struct {
	Host     string
	Port     string
	User     string
	Password string
	DBName   string
}

// InitDB 初始化数据库连接
func InitDB() error {
	// 从环境变量读取配置，如果没有则使用默认值
	config := DatabaseConfig{
		Host:     getEnv("MYSQL9_HOST", "mysql-9"),
		Port:     getEnv("MYSQL9_PORT", "3306"),
		User:     getEnv("MYSQL9_USER", "root"),
		Password: getEnv("MYSQL9_PASSWORD", "123456"),
		DBName:   getEnv("MYSQL9_DATABASE", "test_db"),
	}

	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		config.User,
		config.Password,
		config.Host,
		config.Port,
		config.DBName,
	)

	var err error
	DB, err = sql.Open("mysql", dsn)
	if err != nil {
		return fmt.Errorf("打开数据库连接失败: %v", err)
	}

	// 设置连接池参数
	DB.SetMaxOpenConns(25)                 // 最大打开连接数
	DB.SetMaxIdleConns(25)                 // 最大空闲连接数
	DB.SetConnMaxLifetime(5 * time.Minute) // 连接最大生命周期

	// 测试连接
	if err = DB.Ping(); err != nil {
		return fmt.Errorf("数据库连接测试失败: %v", err)
	}

	log.Println("成功连接到 MySQL9 数据库")
	return nil
}

// CloseDB 关闭数据库连接
func CloseDB() error {
	if DB != nil {
		return DB.Close()
	}
	return nil
}

// CreateTestTable 创建测试表
func CreateTestTable() error {
	query := `
	CREATE TABLE IF NOT EXISTS users (
		id INT AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(50) NOT NULL UNIQUE,
		email VARCHAR(100) NOT NULL,
		age INT,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
	`

	_, err := DB.Exec(query)
	if err != nil {
		return fmt.Errorf("创建表失败: %v", err)
	}

	log.Println("测试表 users 创建成功")
	return nil
}

// getEnv 获取环境变量，如果不存在则返回默认值
func getEnv(key, defaultValue string) string {
	value := os.Getenv(key)
	if value == "" {
		return defaultValue
	}
	return value
}
