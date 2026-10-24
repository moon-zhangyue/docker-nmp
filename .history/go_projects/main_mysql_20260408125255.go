package main

import (
	"database/sql"
	"fmt"
	"log"
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
)

// User 用户结构体
type User struct {
	ID        int       `json:"id"`
	Name      string    `json:"name"`
	Email     string    `json:"email"`
	Age       int       `json:"age"`
	CreatedAt time.Time `json:"created_at"`
}

// Database 数据库连接管理
type Database struct {
	DB *sql.DB
}

// NewDatabase 创建新的数据库连接
func NewDatabase() (*Database, error) {
	// MySQL 9 连接配置
	dsn := "root:123456@tcp(mysql-9:3306)/test_db?charset=utf8mb4&parseTime=True&loc=Local"
	
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
	_, err := d.DB.Exec("CREATE DATABASE IF NOT EXISTS test_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
	if err != nil {
		return fmt.Errorf("failed to create database: %w", err)
	}

	// 切换到 test_db
	_, err = d.DB.Exec("USE test_db")
	if err != nil {
		return fmt.Errorf("failed to use database: %w", err)
	}

	// 创建 users 表
	createTableSQL := `
	CREATE TABLE IF NOT EXISTS users (
		id INT AUTO_INCREMENT PRIMARY KEY,
		name VARCHAR(100) NOT NULL,
		email VARCHAR(100) UNIQUE NOT NULL,
		age INT,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		INDEX idx_email (email),
		INDEX idx_name (name)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
	`

	_, err = d.DB.Exec(createTableSQL)
	if err != nil {
		return fmt.Errorf("failed to create table: %w", err)
	}

	log.Println("Database and tables initialized successfully")
	return nil
}

// CreateUser 创建用户
func (d *Database) CreateUser(name, email string, age int) (int64, error) {
	result, err := d.DB.Exec(
		"INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
		name, email, age,
	)
	if err != nil {
		return 0, fmt.Errorf("failed to create user: %w", err)
	}

	id, err := result.LastInsertId()
	if err != nil {
		return 0, fmt.Errorf("failed to get last insert id: %w", err)
	}

	return id, nil
}

// GetUser 获取单个用户
func (d *Database) GetUser(id int) (*User, error) {
	user := &User{}
	err := d.DB.QueryRow(
		"SELECT id, name, email, age, created_at FROM users WHERE id = ?",
		id,
	).Scan(&user.ID, &user.Name, &user.Email, &user.Age, &user.CreatedAt)

	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("failed to get user: %w", err)
	}

	return user, nil
}

// GetAllUsers 获取所有用户
func (d *Database) GetAllUsers() ([]*User, error) {
	rows, err := d.DB.Query("SELECT id, name, email, age, created_at FROM users ORDER BY id DESC")
	if err != nil {
		return nil, fmt.Errorf("failed to get users: %w", err)
	}
	defer rows.Close()

	var users []*User
	for rows.Next() {
		user := &User{}
		err := rows.Scan(&user.ID, &user.Name, &user.Email, &user.Age, &user.CreatedAt)
		if err != nil {
			return nil, fmt.Errorf("failed to scan user: %w", err)
		}
		users = append(users, user)
	}

	return users, nil
}

// UpdateUser 更新用户
func (d *Database) UpdateUser(id int, name, email string, age int) error {
	_, err := d.DB.Exec(
		"UPDATE users SET name = ?, email = ?, age = ? WHERE id = ?",
		name, email, age, id,
	)
	if err != nil {
		return fmt.Errorf("failed to update user: %w", err)
	}
	return nil
}

// DeleteUser 删除用户
func (d *Database) DeleteUser(id int) error {
	_, err := d.DB.Exec("DELETE FROM users WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("failed to delete user: %w", err)
	}
	return nil
}

// SearchUsers 搜索用户
func (d *Database) SearchUsers(keyword string) ([]*User, error) {
	query := "SELECT id, name, email, age, created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY id DESC"
	searchPattern := "%" + keyword + "%"
	
	rows, err := d.DB.Query(query, searchPattern, searchPattern)
	if err != nil {
		return nil, fmt.Errorf("failed to search users: %w", err)
	}
	defer rows.Close()

	var users []*User
	for rows.Next() {
		user := &User{}
		err := rows.Scan(&user.ID, &user.Name, &user.Email, &user.Age, &user.CreatedAt)
		if err != nil {
			return nil, fmt.Errorf("failed to scan user: %w", err)
		}
		users = append(users, user)
	}

	return users, nil
}

