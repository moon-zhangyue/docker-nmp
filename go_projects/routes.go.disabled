                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            package main

import (
	"net/http"
	"strconv"

	"github.com/gin-gonic/gin"
)

// User 用户结构体
type User struct {
	ID        int    `json:"id"`
	Username  string `json:"username"`
	Email     string `json:"email"`
	Age       int    `json:"age"`
	CreatedAt string `json:"created_at"`
	UpdatedAt string `json:"updated_at"`
}

// SetupRoutes 设置路由
func SetupRoutes(r *gin.Engine) {
	// 健康检查
	r.GET("/health", HealthCheck)

	// API 路由组
	api := r.Group("/api")
	{
		// 用户相关路由
		users := api.Group("/users")
		{
			users.GET("", GetUsers)           // 获取所有用户
			users.GET("/:id", GetUserByID)    // 根据ID获取用户
			users.POST("", CreateUser)        // 创建用户
			users.PUT("/:id", UpdateUser)     // 更新用户
			users.DELETE("/:id", DeleteUser)  // 删除用户
		}

		// 数据库测试路由
		db := api.Group("/db")
		{
			db.GET("/test", TestDBConnection) // 测试数据库连接
		}
	}
}

// HealthCheck 健康检查接口
func HealthCheck(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status":  "ok",
		"message": "Gin + MySQL9 API is running",
	})
}

// TestDBConnection 测试数据库连接
func TestDBConnection(c *gin.Context) {
	if DB == nil {
		c.JSON(http.StatusInternalServerError, gin.H{
			"status":  "error",
			"message": "数据库未初始化",
		})
		return
	}

	if err := DB.Ping(); err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{
			"status":  "error",
			"message": "数据库连接失败: " + err.Error(),
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status":  "success",
		"message": "数据库连接正常",
	})
}

// GetUsers 获取所有用户
func GetUsers(c *gin.Context) {
	rows, err := DB.Query("SELECT id, username, email, age, created_at, updated_at FROM users ORDER BY id DESC")
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{
			"status":  "error",
			"message": "查询失败: " + err.Error(),
		})
		return
	}
	defer rows.Close()

	var users []User
	for rows.Next() {
		var user User
		err := rows.Scan(&user.ID, &user.Username, &user.Email, &user.Age, &user.CreatedAt, &user.UpdatedAt)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{
				"status":  "error",
				"message": "数据解析失败: " + err.Error(),
			})
			return
		}
		users = append(users, user)
	}

	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"data":   users,
		"count":  len(users),
	})
}

// GetUserByID 根据ID获取用户
func GetUserByID(c *gin.Context) {
	id := c.Param("id")

	var user User
	err := DB.QueryRow("SELECT id, username, email, age, created_at, updated_at FROM users WHERE id = ?", id).
		Scan(&user.ID, &user.Username, &user.Email, &user.Age, &user.CreatedAt, &user.UpdatedAt)

	if err != nil {
		c.JSON(http.StatusNotFound, gin.H{
			"status":  "error",
			"message": "用户不存在",
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"data":   user,
	})
}

// CreateUser 创建用户
func CreateUser(c *gin.Context) {
	var input struct {
		Username string `json:"username" binding:"required"`
		Email    string `json:"email" binding:"required,email"`
		Age      int    `json:"age"`
	}

	if err := c.ShouldBindJSON(&input); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"status":  "error",
			"message": "参数验证失败: " + err.Error(),
		})
		return
	}

	result, err := DB.Exec(
		"INSERT INTO users (username, email, age) VALUES (?, ?, ?)",
		input.Username,
		input.Email,
		input.Age,
	)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{
			"status":  "error",
			"message": "创建用户失败: " + err.Error(),
		})
		return
	}

	id, _ := result.LastInsertId()

	c.JSON(http.StatusCreated, gin.H{
		"status":  "success",
		"message": "用户创建成功",
		"data": gin.H{
			"id": id,
		},
	})
}

// UpdateUser 更新用户
func UpdateUser(c *gin.Context) {
	id := c.Param("id")

	var input struct {
		Username string `json:"username"`
		Email    string `json:"email" binding:"omitempty,email"`
		Age      int    `json:"age"`
	}

	if err := c.ShouldBindJSON(&input); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"status":  "error",
			"message": "参数验证失败: " + err.Error(),
		})
		return
	}

	result, err := DB.Exec(
		"UPDATE users SET username = ?, email = ?, age = ? WHERE id = ?",
		input.Username,
		input.Email,
		input.Age,
		id,
	)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{
			"status":  "error",
			"message": "更新用户失败: " + err.Error(),
		})
		return
	}

	rowsAffected, _ := result.RowsAffected()
	if rowsAffected == 0 {
		c.JSON(http.StatusNotFound, gin.H{
			"status":  "error",
			"message": "用户不存在",
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status":  "success",
		"message": "用户更新成功",
	})
}

// DeleteUser 删除用户
func DeleteUser(c *gin.Context) {
	id := c.Param("id")

	result, err := DB.Exec("DELETE FROM users WHERE id = ?", id)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{
			"status":  "error",
			"message": "删除用户失败: " + err.Error(),
		})
		return
	}

	rowsAffected, _ := result.RowsAffected()
	if rowsAffected == 0 {
		c.JSON(http.StatusNotFound, gin.H{
			"status":  "error",
			"message": "用户不存在",
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status":  "success",
		"message": "用户删除成功",
	})
}

// Helper function to convert string to int
func atoi(s string) int {
	i, _ := strconv.Atoi(s)
	return i
}
