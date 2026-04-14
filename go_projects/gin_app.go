//go:build ignore

package main

import (
	"log"
	"os"

	"github.com/gin-gonic/gin"
)

// NewGinApp 创建并配置 Gin 应用
func NewGinApp() *gin.Engine {
	// 设置 Gin 模式
	mode := os.Getenv("GIN_MODE")
	if mode == "" {
		mode = gin.ReleaseMode
	}
	gin.SetMode(mode)

	// 创建 Gin 引擎
	r := gin.Default()

	// 添加中间件
	r.Use(CORSMiddleware())
	r.Use(LoggerMiddleware())

	// 设置路由
	SetupRoutes(r)

	return r
}

// CORSMiddleware CORS 跨域中间件
func CORSMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Writer.Header().Set("Access-Control-Allow-Origin", "*")
		c.Writer.Header().Set("Access-Control-Allow-Credentials", "true")
		c.Writer.Header().Set("Access-Control-Allow-Headers", "Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization, accept, origin, Cache-Control, X-Requested-With")
		c.Writer.Header().Set("Access-Control-Allow-Methods", "POST, OPTIONS, GET, PUT, DELETE")

		if c.Request.Method == "OPTIONS" {
			c.AbortWithStatus(204)
			return
		}

		c.Next()
	}
}

// LoggerMiddleware 日志中间件
func LoggerMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		log.Printf("[%s] %s %s", c.Request.Method, c.Request.URL.Path, c.ClientIP())
		c.Next()
	}
}

// StartServer 启动服务器
func StartServer() {
	// 初始化数据库连接
	if err := InitDB(); err != nil {
		log.Printf("警告: 数据库初始化失败: %v", err)
		log.Println("应用将继续运行，但数据库功能不可用")
	} else {
		// 创建测试表
		if err := CreateTestTable(); err != nil {
			log.Printf("警告: 创建测试表失败: %v", err)
		}
		defer CloseDB()
	}

	// 创建 Gin 应用
	r := NewGinApp()

	// 获取端口
	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	log.Printf("服务器启动在端口 %s", port)
	log.Println("API 文档:")
	log.Println("  - GET  /health              健康检查")
	log.Println("  - GET  /api/db/test         测试数据库连接")
	log.Println("  - GET  /api/users           获取所有用户")
	log.Println("  - GET  /api/users/:id       获取单个用户")
	log.Println("  - POST /api/users           创建用户")
	log.Println("  - PUT  /api/users/:id       更新用户")
	log.Println("  - DELETE /api/users/:id     删除用户")

	if err := r.Run(":" + port); err != nil {
		log.Fatalf("服务器启动失败: %v", err)
	}
}