// GetStats 获取统计信息
func (d *Database) GetStats() (map[string]interface{}, error) {
	stats := make(map[string]interface{})

	// 总用户数
	var totalUsers int
	err := d.DB.QueryRow("SELECT COUNT(*) FROM users").Scan(&totalUsers)
	if err != nil {
		return nil, fmt.Errorf("failed to get total users: %w", err)
	}
	stats["total_users"] = totalUsers

	// 平均年龄
	var avgAge float64
	err = d.DB.QueryRow("SELECT AVG(age) FROM users").Scan(&avgAge)
	if err != nil {
		return nil, fmt.Errorf("failed to get average age: %w", err)
	}
	stats["average_age"] = avgAge

	// 最近创建的用户
	var recentUser User
	err = d.DB.QueryRow(
		"SELECT id, name, email, age, created_at FROM users ORDER BY created_at DESC LIMIT 1",
	).Scan(&recentUser.ID, &recentUser.Name, &recentUser.Email, &recentUser.Age, &recentUser.CreatedAt)
	
	if err == nil {
		stats["recent_user"] = recentUser
	}

	return stats, nil
}

func main() {
	// 初始化 Gin
	r := gin.Default()

	// 初始化数据库
	db, err := NewDatabase()
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}
	defer db.DB.Close()

	// 初始化数据库表
	if err := db.InitDatabase(); err != nil {
		log.Fatalf("Failed to initialize database: %v", err)
	}

	// 健康检查
	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"status":    "healthy",
			"timestamp": time.Now(),
			"database":  "MySQL 9.3.0",
		})
	})

	// CRUD API
	
	// 创建用户
	r.POST("/api/users", func(c *gin.Context) {
		var input struct {
			Name  string `json:"name" binding:"required"`
			Email string `json:"email" binding:"required,email"`
			Age   int    `json:"age" binding:"min=1,max=150"`
		}

		if err := c.ShouldBindJSON(&input); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		id, err := db.CreateUser(input.Name, input.Email, input.Age)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		c.JSON(http.StatusCreated, gin.H{
			"message": "User created successfully",
			"id":      id,
		})
	})

	// 获取所有用户
	r.GET("/api/users", func(c *gin.Context) {
		users, err := db.GetAllUsers()
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		c.JSON(http.StatusOK, gin.H{
			"data":  users,
			"count": len(users),
		})
	})

	// 获取单个用户
	r.GET("/api/users/:id", func(c *gin.Context) {
		id := c.Param("id")
		var userID int
		fmt.Sscanf(id, "%d", &userID)

		user, err := db.GetUser(userID)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		if user == nil {
			c.JSON(http.StatusNotFound, gin.H{"error": "User not found"})
			return
		}

		c.JSON(http.StatusOK, gin.H{"data": user})
	})

	// 更新用户
	r.PUT("/api/users/:id", func(c *gin.Context) {
		id := c.Param("id")
		var userID int
		fmt.Sscanf(id, "%d", &userID)

		var input struct {
			Name  string `json:"name" binding:"required"`
			Email string `json:"email" binding:"required,email"`
			Age   int    `json:"age" binding:"min=1,max=150"`
		}

		if err := c.ShouldBindJSON(&input); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		if err := db.UpdateUser(userID, input.Name, input.Email, input.Age); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		c.JSON(http.StatusOK, gin.H{"message": "User updated successfully"})
	})

	// 删除用户
	r.DELETE("/api/users/:id", func(c *gin.Context) {
		id := c.Param("id")
		var userID int
		fmt.Sscanf(id, "%d", &userID)

		if err := db.DeleteUser(userID); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		c.JSON(http.StatusOK, gin.H{"message": "User deleted successfully"})
	})

	// 搜索用户
	r.GET("/api/users/search", func(c *gin.Context) {
		keyword := c.Query("q")
		if keyword == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "Search keyword is required"})
			return
		}

		users, err := db.SearchUsers(keyword)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		c.JSON(http.StatusOK, gin.H{
			"data":  users,
			"count": len(users),
		})
	})

	// 获取统计信息
	r.GET("/api/stats", func(c *gin.Context) {
		stats, err := db.GetStats()
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		c.JSON(http.StatusOK, gin.H{"data": stats})
	})

	// 启动服务器
	log.Println("Starting Gin server with MySQL 9 on port 8080...")
	r.Run(":8080")
}
