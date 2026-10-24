package main

import (
	"net/http"

	"github.com/gin-gonic/gin"
)

// NewGinEngine 创建并配置 Gin 引擎
func NewGinEngine(db *Database) *gin.Engine {
	// 设置为 release 模式（生产环境）
	gin.SetMode(gin.ReleaseMode)

	r := gin.Default()

	// 全局中间件 - 日志和恢复
	r.Use(gin.Logger())
	r.Use(gin.Recovery())

	// CORS 中间件
	r.Use(func(c *gin.Context) {
		c.Writer.Header().Set("Access-Control-Allow-Origin", "*")
		c.Writer.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		c.Writer.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")

		if c.Request.Method == "OPTIONS" {
			c.AbortWithStatus(http.StatusNoContent)
			return
		}

		c.Next()
	})

	// 设置路由
	SetupRoutes(r, db)

	return r
}
