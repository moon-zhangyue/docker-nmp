package main

import (
	"net/http"
	"strconv"

	"github.com/gin-gonic/gin"
)

// SetupRoutes 设置路由
func SetupRoutes(r *gin.Engine, db *Database) {
	// API v1 路由组
	v1 := r.Group("/api/v1")
	{
		// 健康检查
		v1.GET("/health", HealthCheck)

		// 用户相关路由
		users := v1.Group("/users")
		{
			users.POST("", CreateUserHandler(db))
			users.GET("", GetAllUsersHandler(db))
			users.GET("/:id", GetUserHandler(db))
			users.PUT("/:id", UpdateUserHandler(db))
			users.DELETE("/:id", DeleteUserHandler(db))
		}
	}
}

// HealthCheck 健康检查
func HealthCheck(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status":  "ok",
		"message": "Gin + MySQL 9 API is running",
	})
}

// CreateUserHandler 创建用户处理器
func CreateUserHandler(db *Database) gin.HandlerFunc {
	return func(c *gin.Context) {
		var input struct {
			Username string `json:"username" binding:"required,min=3,max=50"`
			Email    string `json:"email" binding:"required,email"`
			Age      int    `json:"age" binding:"min=0,max=150"`
		}

		if err := c.ShouldBindJSON(&input); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{
				"error":   "Invalid input",
				"details": err.Error(),
			})
			return
		}

		id, err := db.CreateUser(input.Username, input.Email, input.Age)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{
				"error": err.Error(),
			})
			return
		}

		c.JSON(http.StatusCreated, gin.H{
			"message": "User created successfully",
			"id":      id,
		})
	}
}

// GetUserHandler 获取用户处理器
func GetUserHandler(db *Database) gin.HandlerFunc {
	return func(c *gin.Context) {
		id, err := strconv.Atoi(c.Param("id"))
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{
				"error": "Invalid user ID",
			})
			return
		}

		user, err := db.GetUserByID(id)
		if err != nil {
			if err.Error() == "user not found" {
				c.JSON(http.StatusNotFound, gin.H{
					"error": "User not found",
				})
				return
			}
			c.JSON(http.StatusInternalServerError, gin.H{
				"error": err.Error(),
			})
			return
		}

		c.JSON(http.StatusOK, user)
	}
}

// GetAllUsersHandler 获取所有用户处理器
func GetAllUsersHandler(db *Database) gin.HandlerFunc {
	return func(c *gin.Context) {
		users, err := db.GetAllUsers()
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{
				"error": err.Error(),
			})
			return
		}

		c.JSON(http.StatusOK, gin.H{
			"count": len(users),
			"data":  users,
		})
	}
}

// UpdateUserHandler 更新用户处理器
func UpdateUserHandler(db *Database) gin.HandlerFunc {
	return func(c *gin.Context) {
		id, err := strconv.Atoi(c.Param("id"))
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{
				"error": "Invalid user ID",
			})
			return
		}

		var input struct {
			Username string `json:"username" binding:"required,min=3,max=50"`
			Email    string `json:"email" binding:"required,email"`
			Age      int    `json:"age" binding:"min=0,max=150"`
		}

		if err := c.ShouldBindJSON(&input); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{
				"error":   "Invalid input",
				"details": err.Error(),
			})
			return
		}

		err = db.UpdateUser(id, input.Username, input.Email, input.Age)
		if err != nil {
			if err.Error() == "user not found" {
				c.JSON(http.StatusNotFound, gin.H{
					"error": "User not found",
				})
				return
			}
			c.JSON(http.StatusInternalServerError, gin.H{
				"error": err.Error(),
			})
			return
		}

		c.JSON(http.StatusOK, gin.H{
			"message": "User updated successfully",
		})
	}
}

// DeleteUserHandler 删除用户处理器
func DeleteUserHandler(db *Database) gin.HandlerFunc {
	return func(c *gin.Context) {
		id, err := strconv.Atoi(c.Param("id"))
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{
				"error": "Invalid user ID",
			})
			return
		}

		err = db.DeleteUser(id)
		if err != nil {
			if err.Error() == "user not found" {
				c.JSON(http.StatusNotFound, gin.H{
					"error": "User not found",
				})
				return
			}
			c.JSON(http.StatusInternalServerError, gin.H{
				"error": err.Error(),
			})
			return
		}

		c.JSON(http.StatusOK, gin.H{
			"message": "User deleted successfully",
		})
	}
}
